<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Real Weekly Meal Plan (S-03)

- **Plan**: context/changes/real-weekly-meal-plan/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-13
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 6 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | WARNING |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Automated re-run on 2026-11-13: Pest 180 passed (636 assertions); Larastan OK with no baseline change; Pint passed; `npm run build` exit 0; seeder ran twice OK; `queue` service up; Playwright 8/8 passed (chromium + firefox). Manual rows 1.4, 2.5, 3.3–3.7, 4.6 and 4.7 are still unchecked (pending; none was ticked without evidence).

## Findings

### F1 — Raw exception text shown to the user

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Jobs/GenerateMealPlanJob.php:89-98 → resources/js/Pages/MealPlans/Show.vue:37-40
- **Detail**: The generic `catch (\Exception $e)` stores `$e->getMessage()` in `generation_meta.error_message`. The new Show page renders this field verbatim, and the old page did not. A DB failure would show an English `SQLSTATE[...]` string with SQL fragments in the error state.
- **Fix**: In the generic catch, store a fixed Polish message (e.g. "Nie udało się wygenerować jadłospisu. Spróbuj ponownie.") and leave the real message to the logs and the rethrow. Keep the shortfall message as it is.
- **Decision**: FIXED — `GenerateMealPlanJob::GENERIC_ERROR_MESSAGE` (Polish) in the generic catch and in `failed()`; job tests assert it.

### F2 — Concurrent regenerations of one plan are not guarded

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/MealPlanController.php:138-144, app/Http/Requests/RegenerateMealPlanRequest.php
- **Detail**: The `done`/`error` status check runs during validation, and the controller sets `pending` afterwards without a lock or a conditional update. Two simultaneous PUTs (double-click, list menu plus view) both pass and dispatch two jobs. The rate limit was removed on purpose, so this check is the only guard. With more than one worker, two overlapping `generate()` transactions under READ COMMITTED can leave 42 meals, because `meals` has no unique key on (plan, date, category). Today's single `queue:listen` worker runs jobs one at a time, so meal duplication needs F-02 (a production worker pool). The double dispatch, however, happens today.
- **Fix A ⭐ Recommended**: Make the transition atomic (`MealPlan::whereKey($id)->whereIn('status', ['done','error'])->update(['status' => 'pending', 'generation_meta' => null])`, and answer 0 affected rows with a 422 on `status`), and add `WithoutOverlapping($mealPlan->id)` middleware to the job.
  - Strength: Closes both the double dispatch and the overlap at the source; the new Form Request check stays as the friendly error path.
  - Tradeoff: Two small changes plus a feature test for the 0-rows branch; `WithoutOverlapping` uses the cache lock store (database cache, since there is no Redis).
  - Confidence: MED — the pattern is standard; I have not checked that the cache store in Sail and CI supports locks.
  - Blind spot: The behaviour of `WithoutOverlapping` on the sync queue in tests.
- **Fix B**: Accept for now and add it to F-02 (production worker) as a prerequisite.
  - Strength: No change in an already-green change; the risk only becomes real with several workers.
  - Tradeoff: A double click still queues two jobs (wasted work, the plan flickers twice).
  - Confidence: HIGH — the single worker serialises jobs today.
  - Blind spot: Someone has to remember it when F-02 starts.
- **Decision**: FIXED (Fix A) — conditional `whereIn(status, done/error)->update(pending)` in `MealPlanController::update` (0 rows → 422 `status`); `WithoutOverlapping` per plan with `dontRelease()` (a release would end as a reported MaxAttemptsExceeded under `--tries=1`); feature test for the lost race.

### F3 — E2E regenerate step can pass without regenerating

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/e2e/meal-plan.spec.ts:26-29
- **Detail**: Right after the confirm click, `expect(recipeCells).toHaveCount(21)` is already satisfied by the old grid, before the PUT, the redirect and the skeleton. The plan's "click Regeneruj → expect the grid again" step is not actually proven.
- **Fix**: Before the final assertion, wait for the pending state (e.g. `await expect(page.getByText('Generujemy jadłospis…')).toBeVisible()` or `recipeCells` count 0), then expect 21 cells.
- **Decision**: FIXED — the spec waits for "Generujemy jadłospis…" before expecting 21 cells.

### F4 — Shortfall check runs outside the selection transaction

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Services/MealPlanGenerator.php:84-111
- **Detail**: `missingRecipesPerCategory()` runs before `DB::transaction`. If a recipe is soft-deleted between the check and the `limit(7)` draw, a category gets fewer than 7 meals and the plan is still marked `done`.
- **Fix**: Inside the transaction, throw `InsufficientRecipesException` when `$recipes->count() < self::MEALS_PER_CATEGORY` (the transaction then rolls back the delete).
- **Decision**: FIXED — in-transaction count check throws `InsufficientRecipesException` and rolls back; generator test deletes a recipe on `TransactionBeginning`.

