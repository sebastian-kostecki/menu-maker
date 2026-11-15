<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: App Shell Restyle

- **Plan**: context/changes/app-shell-restyle/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-15
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 5 observations

## Verification run (this review)

| Check | Result |
|-------|--------|
| `sail npm run build` (client + SSR) | PASS |
| `sail npm run lint:tokens` | PASS (8 files, 0 hits) |
| `bash scripts/lint-tokens.test.sh` | PASS |
| Sidebar.vue gone, no Breeze dropdown imports in Layouts/Common | PASS |
| /10x-ui hard-coded value scan (Phase 2) | PASS (no hits) |
| `npm run test:visual` (after seeder, no `public/hot`) | PASS, 27/27 |
| `playwright test tests/e2e/app-shell.spec.ts` chromium + firefox | PASS, 6/6 |
| Not re-run here: full E2E suite, PHP suite/pint/phpstan, second visual run | taken from Progress (`[x]` with commit shas) |
| Manual rows 1.8-1.12, 2.5-2.6, 3.7-3.9 | PENDING (all unchecked; no false ticks) |

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

Notes: every planned file matches intent (drift agent found MATCH on all items). EXTRA, harmless: `data-testid` on the kitchen-sink sidebar boxes, `border-t`/`p-4` in AppSidebar, ring-offset and `shadow-none` in GuestLayout. The "NOT doing" list was respected (Breeze components untouched, no `/` redirect, no other `ui/` edits).

## Findings

### F1 — Two pre-existing baselines were rewritten, contradicting Progress 3.4

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/e2e/__screenshots__/visual/design-system.spec.ts/overlay-dialog.png, overlay-alert-dialog.png (commit a015ca5)
- **Detail**: Plan 3.4 requires "modifies none of the 19 existing ones" and is marked `[x]`. Commit a015ca5 changed `overlay-dialog.png` (11650 to 11649 bytes) and `overlay-alert-dialog.png` (7857 to 7855 bytes); the commit message calls them "deliberate" updates. The review first judged this to be encoding noise, but that came from a faulty comparison (alpha channel only). Re-running the gate with the old PNGs restored fails both tests with 46 differing pixels each: the rounded dialog corners show the page behind the overlay, which now includes the "Powłoka" section. The update was therefore legitimate; only the plan and Progress 3.4 were inaccurate.
- **Fix**: Record the deviation in the plan (done) instead of restoring the old PNGs.
- **Decision**: FIXED via plan note (original fix rejected: the baselines are NOT pixel-identical. 46 corner pixels differ because the new "Powłoka" section shows behind the overlay; my first PIL comparison was wrong, it checked only the alpha channel. Restoring the old PNGs fails the visual gate. The p3 update was legitimate; the plan now records the deviation)

### F2 — Logout E2E navigates before the logout request finishes

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/e2e/app-shell.spec.ts:57-62
- **Detail**: After clicking the "Wyloguj" menuitem the test immediately calls `page.goto('/dashboard')`. The Inertia POST to `logout` may still be in flight, so the dashboard request can abort it or leave with the old session. The final `toHaveURL(/\/login/)` can then pass or fail for the wrong reason and does not prove that the logout itself worked. It passed 6/6 here, so the race is latent.
- **Fix**: After the click, wait for the logout redirect (e.g. `await page.waitForURL(url => url.pathname === '/')` or assert a guest-only element), then `goto('/dashboard')` and assert `/login`.
- **Decision**: FIXED - `waitForURL(url => url.pathname === "/")` added before `goto("/dashboard")`; app-shell + login specs 12/12 in chromium and firefox

### F3 — Collapsed sidebar can flash expanded on a full page load

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/Layouts/AuthenticatedLayout.vue:13-19, resources/js/lib/useLocalStorageStateSync.js:12-16
- **Detail**: `sidebar.collapsed` starts `false` and the stored value is applied only in `onMounted` (this is what the plan prescribes, to keep SSR and the first client render identical). On a full load or reload with SSR, the expanded `w-64` layout can paint before JS hydrates, then jump to `w-16` with no animation (`hasToggled` is false) and shift the content. On client-side Inertia visits the restore happens before paint, so it is probably invisible there. Not verified at runtime, and it is unknown whether SSR runs in production. The E2E only asserts the final state, so it would not catch this.
- **Fix A ⭐ Recommended**: Accept and document the limitation in the plan/design-system notes
  - Strength: matches the plan's explicit design; the effect needs a full reload plus a user who collapsed the sidebar.
  - Tradeoff: a one-frame layout shift stays possible on reload.
  - Confidence: MEDIUM — reasoning from the code, no runtime proof.
  - Blind spot: whether production runs the SSR server and how visible the jump is on a slow connection.
