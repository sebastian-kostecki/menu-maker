# App Shell Restyle — Plan Brief

> Full plan: `context/changes/app-shell-restyle/plan.md`

## What & Why

S-08 in roadmap M-2 (DS-03) moves the frame around every page onto the brand tokens and `ui/` primitives from F-03: the sidebar navigation, the header with the user menu, and the guest screens' layout. Today that frame is grey, built from Breeze components, and has behaviour bugs:
- the collapse toggle resets on every click;
- on mobile the hamburger covers the page title, and the drawer has no focus trap or Esc;
- keyboard focus is invisible;
- the guest logo opens the default Laravel page.

## Starting Point

- `Components/Sidebar.vue` and `Layouts/AuthenticatedLayout.vue` (which wraps 9 pages and remounts on each visit) use `gray-*`, `dark:` and Breeze `Dropdown`. `GuestLayout.vue` has a grey gradient and "© 2024". `Dashboard.vue` has 54 palette literals.
- The visual gate screenshots only the kitchen sink (19 PNGs). No app page has a baseline, and `lint:tokens` covers only `Pages/Dev`.

## Desired End State

- **Desktop:** the user navigates in a cream and white shell. The current page shows a light-green fill with a green icon, hover is a neutral fill, and Tab shows a ring on every control. A collapsed sidebar stays collapsed across pages and reloads.
- **Mobile:** the hamburger sits in the header and opens an accessible drawer.
- **User menu:** it is a keyboard-navigable `DropdownMenu`.
- **Guest screens:** they share the look and show the current year.
- **Dashboard:** it uses tokens only and is renamed "Pulpit".
- **Gate:** kitchen-sink states and real-page screenshots (`/login`, `/dashboard` at desktop and 390px) guard all of it.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Component structure | `Common/AppSidebar.vue` + `Common/UserMenu.vue`, prop-driven; `AuthenticatedLayout` stays the wrapper; `Sidebar.vue` deleted | Matches §5.2 `AppShell`, and props let the kitchen sink render every state without login. |
| Mobile navigation | `ui/sheet` (left) drawer, hamburger moved into the header row | Focus trap, Esc and aria-modal come for free and the overlap bug disappears. |
| Collapse behaviour | Persist in localStorage via `useLocalStorageStateSync`, read in `onMounted`, no animation on load | The toggle finally survives navigation without converting 9 pages to persistent layouts. |
| Nav styling | Active: `bg-accent` + `text-primary` icon + `aria-current`; hover: `bg-muted`; focus: `ring-ring` | Active and hover are clearly distinct and not colour-only, following §4.2's accent/primary roles. |
| Visual gate | Kitchen-sink "Powłoka" section (4 PNGs) plus new `visual/app-shell.spec.ts` (4 real-page PNGs, fixed clock, seeded `e2e-planner-visual`) | Only a composed-page screenshot can catch the hamburger and offset regressions. |
| Copy | "Dashboard" → "Pulpit"; "Zwiń/Rozwiń panel boczny"; "Otwórz nawigację" | Polish UI throughout; shown in the approved nav preview. |
| Guest logo target | `route('login')` instead of `/` | `/` serves the Laravel splash until S-15 adds the redirect. |
| Breeze file deletion | Left to S-15 (`Dropdown`, `DropdownLink` become orphans) | The roadmap assigns all Breeze removal to S-15. |

## Scope

**In scope:**
- `lib/navigation.js`, `Common/AppSidebar.vue`, `Common/UserMenu.vue`, the rewired `AuthenticatedLayout`, deleting `Sidebar.vue`.
- Progress bar → `var(--primary)`; an `sr-only` "Zamknij" label on the Sheet close button.
- `GuestLayout` on tokens with a `Card` and the current year; `Dashboard.vue` tokens and the "Pulpit" copy.
- 7 files added to `lint-tokens.paths`.
- Behaviour E2E `tests/e2e/app-shell.spec.ts`; the kitchen-sink section with 4 visual tests; the real-page visual spec; one seeder email.
- Updates to `CLAUDE.md` and `design-system.md`.

**Out of scope:**
- Breeze deletion and the `/` redirect (S-15).
- Page `#header` slot contents and `PageHeader` (S-07 and later slices).
- `Auth/*` page bodies (S-13).
- Dashboard real stats or a full restyle (Parked).
- Persistent layouts, dark mode, `ui/` regeneration, a tooltip primitive.

## Architecture / Approach

`navigation.js` (items) → `AuthenticatedLayout` (reads `$page`, `route().current()`, owns the persisted collapse and `Sheet` state) → `AppSidebar` (pure panel, hosted in the desktop `aside`, the mobile `SheetContent` and the kitchen sink) plus `UserMenu` (pure, `ui/dropdown-menu`).

The kitchen sink feeds the same components demo props. The real-page spec logs in as a dedicated seeded user, with the browser clock fixed so the footer year is stable.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Authenticated shell on tokens and primitives | New `Common/` components, Sheet drawer, persisted collapse, focus rings, progress bar, behaviour E2E, lint paths | Logout must work through `DropdownMenuItem as-child`; the collapse must not animate on every page load. |
| 2. Guest layout and Dashboard tokens | Token-only guest frame with the current year, Dashboard literals → tokens, "Pulpit" | Guest specs (`login`, `password-reset`) must stay green. |
| 3. States and visual gate | "Powłoka" kitchen-sink section, 8 new baselines (19 untouched), visual seed user, docs | Flaky real-page screenshots; mitigated by a fixed clock, a dedicated user and a fresh context. |

**Prerequisites:** F-03 and F-04 done (they are). Sail up with the `queue` service and Mailpit, started from the checkout being implemented. In the worktree that means its own `.env` with distinct ports, plus `VISUAL_DOCKER_NETWORK=app-shell-restyle_sail` (plan § Environment). `.env` with `AI_SCALING_DRIVER=fake` for the E2E runs.
**Estimated effort:** ~2 sessions across 3 phases.

## Open Risks & Assumptions

- S-07 runs in parallel and also adds `Common/` files and `lint-tokens.paths` lines. The filenames differ, so at most a trivial append conflict is expected.
- The 8 page headers outside the Dashboard keep their literal `#header` content inside the new shell until their own slices, so they will look mixed in the meantime.
- Assumes the visual environments render client-side, with no SSR server. If SSR ever runs there, the footer year comes from server time.
- The local `npm run test:visual` now needs `E2eMealPlanSeeder` first; this is documented in `CLAUDE.md`.

## Success Criteria (Summary)

- A keyboard or mobile user can always see where they are and where focus is, and the collapse and drawer behave as expected.
- No palette literal remains in the shell, guest layout or Dashboard, and `lint:tokens` blocks new ones.
- The visual gate holds 27 baselines: the 19 existing ones unchanged and 8 new ones reviewed. E2E is green in chromium and firefox.
