# Spiżarnia App Shell Implementation Plan

## Overview

Roadmap S-22 (M-4, SP-03). F-05 and F-06 gave the app Spiżarnia tokens and primitives, but the shell is still the Zielnik layout. The desktop has a collapsible left sidebar, and the guest screens are a centred card with a tagline and a footer.

This change does three things:
- **Top bar:** replaces the sidebar with a sticky top bar (brand, a pill group of the 4 navigation links, an avatar that opens the user menu). Below `lg` the bar keeps the `Sheet` drawer.
- **Guest split screen:** turns the guest screens into a split screen. A `highlight` panel with the brand, a slogan and three category blocks sits on the left, the form on the right, and each Auth page uses the copy from mockups 02–04.
- **Ordering:** S-22 goes before S-23…S-26 so those slices refresh their screenshots only once, already inside the new shell.

Behaviour stays the same, except that the sidebar collapse is removed.

## Current State Analysis

- **Authenticated shell** (`resources/js/Layouts/AuthenticatedLayout.vue`):
  - Desktop sidebar: a fixed `<aside>` at `w-64`/`w-16` (:37-51), and the content is offset by `lg:pl-64`/`lg:pl-16` (:56).
  - Collapse state: `reactive({ collapsed })`, synced to localStorage `menu-maker:app-shell` (:13-18). `hasToggled` stops the transition firing on restore (:15, :41, :57).
  - Sticky header: `sticky top-0 z-20 border-b bg-card` (:61). It holds the hamburger "Otwórz nawigację" `lg:hidden` (:65), a plain `<p>Menu Maker</p>` (:70) and `UserMenu` (:74).
  - Drawer: `SheetContent side="left" w-64 p-0` with an sr-only "Nawigacja" title (:78-81).
  - Main area: `<main>` with `p-6` (:84-88).
  - The layout has no props. Its only slot is the default one (:86).
- **`Components/Common/AppSidebar.vue`:**
  - Brand: a link to the dashboard (:24-32).
  - Collapse toggle: "Zwiń panel boczny" / "Rozwiń panel boczny" (:34-44).
  - Nav: `<nav aria-label="Główna nawigacja">` (:47). Links get `aria-current="page"`.
  - Link colours: the active link sits on `bg-accent` (yellow, the same as `--warning`; carry-over F-05) (:58), and hover is `bg-muted` (:56).
  - Footer: "Menu Maker v1.0" (:68).
  - Consumers: only the layout and the kitchen sink.
- **`Components/Common/UserMenu.vue`:**
  - Trigger: a `ghost` button with sr-only "Menu użytkownika" (:35), the avatar, and the name and email from `sm` up (:40-43).
  - Menu items: "Profil" and "Wyloguj" (:54-65).
  - Initials are computed from `user.name` (:20-28).
- **`ui/avatar`:** only one look (`bg-secondary`, `font-normal`, `avatar/index.js:7-22`). Its only consumers are `UserMenu` and the kitchen sink.
- **`resources/js/lib/navigation.js:3-8`:** the 4 items (Pulpit, Przepisy, Jadłospisy, Członkowie rodziny), each with a route, a match pattern and a lucide icon. They match the mockup labels, order and icons one to one.
- **`Layouts/GuestLayout.vue`:**
  - A centred column: brand link to `login` with `hover:text-primary`, which no longer changes anything (:14-20; carry-over F-05).
  - Tagline "Twój osobisty asystent kulinarny" (:21-23).
  - `<Card class="px-6 py-8 shadow-none">` around the slot (:28; carry-over F-06).
  - Footer "© {year} Menu Maker…" (:34-38).
  - Used by `Auth/Login`, `Register`, `ForgotPassword`, `ResetPassword` and `ConfirmPassword`. **None of them has a heading.**
- **`ui/sheet`:**
  - The close button is a round `secondary` `icon` at `absolute right-5 top-5` (`SheetContent.vue:53-58`).
  - `SheetHeader` is still `text-center sm:text-left` with no right padding (`SheetHeader.vue:11`). `DialogHeader` is `pr-12 text-left` (`DialogHeader.vue:11`). Carry-over F-06.
- **Type roles** (`resources/css/app.css:30-53`, and `cn()` in `resources/js/lib/utils.js:7`): `display` 60px, `page-title` 36px, `stat`, `section-title`, `dialog-title`, `body`, `label`, `meta`. No role covers the 48px guest slogan.
- **Mockups** (Design System artifact, §10.1; screens 02–05 and 07, 1280px):
  - **Top bar:** `padding 16px 32px`, `bg sidebar`, no border, no max-width.
    - Brand: a 40px `primary` circle with a 20px ChefHat, then "Menu Maker" at 20/24, 800, −0.03em.
    - Nav: centred, one pill on `card` with a 2px `foreground` border, 4px padding and 4px gap. Items are 36px high with 16px side padding, 14/600, `sidebar-foreground`, a 16px icon and 8px gap. The active item is on `sidebar-active`.
    - Avatar: 40px, on `meal-breakfast` / `meal-breakfast-foreground`, with a 2px `foreground` border, 700, showing the first-name initial.
  - **Guest:** a 1fr/1fr grid, `min-h-screen`.
    - Left panel: `highlight`, padding 40/48, edge to edge. It holds the brand (mark on `card` with a `foreground` icon), an h2 slogan at 48/52, 800, −0.035em with `max-width 460px`, and a row of three 72px blocks (`meal-*`, `rounded-lg`, 2px `foreground` border, label bottom-left at 14/700, no icon or shadow).
    - Right side: bare, padding 40/88, 20px gap. Form content is about 464px wide.
  - **Not designed:** mobile layouts and hover/focus states.
