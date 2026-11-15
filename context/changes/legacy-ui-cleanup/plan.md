# Legacy UI Cleanup Implementation Plan

## Overview

S-15 / DS-10 in roadmap milestone M-2, the last slice of the design-system rollout (`context/foundation/design-system.md` §8, decision D-7, §7.4). Every view has been migrated by S-08…S-13 and S-16. What remains:

- the Breeze components and the Laravel splash page that no view uses any more;
- a token scan that still lists files one by one;
- the `PageHeader` placement that §5.2 left "do ujednolicenia w S-15".

After this change:

- `/` takes a guest to the login screen and a signed-in user to the dashboard;
- no Breeze file or Breeze-installed styling remains;
- `npm run lint:tokens` scans all of `resources/js` except `Components/ui/` and fails CI on any new literal;
- every authenticated page renders its `PageHeader` the same way, at the top of its content.

## Current State Analysis

- **Breeze is orphaned.** Twelve Breeze components (`Checkbox`, `DangerButton`, `Dropdown`, `DropdownLink`, `InputError`, `InputLabel`, `Modal`, `NavLink`, `PrimaryButton`, `ResponsiveNavLink`, `SecondaryButton`, `TextInput`) and `ApplicationLogo` sit directly in `resources/js/Components/*.vue`. None has an importer: `Pages/Auth/Login.vue:6` uses `ui/checkbox`, and S-08 and S-13 removed the last other imports.
- **`/` serves the Laravel splash.** `routes/web.php:15-22` is a closure rendering `Welcome` with `canLogin`, `laravelVersion` and `phpVersion`. `Pages/Welcome.vue` holds 35 `#FF2D20` hexes plus dozens of palette, arbitrary and `dark:` literals.
- **Literal hits.** A scan of all of `resources/js` (`scripts/lint-tokens.sh` with a one-line paths file `resources/js`) finds 359 hits today, all in the 12 Breeze components and `Welcome.vue`. Every other file is already clean. `scripts/lint-tokens.paths` lists 30 entries built up slice by slice.
- **Tests touching `/`:**
  - `tests/Feature/ExampleTest.php` (Laravel skeleton) and `tests/Feature/AddLogContextTest.php:21` (guest case) assert `GET /` returns 200.
  - `tests/Feature/Auth/AuthenticationTest.php:45` and `tests/Feature/ProfileTest.php:67` assert that logout and account deletion redirect to `/`. These stay true, because the controllers are unchanged: `Auth/AuthenticatedSessionController.php:50` and `ProfileController.php:59`.
  - E2E: `tests/e2e/app-shell.spec.ts:56` (logout) and `tests/e2e/profile.spec.ts:79` (account deletion) wait for `pathname === '/'`. With a redirect, the browser ends on `/login` and never reports `/`.
- **Redirect targets.** `bootstrap/app.php` sets neither `redirectGuestsTo` nor `redirectUsersTo`. The `auth` middleware therefore sends guests to `route('login')`, and `guest` sends signed-in users to `route('dashboard')` (Laravel defaults).
- **Breeze-era styling:**
  - `resources/css/app.css:3` loads `@plugin '@tailwindcss/forms'` (devDependency `^0.5.3`). It injects base styles with grey and blue hex colours into native `input`, `textarea` and `select`, which `lint:tokens` cannot see.
  - `app.css:6` sources Laravel's Blade pagination views, which are unused under Inertia.
  - Only `ui/input/Input.vue` and `ui/textarea/Textarea.vue` render native form controls once Breeze is gone (`ui/checkbox` and `ui/select` are reka-ui buttons).
