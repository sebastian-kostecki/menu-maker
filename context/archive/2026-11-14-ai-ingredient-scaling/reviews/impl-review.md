<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: AI Ingredient Scaling (S-04)

- **Plan**: context/changes/ai-ingredient-scaling/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-14
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 6 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

### Automated verification (run 2026-11-14)

| Check | Result |
|---|---|
| `vendor/bin/sail composer test` | PASS — 311 passed (1097 assertions) |
| `vendor/bin/sail composer phpstan` | PASS — no errors, baseline untouched |
| `vendor/bin/sail composer pint-test` | PASS |
| `vendor/bin/sail npm run build` | PASS |
| `vendor/bin/sail composer install` | PASS — nothing to install |
| migrate → `migrate:rollback --step=1` → migrate | PASS (run against the `testing` DB to keep dev snapshots intact) |
| `db:seed --class=E2eMealPlanSeeder` + Playwright | PASS — 10/10, chromium + firefox (run with `--output` outside the root-owned `test-results/`) |

Manual rows 1.6–1.7, 2.5, 3.5–3.8, 4.5–4.7, 5.5–5.6 are all unchecked: pending, not flagged as false ticks.

## Findings

### F1 — Unknown AI_SCALING_DRIVER leaves the plan stuck in pending

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Providers/AppServiceProvider.php:47, app/Jobs/GenerateMealPlanJob.php:172
- **Detail**: The `IngredientRefiner` binding throws `InvalidArgumentException` for an unknown driver. `MealIngredientScaler` injects the refiner, so the throw happens while `handle()`'s arguments resolve, before `status = processing`. `failed()` only flips `processing` plans to `error`, so a typo in `.env` leaves the plan `pending`: the UI polls forever and "Regeneruj" is refused (`REGENERATABLE_STATUSES = ['done','error']`). This contradicts the plan's "the plan still finishes" goal.
- **Fix**: In `failed()`, flip the plan to `error` when its fresh status is `pending` or `processing` (and add a job test with an unknown driver).
- **Decision**: FIXED — `failed()` now flips `pending` as well as `processing` to `error`; test: unknown driver dispatch ends `error`.

### F2 — Timeout kill keeps the overlap lock; the next regeneration is silently dropped

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Jobs/GenerateMealPlanJob.php:63
- **Detail**: `WithoutOverlapping(...)->dontRelease()->expireAfter(300)` releases its lock in a `finally`, which never runs when the worker kills the job at `$timeout = 80`. `failed()` sets `error`, the user clicks "Regeneruj", the plan goes `pending`, and the new job finds the lock held and is deleted (dontRelease). The plan stays `pending` for good, because `pending` cannot be regenerated. The middleware is older, but the new AI stage (normal worst case ~61 s against 80 s) makes a timeout plausible.
- **Fix A ⭐ Recommended**: In `failed()`, call `Cache::lock((new WithoutOverlapping((string) $this->mealPlan->id))->getLockKey($this))->forceRelease()`.
  - Strength: Removes the stale lock exactly when the job dies, and `getLockKey()` is public on the middleware (`WithoutOverlapping.php:157`).
  - Tradeoff: Duplicates the middleware construction, so the key must be built the same way in two places (extract a helper).
  - Confidence: MED — `failed()` runs in the killing worker before exit on `queue:work`; not yet verified under Sail's `queue:listen`.
  - Blind spot: Whether `failed()` is invoked on the `queue:listen` child-kill path (listener `--timeout=85`).
- **Fix B**: Shorten `expireAfter(300)` to about `$timeout + 10` (90).
  - Strength: One-line change, and it bounds the stale-lock window on every kill path.
  - Tradeoff: A regeneration within ~90 s of a timeout is still dropped and still leaves the plan `pending`.
  - Confidence: HIGH — narrows the window, does not close it.
  - Blind spot: None significant.
- **Decision**: FIXED via Fix A — `failed()` force-releases the overlap lock on `MaxAttemptsExceededException` (incl. timeouts); key built by a shared `overlapGuard()`; test added.

