<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Empty Meal Slot Implementation Plan

- **Plan**: context/changes/meal-plan-empty-slot/plan.md
- **Mode**: Deep
- **Date**: 2026-11-16
- **Verdict**: SOUND
- **Findings**: 0 critical, 1 warning, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding

33/33 paths ✓, 6/6 symbols ✓ (`missingRecipesPerCategory`, `shortfallMessages`, `recipeShortfall`, `MEALS_PER_CATEGORY`, `$request->integer('recipe_id')`, `backToDraft`), brief↔plan ✓, Progress↔Phase ✓. No `context/foundation/lessons.md` or `docs/reference/contract-surfaces.md`.

Verified without findings: the removed shortfall helpers have no callers beyond those the plan lists (the seeder only reads `MEALS_PER_CATEGORY`); the PDF exporter's null filter, `pdfMeal()` dereference and `recipe_id`-keyed recipe pages are as described; `MealPolicy`, `MealPlanResource` and Show's `meals_count` are safe with a null recipe; `meal-plans.draft` (`backToDraft`) keeps the meal rows.

## Findings

### F1 — reka-ui Combobox can't keep "Nie robimy" unfiltered

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 2 §2 (Swap dialog); Key Discoveries (recipeOptions bullet)
- **Detail**: In reka-ui 2.11 an item can't opt out of the search filter (`ComboboxItem.js:42-46`). With `ignore-filter`, `ComboboxEmpty` shows only when there are no items at all (`ComboboxEmpty.js:21`), so "Brak przepisów" would never show next to an always-present "Nie robimy". `handleSelect` exits on a falsy value (`RecipeSwapDialog.vue:102`), so a null value for the item would do nothing.
- **Fix A ⭐ Recommended**: Use `ignore-filter` + `v-model:search-term` and filter `items` in the component; always render "Nie robimy" first with a marker value; replace `ComboboxEmpty` with a `v-if` paragraph.
  - Strength: Keeps "first list item", keyboard navigation and "Brak przepisów".
  - Tradeoff: About 10 lines; the case-insensitive Polish filter has to be reproduced.
  - Confidence: HIGH — read from reka-ui 2.11.0 dist.
  - Blind spot: Whether the first item is highlighted on open (swap-dialog baseline only).
- **Fix B**: Make "Nie robimy" a separate Button above the Combobox.
  - Strength: No change to the filter.
  - Tradeoff: No longer one list: arrow keys skip it. The contract and check 2.10 need rewording.
  - Confidence: HIGH
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A)

### F2 — Approve can still claim an all-empty draft in a race

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §5 (Approve needs a filled meal)
- **Detail**: The filled-meal check runs in `ApproveMealPlanRequest::after()`, but the claim (`MealPlanController.php:241-248`) re-checks only the status. Emptying the last filled meal from another tab between the two approves an all-empty plan. The job ends `done` with no AI call (`MealIngredientScaler.php:111`), but a rate-limit slot is used up.
- **Fix**: Add `whereHas('meals', whereNotNull('recipe_id'))` to the claim and answer `EMPTY_DRAFT_MESSAGE` when the plan is still a draft.
- **Decision**: FIXED

### F3 — The open whole-number formatting question is settled

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Key Discoveries (last bullet); Phase 3 §1 step 3; brief Open Risks
- **Detail**: `QuantityFormatter` uses `MIN_FRACTION_DIGITS` 0 (`QuantityFormatter.php:18`), so the totals render "40 szt.", "4 kg", "2 l".
- **Fix**: Replace "confirm the whole-number rendering" with the exact strings.
- **Decision**: FIXED