- **Fix B**: Set a `data-` attribute or class from an inline script in the root view before first paint, and key the width classes on it
  - Strength: removes the flash entirely.
  - Tradeoff: adds an inline script and a second source of truth next to the composable, and widens the change beyond the shell files.
  - Confidence: MEDIUM — standard pattern, but it must stay SSR/hydration-safe.
  - Blind spot: CSP or other root-view constraints were not checked.
- **Decision**: ACCEPTED (Fix A) - limitation documented under Migration Notes in plan.md

### F4 — Guest logo link uses `outline-none` instead of `outline-hidden`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Layouts/GuestLayout.vue:16
- **Detail**: The logo link has `focus-visible:outline-none`, while the new shell code (`AppSidebar`, `Button`) uses `focus-visible:outline-hidden`. `outline-none` drops the transparent outline that forced-colours mode relies on, so the focus ring can vanish there.
- **Fix**: Replace `focus-visible:outline-none` with `focus-visible:outline-hidden`.
- **Decision**: FIXED - `focus-visible:outline-hidden` in GuestLayout.vue

### F5 — Dead avatar-image branch and noisy avatar for screen readers

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/Common/UserMenu.vue:37
- **Detail**: `AvatarImage v-if="user.avatar"` never renders because the `User` model has no `avatar` column (no match in `app/Models/User.php` or the users migration). The plan specified the prop, and the old layout had the same dead branch. Separately, the avatar's alt text and initials are read in addition to the visible name.
- **Fix**: Mark the avatar `aria-hidden`; keep the `avatar` prop only if an avatar feature is planned, otherwise drop the branch.
- **Decision**: FIXED - Avatar `aria-hidden="true"`, AvatarImage `alt=""`; the unused `avatar` prop kept as planned

### F6 — Sheet close label is Polish, Dialog close label is still English

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Components/ui/sheet/SheetContent.vue:56 (vs ui/dialog/DialogContent.vue:54, DialogScrollContent.vue:66)
- **Detail**: The new "Zamknij" is correct, but dialogs in the same Polish UI still announce "Close". `SheetContent` also has no `SheetDescription`, so reka-ui may log a "Missing Description" warning in dev (uncertain).
- **Fix**: Follow-up: align the dialog close labels to "Zamknij" (and consider `aria-describedby="undefined"` or a description for the drawer); out of scope for this slice's `ui/` rule.
- **Decision**: SKIPPED - follow-up: ui/dialog labels are outside this slice (ui/ rule)

### F7 — Open mobile drawer survives a viewport change past `lg`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Layouts/AuthenticatedLayout.vue:76-80
- **Detail**: If the Sheet is open and the viewport grows past `lg` (rotation, window resize), the dialog stays mounted; `lg:hidden` hides only the trigger. Two `nav "Główna nawigacja"` landmarks can then coexist. Rare edge case.
- **Fix**: Close the Sheet when the `lg` media query starts matching, or accept the edge case.
- **Decision**: SKIPPED - rare edge case, accepted

### F8 — Pre-existing: full User model shared with every page; seeder guard covers only production

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Middleware/HandleInertiaRequests.php:36, database/seeders/E2eMealPlanSeeder.php:44-47
- **Detail**: `auth.user` serialises the whole model (id, name, email, verification and timestamp fields; `password` and `remember_token` are hidden). The shell uses only name and email and adds nothing sensitive. Neither is part of this diff. The seeder creates known-password users and throws only when `app()->isProduction()`, so `APP_ENV=staging` would run it; it is only invoked explicitly (CI workflow, CLAUDE.md).
- **Fix**: Follow-up: share only `id`, `name`, `email`; widen the seeder guard to `! app()->environment(['local', 'testing'])`.
- **Decision**: SKIPPED - pre-existing and outside this diff; follow-up