### F3 — "Worker may be down" banner shows during normal AI runs

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Pages/MealPlans/Show.vue:166
- **Detail**: `SLOW_AFTER_MS = 30_000` shows "Generowanie trwa dłużej niż zwykle. Sprawdź, czy działa worker kolejki…" after 30 s. The new pipeline's legitimate budget is up to ~61 s (3 × 20 s + pauses), so one retried or slow AI call puts a false queue-worker warning on screen.
- **Fix**: Raise `SLOW_AFTER_MS` above the job budget (e.g. 75 000, under `$timeout = 80`).
- **Decision**: FIXED — `SLOW_AFTER_MS` raised to 75 000.

### F4 — Phase 4 and job tests never exercise scaled data

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/MealPlanControllerTest.php:436, tests/Unit/Services/MealPlanPdfExporterTest.php:158, tests/Unit/Jobs/GenerateMealPlanJobTest.php:38
- **Detail**: The plan asked for tests showing that the Inertia `meals.*.ingredients` props carry scaled `display` strings and that the PDF text contains a scaled line plus the notice. The Testing Strategy also asked for a job test that generates end to end with the fake driver and writes rows. Instead, the controller test uses an `as_written` plan ("250 g"), the PDF text test checks an `as_written` line ("Mąka — 600 g"), and the job test runs without a family (status `as_written`, no row count, no `refined`). E2E covers the scaled grid and list, but not the PDF or the job contract.
- **Fix**: Give those three tests an adult family (factory `adult()`) and assert scaled `display`, a scaled PDF line, `scaling.status = refined` and the `meal_ingredients` count.
- **Decision**: FIXED — new scaled-family tests: Inertia props (`200 g`, `2 szt.`, source `ai`), PDF text (`Mąka — 200 g` + members-without-body notice), job (`refined`, 21 `ai` rows); shared `seedTwoAdultFamily()` helper in tests/Pest.php.

### F5 — Real-driver paths unverified (manual 3.5–3.8)

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: app/Services/IngredientScaling/Drivers/AnthropicGateway.php, OpenRouterIngredientRefiner.php; context/changes/ai-ingredient-scaling/change.md
- **Detail**: Both real drivers are tested only against `Http::fake` or a mocked gateway. Nothing has checked the real SDK call shapes (`OutputConfig`, `JSONOutputFormat`, the Guzzle transporter), whether the chosen OpenRouter model supports `json_schema`, or first-attempt latency. 3.8 asks for timings in `change.md` Notes, and the Notes section is empty. F3 and F2 both depend on that latency.
- **Fix**: Run manual 3.5–3.8 with real keys before merging and record duration and item count in `change.md`.
- **Decision**: SKIPPED — needs real OpenRouter/Anthropic keys; stays on the manual checklist (3.5–3.8) before merge.

