<!-- PLAN-REVIEW-REPORT -->
# Plan Review: PDF Recipe Pages Implementation Plan

- **Plan**: context/changes/pdf-recipe-pages/plan.md
- **Mode**: Deep
- **Date**: 2026-11-16
- **Verdict**: REVISE → SOUND after fixes
- **Findings**: 1 critical, 3 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | FAIL |

## Grounding
16/16 paths ✓, 7/7 symbols ✓, brief↔plan ✓, Progress↔Phase ✓

## Findings

### F1 — Widening td.meal-* breaks the palette guard

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §4 Template — "Category colours"
- **Detail**: The plan widened `td.meal-*` to `.meal-*` while requiring the palette guard to pass unchanged. `MealPlanPdfPaletteTest.php:141-145` requires a literal `td.meal-<value> { background…; color… }` rule per category, so step 1.1 would fail.
- **Fix A ⭐ Recommended**: Keep the CSS unchanged and render the recipe page's category label as a one-cell `td.category.meal-*` table.
  - Strength: No CSS or guard change; it reuses the 8pt bold category role.
  - Tradeoff: A one-cell table for a label.
  - Confidence: HIGH — the rule and the guard regex were read directly.
  - Blind spot: None significant.
- **Fix B**: Add a separate `.category-label.meal-*` selector and extend the guard.
  - Strength: Semantic markup.
  - Tradeoff: Duplicated hex pairs and a guard change.
  - Confidence: MEDIUM.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A)

### F2 — Show header already has the ⋯ menu with PDF; the plan doesn't see it

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architectural Fitness
- **Location**: Current State "UI entry points"; Phase 3 §2
- **Detail**: `ActionDropdown` is also rendered in the Show header (`Show.vue:19`), so after Phase 3 two menus there offer the same two PDF variants. The plan described it only as the list's menu.
- **Fix A ⭐ Recommended**: Record it and accept the duplication (the page offers the PDF twice today); add Show's ⋯ menu to manual checks 3.6 and step 3.
  - Strength: Zero extra code; the ⋯ menu stays identical everywhere.
  - Tradeoff: Redundancy in the header.
  - Confidence: HIGH.
  - Blind spot: Width at 390px is verified only by 3.7 and the visual shot.
- **Fix B**: Add an ActionDropdown prop that hides the PDF items on Show.
  - Strength: A leaner header.
  - Tradeoff: A new prop and wider scope.
  - Confidence: MED.
  - Blind spot: The Show ⋯ menu is not covered by any shot.
- **Decision**: FIXED (Fix A)

### F3 — The expected baseline list misses dashboard-mobile-drawer.png

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3 §6 Visual baselines
- **Detail**: `app-shell.spec.ts:45-52` takes a full-page shot of the dashboard with the drawer open; the PDF button sits under it. The plan treated any unlisted PNG change as a regression.
- **Fix**: Add `dashboard-mobile-drawer.png` to the expected changes.
- **Decision**: FIXED

### F4 — `?recipes=true` is invalid; the validation test is ambiguous

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Testing Strategy → Unit Tests; Phase 2 §1
- **Detail**: Laravel's `boolean` rule accepts only `true, false, 0, 1, '0', '1'`, so the query-string `'true'` is rejected. The plan said the request "accepts … true, false".
- **Fix**: State that it accepts absent, `'1'`, `'0'` and PHP bools, and rejects `'abc'` and `'true'`; links always use `recipes=1`.
- **Decision**: FIXED

### F5 — ⋯ menu icons unspecified; wrong design-system section cited

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Key Discoveries; Phase 3 §2
- **Detail**: The icon rule is in design-system §4.7, not §5. `ActionDropdown` uses `Download` (`:79`), and the new items' icons were unspecified.
- **Fix**: Cite §4.7; use `ChefHat` / `FileDown` for the two ⋯ items.
- **Decision**: FIXED