- **`PageHeader` placement uses three patterns:**
  - `PageHeader` inside the sticky top bar's `#header` slot (`Layouts/AuthenticatedLayout.vue:70-71`): `Dashboard.vue:28`, `Recipes/Index.vue:5`, `Recipes/Show.vue:5`, `Recipes/Form.vue:5`.
  - A hand-written `<h1>` in that slot: `Profile/Edit.vue:13`, `FamilyMembers/Index.vue:45` (with a description and a "Dodaj członka" button).
  - `PageHeader` as the first child of the page's width container: `MealPlans/Index.vue:6`, `MealPlans/Show.vue:6`. Here the bar shows its fallback `<p>Menu Maker</p>`.
- **Not leftovers:**
  - `resources/js/bootstrap.js` (axios on `window`) is live: `lib/reportClientError.js:22` posts through `window.axios`.
  - `FamilyMembers/FamilyMemberDeleteDialog.vue` already wraps `Common/ConfirmDialog`.
  - `FamilyMembers/FamilyMemberPagination.vue` is the link-based variant that S-12 chose.
- **Visual baselines** live in `tests/e2e/__screenshots__/visual/<spec>/`. FamilyMembers has no visual spec. The kitchen sink (`Pages/Dev/DesignSystem.vue`) does not demo the layout's `#header` slot, so it needs no change.

## Desired End State

- `GET /` redirects to `/dashboard`. A guest then lands on `/login` (through the `auth` middleware) and a signed-in user on the dashboard. `Pages/Welcome.vue` and the 13 legacy component files are gone, and `resources/js/Components/` holds only subdirectories.
- `scripts/lint-tokens.paths` has one entry, `resources/js`. `npm run lint:tokens` is green. The rules in `CLAUDE.md` and design-system §7.4 describe a full-tree scan, not "append your paths" or "views not yet migrated".
- `@tailwindcss/forms` is no longer installed or loaded, and `app.css` has no pagination-views `@source`. Rendering of `ui/input` and `ui/textarea` is unchanged, or any change was reviewed and recorded.
- `AuthenticatedLayout` has no `header` slot; its bar always shows "Menu Maker". Dashboard, Recipes Index/Show/Form, Profile and FamilyMembers render `Common/PageHeader` as the first child of their content container, as MealPlans already does. Design-system §5.2 documents this single placement.
- Pest, Larastan, Pint, build, the full E2E suite and the visual gate pass. Only the baselines named in Phase 3 changed, and each was reviewed.

### Key Discoveries:

- All literal hits are in files this change deletes. Collapsing the list to `resources/js` therefore needs no further migration (scan run during planning: 359 hits in 13 files).
- `Route::redirect('/', '/dashboard')` needs no auth logic, because `auth` already redirects guests to `route('login')`. It also stays compatible with the CI `route:cache` step.
- After the redirect, the logout and account-deletion flows go `/` → `/dashboard` → `/login`. PHP assertions on `assertRedirect('/')` stay valid, and the two e2e waits must change to `/login`.
- `PageHeader` already has the right root (`<header class="flex min-w-0 flex-col gap-1">`) and slots (`breadcrumbs`, `description`, `actions`). FamilyMembers' hand-written header maps onto `description` and `actions` without new props.

## What We're NOT Doing

- Removing `bootstrap.js` or the `axios` dependency: `reportClientError.js` uses them. Moving client error reports to Inertia's HTTP client would be its own change.
- Changing where logout or account deletion redirect (they keep pointing at `/`), or the guest logo target in `GuestLayout.vue:15` (it keeps pointing at `route('login')`).
- Building any landing page under `/` (D-7, roadmap Parked).
- Merging `FamilyMemberPagination` into `Common/ServerPagination`, or renaming `Components/recipes/` to PascalCase.
- Extracting the text-link class string duplicated in `Auth/Login.vue` and `Auth/Register.vue`. The S-13 review asked to wait for a third copy, and none appears here.
- Widening `lint:tokens` to `Components/ui/` or to Blade views. The PDF palette is guarded by `MealPlanPdfPaletteTest`.
- Dark mode (D-1), and regenerating the older `ui/` primitives (D-9).

## Implementation Approach

Three phases, ordered so that each visual change can be traced to a single cause:

