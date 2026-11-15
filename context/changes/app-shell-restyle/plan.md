# App Shell Restyle Implementation Plan

## Overview

S-08 in roadmap milestone M-2 (DS-03 in `context/foundation/design-system.md` §8, `AppShell` row of §5.2). The authenticated shell (sidebar navigation, header, user menu) and the guest layout move from grey palette literals and Breeze components to the brand tokens and `ui/` primitives delivered by F-03. `Dashboard.vue` gets tokens in place of literals so the final S-15 scan can pass; its full restyle stays parked.

The shell wraps every authenticated page, so this change also fixes the shell's behaviour defects found in the audit: the collapse toggle that resets on every navigation, the mobile hamburger that covers the page title, the mobile drawer without focus trap or Esc, and invisible keyboard focus on nav links. The change ends with the shell's states in the kitchen sink and a new real-page visual spec, so the composed layout is guarded by screenshots for the first time.

## Current State Analysis

- `resources/js/Layouts/AuthenticatedLayout.vue` (127 lines) wraps 9 pages (`Dashboard`, `FamilyMembers/{Create,Edit,Index}`, `MealPlans/{Index,Show}`, `Profile/Edit`, `Recipes/{Index,Show}`). Each page mounts its own copy (no Inertia persistent layout), so all shell state is recreated on every visit.
  - The user menu uses Breeze `Dropdown`/`DropdownLink` (`:42-110`) with a hand-drawn chevron `<svg>` (`:67-78`). `ui/dropdown-menu` exists and is unused here.
  - The shell background, header and menu use `gray-*`, `white` and `dark:` (`:22, :27, :34, :46, :60-63, :83-87`).
- `resources/js/Components/Sidebar.vue` (188 lines), imported only by `AuthenticatedLayout`.
  - Its state is `isCollapsed` and `isMobileMenuOpen` (`:16-17`), both lost on every navigation.
  - The mobile hamburger is `fixed top-4 left-4 z-50` (`:65`). It sits on top of the header's title, which starts at `px-4 py-3` in `AuthenticatedLayout.vue:27-34`.
  - The mobile overlay is a hand-made `div` with `bg-black/50` (`:78-82`): no focus trap, no Esc, no `aria-modal`. `ui/sheet` exists.
  - Nav links (`:134-159`) use the same `bg-gray-100` for hover, focus and active, with `focus:outline-hidden` and no ring. Keyboard users cannot see where focus is, and the active item is indistinguishable from hover.
  - Copy problems: the label "Dashboard" is English in a Polish UI (`:21`), and the collapse labels say "sidebar" (`:124`). Icons are `Home`, `ChefHat`, `Calendar` and `Users`; §4.7 prescribes `CalendarDays` for meal plans.
- `resources/js/Layouts/GuestLayout.vue` (38 lines), used by the 5 `Auth/*` pages.
  - Gradient `from-gray-50 to-gray-100`, `white`/`gray` card with `shadow-lg rounded-xl` (`:7, :25`).
  - The logo links to `/` (`:12`), which still serves the default Laravel `Welcome.vue` (S-15 redirects it later).
  - The footer is hard-coded to "© 2024" (`:33`).
