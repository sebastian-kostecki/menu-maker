<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Drag-and-drop meals in the draft, any recipe in any slot

- **Plan**: context/changes/meals-draft/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-18
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Evidence

Automated gates re-run during the review on the main Sail stack (`menu-maker`, Mailpit dashboard on 8026):

- `sail pest --filter='MealPlanMealController|MealRequestValidation|MoveMealRequest'`: 65 passed
- `sail composer test`: 589 passed (2467 assertions)
- `sail composer phpstan`: no errors; `sail composer pint-test`: passed
- `sail artisan route:list --name=meal-plans.meals.move`: route registered
- `sail npm run build`: OK; `sail npm run lint:tokens`: OK (74 files, 0 hits)
- `sail pest --filter=E2eMealPlanSeeder`: 12 passed
- E2E (Chromium + Firefox, after the seeder): 36 passed, including `meal-plan.spec.ts` in both browsers
- `npm run test:visual` (after re-seeding): 64 passed

Backend move path checked by tracing: the plan row is locked, draft status is re-checked, both meals are re-read in the plan scope, and the no-op guard (same meal or same `recipe_id`, both empty included) runs before any write. Because a linked pair always shares one recipe, the second `changeRecipe()` call never works on a row the first call changed (`$target->refresh()` adds a second safeguard). Authorization goes through `MoveMealRequest` (`Gate::allows('update', meal)`) plus `scopeBindings()`. No N+1. Nothing from "What We're NOT Doing" was violated: no dependency, migration, `ScalingCalculator`, `MealLinkRules` or PDF change.

## Findings

### F1 — Drop-target outline flickers over a cell's child elements

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/MealPlans/MealPlanGrid.vue:231-235 (bound at :44 and :110)
- **Detail**: `onDragLeave(target)` clears `hoveredMealId` on every `dragleave`. When the pointer moves from the cell onto a child (Badge, Link, „Zmień”, the ingredients toggle), the child's `dragenter` sets the id and then the cell's `dragleave` clears it. A child's `dragleave` also bubbles to the cell's handler. The dashed outline stays off until the next `dragover` (about 50 ms in Chrome, up to about 350 ms by the spec), so it flickers while the pointer moves inside a target.
- **Fix**: Pass the event (`onDragLeave($event, meal)`) and return early when `(event.currentTarget as Node).contains(event.relatedTarget as Node | null)`.
- **Decision**: FIXED — onDragLeave ignores moves onto a child of the same cell (relatedTarget inside currentTarget).