1. Purely subtractive work, plus the redirect and the scan, where no pixels should move.
2. The forms plugin removal, which might move pixels in native inputs. It is checked against unchanged baselines.
3. The deliberate layout change, with its own baseline update.

Docs are updated in the phase that changes the behaviour they describe.

## Critical Implementation Details

**Visual-diff attribution.** Phase 2 must run the visual gate against Phase 1's baselines before Phase 3 touches any page. A diff in Phase 2 is caused by the plugin alone. If it is a regression (for example, lost padding or a lost resize handle on `ui/textarea`), restore the style inside that `ui/` primitive with token utilities; do not re-add the plugin. If it is neutral, update only those PNGs and list them in the commit message.

**PageHeader inside the width container.** Put the header inside each page's existing `mx-auto max-w-*` container as its first child, so the title lines up with the content as on `MealPlans/Index.vue:5-10`. `Recipes/Form.vue` has no such container (its card is the `max-w-2xl` element), so wrap the header and the card in a `mx-auto flex max-w-2xl flex-col gap-6` container. `FamilyMembers/Index.vue` needs `flex flex-col gap-6` on its `max-w-6xl` container.

## Environment (prerequisite for every phase)

This worktree (`.claude/worktrees/legacy-ui-cleanup`) starts without `vendor/`, `node_modules/` or `.env`. Every `sail` command, E2E run and visual run must exercise this checkout's code:

- Copy `.env` from the main checkout. Give it its own `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT` and `FORWARD_MAILPIT_DASHBOARD_PORT`, and set `MAILPIT_URL` to match. Keep `AI_SCALING_DRIVER=fake`, `MAIL_MAILER=smtp` and `MAIL_HOST=mailpit`.
- Install `vendor/` with the Sail Composer container (not host PHP). Then run `vendor/bin/sail up -d`, `vendor/bin/sail npm ci` and `vendor/bin/sail artisan migrate`.
- Run the visual gate against this stack: `VISUAL_DOCKER_NETWORK=legacy-ui-cleanup_sail VISUAL_BASE_URL=http://laravel.test npm run test:visual`. Check the network name with `docker network ls`. Re-seed first with `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`, and stop `npm run dev` (no `public/hot`).
- Before Phase 1, `vendor/bin/sail ps` lists `legacy-ui-cleanup-*` containers, and `curl -s -o /dev/null -w '%{http_code}' http://localhost:${APP_PORT}/login` prints `200`.

---

## Phase 1: Breeze removal, `/` redirect, full scan

### Overview

Delete every legacy UI file, replace the splash page with a redirect, fix the tests that asserted the splash page, and make the token scan cover the whole tree. No rendered pixel changes.

### Changes Required:

#### 1. Legacy components and splash page

**File**: `resources/js/Components/{ApplicationLogo,Checkbox,DangerButton,Dropdown,DropdownLink,InputError,InputLabel,Modal,NavLink,PrimaryButton,ResponsiveNavLink,SecondaryButton,TextInput}.vue`, `resources/js/Pages/Welcome.vue`

**Intent**: Delete the 13 orphaned Breeze files and the Laravel splash page. Nothing imports them, and they hold every remaining literal.

**Contract**: After deletion, `resources/js/Components/` contains only directories, and nothing in `resources/js` or `routes/` refers to `Welcome`.

#### 2. Root route

**File**: `routes/web.php`

**Intent**: Replace the `Welcome` closure with a redirect to the dashboard, and let the existing `auth` middleware send guests on to login (D-7). Drop the imports that become unused (`Illuminate\Foundation\Application`, `Inertia\Inertia`).

**Contract**: `Route::redirect('/', '/dashboard');` (302, unnamed, cacheable). No other route changes.

#### 3. PHP tests

**File**: `tests/Feature/HomeRedirectTest.php` (new), `tests/Feature/ExampleTest.php` (deleted), `tests/Feature/AddLogContextTest.php`

