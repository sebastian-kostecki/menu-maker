<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Meal Plan Draft with Recipe Swap

- **Plan**: context/changes/meal-plan-draft-with-swap/plan.md
- **Mode**: Deep
- **Date**: 2026-11-16
- **Verdict**: REVISE (SOUND after fixes)
- **Findings**: 1 critical, 2 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | WARNING |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | FAIL |

## Grounding
Grounding: 23/23 paths ✓, 8/8 symbols ✓ (scopeForUser, scopeByCategory, Meal::recipe withTrashed, REGENERATABLE_STATUSES, generate-meal-plan limiter key, Badge outline, PendingResourceRegistration::middlewareFor, MealPlan::meals), brief↔plan ✓

Verified with no finding:
- **Store:** the draw can join `store`'s transaction.
- **Polling:** it is driven by `watch(isGenerating)`, so it restarts after an approve redirect.
- **Dashboard:** `latestMealPlan` filters on `done`.
- **Framework:** `Rule::exists` accepts the string `meal_category`.
- **Race tests:** they can reuse the `afterResolving` pattern (`MealPlanControllerTest.php:395`).
- **Shortfall data:** nothing reads `generation_meta.missing`.

## Findings

### F1 — Expected visual baseline changes are wrong

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 4 §4 Baseline updates; criterion 4.6
- **Detail**: The plan said `meal-plan-show-*` must not change, but the done plan's header shows "Regeneruj" (`Show.vue:15`), which Phase 3 renames to "Wróć do szkicu". The list also missed `dashboard-mobile` (plan count 4 → 5) and `meal-plans-index-generate-dialog` (the new description line). It also missed that `meal-plans-index.spec.ts` asserts the row count twice (`:25`, `:61`). Criterion 4.6 could not pass as written.
- **Fix**: Rewrite the expected-change list and criterion 4.6 to match the planned UI changes.
- **Decision**: FIXED

### F2 — throttle:30,1 shares a bucket with client-errors

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §3
- **Detail**: Laravel 13.42 `ThrottleRequests` keys a signed-in user by `sha1(userId)` with an empty prefix. `client-errors.store` (`throttle:10,1`, `routes/web.php:39-41`) would share the bucket with draft creation and redraw.
- **Fix**: Use `throttle:30,1,meal-plan-draw` (key prefix) in `middlewareFor(['store','update'], …)`.
- **Decision**: FIXED

### F3 — The legacy scaling notice still says "zregeneruj"

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 (not covered)
- **Detail**: `ScalingNotices.php:51` tells pre-scaling done plans to "zregeneruj", an action the plan removes. The text is pinned in `ScalingNoticesTest.php:16,53`, and the non-done dataset (`:74-76`) lacks `draft`.
- **Fix**: New Phase 1 item 12: reword to "wróć do szkicu i zatwierdź go ponownie", then update the test text and dataset.
- **Decision**: FIXED

### F4 — Leftover regenerate references not listed

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §3, §11; Phase 4 §2
- **Detail**: Four spots were missing from the plan:
  - `GenerateMealPlanRequest:37,47` (the `regenerate` rule and merge);
  - `E2eMealPlanSeeder:216` (`'regenerate' => false` in the meta);
  - `MealPlanControllerTest:321,327` (store limiter-key assertions);
  - `DashboardControllerTest:21-30` (status loop without `draft`).
- **Fix**: List each one in its item.
- **Decision**: FIXED

### F5 — The seeded draft needs no repeated recipe

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Lean Execution
- **Location**: Phase 4 §2
- **Detail**: With 7 recipes per category, all of them drawn, every non-current swap option already shows "jest już". Forcing a repeat only complicated the reuse of `buildVisualPlan`.
- **Fix**: Drop the repeat requirement and reuse the pinning unchanged.
- **Decision**: FIXED