### F5 — English status filter labels on the meal-plan list

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Http/Controllers/MealPlanController.php:65-70
- **Detail**: The `statuses` filter options are still Pending/Processing/Done/Error, while `StatusTag.vue` now shows Oczekuje/Generowanie/Gotowy/Błąd, so one page mixes two languages. The plan's manual check 3.7 only covers the list menu and the plan view, so this is not drift.
- **Fix**: Use the same Polish labels as `StatusTag.vue` (and translate "All statuses" in `StatusFilter.vue`).
- **Decision**: FIXED — Polish status filter labels in `MealPlanController::index` and "Wszystkie statusy" in `StatusFilter.vue`.

### F6 — Category labels defined twice; RecipeController depends on the meal-plan service

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architecture
- **Location**: app/Http/Controllers/RecipeController.php:13,188-190; resources/js/lib/mealCategories.js
- **Detail**: The category → Polish label map lives in `MealPlanGenerator::CATEGORY_LABELS`, and `mealCategories.js` duplicates it. The recipes feature now imports a meal-plan service just for labels. The two copies agree today. S-04/S-05 will add more consumers.
- **Fix**: Move the categories to a backed enum `App\Enums\MealCategory` (cases in display order, `label()`), use it in `MealPlanGenerator` and `RecipeController`, and keep `mealCategories.js` as the single frontend mirror (or pass the labels as a shared Inertia prop).
  - Strength: The dependency direction becomes correct (both features depend on a domain enum), and the model casts could use it later.
  - Tradeoff: It touches the service, the controller and their tests; this is a refactor in a green change.
  - Confidence: MED — straightforward, but the enum casts on `recipes.category`/`meals.meal_category` are a separate decision.
  - Blind spot: Whether to put the labels in `lang/pl/` instead (CLAUDE.md names it as the translation location).
- **Decision**: FIXED — `App\Enums\MealCategory` (`label()`, `values()`, `displayIndex()`) used by the generator, `RecipeController`, `MealPlanResource`, the E2E seeder and tests; `mealCategories.js` marked as its mirror. Model casts not changed.

### F7 — Queue worker runtime gaps (compose and CI)

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: docker-compose.yml:28-45; .github/workflows/master.yml:205-206
- **Detail**: The `queue` service has no `restart:` policy, so if the worker exits it stays down until the next `sail up`. It also does not depend on `laravel.test`, the service that builds `sail-8.5/app`, so a clean machine may try to pull the image. In CI the worker log goes to `storage/logs/queue.log` and is never uploaded, so a dead worker shows up only as a 15 s E2E timeout.
- **Fix**: Add `restart: unless-stopped` and `depends_on: laravel.test` to `queue`. Add an `if: failure()` step in `e2e-tests` that uploads `storage/logs/*.log`.
- **Decision**: FIXED — `queue`: `restart: unless-stopped`, `depends_on: laravel.test`; CI uploads `storage/logs/*.log` on failure.

### F8 — Each 3 s poll reloads ingredients and logs the view never uses

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/MealPlanController.php:79-85
- **Detail**: `show()` eager-loads `meals.recipe.recipeIngredients.{ingredient,unit}` and `logs` on every poll. `MealPlanGrid` uses only the name, kcal and servings, and the logs section was removed from Show. During a regenerate the old 21 meals are reloaded every 3 s. Polling also never stops if the worker is down (only the 30 s hint appears).
- **Fix**: Load only `meals.recipe` (with trashed) on the Inertia path and drop `logs`, keeping the full load for `expectsJson()` if the API contract needs it.
- **Decision**: FIXED — Inertia `show` loads only `meals.recipe`; the JSON path keeps ingredients and logs.

### F9 — Duplicate start date race returns 500

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/MealPlanController.php:110
- **Detail**: This was already there, but the new dialog now exposes it. The duplicate `start_date` check in the Form Request runs before the insert, so two tabs submitting the same Monday can hit the `unique(user_id, start_date)` constraint and get a 500 instead of a 422. A double-click is already prevented by `:disabled="form.processing"`.
- **Fix**: Catch `UniqueConstraintViolationException` around `MealPlan::create` and throw a `ValidationException` on `start_date` with the existing Polish message.
- **Decision**: FIXED — insert wrapped in `DB::transaction` (savepoint when nested); `UniqueConstraintViolationException` → 422 on `start_date`; feature test.

## Triage

All 9 findings fixed by the recommended option at the user's request ("samodzielnie popraw według rekomendacji"), 2026-11-13. Verified: Pest 183 passed (649 assertions), Larastan OK, Pint passed, `npm run build` OK, Playwright 8/8 (chromium + firefox) against Sail with the restarted `queue` service.