**Intent**: Replace the skeleton example test with one that pins the new behaviour, and keep the guest log-context test on a page that still returns 200.

**Contract**:
- `HomeRedirectTest`: a guest requesting `/` ends on the login page (Inertia component `Auth/Login`), and a signed-in user ends on `Dashboard`. Each case also asserts the first hop is a redirect to `/dashboard`. The file starts with `declare(strict_types=1);` and uses `RefreshDatabase` and factories.
- `AddLogContextTest`: the guest case requests `/login` instead of `/`; the assertion intent is unchanged.

#### 4. E2E waits

**File**: `tests/e2e/app-shell.spec.ts:56`, `tests/e2e/profile.spec.ts:79`

**Intent**: Logout and account deletion still redirect to `/`, which now forwards to `/login`. The waits must match where the browser ends up.

**Contract**: Both `waitForURL` predicates check `url.pathname === '/login'`. The rest of each spec is unchanged.

#### 5. Token scan scope

**File**: `scripts/lint-tokens.paths`

**Intent**: Replace the 30 per-slice entries with the whole tree, so any new file is scanned without anyone adding it.

**Contract**: The only non-comment line is `resources/js`. Update the header comment so it says the list covers all of `resources/js` (`Components/ui/` is excluded by the scanner) and no longer says slices append to it. `scripts/lint-tokens.sh` and its self-test are unchanged.

#### 6. Rule docs

**File**: `CLAUDE.md` (Structure & Conventions, the token rule and its "Verification of that rule" bullets; the `lint:tokens` line under Commands), `context/foundation/design-system.md` §7.4 (last sentence) and frontmatter `updated:`

**Intent**: State the end-state rule: no palette classes, hexes, arbitrary values or `dark:` anywhere in `resources/js` outside `Components/ui/`, enforced by a full-tree `lint:tokens` in CI. Drop the "new files / view being migrated / views not yet migrated" wording and the "a slice that migrates a view appends its paths" bullet. Keep the kitchen-sink and visual-baseline bullets.

**Contract**: The edits are limited to those bullets, the Commands line and the closing sentence of §7.4 ("Lista rośnie…" → the list is `resources/js`). The other text of §7.4 and §2 (a dated audit) stays as written.

### Success Criteria:

#### Automated Verification:

- Legacy files are gone: `find resources/js/Components -maxdepth 1 -type f` prints nothing and `test ! -e resources/js/Pages/Welcome.vue`
- No reference to the splash page remains: `grep -rn "Welcome" routes resources/js` prints nothing
- The scan list is the whole tree and green: `grep -v '^\s*#' scripts/lint-tokens.paths | grep -v '^\s*$'` prints only `resources/js`, and `vendor/bin/sail npm run lint:tokens` exits 0
- Scanner self-test passes: `bash scripts/lint-tokens.test.sh`
- New and changed PHP tests pass: `vendor/bin/sail pest tests/Feature/HomeRedirectTest.php tests/Feature/AddLogContextTest.php tests/Feature/Auth/AuthenticationTest.php tests/Feature/ProfileTest.php`
- Full suite, Larastan and style pass: `vendor/bin/sail composer test`, `vendor/bin/sail composer phpstan`, `vendor/bin/sail composer pint-test`
- Routes still cache: `vendor/bin/sail artisan route:cache && vendor/bin/sail artisan route:clear`
- Bundles build: `vendor/bin/sail npm run build`
- Visual gate passes with no baseline change: `npm run test:visual` exits 0 and `git status --porcelain tests/e2e/__screenshots__` prints nothing
- Full E2E passes after a re-seed: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`

#### Manual Verification:

- In a private window, opening `http://localhost:${APP_PORT}/` lands on the Polish login screen; after login, opening `/` again lands on the dashboard
- Logging out through the user menu ends on the login screen without a flash of the Laravel splash page
- The rewritten `CLAUDE.md` token rule and §7.4 read as one consistent end-state rule (no leftover "not yet migrated" wording)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Drop `@tailwindcss/forms` and the pagination `@source`

