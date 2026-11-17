<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Spiżarnia App Shell Implementation Plan

- **Plan**: context/changes/spizarnia-app-shell/plan.md
- **Mode**: Deep
- **Date**: 2026-11-17
- **Verdict**: REVISE (SOUND after fixes)
- **Findings**: 1 critical, 2 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | FAIL |

## Grounding

Grounding: 19/19 paths ✓, 6/6 symbols ✓ (`MEAL_CATEGORIES`, `MEAL_CATEGORY_LABELS`, `extendTailwindMerge` text list, `--sidebar*`, `--highlight*`, `--meal-*`), brief↔plan ✓.

Verified against the code:
- 56 shots on disk (33/5/2/7/4/5).
- Every authenticated shot is page-level.
- GuestLayout is used only as a template wrapper by the 5 Auth pages, with no VerifyEmail page.
- No spec reads the user's name or e-mail from the header.
- `lint:tokens` doesn't flag any of the planned classes.
- `--spacing` is the Tailwind default.
- Avatar `sm` = 40px.

## Findings

### F1 — Criterion 1.3 grep can't pass while the new test names the removed button

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1, change 9 vs Success Criteria 1.3
- **Detail**: Change 9 told the rewritten E2E test to "Assert that no button named 'Zwiń panel boczny' exists". Criterion 1.3 requires `grep -rn "…Zwiń panel boczny…" resources/js tests/e2e` to return nothing. Once the assertion is written, the string sits in `tests/e2e/app-shell.spec.ts`, so 1.3 always fails. An unattended `/10x-goal-implement` run would loop or stop on that contradiction.
- **Fix**: Drop the negative assertion. The grep plus deleting `AppSidebar` already guard the removal.
- **Decision**: FIXED

### F2 — "Changed set must equal the list" is too strict for overlay-user-menu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1, change 10; criterion 1.7
- **Detail**:
  - `overlay-user-menu.png` is an element screenshot of the menu content alone (`design-system.spec.ts:212-219`).
  - That content (`UserMenu.vue:48-66`) doesn't change, and `--sidebar` = `--background` (`app.css:157`), so the shot is likely byte-identical.
  - The contract "exactly the 25 baselines updated" would then fail even though the result is correct.
- **Fix**: The list becomes an upper bound: 24 or 25 PNGs, with only `overlay-user-menu` allowed to stay identical. All page-level shots must change.
- **Decision**: FIXED

### F3 — On mobile the single-column grid stretches the "compact" band

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2, change 3 (GuestLayout root)
- **Detail**:
  - The planned root was `min-h-screen grid lg:grid-cols-2`.
  - Below `lg` that is one column with two auto rows. With `align-content: normal`, the grid stretches both rows to fill `min-h-screen`.
  - The band would grow by half the free space, and its `justify-between` would pull the brand and the blocks apart.
  - The new `guest-login-mobile.png` would then pin that state as the baseline.
- **Fix**: Make the root `min-h-screen flex flex-col lg:grid lg:grid-cols-2`, and give the form side `flex-1`.
- **Decision**: FIXED

### F4 — Small factual slips in the plan text

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Key Discoveries (copy collisions), Phase 1 changes 2 and 7
- **Detail**:
  - **Copy collisions:** the check missed the link locator `login.spec.ts:57` (`getByRole('link', { name: 'Nie pamiętasz hasła?' })`), so that link text must not change. It also missed that a non-exact `getByLabel('Hasło')` runs on Login.
  - **`SheetHeader`:** the drawer doesn't use it today. The kitchen sink is the only consumer, and the plan adds the drawer. Its `overlay-sheet.png` should stay identical, because with `pr-12` the description still fits.
  - **Kitchen sink:** change 7 talked about "two `AppBrand` links", but phase 1 renders only one.
- **Fix**: Correct the three passages.
- **Decision**: FIXED

### F5 — Criterion 2.3 promised "exactly one h1", but the command didn't check it

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2, Success Criteria 2.3
- **Detail**: The criterion's title claimed one h1 per guest page, but the grep only looked for the tagline and footer copy.
- **Fix**: Retitle it to match Progress 2.3, and add `grep -rn "<h1" resources/js/Pages/Auth` (empty), so the h1 comes only from `GuestLayout`.
- **Decision**: FIXED