### F2 — Linked partner and same-recipe cells look like valid drop targets but the drop is silently ignored

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/MealPlans/MealPlanGrid.vue:199-200, resources/js/Pages/MealPlans/Show.vue:325-328
- **Detail**: `isValidTarget` excludes only the dragged meal itself. A cell with the same recipe (always the case for the dragged meal's own „Z wczoraj” / „Z kolacji” partner) gets the dashed outline and `dropEffect = 'move'`. After the drop, `handleMove` returns without a request or any feedback. The plan's contract ("target differs from the source") let this through, so it is a gap in the plan more than drift. Manual check 3.6 expects "changes nothing", but the UI should not promise a move.
- **Fix**: In `isValidTarget`, also reject `target.recipe?.id === draggedMeal.value.recipe?.id` (the cell then shows the no-drop cursor and no outline).
- **Decision**: FIXED — isValidTarget also rejects a target with the same recipe (no outline, no-drop cursor); design-system.md updated.

### F3 — 10 manual checks still pending, several not covered by automation

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/meals-draft/plan.md:342-374 (Progress)
- **Detail**: 2.3, 2.4, 3.4–3.9, 4.5 and 4.6 are unchecked. Nothing is falsely ticked. The E2E covers 3.4, 3.5 and most of 2.3. These have no automated coverage: 3.6 (cooked Obiad toast and the partner no-op, see F2), 3.7 (click on the name still opens the recipe), 3.8 (coarse pointer), 3.9 (approved plan), 4.5 (PNG review, see F4) and 4.6 (PRD reading).
- **Fix**: Go through the remaining manual rows in Chrome and Firefox before merging to `main` and tick them in Progress.
- **Decision**: PENDING — manual checks stay with the user (2.3, 2.4, 3.4–3.9, 4.5, 4.6).

### F4 — Two baselines changed that the plan did not list, undocumented

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: tests/e2e/__screenshots__/visual/design-system.spec.ts/section-pulpit.png, inverse-focus-visible.png
- **Detail**: Plan (:252, :275) says only the draft grid, the swap dialog and the kitchen-sink grid and dialog should change. `section-pulpit` (655 px, max delta 58) and `inverse-focus-visible` (132 px on button glyphs, max delta 24) also changed. The content is the same: the most likely cause is sub-pixel anti-aliasing after the Jadłospis section above them got taller. No done-plan baseline changed. Neither the plan nor commit 304f3cd records this.
- **Fix**: Note both PNGs and the reason under Progress 4.5 when ticking it.
- **Decision**: FIXED — note added under Progress 4.5 in plan.md.

### F5 — Category tag hidden on every carried meal, not only Kolacja → Śniadanie

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/MealPlans/MealPlanGrid.vue:50-55
- **Detail**: The rule `!from_meal_id` follows the plan, but the plan only had the „Z kolacji” case in mind. Now that any recipe can sit in any slot, Monday Obiad can hold a Śniadanie recipe and be linked „Na 2 dni”. Monday then shows the „Śniadanie” tag, but Tuesday's carried Obiad, with the same recipe, does not.
- **Fix**: Suppress the tag only when the link explains the mismatch: `from_meal_id && recipe.category === carriedPairFor(category)?.from`.
- **Decision**: FIXED — showsCategoryTag hides the tag only when from_meal_id and recipe.category === carriedPairFor(category).from; design-system.md updated.

### F6 — Gaps in move test coverage and one dead test line

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/MealPlanMealControllerTest.php:627
- **Detail**: These paths have no tests:
  - Both meals empty, and an empty source sent through the API. The UI cannot drag an empty cell, but the endpoint accepts both.
  - A carried meal dropped onto a cooked meal of another pair. Each half is covered separately, and the code handles the combination correctly when traced by hand.
  - The info toast in `Show.vue:331-350`, which is covered only by manual check 3.6.

  `$wednesday->update(['recipe_id' => $wednesday->recipe_id]);` in the no-op test does nothing.
- **Fix**: Add a both-empty no-op feature test and the cross-pair case, and delete the dead line.
- **Decision**: FIXED — dead line removed; added feature tests: both-empty no-op, move from an empty meal, carried meal onto another pair’s cooked meal.

### F7 — Grip icon has no reserved space; long names can run under it

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/MealPlans/MealPlanGrid.vue:47
- **Detail**: `GripVertical` is `absolute right-1 top-1` and the cell keeps a symmetric `p-2.5`. In the draft baseline, „Chia z mlekiem kokosowym” and „Placki ziemniaczane ze śmietaną” end a few pixels before the icon. A slightly longer first line, or a badge plus a name in a narrower column (mobile/tablet), would overlap it.
- **Fix**: Add right padding to draggable cells when `canDrag` (e.g. `pr-6`), then deliberately update the draft baselines.
- **Decision**: FIXED — pr-6 on draggable cells; section-jadlospis and meal-plan-draft-{desktop,mobile,swap-dialog} re-baselined and reviewed (padding only).

### F8 — Kitchen-sink demo drag mutates shared demo data and leaves link badges behind

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: resources/js/Pages/Dev/DesignSystem.vue:268, :326-332, :1192
- **Detail**: `planMeals` became `reactive` and is shared by the editable grid, the read-only grid, Pulpit's `LatestPlanHero` and `WeekOverview`. A demo drag therefore changes every section. `moveDemoMeals` swaps only `recipe` and `ingredients`, so a dragged cooked cell keeps its „Na 2 dni” or „Też na śniadanie” badge next to another recipe. The static screenshots are not affected; only manual use of `/dev/design-system` is.
- **Fix**: Give the editable demo grid its own reactive copy of `planMeals` (deliberately re-baseline `section-jadlospis` if the read-only grid then loses the cross-category cell).
- **Decision**: FIXED — editable demo grid and swap dialog use their own reactive copy (draftPlanMeals); demo move unlinks like the server (cooked → carried emptied).