### Overview

Remove the last Breeze-installed styling so that what `lint:tokens` guarantees matches what renders, and prove with the unchanged baselines that inputs still look the same.

### Changes Required:

#### 1. Dependency

**File**: `package.json`, `package-lock.json`

**Intent**: Uninstall the forms plugin (approved in planning as a dependency change).

**Contract**: `vendor/bin/sail npm uninstall @tailwindcss/forms`. No other dependency moves; review the lock diff to confirm only that package and its own transitive entries go.

#### 2. Stylesheet

**File**: `resources/css/app.css:3,6`

**Intent**: Drop the `@plugin '@tailwindcss/forms'` line and the `@source` line for `vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php`.

**Contract**: The `@import`, the other `@source` (`storage/framework/views`) and every token and `@theme` block stay byte-identical.

#### 3. Primitives (only if the gate shows a regression)

**File**: `resources/js/Components/ui/input/Input.vue`, `resources/js/Components/ui/textarea/Textarea.vue`

**Intent**: Restore any style the plugin was silently providing that the primitive should own. Use token utilities only, and do not touch unrelated classes.

**Contract**: No change unless a Phase 2 visual diff shows a regression. Each restored style is named in the commit message.

### Success Criteria:

#### Automated Verification:

- Plugin and source are gone: `grep -n "tailwindcss/forms\|Pagination/resources/views" resources/css/app.css package.json package-lock.json` prints nothing
- Bundles build and the scan is green: `vendor/bin/sail npm run build && vendor/bin/sail npm run lint:tokens`
- Visual gate passes against Phase 1 baselines: `npm run test:visual` exits 0 with `git status --porcelain tests/e2e/__screenshots__` empty (or, if a diff was accepted after review, the commit lists every changed PNG)
- Full E2E passes after a re-seed: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`

#### Manual Verification:

- On the recipe form (`/recipes/create`), the text inputs, number inputs and the instructions textarea keep their padding, border, focus ring and resize behaviour
- The `package-lock.json` diff removes only `@tailwindcss/forms` and its exclusive transitive dependencies

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: `PageHeader` placement unified in content

### Overview

Give every authenticated page the MealPlans pattern: `Common/PageHeader` is the first child of the page's content container, and the sticky bar is only navigation and the user menu. Removing the layout slot makes the rule structural.

### Changes Required:

#### 1. Layout

**File**: `resources/js/Layouts/AuthenticatedLayout.vue:69-72`

**Intent**: Remove the `header` slot so that no page can put its title in the sticky bar again.

**Contract**: The bar always renders `<p class="text-lg font-semibold">Menu Maker</p>` between the navigation trigger and `UserMenu`. The default slot, the sheet, the sidebar and the collapse behaviour are unchanged.

#### 2. Pages already using `PageHeader`

**File**: `resources/js/Pages/Dashboard.vue`, `resources/js/Pages/Recipes/Index.vue`, `resources/js/Pages/Recipes/Show.vue`, `resources/js/Pages/Recipes/Form.vue`

**Intent**: Move each existing `<PageHeader>` element out of `<template #header>` and into the content container, with its props and slots unchanged.

**Contract**: Same title, description, breadcrumbs and actions as today. `Recipes/Form` gets the wrapper described in Critical Implementation Details. One `<h1>` per page.

#### 3. Pages with a hand-written heading

**File**: `resources/js/Pages/Profile/Edit.vue`, `resources/js/Pages/FamilyMembers/Index.vue`

**Intent**: Replace the hand-written `<h1>` blocks with `Common/PageHeader`, keeping every visible text and control.

**Contract**:
- Profile: `<PageHeader title="Profil" />` as the first child of the `max-w-2xl` grid.
- FamilyMembers: `title="Członkowie rodziny"`. The `description` slot holds "Zarządzaj członkami swojej rodziny", followed by the `({{ totalLabel }})` count when `total > 0`. The `actions` slot holds the existing "Dodaj członka" button with its `@click`.
- Accessible names stay identical (`heading` "Profil", "Członkowie rodziny"; button "Dodaj członka").

