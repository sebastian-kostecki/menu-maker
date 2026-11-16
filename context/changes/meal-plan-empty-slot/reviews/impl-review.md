<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Empty Meal Slot (S-18)

- **Plan**: context/changes/meal-plan-empty-slot/plan.md
- **Scope**: Full plan (all 3 phases)
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-16
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

Success Criteria: every Automated item passes (re-run in this review: Pest 494 passed, Larastan no errors, Pint passed, build OK, `lint:tokens` 0 hits, seeder test 7 passed, migration `rollback --step=1` then `migrate` OK, FK `meals_recipe_id_foreign ... ON DELETE SET NULL`). The visual gate and E2E were not re-run here; their `[x]` rows rest on commit 9fc8dfd. Manual rows 1.5, 2.5-2.10 and 3.6 are still `[ ]` (pending, none ticked without evidence).

## Findings

### F1 — Search + Enter in the swap dialog empties the meal

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/MealPlans/RecipeSwapDialog.vue:15
- **Detail**: "Nie robimy (pusty posiłek)" is the first `ComboboxItem` and is always rendered (`ignore-filter` + own filtering). reka-ui's `ListboxFilter` calls `highlightFirstItem()` on every input event (`node_modules/reka-ui/dist/Listbox/ListboxFilter.js:65-68`), so after typing "zupa" the highlighted row is "Nie robimy", not the top match. Pressing Enter PATCHes `recipe_id: null` and the user silently loses the recipe. With no match, "Nie robimy" is the only item, so Enter also empties. No test covers type + Enter (E2E only clicks).
- **Fix**: Render the "Nie robimy" item after the recipe list (or hide it while `searchTerm` is non-empty), so the first highlighted item is always a recipe. Add an E2E/keyboard check for type + Enter.
  - Strength: Removes the destructive default on the keyboard path; matches manual check 2.10 ("work with the keyboard").
  - Tradeoff: "Nie robimy" is no longer the first row, which departs from the plan's wording ("first item"); update the plan.
  - Confidence: MED — highlight behaviour read from reka-ui source, not exercised in a browser.
  - Blind spot: Whether `highlightFirstItem` runs before or after the filtered list re-renders in the real DOM.
- **Decision**: PENDING

### F2 — Short-category notice goes stale after a swap

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/MealPlans/RecipeSwapDialog.vue:142
- **Detail**: `shortCategories` is a separate Inertia prop (`MealPlanController.php:119`). The swap `router.patch` uses `only: ['mealPlan']`, so the prop is not refreshed: filling the last gap of a short category leaves "Za mało przepisów…" on screen, and emptying a slot in a short category does not show it, until a full reload. Plan manual check 2.6 ("disappears once the gap is filled") would fail by hand. E2E does not cover it.
- **Fix**: Use `only: ['mealPlan', 'shortCategories']` in the swap `router.patch`.
- **Decision**: PENDING

### F3 — Approve claim fallback has no test

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: app/Http/Controllers/MealPlanController.php:274-290
- **Detail**: Plan Phase 1 §5 says the claim re-checks for a filled meal and answers `meals` vs `status`. The code does so correctly, but the "approve is refused when every meal is empty" tests only hit the Form Request path; the `whereHas` in the claim and the `$stillDraft` branch are unexercised.
- **Fix**: Add a feature test that empties the last meal between validation and claim (e.g. by hitting the controller with a stubbed/empty plan, or testing the `meals` error via a request that bypasses the Form Request check).
- **Decision**: PENDING

### F4 — Plan inaccurate on `MealPlanResource` and one baseline

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: app/Http/Resources/MealPlanResource.php:51-64; tests/e2e/__screenshots__/visual/meal-plans-index.spec.ts/meal-plans-index-generate-dialog.png
- **Detail**: (a) The plan's Key Discovery said `MealPlanResource` already omits `recipe` for a null recipe and needs no change. The implementation had to rewrite it (spread instead of `$this->when`) so an empty meal does not serialize as an empty array, which is truthy in JS. The change is justified, pinned by `assertJsonMissingPath('data.meals.0.recipe')`, and behaviour-equivalent for filled meals. (b) Phase 3 §4 listed the baselines expected to change but omitted `meal-plans-index-generate-dialog.png`, which is a full-page shot over the list and so shows the draft count 21 → 20 (9fc8dfd documents this).
- **Fix**: Add a short addendum to plan.md (Key Discoveries and §4 list) so the plan matches what shipped.
- **Decision**: PENDING

### F5 — Migration `down()` deletes empty meals, untested

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: database/migrations/2026_11_16_122505_make_meals_recipe_id_nullable.php:35
- **Detail**: Rollback deletes all meals with NULL `recipe_id` (cascading to `meal_ingredients`), turning 21-meal plans into plans with gaps. This is documented in the plan's Migration Notes and the docblock. In this review the rollback removed the one seeded empty meal from the worktree's dev DB (re-run `E2eMealPlanSeeder` before any E2E). No test covers `down()`.
- **Fix**: Accept; optionally add a migration test. No code change needed.
- **Decision**: PENDING

### F6 — Duplicate recipe count queries on draft Show

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/MealPlanController.php:130-150
- **Detail**: `recipeCountsPerCategory` runs 3 count queries and `recipeOptions` queries the same recipes again, only on draft show visits. The plan accepted the extra queries (Performance Considerations).
- **Fix**: Optional: one `groupBy('category')` query or derive counts from `recipeOptions`. No action required.
- **Decision**: PENDING

### F7 — Weak generator test for the 5-recipe case

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Unit/Services/MealPlanGeneratorTest.php (5-recipe case)
- **Detail**: The "each date once per category" invariant is asserted for one category only, and nothing checks that gap days are actually shuffled (plan Critical Implementation Details: gaps must not always be the last days). Not a named plan requirement.
- **Fix**: Assert the date invariant for all categories and, over several draws, that the gap day varies.
- **Decision**: PENDING
