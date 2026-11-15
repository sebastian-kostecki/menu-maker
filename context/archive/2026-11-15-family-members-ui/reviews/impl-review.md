<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Family Members UI Implementation Plan

- **Plan**: context/changes/family-members-ui/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-15
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 3 observations

Scope note: all automated Progress rows are `[x]`; all manual rows (1.7, 2.6–2.10, 3.6) are still pending human verification — no false check-offs.

Automated verification (re-run 2026-11-15 on this worktree's own Sail stack, ports 8111/5463/8056):
1.1 FamilyMemberTest 19 passed · 1.2 policy/request 13 passed · 1.3/2.5 full suite 360 passed · 1.4 Larastan no errors, baseline untouched · 1.5/3.5 Pint 160 files pass · 1.6 four routes (index/store/update/destroy) · 2.1/3.2 build OK · 2.2 only `Index.vue` · 2.3 token grep no matches · 2.4 deleted-refs grep no matches · 3.1 seeder twice, exit 0 · 3.3 spec 2/2 (chromium, firefox) · 3.4 E2E suite 14/14.
(First full-suite and E2E attempts failed on root-owned `storage/` and `bootstrap/ssr` files left by an earlier container run — environment, not code; green after `chown`.)

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Findings

### F1 — Non-validation failures on save/delete are silent

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/FamilyMembers/FamilyMemberDeleteDialog.vue:38; resources/js/Components/FamilyMembers/FamilyMemberDialog.vue:121
- **Detail**: Inertia's `onError` fires only for validation errors (422 → `errors` prop). A delete never produces those, so the planned "Nie udało się usunąć członka rodziny" toast is unreachable; a 403/404/500 goes through Inertia's `httpException` path and the dialog just stays open with no message. Same for the add/edit dialog on non-422 failures. The plan's contract itself prescribed `onError`, and `MealPlans/ActionDropdown.vue` uses the same pattern (codebase-wide, no global `router.on('httpException')`).
- **Fix A ⭐ Recommended**: Add `onHttpException` (and `onNetworkError`) visit callbacks in both dialogs that show the failure toast
  - Strength: Makes the plan's promised toast actually reachable; narrow, two files.
  - Tradeoff: Other features keep the same gap until they get the same treatment.
  - Confidence: MED — v3 renamed `invalid`→`httpException`; exact per-visit callback name should be checked in Inertia v3 docs.
  - Blind spot: Whether returning `false` from the callback is needed to suppress Inertia's dev error modal.
- **Fix B**: Register one global `router.on('httpException')` / `networkError` toast in `app.js`
  - Strength: Fixes every feature at once, including MealPlans.
  - Tradeoff: Outside this change's scope; generic message instead of a contextual one.
  - Confidence: MED — same docs caveat.
  - Blind spot: Interaction with existing client-error reporting (`ClientErrorController`).
- **Decision**: FIXED (Fix A) — onHttpException/onNetworkError toasts in both dialogs, returning false to suppress the default modal

### F2 — Hardcoded URLs instead of Ziggy named routes

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Components/FamilyMembers/FamilyMemberDialog.vue:131,133; resources/js/Components/FamilyMembers/FamilyMemberDeleteDialog.vue:32
- **Detail**: `router.put(\`/family-members/${id}\`)`, `router.post('/family-members')`, `router.delete(...)` — 17 page/component files use `route('…')` (e.g. `Recipes/Index.vue`, `RecipeRow.vue`) and the Boost rules say prefer named routes. The plan's contract literally specified these URLs (plan defect, not implementer drift); `useDeleteRecipe.ts` is the only other hardcoded caller.
- **Fix**: Use `route('family-members.store')`, `route('family-members.update', member.id)`, `route('family-members.destroy', member.id)`.
- **Decision**: FIXED — route('family-members.store'|'.update'|'.destroy')

### F3 — Delete dialog text loses the name while closing

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/FamilyMembers/FamilyMemberDeleteDialog.vue:55,63
- **Detail**: Open state is `member !== null`; on success/Anuluj the parent sets `memberToDelete = null`, so during the exit animation the description reads "Czy na pewno chcesz usunąć ? Tej operacji nie można cofnąć." Caused by the plan's "open when non-null" contract.
- **Fix**: Keep the last non-null member in a local ref (watch `member`) and render the name from it.
- **Decision**: FIXED — delete dialog renders a locally kept displayedName

### F4 — Day-boundary time-zone edges (cached max date, UTC server)

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/FamilyMembers/FamilyMemberDialog.vue:50; app/Http/Requests/FamilyMemberRequest.php:32; resources/js/Components/FamilyMembers/FamilyMemberTable.vue (formatAge)
- **Detail**: `maxDate` is a `computed` with no reactive deps, so "yesterday" is frozen at first evaluation (tab open past midnight keeps a stale `max` and client check). Server `before:today` uses `config/app.php` timezone `UTC`, so between 00:00 and 01:00/02:00 Warsaw time the client accepts a date the server rejects (error is shown — just a confusing round trip). `formatAge` calls `new Date()` during SSR (UTC) vs client (local) → possible hydration mismatch on a birthday right after midnight.
- **Fix**: Compute `maxDate` as a plain function / ref refreshed in the open watcher; accept the UTC edge cases (or set the app timezone to Europe/Warsaw in a separate change).
- **Decision**: FIXED — maxDate is a ref refreshed on every open; UTC server / SSR age edge cases ACCEPTED

### F5 — Unplanned UI additions and minor contract deviations

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: resources/js/Components/FamilyMembers/FamilyMemberDialog.vue:121,158; resources/js/Pages/FamilyMembers/Index.vue:49-51; resources/js/Components/FamilyMembers/FamilyMemberPagination.vue:84-89
- **Detail**: Not in the plan, all harmless: `DialogDescription` copy, input placeholders, `maxlength="255"` (makes the client ≤255 check unreachable), clear-on-input errors, `novalidate`, header text "Zarządzaj członkami swojej rodziny (N osób)". Minor deviations with identical behaviour: pagination takes only `currentPage/lastPage/prevPageUrl/nextPageUrl` (plan: all paginator fields); server errors replace the whole error object instead of per-field merge (client errors are always empty at submit, so equivalent). Nothing violates "What We're NOT Doing".
- **Fix**: Accept as-is; optionally add a one-line addendum to the plan noting the extra copy.
- **Decision**: ACCEPTED — harmless additions kept as recommended

### F6 — `redirect()->back()` follows the Referer header

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/FamilyMemberController.php:56,66
- **Detail**: `back()` uses `URL::previous()`, which trusts the Referer first, so an off-site Referer would redirect off-site. Not practically exploitable (PUT/DELETE need session + CSRF token) and it was the plan's explicit choice; noted for completeness.
- **Fix**: Optional — redirect to `route('family-members.index', ['page' => …])` derived from the same-host previous URL instead of raw `back()`.
- **Decision**: FIXED — update/destroy redirect to family-members.index with only the previous ?page (host dropped); covered by new Pest test
