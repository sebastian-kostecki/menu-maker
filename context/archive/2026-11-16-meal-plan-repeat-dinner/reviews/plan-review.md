<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Repeat Dinner (Cooked Once, Eaten Twice)

- **Plan**: context/changes/meal-plan-repeat-dinner/plan.md
- **Mode**: Deep
- **Date**: 2026-11-16
- **Verdict**: REVISE → SOUND after fixes
- **Findings**: 0 critical · 2 warnings · 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
Grounding: 25/25 paths ✓, 6/6 symbols ✓, brief↔plan ✓ (no `.ai/rules`, no `lessons.md`, no `contract-surfaces.md`)

Verified without findings:
- other meal readers need no change: show, index `meals_count`, approve, back to draft, redraw/destroy cascades, dashboard. There is no cook view;
- `MealPlanResource` always loads every meal, so `from_meal_id` can be derived with no query;
- E2E arithmetic (Mąka 400 g; totals 42 szt / 4,2 kg / 2,1 l), grid layout, "Składniki" button only when there are rows;
- PDF recipe pages group by `recipe_id` and then by printed lines, with `dayLabels` as the per-use label.

## Findings

### F1 — No-family doubling calls the AI and hides the family notice

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details; Phase 1 §6
- **Detail**: "Always computed → produces RefinementItems" means a family-less plan with a linked pair calls the refiner. On success the status becomes `STATUS_REFINED` (`MealIngredientScaler.php:109-134`), and `ScalingNotices.php:32-34` drops "Dodaj członków rodziny…". An unscaled recipe in a family plan would also be sent to the AI.
- **Fix A ⭐ Recommended**: Separate the ×2 multiplier from the scaling factor; `source`, RefinementItems and `unscaledRecipes` keep keying on the single-meal factor.
  - Strength: Keeps "no family → no AI call, status as_written" without a special case.
  - Tradeoff: `as_written` rows of a cooked meal hold recipe quantity × 2.
  - Confidence: HIGH — the status branch was read in full.
  - Blind spot: Whether any UI reads `as_written` as "equal to the recipe".
- **Fix B**: Keep "always computed", pin the status to as_written and skip the refiner without a family.
  - Strength: Matches the original wording.
  - Tradeoff: A special case; an unscaled recipe still goes to the AI.
  - Confidence: MED.
  - Blind spot: Mixed `computed` rows in an `as_written` plan.
- **Decision**: FIXED (Fix A). Critical Implementation Details, Desired End State, Phase 1 §6 and §8, and the Testing Strategy were updated.

### F2 — E2E unlink step can pick the current recipe

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 §1, §3, §4
- **Detail**:
  - `.filter({hasText:'jest już'}).first()` (meal-plan.spec.ts:40,152) can pick Monday's current recipe, which is "obecny" and "jest już: wt.". That is the no-op that keeps the link, so "Tuesday becomes Bez posiłku" fails.
  - `swappedName` may already be Tuesday's recipe, so the name assertion in step 1 can pass without proving anything.
  - The visual spec comment at `meal-plan-show.spec.ts:58` becomes false once Kotlety is replaced by Rosół.
- **Fix**:
  - pick with `hasNotText: 'obecny'`;
  - prove the link by the badges;
  - update the :58 comment and name the Kotlety → Rosół change in the expected baselines.
- **Decision**: FIXED

### F3 — canFeed does not say what to do with a deleted source recipe

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §3–4
- **Detail**: `Meal::recipe()` is `withTrashed()`. The `recipe_id` path refuses trashed recipes, but linking copies the source's `recipe_id` with no stated rule.
- **Fix**: Allow it explicitly (same pot) and add a rule test.
- **Decision**: FIXED (allowed, with the `canFeed allows a source whose recipe is soft-deleted` test)

### F4 — Request "exactly one key" and in-lock reads are underspecified

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §4; Critical Implementation Details
- **Detail**:
  - `recipe_id` is `present` today, and no concrete rule pair was named.
  - The approval-race test (`MealPlanMealControllerTest.php:165-178`) flips the status through `afterResolving` after validation. It passes only if the controller re-reads the status from the locked row, not from the route-bound or cached `mealPlan`.
- **Fix**:
  - name the rules: `recipe_id` gets `Rule::when(! has from_meal_id, ['present'])`; `from_meal_id` gets `missing_with:recipe_id`. Not `prohibits`, which ignores a null field;
  - require fresh reads inside the lock.
- **Decision**: FIXED
