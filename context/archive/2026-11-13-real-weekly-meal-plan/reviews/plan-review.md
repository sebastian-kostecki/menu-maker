<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Real Weekly Meal Plan (S-03)

- **Plan**: context/changes/real-weekly-meal-plan/plan.md
- **Mode**: Deep
- **Date**: 2026-11-13
- **Verdict**: REVISE → SOUND after fixes
- **Findings**: 0 critical, 5 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding

18/18 paths ✓ (app/Services is new, as approved), 8/8 symbols ✓ (forUser, byCategory, RecipeFactory states, usePoll autoStart/only, ConfirmDialog, Ziggy route(), expectsJson vs Inertia v3 headers), brief↔plan ✓, Progress↔Phases ✓.

Verified safe: Inertia v3 sends `Accept: text/html, application/xhtml+xml`, so `expectsJson()` is false for Inertia visits (`@inertiajs/core` 3.11.0 `index.js:3470-3476`; `InteractsWithContentTypes.php:26`). `usePoll` accepts `{ autoStart: false }` and `only`, and returns `{ start, stop, polling }`. A worker service on `image: sail-8.5/app` without `build:` starts on Compose v2.39 (tested). It needs `WWWUSER` in its env for gosu, which the plan already has.

## Findings

### F1 — The recipe form still says "Supper"/"Dinner", the plan says "Obiad"/"Kolacja"

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Phase 3 §1
- **Detail**: Only `CategoryBadge.vue` was being translated. The options in `RecipeController.php:56-57, 71-72, 130-131` stayed "Supper"/"Super"/"Dinner". The shortfall message names "Obiad", which the recipe form does not offer. English "supper" means an evening meal, so users would classify evening meals into the Obiad column.
- **Fix**: Labels in `RecipeController` (3 places) come from the `MealPlanGenerator` map; check `RecipeRow.vue:92-94`.
  - Strength: One source of truth for the mapping.
  - Tradeoff: +1 PHP file in Phase 3.
  - Confidence: HIGH — grep shows these are the only remaining English labels.
  - Blind spot: Recipes already entered under the English meaning are not migrated.
- **Decision**: FIXED (Fix in plan: Phase 3 §1; Phase 3 automated criteria now include Larastan/Pint)

### F2 — The list of tests that will turn red is incomplete

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §4
- **Detail**: These were missing from the plan: `MealPlanControllerTest.php:128` (rate limit; without recipes the limiter is never hit); `…allows_done_status` / `…allows_error_status` (shortfall); English string assertions at `MealPlanRequestValidationTest.php:120,219`; and the "six regenerations" test under `Queue::fake()` (the status stays `pending`).
- **Fix**: List the tests explicitly with the way to update each.
- **Decision**: FIXED (Fix in plan: Phase 2 §4)

### F3 — `handle(MealPlanGenerator)` contradicts "F-01 tests unchanged"

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §2, §4
- **Detail**: `GenerateMealPlanJobTest.php:92` calls `->handle()` with no arguments. With the new signature this gives `ArgumentCountError`, not `RuntimeException`.
- **Fix**: F-01 tests pass `app(MealPlanGenerator::class)`; their assertions do not change.
- **Decision**: FIXED (Fix in plan: Phase 1 §4)

### F4 — Deleting from the menu is broken just like regenerating (204 → Inertia error modal)

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: End-State Alignment
- **Location**: Phase 2 §3, Phase 3 §4
- **Detail**: `ActionDropdown.vue:184` uses `router.delete`, while `destroy` returns `noContent()` (`MealPlanController.php:198`). Inertia v3 opens an error modal (`handleNonInertiaResponse`, core `index.js:2954-2977`).
- **Fix**: `destroy(): Response|RedirectResponse` (204 for JSON, redirect to `meal-plans.index` for Inertia) plus a test.
- **Decision**: FIXED (Fix in plan: Phase 2 §3, §4; Testing Strategy)

### F5 — Deleting a `pending` plan fails the job and sends an F-01 alert

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §2
- **Detail**: The policy allows deleting a `pending` plan. With `SerializesModels`, the worker throws `ModelNotFoundException` and reports it, so F-01 sends an alert for a user action. The E2E seeder triggers this too.
- **Fix**: `public bool $deleteWhenMissingModels = true;` plus a test.
- **Decision**: FIXED (Fix in plan: Phase 1 §2, §4; Testing Strategy)

### F6 — The view has no state for "done with 0 meals" or for a long `pending`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3 §3
- **Detail**: Migration Notes promised an empty state that the Show contract did not describe. Polling has no upper bound, so with no worker the skeleton shows forever.
- **Fix**: An empty-state message with Regenerate, and a "Trwa dłużej niż zwykle…" note after ~30 s.
- **Decision**: FIXED (Fix in plan: Phase 3 §3)

### F7 — Manual checks 1.4 and 3.4 need a worker that only arrives in Phase 4

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 / Phase 3 Manual Verification
- **Detail**: The Sail `queue` service is added only in Phase 4, and a raw JSON POST needs a session and CSRF.
- **Fix**: Specify `sail artisan queue:work --once` / `queue:listen` and how to create the plan.
- **Decision**: FIXED (Fix in plan: Phase 1, 2 and 3 Manual)
