<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Spiżarnia Meal Plans UI Implementation Plan

- **Plan**: context/changes/spizarnia-meal-plans-ui/plan.md
- **Mode**: Deep
- **Date**: 2026-11-17
- **Verdict**: SOUND
- **Findings**: 0 critical, 2 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
6/6 paths ✓, 6/6 symbols ✓ (formatDateRange, pluralize, Toggle, DropdownMenuRadio*, Button xs, PageHeader#breadcrumbs), brief↔plan ✓, Progress↔Phase ✓ (11 + 14 rows)

## Findings

### F1 — focus-visible.png missing from Phase 1's expected baselines

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1, change 8
- **Detail**: `design-system.spec.ts:63-72` screenshots the whole „Przyciski” section as `focus-visible.png`, and that section includes the chipTones pills, so the Obiad/Kolacja label swap changes it. The plan only expected `section-przyciski.png` and treats any other changed PNG as a regression.
- **Fix**: Add `focus-visible.png` to the expected changes, and note that `chip-focus-visible.png` must not change.
- **Decision**: FIXED

### F2 — Swap dialog: highlight replaces the meal fill with accent

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2, change 5
- **Detail**: `ui/combobox/ComboboxItem.vue:25` has `data-highlighted:bg-accent data-highlighted:text-accent-foreground`. That variant class wins over a plain `bg-meal-*`, so the current option loses its meal colour when highlighted from the keyboard.
- **Fix**: The current option's class map adds `data-highlighted:bg-meal-{c} data-highlighted:text-meal-{c}-foreground data-highlighted:brightness-95`.
- **Decision**: FIXED

### F3 — Draft placeholder must not carry data-testid="shopping-list"

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details / Phase 2, change 3
- **Detail**: `meal-plan.spec.ts:32, :146` assert `shopping-list` is hidden on a draft. `ShoppingList pending` now renders on a draft.
- **Fix**: Add a bullet to „Selectors the functional E2E depends on”: the placeholder carries no testid.
- **Decision**: FIXED

### F4 — Dashboard also renders PdfDownloadMenu (app-shell shots)

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1, change 6
- **Detail**: `Pages/Dashboard.vue:60-63` renders `PdfDownloadMenu` with no props, and it appears in `dashboard-desktop.png` / `dashboard-mobile.png`. The plan only promised that Show stays the same.
- **Fix**: Change 6 states that Show and Dashboard render as today with no props and that the `dashboard-*.png` shots must not change.
- **Decision**: FIXED

### F5 — Sticky day column: no gutter while scrolling sideways

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2, change 2
- **Detail**: With `border-spacing-2`, the gap next to the sticky `th` is transparent, so cells scroll right up to the pinned column with no gutter. The fullPage shots (scrollLeft 0) won't catch it.
- **Fix**: `border-spacing-x-0 border-spacing-y-2`, with the horizontal gap moved into cell padding (`pr-2` on the sticky th with `bg-card`, `pl-2` on the meal cells).
- **Decision**: FIXED