- `resources/js/Pages/Dashboard.vue` (104 lines): 54 palette/`dark:` literals (blue/green/purple icon chips, blue gradient welcome box). The stats (`12`, `3`, `2 dni temu`) are hard-coded and fake, which is parked in the roadmap ("Pełny restyle `Dashboard.vue` i dashboard z prawdziwymi statystykami").
- `resources/js/app.js:58-60`: the Inertia progress bar (the shell's only navigation-loading indicator) is hex `#4B5563`.
- `resources/js/Components/ui/sheet/SheetContent.vue:52-56`: the close button renders only an `X` icon, with no accessible name.
- Orphans after this change: Breeze `Dropdown.vue` and `DropdownLink.vue` have no other importers. `NavLink.vue`, `ResponsiveNavLink.vue` and `ApplicationLogo.vue` already have none. Deleting them is S-15 scope.
- Verification today:
  - The visual gate (`tests/e2e/visual/design-system.spec.ts`) screenshots only the kitchen sink: 19 PNGs in `tests/e2e/__screenshots__/visual/design-system.spec.ts/`. No app page has a baseline yet.
  - `scripts/lint-tokens.paths` lists only `resources/js/Pages/Dev`. `scripts/lint-tokens.sh:44,54` accepts files or directories.
  - No E2E spec selects anything inside the shell; `login.spec.ts` only checks `/dashboard` in the URL.
  - CI runs `E2eMealPlanSeeder` before E2E and the visual gate (`.github/workflows/master.yml:217-242`).
- Its seeded planner users follow `e2e-planner-<project>@example.com` (`database/seeders/E2eMealPlanSeeder.php:27-30`, used in `tests/e2e/meal-plan.spec.ts:4`). There is no user for the `visual` project.
- Reusable pieces:
  - `resources/js/lib/useLocalStorageStateSync.js` loads in `onMounted`, saves on deep watch and wraps storage access in `try/catch`; it works with reactive objects, not bare booleans.
  - `ui/button` (ghost/outline variants carry `focus-visible:ring`), `ui/avatar`, `ui/separator`, `ui/card`.

### Charges

The `/10x-ui` pre-audit (there is no `research.md`; the charges live here):

| # | Category | Evidence | User impact | Addressed in |
| --- | --- | --- | --- | --- |
| C1 | Missing tokens | `Sidebar.vue:80,87,111,141-145,168`; `AuthenticatedLayout.vue:22,27,34,46,60-87`; `GuestLayout.vue:7,13,18,25,32`; `Dashboard.vue` (54 hits); `app.js:59` | The whole app frame is grey and off-brand while the buttons are already green. Dead `dark:` classes double every colour. | Phases 1, 2 |
| C2 | Missing shared component | Breeze `Dropdown`/`DropdownLink` + `<svg>` chevron (`AuthenticatedLayout.vue:42-110`); hand-made drawer overlay (`Sidebar.vue:77-99`) | The user menu has no arrow-key navigation. On mobile, Tab escapes behind the open drawer and Esc does nothing. | Phase 1 |
| C3 | Missing tokens (focus/active) | `Sidebar.vue:140-145`, `AuthenticatedLayout.vue:46` (`focus:outline-hidden`, hover = active) | Keyboard focus is invisible, and the current page doesn't stand out from a hovered link. | Phase 1 |
| C4 | Accidental architecture | Layout remounts per page (`Sidebar.vue:16`); fixed hamburger (`Sidebar.vue:65`); guest logo → Laravel page (`GuestLayout.vue:12`); "Dashboard"/"sidebar" copy | Collapse resets on every click. On mobile the hamburger covers the page title. Clicking the logo on the login page opens the Laravel splash. English labels appear in a Polish UI. | Phases 1, 2 |
| C5 | Accidental architecture | `Dashboard.vue:32,49,66` (fake stats `12`, `3`, `2 dni temu`) | The user sees numbers that aren't theirs. | **Deferred**: roadmap Parked ("dashboard z prawdziwymi statystykami"). |

## Desired End State

- `Components/Common/AppSidebar.vue` and `Components/Common/UserMenu.vue` are prop-driven shell components built only from tokens, `ui/` primitives and `lucide-vue-next`. `AuthenticatedLayout.vue` is the page-facing wrapper that reads `$page`/`route()`, owns the collapse state and the mobile `Sheet`, and keeps its `#header` slot API unchanged for the 9 pages.
- Nav states:
  - active item: `bg-accent text-accent-foreground font-medium`, icon `text-primary`, `aria-current="page"`;
  - hover: `bg-muted text-foreground`;
  - keyboard focus: a visible `ring-ring` ring.
- The desktop collapse persists across navigation and reload with no animated collapse on page load.
- Below `lg`, the hamburger sits inside the header row next to the title. Navigation opens in a left `Sheet` with a focus trap, Esc and an accessible close button.
- Labels: "Pulpit" instead of "Dashboard" (nav label, page `<h1>` and `<Head>` title), "Zwiń panel boczny"/"Rozwiń panel boczny" on the collapse toggle, "Otwórz nawigację" on the hamburger.
- `GuestLayout` uses tokens and a `ui/card`, shows the current year in the footer, and its logo links to `route('login')`. `Dashboard.vue` contains no palette literal.
- `scripts/lint-tokens.paths` covers `Common/AppSidebar.vue`, `Common/UserMenu.vue`, `lib/navigation.js`, `Layouts/AuthenticatedLayout.vue`, `Layouts/GuestLayout.vue`, `Pages/Dashboard.vue` and `app.js`, and `npm run lint:tokens` is green.
- The kitchen sink has a 12th section, "Powłoka". The visual gate has 8 new baselines (4 kitchen sink, 4 real pages), and the 19 existing baselines are unchanged.
- `tests/e2e/app-shell.spec.ts` (chromium + firefox) proves three behaviours: collapse persistence, the mobile drawer, and logout through the new menu.

Verify:
- `vendor/bin/sail npm run build` and `vendor/bin/sail npm run lint:tokens` pass.
- The E2E suite passes after the seeder.
- `npm run test:visual` passes twice in a row.
- A human has reviewed the 8 new PNGs and the manual checklist.

### Key Discoveries:

- Inertia props passed to `render` are merged over shared props, but the shell needs no fake auth: prop-driven `AppSidebar`/`UserMenu` let the kitchen sink (`routes/web.php:53`, no auth) render every state with demo data. `route()` (Ziggy) is available there because Ziggy is a shared prop (`app/Http/Middleware/HandleInertiaRequests.php:38`).
- The existing visual spec addresses sections by `getByRole('region', { name })` from `<section aria-labelledby>` (`DesignSystem.vue:188-189`, spec `:49-51`). Adding a section therefore does not shift the other 19 element screenshots.
- Playwright `name` matching is a case-insensitive substring match, so the existing "Otwórz menu" dropdown demo (spec `:104`) must not collide with a new button. "Menu użytkownika" and "Otwórz nawigację" do not contain "Otwórz menu".
- `useLocalStorageStateSync` needs reactive objects: `syncToStorage('menu-maker:app-shell', { sidebar })` with `sidebar = reactive({ collapsed: false })`.
- `sheetVariants` `side: left` already provides `inset-y-0 left-0 h-full w-3/4 border-r sm:max-w-sm` (`ui/sheet/index.js`).

## What We're NOT Doing

- Deleting Breeze components (`Dropdown`, `DropdownLink`, `NavLink`, `ResponsiveNavLink`, `ApplicationLogo`) or redirecting `/` / `Welcome.vue`. Both are S-15 (D-7).
- Restyling page bodies or the `#header` slot contents of the 8 non-dashboard pages, or adding `PageHeader`. That is S-07 and each page's own slice; until then those headers keep their literals inside the new shell.
- Restyling the `Auth/*` page bodies (Breeze inputs/buttons). That is S-13; they change here only through `GuestLayout`.
- A full Dashboard restyle or real statistics (Parked; charge C5 is deferred).
- Converting to Inertia persistent layouts (it would touch all 9 pages).
- Dark mode (D-1); the `.dark` tokens stay untouched.
- Regenerating `ui/` primitives (D-9). The only `ui/` edit is the pixel-neutral `sr-only` close label in `SheetContent`; the Sheet overlay `bg-black/80` stays.
- Adding a tooltip primitive for the collapsed sidebar; collapsed links use `title` plus an `sr-only` label.
- Changing the Profile page or any route, controller or policy.

## Implementation Approach

The work follows `/10x-ui` order: view on tokens and primitives first (Phases 1–2), then the state matrix and the gate (Phase 3). The tokens already exist (F-03), so there is no token-value phase.

- **Split by testability.** Presentation goes into prop-driven `Common/` components. Everything that needs `$page`, `route().current()` or storage stays in `AuthenticatedLayout`.
- **One nav component, two hosts.** `AppSidebar` has no positioning of its own. The layout places it in a fixed `aside` on desktop and inside `SheetContent` on mobile, and the kitchen sink places it in a bounded demo box.
- **One source for nav items.** The item list (label, route name, `route().current` pattern, icon) lives in `resources/js/lib/navigation.js`, so the layout and the kitchen sink don't duplicate labels.
- **Two kinds of gate.** The kitchen sink holds component states (with no DB or auth). The real-page spec holds the composed layout: the hamburger position, the content offset and the guest page.

## Critical Implementation Details

- **Timing & lifecycle (collapse).** Every navigation remounts the layout, and the stored flag can only be read in `onMounted`; reading it there keeps SSR and the first client render identical. If the `aside` width and content offset keep `transition-all duration-300` from the first render, a user with a collapsed sidebar sees it animate shut on every page. Apply the width transition only after the user's first toggle in this mount (e.g. a `hasToggled` flag).
- **User experience spec (logout).** "Wyloguj" must be an Inertia `<Link :href="route('logout')" method="post" as="button">` rendered through `DropdownMenuItem as-child`. That way reka's keyboard select (Enter/Space) fires the same click a mouse does. The behaviour E2E covers this path.
- **User experience spec (Sheet a11y).** `SheetContent` needs a `SheetTitle` (`sr-only`, "Nawigacja"). Without it reka-ui warns, and the dialog has no accessible name. Focus returns to the hamburger trigger on close (reka default; don't override `closeAutoFocus`).
- **Debug & observability (visual determinism).**
  - The footer year comes from `new Date()`. `app-shell.spec.ts` must call `page.clock.setFixedTime(...)` before `page.goto` and assert the footer text matches the fixed year.
  - The visual environments (`php artisan serve` in CI, Sail locally) don't run the SSR server, so the client render is the one screenshotted.
  - Each test uses a fresh browser context, so localStorage starts empty and the sidebar starts expanded.

## Environment (prerequisite for every phase)

Every `sail` command, E2E run and visual run must exercise this checkout's code. Sail serves the checkout it was started from (`.:/var/www/html` in `docker-compose.yml`), and `scripts/visual-test.sh` mounts the checkout it runs from. If the change is implemented in the worktree `.claude/worktrees/app-shell-restyle` (which starts with no `vendor/`, `node_modules/` or `.env`), it needs its own Sail stack:
- Copy `.env` from the main checkout. Give it its own `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT` and `FORWARD_MAILPIT_DASHBOARD_PORT`, and set `MAILPIT_URL` to match. Keep `AI_SCALING_DRIVER=fake`, `MAIL_MAILER=smtp` and `MAIL_HOST=mailpit`.
- Install `vendor/` with the Sail Composer container, not host PHP (Laravel docs, "Installing Composer Dependencies for Existing Applications"). Then run `vendor/bin/sail up -d`, `vendor/bin/sail npm ci` and `vendor/bin/sail artisan migrate`.
- Run the visual gate against this stack: `VISUAL_DOCKER_NETWORK=app-shell-restyle_sail VISUAL_BASE_URL=http://laravel.test npm run test:visual`. The Compose project name defaults to the directory name, and there is no `COMPOSE_PROJECT_NAME`.
- Before Phase 1, `vendor/bin/sail ps` lists `app-shell-restyle-*` containers, and `curl -s -o /dev/null -w '%{http_code}' http://localhost:${APP_PORT}/login` prints `200`.

If the change is implemented in the main checkout instead, its existing stack applies and none of this is needed.

## Phase 1: Authenticated shell on tokens and primitives

### Overview

Replace `Sidebar.vue` and the Breeze user menu with prop-driven `Common/` components on tokens and `ui/` primitives. Rewire `AuthenticatedLayout` (Sheet drawer, header hamburger, persisted collapse), fix the progress bar colour, add the files to `lint:tokens`, and cover the new behaviour with an E2E spec. This addresses charges C1–C4 for the authenticated shell.

### Changes Required:

#### 1. Navigation items

**File**: `resources/js/lib/navigation.js` (new)

**Intent**: A single list of the four main nav entries, so the layout and the kitchen sink render the same labels and icons.

**Contract**: The module exports `mainNavigation`, an array of `{ label, routeName, match, icon }`:
- `Pulpit` → `dashboard`, match `dashboard`, `Home`;
- `Przepisy` → `recipes.index`, `recipes.*`, `ChefHat`;
- `Jadłospisy` → `meal-plans.index`, `meal-plans.*`, `CalendarDays`;
- `Członkowie rodziny` → `family-members.index`, `family-members.*`, `Users`.

No literal hrefs: consumers call `route(item.routeName)`.

#### 2. Sidebar panel

**File**: `resources/js/Components/Common/AppSidebar.vue` (new)

**Intent**: The sidebar panel content (brand row with the optional collapse toggle, nav list, footer line) as a presentational component with no positioning, so it can live in the desktop `aside`, the mobile `Sheet` and the kitchen sink.

**Contract**:
- Props: `items: Array<{ label, href, icon, current: boolean }>`, `collapsed: boolean = false`, `collapsible: boolean = false`.
- Emits: `toggle-collapse`, and `navigate` when any link starts a visit, so the Sheet can close. Emit it from each `<Link @start="emit('navigate')">`, never from `@click`: `@inertiajs/vue3` 3.11 renders `h(as, { ...attrs, ...elProps, ...regularEvents })`, so Link's own `onClick` overwrites a passed-in click handler. `onStart` is a declared Link prop.
- Root: a `flex h-full flex-col bg-card` column. The nav list is a `<nav aria-label="Główna nawigacja">`.
- DOM order: brand row (brand link, then the collapse toggle when `collapsible`), then `<nav>`, then the footer line. Tab therefore goes from the toggle to the first nav item. The brand row is `flex items-center justify-between` when expanded. When collapsed it is `flex flex-col items-center gap-2`, with the brand icon above the toggle, so both fit in `w-16`.
- The brand link goes to `route('dashboard')`, with `ChefHat` `text-primary` and "Menu Maker" hidden when collapsed.
- Collapse toggle (rendered only when `collapsible`): `ui/button` ghost icon with `PanelLeftClose`/`PanelLeftOpen`, `aria-label` "Zwiń panel boczny"/"Rozwiń panel boczny" and `aria-expanded`.
- Nav item classes:
  - base `flex items-center gap-3 rounded-md px-3 py-2 text-sm text-muted-foreground transition-colors motion-reduce:transition-none`;
  - hover `hover:bg-muted hover:text-foreground`;
  - focus `focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring`;
  - active `bg-accent text-accent-foreground font-medium` with icon `text-primary`, plus `aria-current="page"`.
- Icons are `size-5 shrink-0`. When collapsed: `justify-center`, the label is `sr-only`, and the link gets `title`.
- The footer line "Menu Maker v1.0" uses `text-xs text-muted-foreground` and is hidden when collapsed.
- No palette classes, hexes, arbitrary values or `dark:`.

#### 3. User menu

**File**: `resources/js/Components/Common/UserMenu.vue` (new)

**Intent**: Replace the Breeze dropdown with `ui/dropdown-menu`, so the menu gets roving focus, Esc and arrow keys, and uses tokens.

**Contract**:
- Props: `user: { name: string, email: string, avatar?: string | null }`.
- Trigger: `ui/button` variant `ghost` (it carries the focus-visible ring). Use no `aria-label`, which would hide the visible name (WCAG 2.5.3, label in name). Instead it starts with `<span class="sr-only">Menu użytkownika</span>`, and its accessible name begins with that text. It then contains `Avatar` (`AvatarImage` when `avatar` is set, otherwise `AvatarFallback` initials, max 2, uppercase), then name (`text-sm font-medium`) and email (`text-xs text-muted-foreground`) hidden below `sm`, then `ChevronDown` `size-4 text-muted-foreground`.
- Content: `align="end"`, `w-56`. It holds:
  - a `DropdownMenuLabel` with name and email;
  - a `DropdownMenuSeparator`;
  - the item "Profil" (`User` icon, `<Link :href="route('profile.edit')">` via `as-child`);
  - the item "Wyloguj" (`LogOut` icon, see Critical Implementation Details).
- The `as-child` Links overwrite reka's `onClick`, so `handleSelect` never runs: `@select` never fires, and the menu does not close by itself on selection. Keyboard selection still works, because `MenuItem` calls `currentTarget.click()`. Both items rely on the visit remounting the layout. Do not add `@select` handlers to them.

#### 4. Layout rewiring

**File**: `resources/js/Layouts/AuthenticatedLayout.vue`

**Intent**: Compose the shell from the new components. Own the collapse state (persisted) and the mobile Sheet. Move the hamburger into the header. Keep the `#header` slot and default slot API so the 9 pages need no change.

**Contract**:
- Items: `mainNavigation` mapped to `{ label, href: route(routeName), icon, current: route().current(match) }`.
- State: `sidebar = reactive({ collapsed: false })`, synced through `useLocalStorageStateSync().syncToStorage('menu-maker:app-shell', { sidebar })`, plus `mobileNavOpen` (`ref(false)`) and `hasToggled` (see Critical Implementation Details).
- Desktop: a fixed `aside` (`inset-y-0 left-0 z-30 hidden lg:flex border-r`, `w-64` or `w-16`) containing `AppSidebar` with `collapsible`. The content wrapper gets `lg:pl-64` or `lg:pl-16`.
- Header: `sticky top-0 z-20 border-b bg-card px-4 py-3`. It contains:
  - on the left, a `SheetTrigger` hamburger (`ui/button` outline icon, `Menu`, `aria-label="Otwórz nawigację"`, `lg:hidden`);
  - then the `#header` slot, falling back to `<h1 class="text-lg font-semibold">Menu Maker</h1>`;
  - on the right, `UserMenu :user="$page.props.auth.user"`.
- Mobile: `Sheet v-model:open="mobileNavOpen"` with `SheetContent side="left" class="w-64 p-0"`, a `SheetTitle class="sr-only"` "Nawigacja", and `AppSidebar` (not collapsible) whose `@navigate` closes the Sheet.
- Root: `min-h-screen bg-background text-foreground`; `<main>` keeps `p-6`; `Toaster` stays.

**File**: `resources/js/Components/Sidebar.vue`

**Intent**: Delete it. `AppSidebar` plus the layout replace it, and it has no other importer.

**Contract**: The file is removed; no import of `@/Components/Sidebar.vue` remains.

#### 5. Progress bar and Sheet close label

**File**: `resources/js/app.js`

**Intent**: The navigation progress bar uses the brand token instead of grey hex.

**Contract**: `progress.color` is `'var(--primary)'` (Inertia injects it into a CSS `background` rule, so a CSS variable resolves).

**File**: `resources/js/Components/ui/sheet/SheetContent.vue`

**Intent**: Give the Sheet's close button an accessible name, which the mobile drawer now exposes to every user.

**Contract**: Add `<span class="sr-only">Zamknij</span>` inside the existing `DialogClose`. This is pixel-neutral: `overlay-sheet.png` must stay unchanged.

#### 6. Token scan coverage

**File**: `scripts/lint-tokens.paths`

**Intent**: Lock the migrated shell files against new literals.

**Contract**: Append one path per line:
- `resources/js/Components/Common/AppSidebar.vue`
- `resources/js/Components/Common/UserMenu.vue`
- `resources/js/lib/navigation.js`
- `resources/js/Layouts/AuthenticatedLayout.vue`
- `resources/js/app.js`

List the files individually, not the `Common/` directory, which S-07 fills in parallel.

#### 7. Behaviour E2E

**File**: `tests/e2e/app-shell.spec.ts` (new; runs in the `chromium` and `firefox` projects)

**Intent**: Guard the behaviour this phase changes, which no existing spec touches.

**Contract**: Log in as `e2e-planner-${testInfo.project.name}@example.com` / `Password123!` (same pattern as `meal-plan.spec.ts:3-9`). Three tests:
1. **Collapse persists.** On desktop, click "Zwiń panel boczny". Navigate with the "Przepisy" nav link and assert that "Rozwiń panel boczny" is visible and that "Przepisy" has `aria-current="page"`. Reload and assert it is still collapsed.
2. **Mobile drawer.** At a 390×844 viewport, open "Otwórz nawigację" and assert the dialog is visible. Press Esc, then assert the dialog is hidden and the hamburger is focused. Reopen, click "Jadłospisy", then assert the URL matches `/meal-plans` and the dialog is hidden.
3. **Logout through the user menu.** Open "Menu użytkownika", choose the "Wyloguj" menuitem, then assert that `/dashboard` now redirects to `/login`.

No class or colour selectors: roles and names only. Name matching is a case-insensitive substring match, so locate nav links inside `getByRole('navigation', { name: 'Główna nawigacja' })` (in the drawer: inside the dialog's navigation) with `exact: true`.

### Success Criteria:

#### Automated Verification:

- Client and SSR build passes: `vendor/bin/sail npm run build`
- Token scan is green with the new paths: `vendor/bin/sail npm run lint:tokens`
- Scanner self-test still passes: `bash scripts/lint-tokens.test.sh`
- `Sidebar.vue` is gone and no shell file imports Breeze dropdowns: `test ! -e resources/js/Components/Sidebar.vue && ! grep -rnE "Components/(Sidebar|Dropdown|DropdownLink)\.vue" resources/js/Layouts resources/js/Components/Common`
- The new behaviour spec passes in chromium and firefox: after `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`, `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npx playwright test tests/e2e/app-shell.spec.ts --project=chromium --project=firefox`
- The full E2E suite still passes (re-seed first): `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- The existing visual gate is unchanged (sr-only label pixel-neutral, kitchen sink untouched): `npm run test:visual`

#### Manual Verification:

- Desktop: the active item shows the accent fill with a green icon, and hover shows the muted fill, distinct from active. Tabbing through the nav shows a ring on each link, the collapse toggle and the user-menu trigger.
- Desktop: after collapsing, navigating between pages and reloading keeps the sidebar collapsed with no visible collapse animation on page load. Collapsed icons expose their label on hover (`title`).
- At 390px width: the hamburger sits in the header row and does not cover the page title. The drawer opens from the left, traps Tab, closes on Esc and on the X (screen reader announces "Zamknij"), and returns focus to the hamburger.
- User menu: it opens with keyboard (Enter, then arrow keys). "Profil" opens the profile page; "Wyloguj" logs out.
- The progress bar is green while navigating between pages.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Guest layout and Dashboard tokens

### Overview

Bring the guest screens' frame and the dashboard body onto tokens, fix the footer year and the logo target, rename the dashboard to "Pulpit", and extend the token scan. This addresses charges C1 and C4 for the guest layout and the dashboard.

### Changes Required:

#### 1. Guest layout

**File**: `resources/js/Layouts/GuestLayout.vue`

**Intent**: Give the guest frame the same warm look as the app, show the current year, and stop the logo from sending users to the Laravel splash page.

**Contract**:
- Root: `min-h-screen bg-background text-foreground`, with no gradient.
- Logo: `Link :href="route('login')"`, styled `text-2xl font-semibold tracking-tight text-foreground hover:text-primary transition-colors` with a focus-visible ring. `ChefHat` is `size-10 text-primary`.
- Tagline: `text-sm text-muted-foreground`.
- Form container: `ui/card` (border, no shadow per §4.6) wrapping the default slot in `max-w-md`.
- Footer: `text-xs text-muted-foreground` reading `© {currentYear} Menu Maker. Wszystkie prawa zastrzeżone.`, where `currentYear = new Date().getFullYear()`.
- The slot API is unchanged for the 5 `Auth/*` pages.

#### 2. Dashboard tokens and copy

**File**: `resources/js/Pages/Dashboard.vue`

**Intent**: Replace every palette literal and `dark:` class with tokens. This is no restyle: layout, content and the fake stats stay as they are (C5 deferred). Rename the page to "Pulpit" to match the nav.

**Contract**:
- `<Head title="Pulpit">`; the header `<h1>` reads "Pulpit" with the page-title role `text-2xl font-semibold tracking-tight`.
- Stat and quick-action cards: `rounded-lg border bg-card p-6`, no shadow.
- Icon chips: `bg-accent` with icons `text-primary` (one brand colour instead of blue, green and purple).
- Labels: `text-sm font-medium text-muted-foreground`. Values: `text-2xl font-semibold text-foreground` (the `Ostatni PDF` value keeps `text-sm font-medium`). Section heading: `text-lg font-semibold`.
- Welcome box: `rounded-lg border bg-accent p-6 text-accent-foreground`, with no gradient.
- Buttons and `GenerateMealPlanDialog` are unchanged.

#### 3. Token scan coverage

**File**: `scripts/lint-tokens.paths`

**Intent**: Lock the two files against new literals.

**Contract**: Append `resources/js/Layouts/GuestLayout.vue` and `resources/js/Pages/Dashboard.vue`.

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Token scan is green, including both files: `vendor/bin/sail npm run lint:tokens`
- The `/10x-ui` hard-coded value scan finds nothing in the shell files: `grep -nE '#[0-9a-fA-F]{3,8}\b|rgba?\(|hsla?\(|oklch\(|-\[[0-9.]+(px|rem)\]|\b(bg|text|border|ring|outline|from|via|to|fill|stroke|shadow|divide)-(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|white|black)\b' resources/js/Layouts/*.vue resources/js/Components/Common/AppSidebar.vue resources/js/Components/Common/UserMenu.vue resources/js/Pages/Dashboard.vue` prints nothing
- Guest-screen specs still pass (re-seed first): `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npx playwright test tests/e2e/login.spec.ts tests/e2e/password-reset.spec.ts --project=chromium --project=firefox`

#### Manual Verification:

- `/login`, `/register` and `/forgot-password` show the cream background, a bordered white card, the green logo, and "© <current year>" in the footer. Clicking the logo stays on `/login`.
- `/dashboard` shows "Pulpit" in the header, the browser tab and the active nav item. There is no blue, green or purple chip and no blue gradient; the layout is otherwise unchanged.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: States and visual gate

### Overview

Show the shell's states in the kitchen sink, gate them with screenshots, add the first real-page baselines (guest login and the composed authenticated layout at desktop and mobile), and update the rule and docs. This closes the `/10x-ui` 7-state matrix and the gate.

### Changes Required:

#### 1. Kitchen sink section "Powłoka"

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: Demo the new shared pattern with its states, as the CLAUDE.md design-system rule requires for a new shared pattern.

**Contract**: A new 12th `<section aria-labelledby="ds-powloka">` with `<h2 id="ds-powloka">Powłoka</h2>`, placed after "Nakładki". It contains:
- an expanded `AppSidebar` (collapsible, `Przepisy` current) in a bordered `h-96 w-64` box;
- a collapsed `AppSidebar` (`Przepisy` current) in an `h-96 w-16` box. Both are built from `mainNavigation`, and their collapse toggles flip local refs.
- a `UserMenu` with demo user `{ name: 'Anna Kowalska', email: 'anna@example.com' }` and no avatar, so it shows the initials fallback.

Tokens only (the page is already in `lint:tokens`).

#### 2. Kitchen-sink visual tests

**File**: `tests/e2e/visual/design-system.spec.ts`

**Intent**: Gate the section and the interaction states that a static screenshot can't show.

**Contract**:
- Add `{ name: 'Powłoka', slug: 'powloka' }` to `sections`, producing `section-powloka.png`.
- Add `shell nav hover`: hover the first "Jadłospisy" link (`exact: true`) inside the section's first `navigation`, move nothing else, and screenshot the expanded sidebar box as `shell-nav-hover.png`.
- Add `shell nav focus-visible`: focus the expanded sidebar's "Zwiń panel boczny" toggle (the control right before the nav list), press Tab so "Pulpit" is keyboard-focused, assert it is focused, and screenshot the box as `shell-nav-focus-visible.png`.
- Add `overlays › user menu`: click "Menu użytkownika", then screenshot `getByRole('menu')` as `overlay-user-menu.png`, following the existing overlay pattern (`page.mouse.move(0, 0)` first).
- Move the Figtree load assertion from `openKitchenSink` into a shared helper `tests/e2e/visual/support/fonts.ts`, so the new spec reuses it. The existing tests' behaviour is unchanged.

#### 3. Real-page visual spec

**File**: `tests/e2e/visual/app-shell.spec.ts` (new; `visual` project only, same `PLAYWRIGHT_VISUAL_DOCKER` skip guard)

**Intent**: Guard the composed layout (hamburger placement, content offset, guest frame) that component demos can't show.

**Contract**: Every test calls `page.clock.setFixedTime(new Date('2026-06-15T10:00:00'))` before navigating and asserts that Figtree loaded. Full-page screenshots:
- `guest-login.png`: `/login`; also assert the footer contains "© 2026".
- `dashboard-desktop.png`: log in as `e2e-planner-visual@example.com` / `Password123!`, wait for `/dashboard`, `page.mouse.move(0, 0)`.
- `dashboard-mobile.png`: the same at viewport 390×844.
- `dashboard-mobile-drawer.png`: at 390×844, after clicking "Otwórz nawigację" and asserting the dialog is visible.

#### 4. Visual seed user

**File**: `database/seeders/E2eMealPlanSeeder.php`

**Intent**: Give the `visual` project its own planner, following the per-project convention, so the visual spec never shares a session or data with the E2E projects.

**Contract**: Add `'e2e-planner-visual@example.com'` to `EMAILS` (the class already has `declare(strict_types=1)`). The seeder stays idempotent. Run Pint.

#### 5. Baselines

**Files**: `tests/e2e/__screenshots__/visual/design-system.spec.ts/{section-powloka,shell-nav-hover,shell-nav-focus-visible,overlay-user-menu}.png`, `tests/e2e/__screenshots__/visual/app-shell.spec.ts/{guest-login,dashboard-desktop,dashboard-mobile,dashboard-mobile-drawer}.png`

**Intent**: Create the 8 new baselines deliberately and touch none of the 19 existing ones.

**Contract**: Generate them only through `npm run test:visual -- --update-snapshots`, after the seeder, with `public/hot` absent.

#### 6. Rule and docs

**File**: `CLAUDE.md`

**Intent**: The visual-gate command now depends on seeded data; the next agent must know that.

**Contract**: In the "Visual gate" bullet under `## Commands`, say it screenshots the kitchen sink and the app shell, and that `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder` must run first (the app-shell spec logs in as `e2e-planner-visual@example.com`).

**File**: `context/foundation/design-system.md`

**Intent**: Keep the contract document true.

**Contract**:
- §5.2 `AppShell` row: it is realised as `Common/AppSidebar` and `Common/UserMenu`, with `AuthenticatedLayout` as the wrapper. Mobile nav uses `ui/sheet`, and the collapse persists in localStorage.
- §7.2: 12 sections, adding "Powłoka".
- §7.3: 23 kitchen-sink screenshots, plus `tests/e2e/visual/app-shell.spec.ts` with 4 real-page screenshots, a fixed clock, and the seeded `e2e-planner-visual` user.
- Bump `updated:`.

### Success Criteria:

#### Automated Verification:

- PHP suite, style and static analysis pass: `vendor/bin/sail composer test`, `vendor/bin/sail composer pint-test`, `vendor/bin/sail composer phpstan`
- Seeder runs twice without error (idempotent): `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder` (twice)
- Build and token scan pass: `vendor/bin/sail npm run build && vendor/bin/sail npm run lint:tokens`
- Baseline generation adds exactly 8 PNGs and modifies none of the 19 existing ones: after `npm run test:visual -- --update-snapshots`, `git status --porcelain -uall tests/e2e/__screenshots__` shows exactly 8 `??` entries and no ` M` (without `-uall`, the new `app-shell.spec.ts/` folder collapses into one entry)
- The visual gate passes twice in a row without updates: `npm run test:visual && npm run test:visual`
- The full E2E suite still passes (re-seed first): `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`

#### Manual Verification:

- Each of the 8 new PNGs is reviewed in the diff:
  - accent fill and green icon on "Przepisy";
  - hover distinct from active;
  - focus ring visible;
  - initials "AK" in the user menu;
  - the guest card with "© 2026";
  - the desktop dashboard with an expanded sidebar;
  - the mobile header with the hamburger left of "Pulpit", not overlapping it;
  - the mobile drawer open over the dimmed Sheet overlay (`bg-black/80`, kept as is).
- 7-state matrix confirmed:
  - default: `section-powloka`, `dashboard-desktop`, `guest-login`;
  - hover: `shell-nav-hover`;
  - focus-visible: `shell-nav-focus-visible`;
  - disabled: N/A, the shell has no disabled control;
  - error: N/A, the shell submits nothing (page errors surface as toasts, already gated by `overlay-toast`);
  - empty: no-avatar initials fallback in `section-powloka`;
  - loading: green progress bar, checked manually in Phase 1, because it is transient and not screenshottable.
- `CLAUDE.md` and `design-system.md` read correctly. `/10x-ui`'s `ui-quality-checklist.md` is walked through before merge.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- None. The changes are presentation plus one seeder constant. There is no JS unit runner in the repo, and the collapse persistence reuses an existing composable whose behaviour the E2E spec proves end to end.

### Integration Tests:

- `tests/e2e/app-shell.spec.ts` (chromium + firefox) covers collapse persistence across navigation and reload, the mobile drawer (Esc, focus return, link navigation) and logout through `DropdownMenuItem`.
- The existing `login`, `password-reset`, `meal-plan` and `recipe-form` specs must pass unchanged. They rely on roles and texts, and none touches shell selectors.
- The visual gate: 4 new kitchen-sink screenshots and 4 real-page screenshots, with the 19 existing ones unchanged.

### Manual Testing Steps:

1. At desktop width, Tab from the address bar through the shell. Every control shows the ring, and the active item differs from hover.
2. Collapse the sidebar, click through all four nav items, and reload. It stays collapsed and never animates on load.
3. At 390px: open the drawer, Tab cycles inside it, Esc closes it, and focus is back on the hamburger. Then open it and pick "Członkowie rodziny".
4. Open the user menu with the keyboard, then go to "Profil", back, then "Wyloguj".
5. Open `/login`, `/register` and `/forgot-password`. The footer year is current, and the logo stays on the guest flow.

## Performance Considerations

None material. The Sheet content mounts only while open. localStorage is read once per layout mount.

## Migration Notes

- No data migration. Existing users get the expanded sidebar until they collapse it; the key `menu-maker:app-shell` is new.
- Merge with S-07 (parallel): both append to `scripts/lint-tokens.paths` and both add files under `Components/Common/` (different filenames). Expect a trivial line-append conflict at most.
- Later slices that restyle a page (S-09…S-13) will change `dashboard-*`/`guest-login` baselines only if they touch the Dashboard or the Login body. S-13 will intentionally update `guest-login.png`.
- Known trap, not fixed here: `updateSnapshots` on the `visual` project (`playwright.config.ts:30`) is ignored by Playwright 1.65, because only the CLI or the top-level config value counts. The effective mode is `default`: a missing baseline is written and the test fails. Create the new baselines only with `--update-snapshots`. Moving the setting to the top level is a follow-up (capture it with `/10x-lesson` or a roadmap item).

## References

- Roadmap item: `context/foundation/roadmap.md` (S-08, `app-shell-restyle`)
- Contract: `context/foundation/design-system.md` §4.2, §4.6, §4.7, §5.2, §7, §8 (DS-03)
- UI method: `.claude/skills/10x-ui/SKILL.md`, `.claude/skills/10x-ui/references/ui-quality-checklist.md`
- Prior gate plan: `context/archive/2026-11-15-design-system-visual-gate/plan.md`
- Patterns: `tests/e2e/visual/design-system.spec.ts:74-110` (overlay screenshots), `tests/e2e/meal-plan.spec.ts:3-9` (seeded login), `resources/js/lib/useLocalStorageStateSync.js`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Authenticated shell on tokens and primitives

#### Automated

- [x] 1.1 Client and SSR build passes — a5b971d
- [x] 1.2 Token scan is green with the new paths — a5b971d
- [x] 1.3 Scanner self-test still passes — a5b971d
- [x] 1.4 Sidebar.vue is gone and no shell file imports Breeze dropdowns — a5b971d
- [x] 1.5 The new behaviour spec passes in chromium and firefox — a5b971d
- [x] 1.6 The full E2E suite still passes — a5b971d
- [x] 1.7 The existing visual gate is unchanged — a5b971d

#### Manual

- [ ] 1.8 Desktop: active vs hover distinct, focus ring on every shell control
- [ ] 1.9 Desktop: collapse persists across navigation and reload without animation on load
- [ ] 1.10 At 390px: hamburger in header, drawer traps focus, closes on Esc and X, returns focus
- [ ] 1.11 User menu works by keyboard; Profil and Wyloguj work
- [ ] 1.12 Progress bar is green while navigating

### Phase 2: Guest layout and Dashboard tokens

#### Automated

- [x] 2.1 Build passes
- [x] 2.2 Token scan is green, including both files
- [x] 2.3 The /10x-ui hard-coded value scan finds nothing in the shell files
- [x] 2.4 Guest-screen specs still pass

#### Manual

- [ ] 2.5 Guest screens show tokens, a card, the current year; logo stays on /login
- [ ] 2.6 Dashboard shows "Pulpit" and no palette colours, layout otherwise unchanged

### Phase 3: States and visual gate

#### Automated

- [ ] 3.1 PHP suite, style and static analysis pass
- [ ] 3.2 Seeder runs twice without error
- [ ] 3.3 Build and token scan pass
- [ ] 3.4 Baseline generation adds exactly 8 PNGs and modifies none of the 19 existing ones
- [ ] 3.5 The visual gate passes twice in a row without updates
- [ ] 3.6 The full E2E suite still passes

#### Manual

- [ ] 3.7 Each of the 8 new PNGs is reviewed in the diff
- [ ] 3.8 7-state matrix confirmed
- [ ] 3.9 CLAUDE.md and design-system.md read correctly; ui-quality-checklist walked through
