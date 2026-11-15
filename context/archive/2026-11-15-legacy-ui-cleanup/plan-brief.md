# Legacy UI Cleanup — Plan Brief

> Full plan: `context/changes/legacy-ui-cleanup/plan.md`

## What & Why

This is S-15 / DS-10, the last slice of milestone M-2 (one visual contract for the whole app). Every view is migrated, but `/` still shows the Laravel splash page, 13 dead Breeze files remain, and the token scan only covers the files that slices have listed by hand. This change removes the legacy UI, makes `/` an entry point (D-7), and turns `lint:tokens` into a full-tree CI guard. It also settles the one item §5.2 left for S-15: where pages put their `PageHeader`.

## Starting Point

- Nothing imports the 12 Breeze components or `ApplicationLogo`.
- A scan of all of `resources/js` finds 359 literals, every one in those files or `Welcome.vue`.
- `/` is a closure rendering `Welcome`.
- The forms plugin from Breeze still styles native inputs.
- Pages place their title three ways: in the sticky bar's `#header` slot (Dashboard, Recipes), as a hand-written `<h1>` in that slot (Profile, Family), or at the top of the content (MealPlans).

## Desired End State

- A guest opening `/` lands on login, and a signed-in user lands on the dashboard.
- No Breeze code or Breeze-installed styling is left.
- `scripts/lint-tokens.paths` is just `resources/js`, and CI fails on any new palette class, hex, arbitrary value or `dark:` outside `Components/ui/`.
- Every authenticated page shows its `PageHeader` at the top of its content, and the sticky bar is only navigation, "Menu Maker" and the user menu.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| `/` behaviour | `Route::redirect('/', '/dashboard')`; `auth` sends guests on to `/login` | D-7 says redirect, not landing page; no auth logic needed and the route stays cacheable | Design system D-7 / Plan |
| Logout and account-deletion targets | Unchanged (`/`), now forwarding to login | Smallest change; PHP assertions stay valid, only two e2e waits move to `/login` | Plan |
| `ExampleTest.php` | Replaced by `HomeRedirectTest.php` | The skeleton test asserted the splash page; the new one pins the redirect for both personas | Plan |
| Scan scope | All of `resources/js`, `Components/ui/` still excluded | Roadmap S-15 outcome; every remaining hit is in a deleted file | Roadmap |
| `PageHeader` placement | Top of page content, inside the width container; layout `#header` slot removed | Keeps the sticky bar slim on mobile, matches the newest views (S-09, S-10), and removing the slot enforces it | Plan (user) |
| Breeze-era leftovers | Uninstall `@tailwindcss/forms`, drop the pagination-views `@source` | Removes hidden grey/blue base styles that `lint:tokens` cannot see; the visual gate catches any pixel shift | Plan (user) |
| Regression after plugin removal | Fix in the `ui/` primitive with tokens, never re-add the plugin | Primitives should own their look | Plan |
| `bootstrap.js` / axios | Kept | `reportClientError.js` still uses `window.axios` | Plan (research) |

## Scope

**In scope:**
- Delete 12 Breeze components, `ApplicationLogo.vue` and `Welcome.vue`
- Redirect `/` to `/dashboard`; update the PHP and e2e tests that asserted the splash page
- Collapse `lint-tokens.paths` to `resources/js`; rewrite the token rule in `CLAUDE.md` and design-system §7.4
- Uninstall `@tailwindcss/forms`; drop two lines from `app.css`
- Move `PageHeader` into content on Dashboard, Recipes ×3, Profile and FamilyMembers; remove the layout slot; update design-system §5.2; update 12 baselines deliberately

**Out of scope:**
- Removing axios or `bootstrap.js`; any landing page; dark mode (D-1)
- `FamilyMemberPagination` merge, `Components/recipes/` rename, the duplicated auth link class
- Scanning `Components/ui/` or Blade views

## Architecture / Approach

The change is mostly subtraction, plus one route and one layout slot. Phases are ordered by visual risk: first the zero-pixel work (deletions, redirect, scan), then the plugin removal checked against unchanged baselines, then the deliberate layout move with its own baseline update. That way every moved PNG has exactly one cause.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Breeze removal, `/` redirect, full scan | No legacy files, `/` redirects, scan covers the whole tree, rules updated | E2E waits that expected `/` after logout or deletion |
| 2. Drop `@tailwindcss/forms` and the pagination `@source` | No Breeze-installed styling left | Inputs or textarea silently relied on plugin styles |
| 3. `PageHeader` placement unified in content | One header placement, layout slot removed, §5.2 updated | Baseline churn beyond the 12 expected PNGs |

**Prerequisites:** S-08…S-13 and S-16 done (they are). A worktree environment with its own `.env` ports and Sail stack (see the plan's "Environment" section). Docker for the visual gate.
**Estimated effort:** ~1 session across 3 small phases.

## Open Risks & Assumptions

- Removing the forms plugin is assumed to change no pixels, because `ui/input` and `ui/textarea` set their own border, padding and ring. If the gate disagrees, the fix goes into the primitive.
- The `dashboard-mobile-drawer` shot is expected to change, since the page behind the overlay moves. If it does not, that is fine; the criterion only forbids changes outside the 12 PNGs.
- FamilyMembers has no visual spec, so its header move is verified by hand and by the `family-members.spec.ts` heading locators.

## Success Criteria (Summary)

- Opening `/` never shows the Laravel page: a guest sees login, and a signed-in user sees the dashboard.
- `npm run lint:tokens` scans all of `resources/js` in CI and is green. No Breeze file or plugin remains.
- Every page shows its title the same way, at the top of the content. The full Pest, E2E and visual suites pass, and only the reviewed baselines changed.