### F6 — Permanent 4xx errors are retried; drivers catch different exceptions

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Services/IngredientScaling/Drivers/OpenRouterIngredientRefiner.php:55, app/Services/IngredientScaling/Drivers/AnthropicIngredientRefiner.php:38
- **Detail**: Every `RequestException` (OpenRouter) and every `Throwable` (Anthropic) becomes a retryable `RefinementFailedException`, so a 401, 402, 403 or 400 (bad key, no credit, model without `json_schema`) makes 3 calls before the report. The plan's own "invalid key" manual case (3.7) takes this path. OpenRouter also does not wrap the `JsonException` that `RefinementPrompt::userMessage()` (`JSON_THROW_ON_ERROR`) can throw inside its `try`, so in principle it escapes the retry loop and fails the job. That is practically unreachable on PostgreSQL UTF-8.
- **Fix**: Map 4xx other than 408/429 (and the SDK's auth, permission and bad-request errors) to `RefinementConfigurationException`, and wrap `JsonException` in the OpenRouter driver.
- **Decision**: FIXED — shared `RefinementFailedException::fromHttpStatus()` maps 4xx except 408/429 to `RefinementConfigurationException` in both drivers; OpenRouter wraps `JsonException`; tests added.

### F7 — AI proposals are truncated, not rounded

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Services/IngredientScaling/RefinementGuard.php:31
- **Detail**: `bcadd($proposal, '0', 2)` truncates, so an AI answer of 1.239 kg is stored as 1.23. `ScalingCalculator::scale` and `EnergyNorms` round half-up, so this is inconsistent with the rest of the module.
- **Fix**: Round half-up to 2 dp in the guard (`bcround($proposal, 2)` on PHP 8.5, or the module's `+0.005` idiom) and add a guard test case.
- **Decision**: FIXED — guard rounds half-up (`+0.005` idiom, as in ScalingCalculator); test added.

### F8 — unscaledRecipes de-duplicated by id, not by name

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Services/IngredientScaling/MealIngredientScaler.php:75
- **Detail**: `$unscaledRecipes[$recipe->id] = $recipe->name` keeps one entry per recipe id. Recipe names are not unique, so two 0-kcal recipes called "Zupa" show as "Zupa, Zupa" in the notice. The plan said "unique recipe names".
- **Fix**: `array_unique` the names before the Polish collator sort.
- **Decision**: FIXED — `unscaledRecipes` keyed by name; test extended with a same-name recipe.

### F9 — Calories gt:0 allows implausibly small totals

- **Severity**: 💬 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Http/Requests/StoreRecipeRequest.php:35, app/Http/Requests/UpdateRecipeRequest.php:36
- **Detail**: The plan chose `gt:0`, so 0.01 kcal is valid. Factors explode (0.3 × 5400 / 0.01 = 162 000), and a common mistake (per-serving kcal entered as the total) silently multiplies amounts by 10–30×. In the extreme, `quantity × factor` overflows `decimal(12,2)` and the insert fails the whole job with the generic error. This is a flaw in the plan, not drift.
- **Fix**: Add a sane lower bound on recipe calories (e.g. `min:50`, with a Polish message) and clamp the factor in `ScalingCalculator` (fall back to `as_written` above, e.g., 20×).
  - Strength: Catches the per-serving mistake at input and makes overflow impossible.
  - Tradeoff: A product decision on the threshold; existing low-kcal recipes need the clamp path, not only validation.
  - Confidence: MED — the threshold value is a guess without real recipe data.
  - Blind spot: Distribution of existing users' recipe kcal values.
- **Decision**: FIXED — calories `min:50` (message „Kaloryczność całkowita musi wynosić co najmniej 50 kcal.”, input `min="50"`), `ScalingCalculator::MAX_FACTOR = 50` (above it the recipe stays as written); notice reworded to „Przepisy z brakującą lub zbyt niską kalorycznością nie zostały przeliczone: …”; db-plan and test-plan updated.

### F10 — Family-member validation: test placement, missing 19th-birthday case, triplicated client checks

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Feature/FamilyMemberTest.php; resources/js/Pages/FamilyMembers/{Create,Edit,InlineEditDialog}.vue
- **Detail**: The new `FamilyMemberRequest` rules are tested only over HTTP. CLAUDE.md puts Form Request tests in `tests/Unit/` (compare `RecipeRequestValidationTest.php`), and no case hits the exact 19th-birthday boundary of the age rule. The ~30-line age, weight and height client check is copied into three Vue files, while shared logic lives in `resources/js/hooks/` (e.g. `useDeleteRecipe.ts`).
- **Fix**: Add `tests/Unit/FamilyMemberRequestValidationTest.php` with the 18y364d / 19y0d boundary, and extract the client checks into a `useFamilyMemberBodyValidation` composable.
- **Decision**: FIXED — `tests/Unit/FamilyMemberRequestValidationTest.php` (19th-birthday boundary, range edges, messages); client checks extracted to `resources/js/hooks/useFamilyMemberBodyValidation.ts`.

## Triage summary (2026-11-14)

User asked to fix everything per the recommendations.

- Fixed: F1, F2 (Fix A), F3, F4, F6, F7, F8, F9, F10 (9)
- Skipped: F5 (needs real API keys; manual 3.5–3.8) (1)

Re-verified after fixes: Pest 341 passed (1176 assertions), Larastan OK, Pint OK, build OK, E2E 10/10.
Note: `tests/e2e/recipe-form.spec.ts` is flaky in Firefox (≈50% on `--repeat-each`, `Zapisz przepis` stays disabled) with or without these changes — pre-existing, not addressed here.

## Considered, not raised

- Rollback (`down()`) drops snapshots, and a re-run backfill labels `scaled-v1` plans as `as_written` with no notice: acceptable for a rollback.
- The `meal_ingredients.ingredient_id`/`unit_id` foreign keys RESTRICT, so a future catalog-merge migration must repoint them.
- The 3 s poll serializes the previous draw's ingredients while generating: small, could load only when `done`.
- The resource does not skip rows with a null ingredient or unit; the foreign keys make this unreachable.
- `test-results/` is root-owned locally and breaks `npm run test:e2e` until you `chown` it back.
