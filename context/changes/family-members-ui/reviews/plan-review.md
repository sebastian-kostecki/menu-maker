<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Family Members UI Implementation Plan

- **Plan**: context/changes/family-members-ui/plan.md
- **Mode**: Deep
- **Date**: 2026-11-15
- **Verdict**: REVISE → SOUND after fixes
- **Findings**: 2 critical, 0 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | PASS |
| Plan Completeness | FAIL |

## Grounding
Grounding: 8/8 paths ✓, 5/5 symbols ✓, brief↔plan ✓

Verified without findings: `birth_date` JSON consumed only by `Pages/FamilyMembers/*` (`MealIngredientScaler.php:47,51` still gets Carbon); planned server messages identical to client strings (incl. weight/height and the server-side 19+ rule); Inertia v3 `post/put/delete` default `preserveState: true` and the URL follows DELETE → back() → `?page=1`; Playwright projects `chromium`/`firefox`, no storageState, no retries; "Kobieta" label; weight serializes as `"60.0"` and still formats as "60 kg".

## Findings

### F1 — /family-members/create returns 405, not 404

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: End-State Alignment
- **Location**: Desired End State; Phase 1 §1 Routes; Manual 1.7; Migration Notes
- **Detail**: With `only(index, store, update, destroy)` the route `family-members/{family_member}` still exists for PUT/PATCH/DELETE; "create" binds as the parameter, and `AbstractRouteCollection::checkForAlternateVerbs` (vendor `AbstractRouteCollection.php:56-68,107-115`) throws `MethodNotAllowedHttpException` → 405, before middleware. `/{id}/edit` gives 404. The planned `Route::has` test passes regardless and would not catch it.
- **Fix**: Add `->where(['family_member' => '[0-9]+'])` to the resource; replace the `Route::has` test with URL assertions (`/family-members/create` and `/family-members/{id}/edit` → `assertNotFound()`).
- **Decision**: FIXED (recommended fix)

### F2 — Grep gate 2.4 matches the plan's own new Pest test

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 Success Criteria (2.4) vs Phase 1 §5
- **Detail**: 2.4 greps `family-members\.(create|edit)` in `resources/js app routes tests` expecting no matches, while Phase 1 added `Route::has('family-members.create')` to `tests/Feature/FamilyMemberTest.php` — the gate could never pass, and an unattended run would loop or weaken the test.
- **Fix**: Resolved by F1's URL-based assertions (no route names in tests).
- **Decision**: FIXED (via F1)

### F3 — "Stays on the same page" doesn't cover adding a member

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: End-State Alignment
- **Location**: Desired End State, bullet 2
- **Detail**: The end state promised the same page after save/delete, but `store` redirects to page 1 (Critical Implementation Details, Phase 1 §2); a new member sorted by name may sit on another page.
- **Fix**: Reword: edit/delete keep the page; add lands on page 1.
- **Decision**: FIXED

### F4 — Docs still point to the files being deleted

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §1 (deletions); brief "Open Risks"
- **Detail**: `context/foundation/design-system.md:36` (audit table DS-02 reads) names `FamilyMembers/ConfirmDialog`; the brief listed this as a risk but no phase fixed it.
- **Fix**: Phase 2 §8 updates the "Duplikaty" row to the new component paths; `.ai/` specs stay (already marked partly superseded).
- **Decision**: FIXED
