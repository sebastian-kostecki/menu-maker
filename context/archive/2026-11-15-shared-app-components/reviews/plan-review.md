<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Shared App Components Implementation Plan

- **Plan**: context/changes/shared-app-components/plan.md
- **Mode**: Deep
- **Date**: 2026-11-15
- **Verdict**: REVISE → SOUND after fixes
- **Findings**: 0 critical, 3 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding

30/30 paths ✓, 6/6 symbols ✓ (`paginationInformation` hook, `AlertDialogCancel` auto-focus, `AlertDialogAction` close, `PaginationListItem` `aria-current`, badge variants without `muted`, `MEAL_CATEGORIES`), brief↔plan ✓, Progress↔Phase ✓

Verified (no finding):
- `paginationInformation()` returning `[]` removes the doubled meta.
- The JSON branch (`response()->json`) never goes through `toResponse()`, so it is unchanged.
- The `meta` keys match `toArray()`.
- Recipes already get scalar `meta` from `RecipeResource::collection`.
- reka-ui 2.11.0: `AlertDialogContent` forwards `escape-key-down`, and controlled `open` stays open when the parent drops `update:open(false)`.
- No importers outside the plan's list.
- No full-page or TOC screenshot, so the claimed PNG delta is correct.

## Findings

### F1 — Meal-plan page change: status-filter source unspecified

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Phase 2 §4; What We're NOT Doing
- **Detail**:
  - The end state promises that page changes keep filters (check 2.7).
  - `props.filters['filter.status']` (Index.vue:135,195) is always undefined, because `only()` nests the key as `filters.filter.status`.
  - Status survives only through localStorage, and the query key must be `filter[status]`.
  - Arriving by URL with empty storage, a page change drops the filter.
- **Fix A ⭐ Recommended**:
  - Build the page query with a helper extracted from `handleFiltersChange` (`localFilters`, `filter[status]`).
  - Read the initial status from `props.filters.filter?.status`.
  - Strength: The promise holds however the user arrives.
  - Tradeoff: Takes 2 lines of S-10 scope.
  - Confidence: HIGH — controller :44-45,69 and Index.vue verified.
  - Blind spot: FiltersBar may have its own initial-state read.
- **Fix B**: Name `localFilters` explicitly, leave the prop bug in S-10, and note the limitation in check 2.7.
  - Strength: Slice boundary unchanged.
  - Tradeoff: Pagination inherits the localStorage dependency.
  - Confidence: HIGH.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A). Changes: Phase 2 §4, What We're NOT Doing, manual check 2.7, the Phase 5 roadmap note for S-10, and plan-brief out of scope.

### F2 — Close-on-error rule differs across dialog callers

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: End-State Alignment
- **Location**: Phase 3 §2–3
- **Detail**:
  - Recipes/Index and the meal-plan callers close in onFinish.
  - Recipes/Show closes only on success (Show.vue:123-133), and the hook's rejection is unhandled.
  - FamilyMemberTable has no closing rule.
- **Fix**: One rule in Critical Implementation Details: close on finish, success or error. Recipes/Show uses try/catch/finally; FamilyMemberTable closes in onFinish.
- **Decision**: FIXED

### F3 — Kitchen-sink trigger names collide in the visual spec

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 5 §1–2
- **Detail**: `getByRole('button', { name })` matches by substring. „Otwórz ConfirmDialog” is contained in „Otwórz ConfirmDialog w toku”, which breaks strict mode.
- **Fix**: Name the second trigger „Pokaż dialog w toku”, and use `{ exact: true }` in the new locators.
- **Decision**: FIXED

### F4 — Dropdown → alertdialog focus path is not covered

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3 §4, §6; check 3.7
- **Detail**:
  - ActionDropdown opens its dialogs from `DropdownMenuItem @click` (:28-45).
  - Without `v-if`, the menu's return-focus-to-trigger can race the focus on „Anuluj” and leave `pointer-events: none` on body.
  - The E2E focus check runs only on /recipes.
- **Fix**: Extend check 3.7. The fallback is `preventDefault` on `close-auto-focus`, or opening the dialog on `nextTick`.
- **Decision**: FIXED

### F5 — Disabling the focused button drops focus to body

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3 §1
- **Detail**: A native `disabled` on the activated confirm button blurs it, so focus leaves the dialog while the request runs.
- **Fix**: The confirm button uses `aria-disabled="true"` plus a click guard and `aria-disabled:` styles. „Anuluj” stays natively disabled.
- **Decision**: FIXED

### F6 — The family-member E2E spec under-specifies the form

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 §6, test 2
- **Detail**:
  - FamilyMemberRequest requires `first_name`, `birth_date` and `gender`, plus `weight_kg`/`height_cm` for an adult.
  - The toast text uses straight quotes.
  - There is no login helper.
- **Fix**: List the field values, the exact toast text and the inline login in test 2.
- **Decision**: FIXED
