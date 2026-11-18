<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Spiżarnia Recipes UI Implementation Plan

- **Plan**: context/changes/spizarnia-recipes-ui/plan.md
- **Mode**: Deep
- **Date**: 2026-11-18
- **Verdict**: REVISE → SOUND after fixes
- **Findings**: 2 critical, 3 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | FAIL |
| Plan Completeness | FAIL |

## Grounding
12/12 paths ✓, 10/10 symbols ✓ (Badge tag/status, Toggle meal tones, Button dashed/secondary/lg, text-hero, shadow-sticker, highlight, EmptyState headingLevel, canEdit, MealCategory values, lint:tokens allows `has-[…]:` and `max-w-205`), brief↔plan ✓. `lib/mealCategories.js` already exists (F6). Verified safe: `Recipe::search()` adds no select or orderBy (GROUP BY-safe on PostgreSQL); `RecipeControllerTest` does not pin the page size or the prop set; the parallel S-23 and S-26 plans use only `CategoryBadge`, which stays.

## Findings

### F1 — Phase 1 changes the recipes-index baselines it says stay unchanged

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §5 vs. criterion 1.8
- **Detail**: per_page 15 → 12 in Phase 1 changes the full-page `recipes-index-{desktop,mobile}` shots (`tests/e2e/visual/recipes.spec.ts:35-49`: 12 rows instead of 15, and „Wyniki 1–12 z 21” in `ServerPagination.vue:3,7`), while 1.8 requires no snapshot update.
- **Fix**: Move the 12 default and its test to Phase 2 (new §4 „Page size of 12”); Phase 1 keeps 15 plus the 5–100 clamp.
- **Decision**: FIXED

### F2 — Playwright .check() on an sr-only radio will not pass the hit-target check

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 4 §1, §4; Critical Implementation Details
- **Detail**: Tailwind 4 `sr-only` = 1×1 px + `clip-path: inset(50%)`; Playwright 1.65 hit-tests the input's centre and lands on the `<label>`, so it reports „intercepts pointer events” until the timeout. The repo has no precedent for native radios or `.check()`. Confidence ~85%, not run.
- **Fix A ⭐ Recommended**: The input as `absolute inset-0 opacity-0 cursor-pointer` over the whole tile; the tile `relative`, its contents `pointer-events-none`.
  - Strength: `.check()` and `getByRole('radio')` work as planned; a tile click hits the radio.
  - Tradeoff: The order and pointer-events of the tile's contents need care.
  - Confidence: HIGH — a standard card-radio pattern.
  - Blind spot: Not seen in a visual shot (the input is invisible).
- **Fix B**: Keep sr-only; the E2E clicks the label and asserts `toBeChecked()`.
  - Strength: No change to the component contract.
  - Tradeoff: The test bypasses the role-based query.
  - Confidence: HIGH.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A)

### F3 — Re-syncing search from props can erase what the user types

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details — "Search input while typing"
- **Detail**: Re-syncing after every visit + preserveState + a 300 ms debounce means a response for „ros” can overwrite „rosół” typed in the meantime. There is no precedent: the old Index did not re-sync, and MealPlans has no text search.
- **Fix**: Re-sync only when no debounce is pending and the value differs from `lastSentSearch`; manual 2.10 extended with fast typing.
  - Strength: URL-only state without losing keystrokes.
  - Tradeoff: One more piece of state.
  - Confidence: HIGH — a classic debounced-search race.
  - Blind spot: Inertia cancelling earlier visits not checked.
- **Decision**: FIXED

### F4 — Phase 4 manual check quotes the wrong kcal message

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 4, Manual Verification (4.9)
- **Detail**: The actual message (`StoreRecipeRequest.php:67`, `UpdateRecipeRequest.php:68`) is „Kaloryczność całkowita musi wynosić co najmniej 50 kcal.”
- **Fix**: Correct the quote.
- **Decision**: FIXED

### F5 — "Section count stays 14" contradicts S-23, which merges first

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §7 (Kitchen sink and docs)
- **Detail**: The spizarnia-dashboard-ui plan (plan.md:232-255) adds a 15th section „Pulpit” and merges before S-24.
- **Fix**: Contract: "adds no new section", with no count.
- **Decision**: FIXED

### F6 — lib/mealCategories.js already exists

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §4, What We're NOT Doing
- **Detail**: The file exports `MEAL_CATEGORIES`, `MEAL_CATEGORY_LABELS` and `mealCategoryLabel`, and 5 files import it; the plan called it "the new helper".
- **Fix**: Extend it, do not overwrite; labels via `mealCategoryLabel`.
- **Decision**: FIXED

### F7 — per_page from the URL is lost on the first click

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2 §3 (currentFilters / buildQuery)
- **Detail**: `currentFilters` omitted `per_page`, contradicting "per_page is still honoured from the URL".
- **Fix**: `per_page` in `currentFilters`/`buildQuery` when it is in the URL (like `perPage` in meal plans).
- **Decision**: FIXED

### F8 — RecipeHero has no prop for the heading level the kitchen sink needs

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 §2 vs §5
- **Detail**: §5 required `as="h2"` "or a demo wrapper"; the §2 contract had no such prop.
- **Fix**: `headingLevel?: 1 | 2` (default 1), the EmptyState convention.
- **Decision**: FIXED
