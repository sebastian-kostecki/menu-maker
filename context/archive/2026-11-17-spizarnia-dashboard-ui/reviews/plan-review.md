<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Spiżarnia Dashboard UI Implementation Plan

- **Plan**: context/changes/spizarnia-dashboard-ui/plan.md
- **Mode**: Deep
- **Date**: 2026-11-18
- **Verdict**: REVISE → SOUND (after fixes)
- **Findings**: 1 critical, 0 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | PASS (1 observation) |
| Plan Completeness | FAIL → PASS after F1 |

## Grounding
13/13 paths ✓, 12/12 symbols ✓, brief↔plan ✓

Verified against the code, with no finding:
- Inertia SSR is configured but no SSR server runs (Sail, CI or prod), and `app.js` mounts with `createApp`, so the client-side „today” is safe.
- With tailwind-merge 3.7.0, `focus-visible:outline-highlight-foreground` replaces the base `focus-visible:outline-ring` in `cn()`.
- `lint:tokens` accepts every planned class (`size-18`, `min-h-13`, `p-7`, `bg-highlight`, …).
- The seeder gives `e2e-planner-visual` 21 recipes and 5 plans; the CLAUDE.md „four plans” is out of date.
- No spec depends on „Szybkie akcje” or „Ostatni jadłospis”.
- `GenerateMealPlanDialog` reads no page props.
- `Meal::recipe()` is `withTrashed()`.
- The kitchen-sink spec pins no clock, and the `today` prop already covers that.

## Findings

### F1 — `meals[].ingredients` is not absent; Phase 1 test will fail

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Completeness
- **Location**: Phase 1 — §1 Contract and §2 test „exposes the latest plan's meals…”
- **Detail**: `MealPlanResource.php:70` puts `$this->when(false, …)` inside the Collection that the `whenLoaded('meals')` closure returns. `ConditionallyLoadsAttributes::filter()` does not recurse into Collections, so the key serialises as `{}`. The JSON endpoints (`MealPlanController.php:224,260,341`) already emit `"ingredients": {}`. A `missing('latestMealPlan.meals.0.ingredients')` assertion would fail at gate 1.1.
- **Fix A ⭐ Recommended**: Change the contract and the test to „ingredients is an empty object” and use `->has('latestMealPlan.meals.0.ingredients', 0)`.
  - Strength: Keeps „no resource change” in scope; same shape the JSON endpoints already return.
  - Tradeoff: An empty key per meal in the payload.
  - Confidence: HIGH — the assertion matches the observed serialisation.
  - Blind spot: None significant.
- **Fix B**: Make the resource omit `ingredients` when not loaded.
  - Strength: Removes a latent quirk for every endpoint.
  - Tradeoff: Touches a shared resource and three JSON endpoints; needs a client sweep.
  - Confidence: MED — JSON consumers not audited.
  - Blind spot: Front-end code that assumes the key exists.
- **Decision**: FIXED (Fix A)

### F2 — „Pulpit” demo button names can collide with page-wide selectors

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2 — §7 Kitchen-sink section „Pulpit”
- **Detail**: The overlay tests (`design-system.spec.ts:138-241`) click buttons by non-exact, page-wide names („Otwórz dialog”, „Otwórz menu”, „Pokaż powiadomienie”, …). The inverse-strip demo labels were unspecified.
- **Fix**: Name the demo labels and forbid those names in „Pulpit”.
- **Decision**: FIXED