#### 4. Baselines

**File**: `tests/e2e/__screenshots__/visual/**`

**Intent**: Update deliberately with `npm run test:visual -- --update-snapshots` and review each changed PNG.

**Contract**: Changes are limited to this set:
- `app-shell.spec.ts`: `dashboard-desktop`, `dashboard-empty-desktop`, `dashboard-mobile`, `dashboard-mobile-drawer`;
- `recipes.spec.ts`: `recipes-index-desktop`, `recipes-index-mobile`, `recipes-index-no-results`, `recipe-show-desktop`, `recipe-show-mobile`, `recipe-form-create-desktop`, `recipe-form-create-mobile`;
- `account.spec.ts`: `profile-desktop`.

All `meal-plan*`, `guest-*`, `section-*`, `overlay-*`, `focus-visible` and `shell-nav-*` PNGs stay byte-identical. A change outside the set means something else moved: stop and investigate.

#### 5. Docs

**File**: `context/foundation/design-system.md` §5.2 (`PageHeader` and `AppShell` rows) and frontmatter `updated:`

**Intent**: Replace "Umiejscowienie nie jest jeszcze jednolite … Do ujednolicenia w S-15" with the single rule. `PageHeader` is the first element of the page content, inside its width container. `AuthenticatedLayout` has no `header` slot, and its bar shows "Menu Maker".

**Contract**: The other rows of §5.2 are unchanged. The `CLAUDE.md` pointer ("placement in … §5.2") stays valid without an edit.

### Success Criteria:

#### Automated Verification:

- The slot is gone everywhere: `grep -rn '#header\|name="header"' resources/js` prints nothing
- Each authenticated page renders `PageHeader`: `grep -L "PageHeader" resources/js/Pages/{Dashboard,Recipes/Index,Recipes/Show,Recipes/Form,Profile/Edit,FamilyMembers/Index,MealPlans/Index,MealPlans/Show}.vue` prints nothing
- Bundles build and the scan is green: `vendor/bin/sail npm run build && vendor/bin/sail npm run lint:tokens`
- Baseline delta is within the 12 named PNGs: `git status --porcelain tests/e2e/__screenshots__` lists only files from the set in change 4
- Visual gate is stable twice in a row after the update: `npm run test:visual && npm run test:visual`
- Full E2E passes after a re-seed: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- Full PHP suite still passes: `vendor/bin/sail composer test`

#### Manual Verification:

- Each changed PNG reviewed in the diff: the title sits at the top of the content aligned with it, the bar shows "Menu Maker", breadcrumbs and actions on recipe Show and Form are intact, and the mobile shots no longer have a tall sticky bar
- FamilyMembers (no visual spec) checked by hand at desktop and 390 px: title, count, "Dodaj członka" button, empty state and table all present, button still opens the dialog
- Each authenticated page has exactly one `<h1>` (check in the accessibility tree or with `document.querySelectorAll('h1').length`)
- §5.2 reads as one placement rule that matches the shipped pages

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- None new. The change adds no logic; the scanner keeps its self-test (`scripts/lint-tokens.test.sh`).

### Integration Tests:

- `HomeRedirectTest` (Pest feature): a guest on `/` ends on `Auth/Login`, and a signed-in user ends on `Dashboard`, both through a first hop to `/dashboard`.
- `AddLogContextTest` guest case moved to `/login`. `AuthenticationTest` and `ProfileTest` keep asserting the redirect to `/`.
- E2E: the logout (`app-shell.spec.ts`) and account-deletion (`profile.spec.ts`) specs now prove the redirect end to end by landing on `/login`. The remaining specs rely on roles and texts that this change keeps, so they run unchanged after Phase 3.
- The visual gate stays green in all three phases. It must stay unchanged in Phases 1 and 2, and only the 12 named PNGs may change in Phase 3.

