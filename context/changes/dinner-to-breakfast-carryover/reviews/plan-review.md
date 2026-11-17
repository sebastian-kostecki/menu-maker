<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Kolacja Carried Over to the Next Day's Śniadanie

- **Plan**: context/changes/dinner-to-breakfast-carryover/plan.md
- **Mode**: Deep
- **Date**: 2026-11-17
- **Verdict**: SOUND
- **Findings**: 0 critical, 1 warning, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | PASS |
| Plan Completeness | WARNING |

## Grounding

20/20 paths ✓, 8/8 symbols ✓ (`PAIRS`, `canFeed`, `fedMealIds`, `servingsFor`, `MEAL_LINK_PAIRS`, `linkSourceFor`, `LINK_NOT_ALLOWED_MESSAGE`, `MEAL_SHARES`), brief↔plan ✓. One line reference had drifted: the `MealPlanGrid.vue` badges are at `:41-42`, not `:43-44`.

Verified against code:
- **Linking and unlinking:**
  - `link()` writes the Kolacja recipe onto the Śniadanie without a category check (`MealPlanMealController.php:80`); picking a recipe directly still checks the meal's own category (`UpdateMealRequest.php:48-51`).
  - "Wróć do szkicu" keeps links (`MealPlanController.php:310-327`); "Losuj ponownie" recreates the meals (`MealPlanGenerator.php:58-59`).
- **Amounts:** breakfast and dinner have equal kcal shares (`ScalingCalculator.php:18`), and the scaler multiplies the cooked meal's own factor by `servingsFor()`.
- **E2E:**
  - Every E2E recipe has the same ingredients, so `totalsWithEmptyMeal` holds.
  - The `td` nth(2) and `Śniadanie` nth(1) locators hit the right cells.
  - The step-4 "jest już / not obecny" option always exists (7 dinner recipes, all used).
- **Seeder:**
  - The offsets put Kanapki on Monday's Kolacja and Owsianka on Tuesday's Śniadanie.
  - The seeder test counts become 19 → 18 and 1 → 2, scoped to the draft.
- **Index page:** `meals_count` counts meals with a recipe and doesn't subtract linked meals (`MealPlanController.php:50`), so the `meal-plans-index-*` baselines stay unchanged.
- **No category assumptions elsewhere:** apart from `UpdateMealRequest`, no server code assumes a meal's recipe has the meal's category.

## Findings

### F1 — Kitchen-sink demo: the recipe mapping is missing

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §5 — Kitchen sink
- **Detail**: `DesignSystem.vue` has two separate ternaries: `link` (`:227`) and `dish` (`:228`, a 0-based index). The plan only replaced "the single ternary", and its "Id 16 shows dish 15's recipe" would keep Saturday's own "Tosty z awokado" instead of Friday's "Leczo z kiełbasą" (index 14). The demo would show a link between two different recipes, and the `section-jadlospis` baseline would lock that in.
- **Fix**: State that one id-keyed map covers both `link` and `dish`: `16 → { from_meal_id: 15, dish: 14 }`.
- **Decision**: FIXED

### F2 — Comments and titles that will become wrong

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phases 2–3, References
- **Detail**: Four spots outside the plan's list will be wrong after the change:
  - `resources/js/lib/mealLinks.js:12` ("Jak wczoraj" docblock);
  - `E2eMealPlanSeeder.php:181` (comment names only the Obiad badges);
  - `E2eMealPlanSeederTest.php:57` (title says "one repeated");
  - `meal-plan-show.spec.ts:58` ("uses every recipe"; Owsianka drops out).

  The `MealPlanGrid.vue:43-44` references are now `:41-42`.
- **Fix**: Add these spots to the relevant phases and correct the line references.
- **Decision**: FIXED

### F3 — The JS pair object isn't fully specified

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §1–§3
- **Detail**: `MEAL_LINK_PAIRS` would mix snake_case (`cooked_label`) and camelCase (`offerLabel`) keys. The PHP lookups say what happens when no pair is found (null); the JS ones don't, and their callers would read a property off a possible `null`.
- **Fix**: Use camelCase keys only on the JS side, have the JS lookups return `null` when no pair is found, and make the callers use `?.` and render nothing in that case.
- **Decision**: FIXED
