<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Spiżarnia Recipes UI

- **Plan**: context/changes/spizarnia-recipes-ui/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-18
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 6 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

Automated gates re-run on 2026-11-18 against the worktree stack `mm-recipes-ui` (mount `/var/www/html` → this worktree, confirmed with `docker inspect`; no `public/hot`):

- `sail pest --filter=RecipeControllerTest`: 31 passed
- `sail composer test`: 565 passed
- `sail composer phpstan`: no errors; `phpstan-baseline.neon` unchanged
- `sail composer pint-test`: passed
- `sail npm run build`: passed
- `sail npm run lint:tokens`: OK (72 files, 0 hits)
- grep for the removed components: no hits
- grep for `rounded-lg border bg-card|hover:text-primary`: no hits
- E2E (`E2E_BASE_URL=http://localhost:8095 MAILPIT_URL=http://localhost:8097`, after re-seeding): 36 passed
- Visual (`VISUAL_DOCKER_NETWORK=mm-recipes-ui_sail`): 58 passed with no snapshot update

## Triage (2026-11-18)

- Fixed: F1–F8 (F3 via Fix A).
- Pending: F9 (manual checklist).
- After the fixes:
  - Pest: 568 passed
  - Larastan, Pint, build and `lint:tokens`: OK
  - E2E: 36 passed
  - Visual: 58 passed (the 4 `meal-plans-index-*` baselines were updated deliberately)

## Findings

### F1 — Pending search debounce is not flushed on pill, sort or page change

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Pages/Recipes/Index.vue:212-221
- **Detail**:
  - `handleCategoryChange`, `handleSortChange` and `handlePageChange` call `visit()` but leave `searchTimeout` armed.
  - If the user types and then clicks „Obiad” within 300 ms, the timer fires and runs `visit(buildQuery({ search }))`. That merges `currentFilters.category`, which is still the stale server echo.
  - Inertia then cancels the in-flight category visit. The pill click is lost, or at best the request goes out twice.
  - `handleReset` already clears the timer.
  - Both review agents found this independently.
- **Fix**: Add a `flushPendingSearch()` helper that clears `searchTimeout` and sets `lastSentSearch = searchValue.value`, and call it at the start of the three handlers.
- **Decision**: FIXED — `flushPendingSearch()` is called in the category, sort, page and reset handlers.

### F2 — Server-trimmed search echo overwrites the input mid-typing

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Pages/Recipes/Index.vue:184-189
- **Detail**:
  - The global `TrimStrings` middleware is active: `bootstrap/app.php` does not remove it.
  - Steps: the user types „placek ” and pauses. `lastSentSearch` is `"placek "`, but `filters.search` comes back as `"placek"`.
  - The watcher sees a different value with no timer pending and resets the field to „placek”. The next letter then lands without the space.
  - This breaks multi-word searches typed with a pause.
- **Fix**: Compare trimmed values in the watcher (`incoming !== lastSentSearch.trim()`), or trim before sending and store the trimmed value as `lastSentSearch`.
- **Decision**: FIXED — the watcher compares with `lastSentSearch.trim()`.

### F3 — Meal-plans sort pill reads „Sortuj” on a bare URL (`[].sort`)

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/Pages/MealPlans/Index.vue:154-159
- **Detail**:
  - With no query params, `filters` arrives as `[]`, so `props.filters.sort` is `Array.prototype.sort`, which is truthy.
  - `SortMenu` therefore matches no option and shows „Sortuj” instead of „Najnowsze”.
  - The committed baseline `meal-plans-index-desktop.png` captures it.
  - The bug predates this change (S-25). `Recipes/Index.vue:132` normalises `[]`, so the two list pages now behave differently.
  - The plan required meal plans to stay byte-identical, so fixing it here changes meal-plans baselines.
- **Fix A ⭐ Recommended**: Copy the `Array.isArray(props.filters) ? {} : props.filters` normalisation into `MealPlans/Index.vue` and deliberately refresh the `meal-plans-index-*` baselines in a separate `fix:` commit.
  - Strength: A one-line, proven fix (the recipes page already has it). The bug shows up on the most common URL.
  - Tradeoff: The meal-plans baselines change on a branch whose plan promised they would not. That needs to be recorded in the commit and in the plan.
  - Confidence: HIGH — the mechanism is confirmed in the code and in the baseline image.
  - Blind spot: Other pages that read `props.filters.<key>` without normalising were not checked.