### Manual Testing Steps:

1. Private window → `/` → login screen. Log in → `/` → dashboard.
2. Log out from the user menu → login screen, no splash flash.
3. `/recipes/create`: inputs and textarea look and behave as before the plugin removal.
4. Visit Dashboard, Recipes (list, a recipe, new recipe), Profile, Family and both MealPlans pages at desktop and 390 px. Each shows its title at the top of the content and "Menu Maker" in the bar.

## Performance Considerations

None of note. Removing the forms plugin and the vendor `@source` slightly shrinks the CSS bundle; the redirect adds one 302 hop on `/`.

## Migration Notes

No data changes. Rollback is a `git revert` per phase, because each phase is self-contained. Anyone bookmarking `/` lands on the right page.

## References

- Roadmap item: `context/foundation/roadmap.md` (S-15, `legacy-ui-cleanup`)
- Design system: `context/foundation/design-system.md` §5.2, §7.4, §8 (DS-10), §9 (D-7)
- Deferrals to this slice: `context/archive/2026-11-15-auth-profile-ui/plan.md:57`, `context/archive/2026-11-15-app-shell-restyle/plan.md:27,81`, `context/archive/2026-11-15-dashboard-ui/plan.md:81`
- Pattern to copy for header placement: `resources/js/Pages/MealPlans/Index.vue:5-10`
- Environment and visual-gate precedent: `context/archive/2026-11-15-dashboard-ui/plan.md` ("Environment", Phase 3)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Breeze removal, `/` redirect, full scan

#### Automated

- [x] 1.1 Legacy files are gone — d38cda0
- [x] 1.2 No reference to the splash page remains — d38cda0
- [x] 1.3 The scan list is the whole tree and green — d38cda0
- [x] 1.4 Scanner self-test passes — d38cda0
- [x] 1.5 New and changed PHP tests pass — d38cda0
- [x] 1.6 Full suite, Larastan and style pass — d38cda0
- [x] 1.7 Routes still cache — d38cda0
- [x] 1.8 Bundles build — d38cda0
- [x] 1.9 Visual gate passes with no baseline change — d38cda0
- [x] 1.10 Full E2E passes after a re-seed — d38cda0

#### Manual

- [ ] 1.11 `/` lands on login for a guest and on the dashboard after login
- [ ] 1.12 Logout ends on the login screen without a splash flash
- [ ] 1.13 `CLAUDE.md` token rule and §7.4 read as one end-state rule

### Phase 2: Drop `@tailwindcss/forms` and the pagination `@source`

#### Automated

- [x] 2.1 Plugin and source are gone — 84cee7e
- [x] 2.2 Bundles build and the scan is green — 84cee7e
- [x] 2.3 Visual gate passes against Phase 1 baselines — 84cee7e
- [x] 2.4 Full E2E passes after a re-seed — 84cee7e

#### Manual

- [ ] 2.5 Recipe form inputs and textarea keep padding, border, focus ring and resize
- [ ] 2.6 Lock diff removes only `@tailwindcss/forms` and its exclusive dependencies

### Phase 3: `PageHeader` placement unified in content

#### Automated

- [x] 3.1 The slot is gone everywhere
- [x] 3.2 Each authenticated page renders `PageHeader`
- [x] 3.3 Bundles build and the scan is green
- [x] 3.4 Baseline delta is within the 12 named PNGs
- [x] 3.5 Visual gate is stable twice in a row after the update
- [x] 3.6 Full E2E passes after a re-seed
- [x] 3.7 Full PHP suite still passes

#### Manual

- [ ] 3.8 Each changed PNG reviewed in the diff
- [ ] 3.9 FamilyMembers checked by hand at desktop and 390 px
- [ ] 3.10 Each authenticated page has exactly one `<h1>`
- [ ] 3.11 §5.2 reads as one placement rule matching the shipped pages
