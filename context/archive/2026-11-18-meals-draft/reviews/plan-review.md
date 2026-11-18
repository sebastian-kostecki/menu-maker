<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Drag-and-drop meals in the draft, any recipe in any slot

- **Plan**: context/changes/meals-draft/plan.md
- **Mode**: Deep
- **Date**: 2026-11-18
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
14/14 paths ✓, 7/7 symbols ✓ (changeRecipe, lockForUpdate pattern, ApproveMealPlanRequest Gate, ComboboxGroup with `heading`, MealLinkRules::PAIRS, recipe.category in MealPlanResource, US-018 free), brief↔plan ✓, Progress↔Phases ✓ (24/24 rows)

Verified as non-issues:
- **SSR:** no SSR process runs and `app.js` mounts with `createApp`, so `useMediaQuery` cannot cause a hydration mismatch.
- **Double `changeRecipe()`:** the no-op guard (same recipe, which covers every linked pair) rules out stale-model rewrites of the target row.
- **Authorization:** `move` is authorized in `MoveMealRequest`, as `approve` and `backToDraft` already are.
- **"obecny" row:** the grouped dialog fixes the gap for cross-category recipes.

## Findings

### F1 — New E2E drag steps can break the spec's existing amount and link assertions

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 4 §2 — E2E drag steps
- **Detail**: `meal-plan.spec.ts` is one positional flow that checks exact scaled amounts ("Mąka — 200 g" at :95, "400 g" at :198 and :254) and shopping totals. These hold only because every slot scales by 2.0 (`E2eMealPlanSeeder.php:396-412`). The flow also builds links on Mon/Tue Obiad and Mon Kolacja → Tue Śniadanie. The plan did not say which cells the drag uses or where the steps go. A Śniadanie or Kolacja recipe moved into Obiad scales by 2.67 and breaks those assertions.
- **Fix**: Append the drag steps at the end of the flow. Swap Friday Śniadanie ↔ Friday Kolacja (both 810 kcal at 30%). Drag into Tuesday's empty Śniadanie. Make the cross-category pick only between Śniadanie and Kolacja.
- **Decision**: FIXED

### F2 — Swapping two cooked meals empties two carried meals; the toast names one

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3 §2 (toast contract), Phase 1 §4 (move tests)
- **Detail**: Dragging one "Na 2 dni" Obiad onto another runs `changeRecipe()` on both rows, so both carried meals are emptied. The toast template names one meal, and no test covers two cooked meals.
- **Fix**: Build the toast from all emptied meals in one message. Add a feature test where both meals are cooked.
- **Decision**: FIXED

### F3 — The short-categories notice still points only to recipes already in the week

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: What We're NOT Doing (shortCategories)
- **Detail**: The count logic stays valid because the draw still works per category. But `Show.vue:92` suggests filling empty meals only "przepisem, który już jest w tygodniu", while any recipe from another category now fits.
- **Fix**: Change the notice copy in Phase 2 §1 and keep the logic.
- **Decision**: FIXED
