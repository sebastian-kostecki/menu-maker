<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Meal Plan Show View in the New Design

- **Plan**: context/changes/meal-plan-show-ui/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-15
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Success criteria re-run (2026-11-15, this worktree, `APP_PORT=8121`)

| Check | Result |
|---|---|
| `sail npm run build` | PASS |
| `sail npm run lint:tokens` + `scripts/lint-tokens.test.sh` | PASS (20 files, 0 hits) |
| Literal grep on MealPlanGrid/ShoppingList/Show, no `<details>` | PASS (no output) |
| `sail composer test` | PASS (372 passed) |
| `sail composer pint-test`, `sail composer phpstan` | PASS |
| `sail pest tests/Unit/Seeders/E2eMealPlanSeederTest.php` | PASS (4 passed) |
| Seeder twice | PASS |
| Full E2E, chromium + firefox | PASS (22/22; password-reset needed `MAILPIT_URL=http://localhost:8066`) |
| `npm run test:visual` twice | PASS (33 passed, no snapshot changes) |
| `git merge-base --is-ancestor develop HEAD` | FAIL now (develop moved at 17:30, after p4 at 17:26). See F3 |

Manual rows 1.5–1.6, 2.6–2.7, 3.6–3.8, 4.8–4.10 are all still `[ ]` (pending, none falsely ticked).

## Findings

### F1 — Seeder test's "no real AI" guard cannot fail

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Unit/Seeders/E2eMealPlanSeederTest.php:16-18
- **Detail**: The test sets `services.ingredient_refiner.driver=openrouter` and `Http::preventStrayRequests()` to prove the seeder ignores the configured driver. In testing there is no `OPENROUTER_API_KEY`, so a real driver would throw `RefinementConfigurationException` (OpenRouterIngredientRefiner.php:30) before any HTTP call. That exception extends `RefinementFailedException`, which `MealIngredientScaler::refineWithRetries()` catches, reports and turns into `null` (MealIngredientScaler.php:165-180). The computed quantities at factor 2.0 equal the fake-rounded ones, so every assertion still passes. A regression such as renaming the scaler's `$refiner` parameter, which makes `app()->make(..., ['refiner' => ...])` drop the override silently, would go unnoticed.
- **Fix**: Also assert `$plan->generation_meta['scaling']['status'] === ScalingOutcome::STATUS_REFINED`, which is set only when refinement succeeds (MealIngredientScaler.php:113-119).
- **Decision**: FIXED — asserts `generation_meta.scaling.status === refined`; deliberate break (container-bound scaler) now fails this test while the other 3 still pass

### F2 — Shopping list heading dropped from h2 to h3

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/MealPlans/ShoppingList.vue:3
- **Detail**: The old component rendered `<h2>Lista zakupów</h2>`. `CardTitle` hard-codes `<h3>` (ui/card/CardTitle.vue), so the Show page outline is now h1 "Jadłospis" → h3 "Lista zakupów", skipping a level. The plan asked for `CardTitle` but did not consider its level. This is a plan gap rather than drift, and it is an accessibility regression from the previous view.
- **Fix**: In ShoppingList, replace `CardTitle` with an `<h2>` carrying the same classes (`flex items-center gap-2 font-semibold leading-none tracking-tight` + current ones), leaving `ui/` untouched; re-run the visual gate (pixels should be unchanged).
- **Decision**: FIXED — `<h2>` with CardTitle's classes in ShoppingList; `ui/` untouched

### F3 — Branch is behind develop; 3 textual conflicts

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: CLAUDE.md (Visual gate / E2E bullets), database/seeders/E2eMealPlanSeeder.php (class docblock), scripts/lint-tokens.paths
- **Detail**: Row 4.5's precondition (develop is an ancestor) held when Phase 4 ran (develop = 72ab6b0), but `family-members-ui` (7366493) landed four minutes later. A trial `git merge-tree` shows 3 conflicts:
  - CLAUDE.md: both sides rewrote the Visual gate and E2E bullets;
  - seeder docblock: "visual plan" here vs "family-members user" there;
  - lint-tokens.paths: both appended lines.

  Each resolves as the union of both sides. Develop also adds a `FAMILY_EMAILS` loop to the same seeder, and the seeder test asserts recipe counts and planners, so it must be re-run after the merge. No kitchen-sink PNG or `DesignSystem.vue` change comes in from develop.
- **Fix**: `git merge develop` and keep both sides in all three conflicts. Then re-run the seeder test, the seeder, full E2E and `npm run test:visual` twice (no `--update-snapshots`).
  - Strength: restores the plan's own precondition before the branch reaches develop; all conflicts are additive.
  - Tradeoff: one more gate cycle (~5 min).
  - Confidence: HIGH — trial merge shows exactly 3 additive conflicts, no PNG conflicts.
  - Blind spot: develop's family-members E2E specs have not been run together with this branch's seeder.