- **Fix B**: Leave it alone and add a roadmap carry-over, or a separate small change.
  - Strength: Keeps this branch's scope and baseline promise strict.
  - Tradeoff: A known UI bug ships, and the baseline keeps locking in the wrong label.
  - Confidence: MED — depends on when the follow-up lands.
  - Blind spot: None significant.
- **Decision**: FIXED via Fix A — `normalizedFilters` in `MealPlans/Index.vue`. The 4 `meal-plans-index-*` baselines were refreshed deliberately and reviewed: only the sort pill changed („Sortuj” → „Najnowsze”). Recorded in the plan addendum.

### F4 — Small undocumented deviations from the plan's contracts

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/Pages/Recipes/Index.vue:142; resources/js/Components/recipes/CategoryRadioTiles.vue:6-9, 41
- **Detail**:
  - `perPage` is read from the server's clamped `recipes.meta.per_page`, not from the URL as the plan says.
    - An explicit `?per_page=12` is dropped on the next change.
    - `?per_page=1000` is carried on as 100.
  - `CategoryRadioTiles` has an extra `name` prop, needed so the kitchen sink can show four groups on one page. It is documented in design-system.md.
  - The error state adds a `border-destructive` border.
  - All three are reasonable, but the plan does not record them.
- **Fix**: Add a short addendum to plan.md recording the three deviations as accepted.
- **Decision**: FIXED — plan.md „Addendum: impl-review fixes (2026-11-18)”.

### F5 — Source note hidden when the instructions hold only the source line

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/recipes/InstructionsSection.vue:8-29
- **Detail**:
  - The source `<p>` renders only inside `v-if="parsed.steps.length > 0"`.
  - Instructions made only of „Źródło: https://…” therefore show the empty state, and the source is not shown.
  - The importer always writes steps first, so only hand-typed text hits this.
- **Fix**: Render the source paragraph outside the steps/empty-state branch.
- **Decision**: FIXED — the source paragraph now renders after the steps or the empty state.

### F6 — Array `category` query param causes a 500

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/RecipeController.php:46
- **Detail**:
  - With `?category[]=x`, `(string) $request->input('category')` raises „Array to string conversion”, which becomes an `ErrorException`.
  - This is not a regression (the old code returned a TypeError 500), but the line is meant to be lenient.
- **Fix**: `$raw = $request->input('category'); $category = is_string($raw) ? MealCategory::tryFrom($raw) : null;`, plus a feature test.
- **Decision**: FIXED — `is_string()` guard plus the feature test „recipes index ignores a category sent as an array”.

### F7 — Category radio group has no required semantics

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/recipes/CategoryRadioTiles.vue (fieldset)
- **Detail**:
  - The other required fields in `RecipeForm` carry `required`.
  - The category group signals it only with the „*” in the legend: there is no `required` or `aria-required`.
  - `aria-invalid` and `aria-describedby` sit on every radio instead of the `role="radiogroup"` fieldset.
- **Fix**: Add `aria-required="true"` to the fieldset and move `aria-invalid`/`aria-describedby` to it.
- **Decision**: FIXED — `aria-required`, `aria-invalid` and `aria-describedby` are on the `fieldset`; design-system.md §5.3 updated.

### F8 — Deleting a recipe from a filtered list drops the filters

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/RecipeController.php (destroy → `redirect()->route('recipes.index')`)
- **Detail**:
  - The list state now lives only in the URL (localStorage restore removed, as planned).
  - Deleting from a searched or filtered list therefore lands on the unfiltered page 1.
  - The previous localStorage restore had at least kept the pills.
- **Fix**: Redirect back to the referring index URL when the delete came from the list (e.g. `redirect()->back()` unless the referrer is the recipe page).
- **Decision**: FIXED — `destroy` returns to the Referer list URL without `page`, otherwise to the plain index; two feature tests.

### F9 — All 24 manual checks are still pending

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/spizarnia-recipes-ui/plan.md (Progress: 1.9, 2.7–2.12, 3.5–3.9, 4.7–4.11)
- **Detail**:
  - Every automated row is `[x]` and was re-verified green here.
  - No manual row is ticked: no false check-offs, but nothing has been confirmed by hand yet.
  - Rows 2.10 (search focus and history) and 1.9 (meal-plans pills) touch F1–F3.
- **Fix**: Walk the plan's manual checklist (after F1–F3 are decided) and tick the rows before merging.
- **Decision**: PENDING — manual checklist; needs a human run before merge.