- **Tests that depend on the shell:**
  - `tests/e2e/app-shell.spec.ts`:
    - :11-25: sidebar collapse that persists across navigation and reload.
    - :27-49: drawer at 390×844. It uses "Otwórz nawigację", `getByRole('dialog')`, the `navigation` "Główna nawigacja" inside the drawer and the "Jadłospisy" link.
    - :51-60: "Menu użytkownika" → menuitem "Wyloguj".
  - `tests/e2e/visual/app-shell.spec.ts:17-23` (`guest-login.png`) and `account.spec.ts:15-21` (`guest-register.png`) wait for `getByText('© 2026')`.
  - `tests/e2e/visual/design-system.spec.ts`:
    - `shell-nav-hover.png` (:118-127) uses `getByTestId('shell-sidebar-expanded')`.
    - `shell-nav-focus-visible.png` (:129-137) focuses "Zwiń panel boczny", presses Tab and expects "Pulpit".
    - `overlay-user-menu.png` (:212-219) clicks an unscoped `getByRole('button', { name: 'Menu użytkownika' })`. It relies on there being one `UserMenu` on the page.
  - Kitchen sink: the "Powłoka" section (`Pages/Dev/DesignSystem.vue:771-794`) renders two `AppSidebar` boxes and one `UserMenu`. The fixtures are at :199-207.
- **Baselines that show the shell** (`tests/e2e/__screenshots__/visual/<spec>/`):
  - app-shell ×5
  - account ×2
  - recipes ×7
  - meal-plans-index ×4
  - meal-plan-show ×5
  - design-system: `section-powloka`, `shell-nav-hover`, `shell-nav-focus-visible`, `overlay-user-menu`
- **Backend:** none. `HandleInertiaRequests.php:35-37` already shares `auth.user`. No Pest test asserts shell markup.

## Desired End State

**Signed-in user, desktop (`lg` and up).** A sticky, borderless top bar on the page background shows:
- the brand on the left (40px ink circle with a ChefHat, "Menu Maker"), which links to the dashboard;
- a centred outlined pill group with the four links, where the current section is in ink with `aria-current="page"`;
- a 40px honey avatar circle with the initials on the right, which opens the menu with name/e-mail, "Profil" and "Wyloguj".

No sidebar, no collapse button, and nothing written to localStorage.

**Signed-in user, below `lg`.** The bar shows the hamburger "Otwórz nawigację" and the brand on the left, and the avatar on the right. The drawer shows the brand under the close button and the four links stacked as full-width pills.

**Guest.** Logowanie, rejestracja, „Nie pamiętasz hasła?”, reset hasła and potwierdzenie hasła use a split screen:
- left: the `highlight` panel with the brand, the slogan and three category blocks;
- right: the form with exactly one h1, a subtitle, full-width submit buttons and the mockup cross-links;
- below `lg`: a compact `highlight` band (brand and category blocks, no slogan) above the form;
- no tagline, no card and no footer.

The kitchen sink shows the top bar, the drawer nav and both brand tones. The visual gate goes from 56 to 58 shots, with every changed baseline reviewed. `design-system.md` describes the new shell.

**Verify by:**
- `/dashboard`, `/recipes` and `/login` in the browser at 1280px and 390px;
- `vendor/bin/sail npm run build`;
- `npm run lint:tokens`;
- Pest;
- the full E2E suite;
- `npm run test:visual`.

### Key Discoveries:

- No page passes props or named slots to either layout, and nothing outside the layout offsets for the sidebar. The only offset is `AuthenticatedLayout.vue:56`, so removing the sidebar is local to the layout.
- **Only one "Główna nawigacja" may be in the accessibility tree at a time.** At desktop the drawer isn't mounted; below `lg` the horizontal nav is `hidden` (`display: none`). The existing drawer locator `dialog.getByRole('navigation', { name: 'Główna nawigacja' })` and an unscoped desktop locator both stay unambiguous.
- **The new guest copy collides with no E2E locator.** Specs use `getByLabel` and `getByRole('button')`:
  - `login.spec.ts:36-38`
  - `password-reset.spec.ts:10-42`
  - `profile.spec.ts:10-17,57-59`

  The new h1s ("Logowanie", "Rejestracja", "Nie pamiętasz hasła?", "Ustaw nowe hasło", "Potwierdź hasło") are neither labels nor buttons. On the register page the link "Zaloguj się" doesn't clash with the button "Zarejestruj się".

  The one link locator is `login.spec.ts:57`: `getByRole('link', { name: 'Nie pamiętasz hasła?' })` on the login page. That link text must stay exactly as it is. The ForgotPassword h1 with the same text is on another page. Several specs use a non-exact `getByLabel('Hasło')` on the login page, so no new element on Login may get an accessible name that contains "Hasło".