- **Decision**: FIXED — two merges of develop.
  - 7cef896 (S-12, S-13): the predicted additive conflicts, plus S-13's kitchen-sink changes.
  - The S-10 merge (meal-plans-index-ui), which landed later with its own `Common/PageHeader` and `Common/EmptyState`. On the user's decision, S-10's components stay canonical and were extended:
    - `PageHeader` gains a backward-compatible `as` prop and `flex-wrap` on its actions;
    - Show.vue uses `EmptyState`'s default slot inside a bordered wrapper;
    - our duplicate kitchen-sink demos were dropped, and S-10's demo passes `as="h2"`.
  - S-10's done 2026-06-15 fixture is now the deterministic visual plan, with its pinned timestamps kept. The seeder test and the visual spec select it by start date.
  - Verified on the merged tree: Pest 375, Pint, Larastan, build, lint:tokens (45 files), seeder test, E2E 34/34, and test:visual 40/40 twice.
  - Baselines: `section-komponenty-aplikacji` and `overlay-confirm-dialog-field` are identical to develop. The meal-plan-show pages grew 4px/8px from S-10's header spacing (reviewed). `section-jadlospis` and `overlay-user-menu` changed in corner anti-aliasing only.

### F4 — Grid skeleton ignores reduced motion

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Components/MealPlans/MealPlanGrid.vue:15-17
- **Detail**: `ui/skeleton` applies `animate-pulse`. The grid's skeleton cells don't add `motion-reduce:animate-none`, while design-system.md requires `motion-reduce:` to be respected and the same view's spinner (Show.vue) does so. The old markup pulsed too, so this is not a regression, but the migration was the moment to align it.
- **Fix**: Add `motion-reduce:animate-none` to the grid's `Skeleton` classes (`cn` merges it).
- **Decision**: FIXED — `motion-reduce:animate-none` on both grid Skeletons

### F5 — overlay-user-menu.png changed outside the allowed PNG delta

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/e2e/__screenshots__/visual/design-system.spec.ts/overlay-user-menu.png
- **Detail**: Criterion 4.5 allowed ` M` only for `section-komponenty-aplikacji.png` plus at most `overlay-dialog.png`/`overlay-alert-dialog.png`; neither of those changed, but `overlay-user-menu.png` did. A pixel diff shows exactly 19 pixels in the top-right rounded corner (x 217–223, y 0–6), the same corner artefact the plan anticipated, on a different overlay. The commit message mentions it ("corner pixels only"); the plan does not, and 4.5 is ticked against wording it does not literally meet.
- **Fix**: Add a one-line deviation note under Phase 4 in plan.md. For future slices, phrase the allowance as "any `overlay-*.png`, corner pixels only".
- **Decision**: FIXED — deviation note added under Phase 4 › Baselines in plan.md

### F6 — Scaling notice is now announced assertively

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Pages/MealPlans/Show.vue:64
- **Detail**: `ui/alert/Alert.vue` always sets `role="alert"`. The AI scaling notice used to be a plain `div`, so screen readers now interrupt with it on every load of a done plan with notices. That is right for the error and slow-generation alerts but noisy for a static, informational notice.
- **Fix**: Pass `role="status"` on the scaling-notice `Alert` (Vue fallthrough attrs override the root's static `role`), no change to `ui/`.
- **Decision**: FIXED — `role="status"` on the scaling-notice Alert

### F7 — Roadmap S-09 status left at in-progress

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: context/foundation/roadmap.md:49, :149
- **Detail**: Phase 1 was to change only the Unknown text in S-09/S-10/S-11 ("Change nothing else in those items"), but it also set S-09 `ready` → `in-progress` (normal bookkeeping, harmless). change.md is now `implemented` and the roadmap was not updated, so the two disagree.
- **Fix**: Set S-09 to `done` in the table and item when the change is archived/merged (as the earlier slices did in their roadmap-sync commit).
- **Decision**: FIXED — S-09 set to `done` by `/10x-archive` (2026-11-15)

### F8 — Fixture robustness nits (seeder and visual spec)

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: database/seeders/E2eMealPlanSeeder.php:152-191; tests/e2e/visual/meal-plan-show.spec.ts:28-30
- **Detail**:
  - The seeder creates the plan as `pending`, then runs 21 updates, 21 inserts, scaling and the `done` update outside a transaction, so a mid-run throw leaves a half-built plan (the next run deletes it).
  - The spec does `JSON.parse(pageJson ?? '{}').props.mealPlans.data[0]...`, so a missing `script[data-page="app"]` or a changed prop shape fails with an opaque `TypeError`.
  - Minor: family birth dates are `now()->subYears(40)` while the plan date is fixed, so the 31–50 energy band (and the ×2.0 amounts) holds until about 2036.
- **Fix**: Wrap `seedVisualPlan()` in `DB::transaction()` and add `expect(pageJson).not.toBeNull()` before parsing.
- **Decision**: FIXED — `DB::transaction()` around the visual plan; spec asserts page JSON is non-null; birth-date drift queued in follow-ups/review-fixes.md
