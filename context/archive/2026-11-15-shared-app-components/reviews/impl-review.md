<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Shared App Components

- **Plan**: context/changes/shared-app-components/plan.md
- **Scope**: Full plan (all 5 phases implemented; all Automated rows `[x]`, all Manual rows still `[ ]`)
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-15
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 6 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Verification run in this review

- `bash scripts/lint-tokens.test.sh` — all PASS. `npm run lint:tokens` — OK (6 files, 0 hits).
- Grep 2.3 (old ServerPagination/SortableHeader paths) and 3.3 (old dialogs) — no matches.
- Pest, Larastan, Pint, build, E2E and visual gate were NOT re-run: the worktree has empty `vendor/` and `node_modules/` and no Sail containers of its own. Results rely on the phase commits.
- `overlay-dialog.png` / `overlay-alert-dialog.png`: pixel diff vs. `4b36d79^` is 26 px in an 8×9 px top-left corner, max channel delta 2/255.

## Findings

### F1 — Recipe details delete dialog can stay open with no feedback

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/hooks/useDeleteRecipe.ts:17-35, resources/js/Pages/Recipes/Show.vue:121-130
- **Detail**: The hook's promise settles only in `onSuccess` or `onError`. `onError` fires only for validation-error bags, so a 403/404/500 or a network failure runs just `onFinish`, which resets `isLoading` and never settles the promise. `handleDeleteConfirm` therefore never reaches its `finally`: the dialog stays open, the spinner is gone, and no toast appears. This breaks the plan's rule (plan.md:156) that the dialog closes when the request finishes, on success and on error alike. Other callers close in `onFinish` and are fine.
- **Fix**: Settle the promise from `onFinish` as well (a `settled` flag; reject with a toast when neither callback ran) so `finally` always closes the dialog.
  - Strength: One change in the hook; matches the plan's single close rule and keeps `Show.vue` as planned.
  - Tradeoff: Small hook change; the hook's unused `error` ref and the misleading "Laravel flash" comment (l.19-20) can be cleaned in the same edit.
  - Confidence: HIGH — Inertia only calls `onFinish` unconditionally.
  - Blind spot: Not reproduced in a browser (no stack in this worktree).
- **Decision**: FIXED via Fix (settle the promise in onFinish; useDeleteRecipe.ts) — not run in a browser/Pest here

### F2 — Stale localStorage filters leak into meal-plan page changes

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/Pages/MealPlans/Index.vue:174-180, :199-219
- **Detail**: `loadFiltersFromStorage()` copies a saved `status`/`perPage` into `localFilters` without issuing a request, so the server data (from the URL) is unfiltered while the filter widget shows the stored value. The new `handlePageChange` sends `{...buildQuery(), page}` built from `localFilters`, so clicking page 2 silently applies the stale filter or page size (a different `perPage` also shifts which rows land on page 2). Before this change pagination used server links, so this could not happen.
- **Fix A ⭐ Recommended**: Build the page-change query from `props.filters` (what the server echoed), not from `localFilters`.
  - Strength: Page 2 always continues the list the user is looking at; minimal, local to `handlePageChange`.
  - Tradeoff: The widget can still show a stored value that does not match the data (pre-existing; S-10 owns the filter cleanup).
  - Confidence: HIGH — `props.filters` already carries filter, sort, direction, perPage.
  - Blind spot: Behaviour with the stored filter and a user-initiated filter change was not exercised.
- **Fix B**: On mount, if stored values differ from the URL, navigate once (`router.get`, `replace`) so data and widget agree.
  - Strength: Fixes the mismatch at the source.
  - Tradeoff: Extra request on load; pulls S-10 filter work into this change.
  - Confidence: MEDIUM — depends on how S-10 plans to handle persistence.
  - Blind spot: Possible redirect loops with `perPage` defaults.
- **Decision**: FIXED via Fix A (handlePageChange builds the query from props.filters; MealPlans/Index.vue)

### F3 — All manual checks still pending; focus race in ActionDropdown unverified

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: plan.md Progress rows 1.5, 2.6–2.8, 3.6–3.8, 4.4–4.5, 5.7–5.9
- **Detail**: All 11 Manual rows are `[ ]`, which is legitimate (pending, not ticked without evidence). Check 3.7 matters most: `ActionDropdown.vue` has no `close-auto-focus` `preventDefault` and no `nextTick`, which the plan made conditional on that check (focus on „Anuluj”, no `pointer-events: none` left on `<body>`). Automated gates were also not re-run here.
- **Fix**: Run the manual checklist (especially 3.7 and 3.6 with throttled network) before merging.
- **Decision**: SKIPPED — manual checklist (esp. 3.7 focus/pointer-events and 3.6 throttled network) remains for the user before merge

### F4 — Two unplanned PNG baselines changed (accepted)

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: tests/e2e/__screenshots__/visual/design-system.spec.ts/overlay-dialog.png, overlay-alert-dialog.png
- **Detail**: Plan 5.4 promised only 3 new PNGs plus `section-odznaki.png`. These two also changed. Verified: 26 px in an 8×9 px top-left corner, max delta 2/255 (anti-aliasing noise). The exception is recorded in Progress 5.4 and accepted in 4484bac.
- **Fix**: None needed; the exception is documented and the diff is verified.
- **Decision**: ACCEPTED — verified 26 px / max delta 2/255; already recorded in Progress 5.4 and 4484bac

### F5 — English sr-only text in the pagination ellipsis

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/Components/Common/ServerPagination.vue:35
- **Detail**: `<PaginationEllipsis>` is rendered without a slot, so the default sr-only "More pages" stays English in an otherwise Polish component.
- **Fix**: Give the ellipsis a Polish sr-only label ("Więcej stron").
- **Decision**: FIXED (Polish sr-only "Więcej stron" in ServerPagination.vue)

### F6 — Confirm description flashes empty while the dialog closes

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Pages/Recipes/Index.vue (`handleDeleteConfirm` onFinish), resources/js/Pages/FamilyMembers/FamilyMemberTable.vue:93-98
- **Detail**: `onFinish` closes the dialog and clears the selection in the same tick, so the description (`„${selection?.name ?? ''}”`) renders „” during the ~200 ms close animation. Cosmetic; cancel/Esc paths are unaffected.
- **Fix**: Keep the last non-null name for the description (or clear the selection after the transition).
- **Decision**: FIXED (selection no longer cleared in onFinish in Recipes/Index.vue and FamilyMemberTable.vue)

### F7 — `filters.filter.status` shape is not covered by a test

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/MealPlanControllerTest.php
- **Detail**: Phase 2 changed the Vue code to read `filters.filter.status`; the old read was silently broken on exactly this point, and nothing asserts the shape the controller returns.
- **Fix**: Add `->where('filters.filter.status', 'done')` to a test requesting `filter[status]=done`.
- **Decision**: FIXED (added where filters.filter.status = done to the status-filter test)

### F8 — Roadmap status and change status disagree

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: context/foundation/roadmap.md (S-07 row), context/changes/shared-app-components/change.md
- **Detail**: Roadmap marks S-07 `in-progress` while `change.md` was `implemented`. Likely intentional until manual checks pass, but unreconciled.
- **Fix**: Move the roadmap row to done at archive time (`/10x-archive`), after manual checks.
- **Decision**: SKIPPED — move roadmap S-07 to done at /10x-archive, after manual checks