- **The mockup sizes are Tailwind 4 multiplier classes, not arbitrary values:**
  - `h-18` (72px category block)
  - `size-10` (40px mark/avatar)
  - `h-9` (36px nav item)
  - `max-w-116` (464px form column)
  - `max-w-115` (460px slogan)
  - `lg:px-22` (88px)
  - `py-10` / `px-12` (40/48px panel)
- **A new type role must be registered in two places:** `--text-*` in `app.css` and the `text` list of `extendTailwindMerge` (`utils.js:7`). Otherwise `cn()` treats it as a text colour and drops it (§10.3).
- `lib/mealCategories.js` already exports `MEAL_CATEGORIES` and `MEAL_CATEGORY_LABELS` ("Śniadanie", "Obiad", "Kolacja"). The guest blocks read them instead of hard-coding the labels.

## What We're NOT Doing

- **Restyling the content of individual views** (dashboard hero, recipe tiles, meal-plan grid, family cards, profile tiles). That is S-23…S-26. Pages keep their own width containers (`max-w-6xl` = 1152px vs the mockup's 1200px); the slices settle that.
- **A 40px guest h1.** Guest h1s use the existing `text-page-title` (36/40). §10.3 wins over the mockup (D-11), so the guest and app titles share one role.
- **Keeping the sidebar collapse in any form, or cleaning up stale `menu-maker:app-shell` keys** in users' browsers. Nothing reads them any more.
- **A border or shadow on the sticky bar while scrolling.** The bar is borderless in every state.
- **New icons on the guest category blocks** (the mockup has none), and the landing page (screen 01, D-13).
- **Dark mode** (D-1/D-14), and regenerating `ui/` components through the CLI (D-9).
- **Changing route names, controllers, Inertia props or `<Head>` titles.**

## Implementation Approach

The shell is split into two shared pieces that both layouts compose:
- **`Common/AppBrand`:** mark plus wordmark as a link, in a `default` tone (ink mark) and an `inverse` tone for `highlight` (card mark).
- **`Common/MainNav`:** the four links, `horizontal` as the outlined pill group or `vertical` as stacked pills for the drawer. It replaces `AppSidebar`, which is deleted.

Primitive-level looks (the avatar sticker, the sheet header padding) go into `ui/`, so views don't override colour or shape (§5.1). State styling follows the §10.4 control-state contract:
- inactive nav items and the dropdown trigger hover to `accent`;
- the avatar hovers with `brightness-95` (a meal tone);
- focus is a 2px `outline` in `ring` offset by 2px;
- the active pill is flat ink with no hover change.

Phase 1 finishes the whole signed-in surface, including every authenticated baseline, so it refreshes those PNGs once. Phase 2 touches only guest pages, the typography sample and its own baselines. Each phase ends with the full gate green.

## Phase 1: Top bar

### Overview

Build `AppBrand`, `MainNav` and the avatar sticker variant, slim `UserMenu` down to the avatar, align `SheetHeader`, and rebuild `AuthenticatedLayout` as a sticky top bar with the drawer below `lg`. Remove the collapse and `AppSidebar`. Then retarget the kitchen sink, the E2E shell test and the shell screenshots, refresh every authenticated baseline, and update `design-system.md` §5.1, §5.2 and §7.

### Changes Required:

#### 1. Avatar sticker variant

**File**: `resources/js/Components/ui/avatar/index.js`, `resources/js/Components/ui/avatar/Avatar.vue`

**Intent**: Give the avatar the Spiżarnia look from the mockup as a primitive variant, so `UserMenu` doesn't colour it in place.

**Contract**: Add a `variant` key to `avatarVariant`:
- `default` (today's classes);
- `sticker` (`bg-meal-breakfast text-meal-breakfast-foreground border-2 border-foreground font-bold`).

`Avatar.vue` accepts and forwards a `variant` prop (default `default`). The `sm` size (40px) stays the one the trigger uses.

#### 2. Sheet header alignment

**File**: `resources/js/Components/ui/sheet/SheetHeader.vue`

**Intent**: Carry-over F-06. Content in a sheet header must not run under the round close button at `right-5 top-5`.

**Contract**: The classes become `flex flex-col gap-y-2 pr-12 text-left`, the same as `DialogHeader`.
- Today the only consumer is the kitchen-sink "Filtry" sheet (`DesignSystem.vue:746-749`, shot `overlay-sheet.png`). With `pr-12` its description still fits on one line (about 25px to spare in the 384px sheet), so that baseline stays identical.
- The layout drawer becomes the second consumer in change 6.

#### 3. `AppBrand` (new)

**File**: `resources/js/Components/Common/AppBrand.vue`

**Intent**: One brand mark for the top bar, the drawer and (in phase 2) the guest panel. The mark matches the mockup, and the brand gets a hover cue that isn't colour, because `primary` = `foreground` (carry-over F-05).

**Contract**:
- Props: `href` (required), `tone: 'default' | 'inverse'` (default `default`).
- Renders an Inertia `Link` that contains a `size-10 rounded-full` mark with a `size-5` `ChefHat` (`aria-hidden`), followed by "Menu Maker" in `text-section-title font-extrabold tracking-tight`.
- Mark colours: `bg-primary text-primary-foreground` in `default`; `bg-card text-foreground` in `inverse`. The text inherits the colour, so on `highlight` it is `highlight-foreground`.
- Hover underlines the wordmark (`underline-offset-4`).
- Focus is `outline-2 outline-ring outline-offset-2` on a `rounded-full` link.
- Forwards the `start` event from the Inertia link (`@start`), so the drawer can close on navigation.

#### 4. `MainNav` (new, replaces `AppSidebar`)

**File**: `resources/js/Components/Common/MainNav.vue`; delete `resources/js/Components/Common/AppSidebar.vue`

**Intent**: The four navigation links as the outlined pill group in the bar, and as stacked pills in the drawer, keeping the accessible name and `aria-current` that the E2E specs use.

**Contract**:
- Props: `items` (the array the layout already builds: `{ label, href, icon, current }`), `orientation: 'horizontal' | 'vertical'` (default `horizontal`). Emits `navigate` on link `start`.
- The root is `<nav aria-label="Główna nawigacja">` around a list of Inertia `Link`s, with `aria-current="page"` on the current one.
- **Horizontal:**
  - Group: `inline-flex gap-1 rounded-full border-2 border-foreground bg-card p-1`.
  - Item: `h-9 px-4 gap-2 rounded-full text-label text-sidebar-foreground`, with a `size-4` icon.
- **Vertical:**
  - Group: `flex flex-col gap-1`.
  - Item: full-width `h-11 px-4 gap-3 rounded-full text-label`, with a `size-5` icon.
- **Both orientations:**
  - Current item: `bg-sidebar-active text-sidebar-active-foreground`.
  - Inactive hover: `bg-accent text-accent-foreground`.
  - Focus: `outline-2 outline-ring outline-offset-2`.
  - Transitions: `motion-reduce:transition-none`.

#### 5. `UserMenu` trigger

**File**: `resources/js/Components/Common/UserMenu.vue`

**Intent**: The trigger becomes just the 40px sticker avatar, as in the mockup. The name and e-mail move to the menu label only.

**Contract**:
- `DropdownMenuTrigger` renders a `button` that wraps `<Avatar size="sm" variant="sticker">`.
- The button: `rounded-full`, `aria-label="Menu użytkownika"` (it replaces the sr-only span; the accessible name is unchanged), `hover:brightness-95`, and focus `outline-2 outline-ring outline-offset-2`.
- The `ChevronDown` and the inline name/e-mail are removed.
- Menu content, items and labels ("Profil", "Wyloguj") are unchanged.

#### 6. `AuthenticatedLayout` as a top bar

**File**: `resources/js/Layouts/AuthenticatedLayout.vue`

**Intent**: Replace the sidebar and header with one sticky top bar, keep the drawer below `lg`, and drop the collapse state and its localStorage sync.

**Contract**:
- Remove `sidebar`, `hasToggled`, `toggleCollapse`, the `useLocalStorageStateSync` import and call, the `<aside>` and the `lg:pl-*` offset. The composable itself stays (`Recipes/Index.vue` uses it).
- **Bar:** `<header class="sticky top-0 z-20 bg-sidebar">` with a flex row and `gap-6 px-4 py-4 lg:px-8`. In order:
  - `SheetTrigger` (round `secondary` `icon` button, `aria-label="Otwórz nawigację"`, `lg:hidden`);
  - `AppBrand href=dashboard`;
  - `MainNav orientation="horizontal"`, wrapped `hidden lg:flex mx-auto`;
  - `UserMenu` pushed to the end (`ms-auto` below `lg`).
- **Drawer:** `SheetContent side="left"` with `w-72 p-5 flex flex-col gap-6` holds:
  - a `SheetHeader` with the sr-only `SheetTitle` "Nawigacja" and an `AppBrand` (both close the drawer on navigate);
  - `MainNav orientation="vertical"`.
- **Main:** `<main>` with `px-4 pt-4 pb-10 lg:px-8` (mockup `16px 32px 40px`). The `<slot />` stays and `<Toaster />` stays.
- The `items` computed stays as is.

#### 7. Kitchen sink "Powłoka"

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: Demo the new shared shell pieces in their states (CLAUDE.md: a new shared pattern gets a demo), replacing the two sidebar boxes.

**Contract**:
- Imports: drop `AppSidebar` and the `shellExpandedCollapsed` / `shellCollapsedCollapsed` fixtures; import `AppBrand` and `MainNav`. Keep `shellItems` (current = Przepisy) and `shellUser`.
- The section renders:
  - `data-testid="shell-top-bar"`: a `bg-sidebar` row with `AppBrand`, a horizontal `MainNav` and the only `UserMenu` on the page;
  - `data-testid="shell-drawer-nav"`: a `w-72` box with a vertical `MainNav`.
- The `AppBrand` link points at the current URL (`#`), so the sink doesn't navigate. Phase 2 adds a second one, in the `inverse` tone.

#### 8. Kitchen-sink visual spec

**File**: `tests/e2e/visual/design-system.spec.ts`

**Intent**: Point the shell state shots at the top bar.

**Contract**:
- `shell-nav-hover.png`: hover "Jadłospisy" inside `getByTestId('shell-top-bar')`, then screenshot that box.
- `shell-nav-focus-visible.png`: focus the `AppBrand` link in `shell-top-bar`, press Tab, expect the "Pulpit" link to be focused, then screenshot the box.
- Shot names and the count (33) don't change.
- `overlay-user-menu.png` keeps its unscoped locator, which is valid because the sink still has exactly one `UserMenu`.

#### 9. E2E shell test

**File**: `tests/e2e/app-shell.spec.ts`

**Intent**: Replace the collapse test (feature removed; approved in planning) with a guard on desktop navigation, and keep the drawer and logout tests unchanged.

**Contract**: The test at :11-25 becomes "top bar marks the current page and keeps it after reload":
1. Log in.
2. `nav = getByRole('navigation', { name: 'Główna nawigacja' })`; expect "Pulpit" to have `aria-current="page"`.
3. Click "Przepisy" and `waitForURL('**/recipes')`; expect "Przepisy" to have `aria-current="page"` and "Pulpit" not to.
4. Reload; expect "Przepisy" to still have `aria-current="page"`.

Don't assert that the collapse button is absent. The string would put "Zwiń panel boczny" back into `tests/e2e` and fail criterion 1.3, and that grep, together with deleting `AppSidebar`, already guards the removal. The tests at :27-49 and :51-60 are unchanged.

#### 10. Baselines

**Files**:
- `tests/e2e/__screenshots__/visual/app-shell.spec.ts/`: `dashboard-desktop`, `dashboard-empty-desktop`, `dashboard-mobile`, `dashboard-mobile-drawer`
- `account.spec.ts/`: `profile-desktop`
- `recipes.spec.ts/`: all 7
- `meal-plans-index.spec.ts/`: all 4
- `meal-plan-show.spec.ts/`: all 5
- `design-system.spec.ts/`: `section-powloka`, `shell-nav-hover`, `shell-nav-focus-visible`, `overlay-user-menu`

**Intent**: Refresh every shot that shows the new shell, deliberately, and review each PNG.

**Contract**:
- Regenerate with `npm run test:visual -- --update-snapshots`.
- The changed set must be a subset of the list above. Every page-level shot in it must change, because the bar shows in all of them, including the swap dialog seen through its overlay.
- `overlay-user-menu.png` is the only shot allowed to stay byte-identical. It is an element shot of the menu alone, whose content doesn't change, and `--sidebar` = `--background`. So 24 or 25 PNGs change.
- `guest-login`, `guest-register` and every other design-system shot must stay byte-identical, including `overlay-sheet` (see change 2). Any other changed PNG is a regression to fix, not to accept.

#### 11. Design system docs

**File**: `context/foundation/design-system.md`

**Intent**: Keep §5 and §7 true to the code.

**Contract**:
- **§5.1:** add a row for `avatar` (`variant="sticker"`: `meal-breakfast`, 2px border, 700). Update the `sheet` row: `SheetHeader` is `pr-12 text-left`, and the shell drawer layout is defined by SP-03.
- **§5.2:** rewrite the `AppShell` row:
  - `Common/AppBrand` (tones) + `Common/MainNav` (orientation) + `Common/UserMenu` (avatar trigger), composed by `AuthenticatedLayout` as a sticky top bar on `bg-sidebar` with the drawer on `ui/sheet` below `lg`;
  - no collapse and no localStorage;
  - `AppSidebar` removed.

  In the `PageHeader` row, "pasek górny `AuthenticatedLayout` zawiera tylko nawigację i menu użytkownika" stays true; keep it.
- **§7.2–7.3:** in the description of the "Powłoka" section and the shell hover/focus shots, replace "sidebar" with "pasek górny i nawigacja szuflady".

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Token scan and its self-test pass: `vendor/bin/sail npm run lint:tokens` and `bash scripts/lint-tokens.test.sh`
- No reference to the removed sidebar remains: `grep -rn "AppSidebar\|Zwiń panel boczny\|menu-maker:app-shell" resources/js tests/e2e` returns nothing
- Pest suite passes: `vendor/bin/sail composer test`
- Larastan and Pint pass: `vendor/bin/sail composer phpstan` and `vendor/bin/sail composer pint-test`
- E2E suite passes (including the rewritten top-bar test, the drawer test and logout): `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e` after `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`
- Visual gate passes after the deliberate refresh, with only baselines from the change 10 list updated (24 or 25; only `overlay-user-menu` may stay identical): `npm run test:visual`

#### Manual Verification:

- At 1280px on `/dashboard`, `/recipes/{id}` and `/meal-plans`, the bar matches mockup 05/07: brand left, outlined pill group centred, current section in ink, honey avatar right, no border; it stays at the top while scrolling a long meal plan
- At 1024px (`lg`), brand, the four links (including "Członkowie rodziny") and the avatar fit on one row without wrapping or overlap
- Keyboard: Tab goes brand → Pulpit → Przepisy → Jadłospisy → Członkowie rodziny → avatar, with a visible 2px ring on each; Enter on the avatar opens the menu
- At 390px the bar shows hamburger + brand + avatar; the drawer shows the brand below the close button (not under it) and the four stacked pills; tapping a link navigates and closes the drawer, and Esc returns focus to the hamburger
- The reviewed baseline diffs show only shell changes (no shifted page content beyond the new padding)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Guest split screen

### Overview

Add the `text-hero` role, give `AppBrand` its `inverse` use, and add a small `TextLink`. Rebuild `GuestLayout` as the split screen with a compact band below `lg`, and move all five Auth pages to the mockup copy with full-width submit buttons. Re-anchor the two guest visual shots on the h1, add `guest-login-mobile` and `guest-forgot-password`, refresh the guest and typography baselines, and update §10.3 and §7.

### Changes Required:

#### 1. `text-hero` type role

**File**: `resources/css/app.css`, `resources/js/lib/utils.js`, `resources/js/Pages/Dev/DesignSystem.vue` (Typografia section)

**Intent**: The 48px guest slogan needs a role, because arbitrary values are banned and `display` (60px) is reserved for the landing page.

**Contract**:
- `--text-hero: 3rem` with line height `3.25rem`, letter spacing `-0.035em` and font weight `800`, defined next to `--text-display`.
- Add `'hero'` to the `text` list in `extendTailwindMerge`.
- One sample row in the Typografia section, in the same format as the other roles.

#### 2. `TextLink` (new)

**File**: `resources/js/Components/Common/TextLink.vue`; demo in `DesignSystem.vue` "Powłoka"

**Intent**: The mockup link style (`.s-a`: 600, underline, offset 3px, inherited colour) is used about 6 times across the Auth pages. Today each page repeats a long class string with a 1px `ring`, which is outside the §10.4 focus contract.

**Contract**:
- Wraps the Inertia `Link` and passes all attributes through (`href`, `method`, …).
- Classes: `rounded-sm font-semibold underline underline-offset-3 hover:text-muted-foreground`, plus focus `outline-2 outline-ring outline-offset-2`.
- The default slot holds the content, so icons such as `ArrowLeft` can go before the text.
- Demo: one `TextLink` next to plain text in the "Powłoka" section.

#### 3. `GuestLayout` as a split screen

**File**: `resources/js/Layouts/GuestLayout.vue`

**Intent**: The mockup 02–04 layout: a `highlight` panel on the left with the brand, a slogan and three category blocks, and the form on the right. Below `lg` it collapses to a compact band above the form. The layout owns the page's single h1 and subtitle.

**Contract**:
- Props:
  - `title: String` (required), rendered as `<h1 class="text-page-title">`;
  - `description: String` (optional), rendered as `text-muted-foreground` under the h1;
  - `slogan: String` (default "Cały tydzień jedzenia na jednej kartce.").
- The default slot holds the alerts, form and links.
- **Root:** `min-h-screen flex flex-col bg-background lg:grid lg:grid-cols-2`.
  - Below `lg` it is a flex column, and the form side takes `flex-1`. A one-column `grid` with `min-h-screen` would stretch both auto rows (`align-content: normal`), so the band would grow and `justify-between` would pull the brand and the blocks apart.
  - From `lg` up, the single grid row fills the height, so the panel runs edge to edge.
- **Panel:** `<aside>` with `bg-highlight text-highlight-foreground flex flex-col justify-between gap-6 px-4 py-6 sm:px-8 lg:px-12 lg:py-10`. It contains:
  - `AppBrand tone="inverse" href=login`;
  - the slogan as `<p class="hidden lg:block text-hero max-w-115">`. It is decorative, so it is a paragraph rather than an h2 and the page outline stays h1-only.
  - the blocks row (`flex gap-3`): one `div` per `MEAL_CATEGORIES` entry with `flex-1 h-12 lg:h-18 rounded-lg border-2 border-foreground bg-meal-{c} text-meal-{c}-foreground flex items-end px-3.5 pb-2.5 text-sm font-bold` and the label from `MEAL_CATEGORY_LABELS`.

  Static class names per category; don't build `bg-meal-${c}` dynamically, or Tailwind won't generate the classes.
- **Form side:** `<main>` with `flex flex-1 flex-col justify-center px-4 py-10 sm:px-8 lg:px-12 xl:px-22`, holding an inner `w-full max-w-116 mx-auto lg:mx-0 flex flex-col gap-5` with the header block (h1, then the description with `-mt-3`) followed by the slot.
- **Removed:** the tagline, `Card`, the footer, `currentYear` and the `ChefHat` import.

#### 4. Auth pages copy and structure

**File**:
- `resources/js/Pages/Auth/Login.vue`
- `resources/js/Pages/Auth/Register.vue`
- `resources/js/Pages/Auth/ForgotPassword.vue`
- `resources/js/Pages/Auth/ResetPassword.vue`
- `resources/js/Pages/Auth/ConfirmPassword.vue`

**Intent**: Mockup copy and structure (approved in planning). The reset and confirm pages, which have no mockup, get copy in the same voice. Field labels and button labels that E2E uses stay unchanged.

**Contract**: Every page passes `title` and `description` (and `slogan` where noted) to `GuestLayout`. It removes its own intro paragraph and replaces inline link classes with `TextLink`. The submit button becomes `size="lg" class="w-full"` (layout width only).

| Page | `title` | `description` | Other changes |
| --- | --- | --- | --- |
| Login | Logowanie | Witaj ponownie! Zaloguj się, żeby zaplanować tydzień. | The button "Zaloguj się" gets an `ArrowRight` icon after the text. Below the button, a row `flex flex-wrap justify-between gap-3 text-sm`: left `TextLink` "Nie pamiętasz hasła?" (still `v-if="canResetPassword"`); right `<span class="text-muted-foreground">Nie masz konta?</span>` plus `TextLink` "Załóż konto" → `register`. |
| Register | Rejestracja | Konto jest darmowe. Przepisy zostają tylko u Ciebie. | `slogan` "Trzy posiłki dziennie, zero zgadywania przy sklepie.". "Hasło" and "Potwierdź hasło" sit in `grid gap-4 sm:grid-cols-2`. Bottom row: "Masz już konto?" (muted) plus `TextLink` "Zaloguj się" → `login`. |
| ForgotPassword | Nie pamiętasz hasła? | Podaj swój adres e-mail, a wyślemy Ci link do resetu hasła, który pozwoli ustawić nowe. | The status `Alert` stays. Below the button: `TextLink` with `ArrowLeft` "Wróć do logowania" → `login`. |
| ResetPassword | Ustaw nowe hasło | Wpisz nowe hasło dwa razy. Potem zalogujesz się nim jak zwykle. | Labels "Nowe hasło" and "Potwierdź hasło" are unchanged. |
| ConfirmPassword | Potwierdź hasło | To zabezpieczony obszar aplikacji. Potwierdź hasło, zanim przejdziesz dalej. | — |

Existing `<Head title>`s, the `status` alerts, the "Zapamiętaj mnie" checkbox and the button labels "Zaloguj się", "Zarejestruj się", "Wyślij link do resetu hasła", "Zresetuj hasło" and "Potwierdź" stay as they are.

#### 5. Kitchen sink: inverse brand

**File**: `resources/js/Pages/Dev/DesignSystem.vue` ("Powłoka")

**Intent**: Demo the `inverse` tone on its real background.

**Contract**: Add a `bg-highlight text-highlight-foreground rounded-lg p-6` box with `<AppBrand tone="inverse">` next to the phase 1 demos.

#### 6. Guest visual specs

**File**: `tests/e2e/visual/app-shell.spec.ts`, `tests/e2e/visual/account.spec.ts`

**Intent**: The footer is gone, so the shots wait for the h1. Pin the two newly designed guest states.

**Contract**:
- `guest-login`: replace the `getByText('© 2026')` wait with `getByRole('heading', { name: 'Logowanie', level: 1 })`. Do the same for `guest-register` with "Rejestracja". Keep the clock pin, because the dashboard shots in the same files use it.
- New in `app-shell.spec.ts`: `guest login mobile` at `MOBILE` (390×844). `goto('/login')`, font check, wait for the h1, `mouse.move(0, 0)`, then `guest-login-mobile.png` (fullPage).
- New in `account.spec.ts`: `guest forgot password`. `goto('/forgot-password')`, font check, wait for the h1 "Nie pamiętasz hasła?", then `guest-forgot-password.png` (fullPage, 1280×720).

#### 7. Baselines

**Files**:
- `app-shell.spec.ts/`: `guest-login`, new `guest-login-mobile`
- `account.spec.ts/`: `guest-register`, new `guest-forgot-password`
- `design-system.spec.ts/`: `section-typografia`, `section-powloka`

**Intent**: Deliberate refresh of the guest and typography shots.

**Contract**:
- Regenerate with `npm run test:visual -- --update-snapshots` and review each PNG.
- The changed or added set must equal the list above. Every phase 1 baseline stays identical.

#### 8. Design system docs

**File**: `context/foundation/design-system.md`

**Intent**: Keep §10.3 and §7 true to the code.

**Contract**:
- **§10.3:** add a `Hasło gościa` row (48/52 px, −0.035em, 800, panel of the guest screens) and `text-hero` to the class list. Note that the guest h1 uses `text-page-title` (36px) instead of the mockup's 40px.
- **§5.2:** add rows for `AppBrand` `tone="inverse"` (on `highlight`) and `TextLink`.
- **§7.3:**
  - app-shell gets 6 shots (adds `guest-login-mobile`);
  - account gets 3 (adds `guest-forgot-password`);
  - the two guest shots wait for the h1 instead of "© 2026";
  - the total goes from 56 to 58 (33 + 6 + 3 + 4 + 7 + 5).

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Token scan and its self-test pass: `vendor/bin/sail npm run lint:tokens` and `bash scripts/lint-tokens.test.sh`
- No tagline or footer copy remains, and the h1 comes only from `GuestLayout`: `grep -rn "Wszystkie prawa zastrzeżone\|Twój osobisty asystent" resources/js` and `grep -rn "<h1" resources/js/Pages/Auth` both return nothing
- Pest suite passes: `vendor/bin/sail composer test`
- E2E suite passes (register, login, password reset including the Mailpit flow, confirm password): `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e` after `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`
- Visual gate passes with 58 shots, with exactly the 6 baselines listed in change 7 changed or added: `npm run test:visual`

#### Manual Verification:

- At 1280px, `/login`, `/register` and `/forgot-password` match mockups 02–04: two equal columns, the edge-to-edge `highlight` panel with the brand, the slogan (per screen) and three category blocks, and the form with h1, subtitle, a full-width button and the cross-links
- At 390px the compact band (brand + blocks, no slogan) sits above the form and the login form's first field is visible without scrolling
- `/reset-password/{token}` (from a Mailpit link) and `/confirm-password` show the new headings and still submit correctly
- A validation error (wrong password on login) shows the field error under the input, and the reset-success status shows as the green alert above the form
- Keyboard: Tab order on the login page is brand → e-mail → password → "Zapamiętaj mnie" → "Zaloguj się" → "Nie pamiętasz hasła?" → "Załóż konto", each with a visible ring

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding.

---

## Testing Strategy

### Unit Tests:

- None. The change is frontend-only and the repo has no JS unit runner. The Pest suite runs unchanged as a regression check.

### Integration Tests:

- **E2E** `tests/e2e/app-shell.spec.ts`:
  - the rewritten desktop test (`aria-current` follows navigation and survives a reload; no collapse button);
  - the unchanged drawer test (open, Esc, focus return, navigate and close);
  - logout through the avatar menu.
- **Auth flows unchanged** (they guard that the copy rewrite kept labels and buttons): `login.spec.ts`, `password-reset.spec.ts` and `profile.spec.ts` (including confirm-password).
- **Visual gate:** 58 shots. The new shots pin the mobile guest band and screen 04. The retargeted kitchen-sink shots pin nav hover and focus-visible in the top bar.

### Manual Testing Steps:

1. `vendor/bin/sail npm run build`, log in as `e2e-planner-visual@example.com`, and walk Pulpit → Przepisy → Jadłospisy → Członkowie rodziny at 1280px, 1024px and 390px.
2. Scroll a meal plan and check that the bar stays on top and content passes under it.
3. Log out, then open `/login`, `/register` and `/forgot-password` at 1280px and 390px; trigger a validation error on each.
4. Run the reset flow through Mailpit and land on the login page with the success alert.

## Performance Considerations

None. The change removes a localStorage watcher and a width transition. The bar is one sticky element.

## Migration Notes

The localStorage key `menu-maker:app-shell` is no longer read or written. Existing values stay in users' browsers, harmless. Nothing to migrate on the server.

## References

- Roadmap item: `context/foundation/roadmap.md` S-22 (carry-overs from F-05 and F-06)
- Spec: `context/foundation/design-system.md` §5.1, §5.2, §7, §10.2–10.5; decisions D-11, D-13, D-14
- Mockups: Design System artifact "Menu Maker — Spiżarnia" (§10.1), screens 02–05 and 07
- Previous change: `context/archive/2026-11-17-spizarnia-primitives/plan.md` (control-state contract, kitchen-sink and baseline workflow)
- Current shell: `resources/js/Layouts/AuthenticatedLayout.vue:13-91`, `resources/js/Components/Common/AppSidebar.vue:16-69`, `resources/js/Layouts/GuestLayout.vue:9-40`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Top bar

#### Automated

- [x] 1.1 Build passes — 23c75ed
- [x] 1.2 Token scan and its self-test pass — 23c75ed
- [x] 1.3 No reference to the removed sidebar remains — 23c75ed
- [x] 1.4 Pest suite passes — 23c75ed
- [x] 1.5 Larastan and Pint pass — 23c75ed
- [x] 1.6 E2E suite passes (top-bar test, drawer, logout) — 23c75ed
- [x] 1.7 Visual gate passes with only the listed baselines refreshed — 23c75ed

#### Manual

- [ ] 1.8 Desktop bar matches mockups 05/07 and stays sticky
- [ ] 1.9 Bar fits on one row at 1024px
- [ ] 1.10 Keyboard order and focus rings in the bar
- [ ] 1.11 Mobile bar and drawer (brand clear of close button, navigate closes, Esc returns focus)
- [ ] 1.12 Reviewed baseline diffs show only shell changes

### Phase 2: Guest split screen

#### Automated

- [x] 2.1 Build passes
- [x] 2.2 Token scan and its self-test pass
- [x] 2.3 No tagline or footer copy remains
- [x] 2.4 Pest suite passes
- [x] 2.5 E2E suite passes (register, login, password reset, confirm password)
- [x] 2.6 Visual gate passes with 58 shots and exactly the 6 listed baselines changed or added

#### Manual

- [ ] 2.7 Desktop guest screens match mockups 02–04
- [ ] 2.8 Mobile compact band keeps the first field above the fold
- [ ] 2.9 Reset and confirm-password pages show new headings and still submit
- [ ] 2.10 Validation error and status alert render correctly
- [ ] 2.11 Keyboard order and focus rings on the login page
