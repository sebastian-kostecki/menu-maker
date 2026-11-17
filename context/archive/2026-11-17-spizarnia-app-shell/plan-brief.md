# Spiżarnia App Shell — Plan Brief

> Full plan: `context/changes/spizarnia-app-shell/plan.md`

## What & Why

This is roadmap S-22 (M-4, SP-03). The Spiżarnia tokens and primitives are already in, but signed-in users still see the Zielnik shell: a collapsible left sidebar. Guests still see a centred card with a tagline and a footer.

This change does two things:
- **Signed-in shell:** replaces it with the Spiżarnia top bar (pill navigation and an avatar menu).
- **Guest screens:** turns them into the split screen from mockups 02–04.

It goes before S-23…S-26 so those view slices refresh their screenshots only once, inside the new shell.

## Starting Point

- **`AuthenticatedLayout`:**
  - a fixed `AppSidebar` at 64 or 256px wide, with its collapse state stored in localStorage;
  - a sticky header with a plain "Menu Maker", a hamburger and `UserMenu` (avatar, name and e-mail).
- **`GuestLayout`:**
  - a centred brand link, a tagline, a `Card` around the form and a "© 2026" footer.
  - None of the 5 Auth pages has a heading.
- **Tests that depend on it:**
  - one E2E test (`app-shell.spec.ts`) covers the collapse;
  - 2 visual shots wait for the footer;
  - 25 baselines show the shell.

## Desired End State

**Signed-in, desktop.** A sticky, borderless bar shows the brand on the left, a centred outlined pill group of the four links (current one in ink) and a 40px honey avatar that opens "Profil" and "Wyloguj". There is no sidebar and no collapse.

**Signed-in, below `lg`.** The bar shows hamburger, brand and avatar. The drawer holds the brand and four stacked pills.

**Guest.** A `highlight` panel on the left has the brand, the slogan and three category blocks; the form is on the right with one h1, a subtitle, a full-width button and the mockup cross-links. On phones a compact band (brand and blocks) sits above the form.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Sidebar collapse | Removed. The E2E collapse test becomes "the top bar marks the current page" | A top bar has nothing to collapse, and desktop navigation keeps a functional E2E guard. |
| Guest copy | All mockup copy: h1 + subtitle per page, cross-links, slogan per screen. Tagline and footer dropped. Reset and confirm pages get copy in the same voice | The mockups are the screen source (D-11), and each page gets its one h1. |
| Guest below `lg` | Compact `highlight` band (brand + blocks, no slogan) above the form | The form stays above the fold on 390px and the Spiżarnia identity remains. |
| Mobile bar | Hamburger + brand left, avatar right; drawer = brand + stacked nav pills | The avatar menu stays the single place for "Profil" and "Wyloguj", and the existing drawer and logout locators keep working. |
| Sticky bar | Sticky, no border or shadow in any state | Navigation stays reachable on long pages, and at scroll 0 it matches the mockup pixel for pixel. |
| Visual coverage | Retarget the shell shots and add `guest-login-mobile` and `guest-forgot-password` (56 → 58 shots) | Every newly designed state is pinned. |
| Guest h1 size | `text-page-title` (36px), not the mockup's 40px | §10.3 wins over the mockup (D-11), so the guest and app titles share one role. |
| Slogan size | New role `text-hero` (48/52, 800) | Arbitrary values are banned and `display` belongs to the landing page. |
| Component split | `Common/AppBrand` (tones) + `Common/MainNav` (orientation) replace `AppSidebar`. The avatar gets a `sticker` variant in `ui/` | One brand and one nav serve the bar, the drawer and the guest panel, and views don't recolour primitives (§5.1). |

## Scope

**In scope:**
- `AuthenticatedLayout`, `GuestLayout`, `UserMenu`, the new `AppBrand`, `MainNav` and `TextLink`
- the `ui/avatar` variant and the `SheetHeader` padding
- the 5 Auth pages and the `text-hero` role
- the kitchen-sink "Powłoka" and Typografia sections
- `app-shell.spec.ts` and 4 visual specs
- up to 29 baselines (24–25 refreshed in phase 1, since `overlay-user-menu` may stay identical; 4 refreshed and 2 added in phase 2)
- `design-system.md` §5.1, §5.2, §7 and §10.3

**Out of scope:**
- the content of individual views (S-23…S-26), including page width (1152 vs 1200px)
- a scroll border on the bar
- icons on the guest blocks
- the landing page (D-13)
- dark mode
- cleaning up stale localStorage keys
- backend, routes and `<Head>` titles

## Architecture / Approach

```
AuthenticatedLayout ── header (sticky, bg-sidebar)
  ├─ SheetTrigger (lg:hidden) · AppBrand · MainNav[horizontal] (hidden lg:flex) · UserMenu(Avatar sticker)
  └─ Sheet drawer: SheetHeader(AppBrand) · MainNav[vertical]
GuestLayout(title, description, slogan) ── grid lg:2 cols
  ├─ aside bg-highlight: AppBrand[inverse] · slogan (lg+) · 3 meal blocks
  └─ main: h1 · description · <slot/> (form, TextLink cross-links)
```

State styling follows the §10.4 contract: hover goes to `accent`, focus is a 2px `outline` in `ring`, and the active pill is ink.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Top bar | A sticky bar and drawer on `AppBrand`, `MainNav` and the avatar sticker; collapse and `AppSidebar` removed; kitchen sink, E2E and 25 baselines refreshed; docs §5 and §7 | The bar may crowd at 1024px with "Członkowie rodziny", and the baseline refresh is large, so unrelated diffs could slip through review. |
| 2. Guest split screen | `text-hero`, `TextLink`, a split-screen `GuestLayout`, mockup copy on 5 Auth pages, 2 new shots, 6 baselines; docs §10.3 and §7 | The copy rewrite must keep every label and button that the auth E2E flows click. |

**Prerequisites:**
- F-06 merged (it is, at `285c79c`);
- Sail running, with Mailpit and the queue;
- `.env` with `AI_SCALING_DRIVER=fake`.

**Estimated effort:** about 2 sessions, one per phase. Each phase ends with the full gate green and a reviewed baseline diff.

## Open Risks & Assumptions

- **Mobile and focus/hover states are designed here, not in the mockups.** They follow §10.4 and the decisions above, so expect review comments on the drawer's look.
- **Copy for the reset and confirm-password pages is authored in this change.** Edit it in review if the voice is off.
- **Sticky bar has no visible edge while scrolling.** With no border and the same background as the page, the bar's edge is invisible over content. This was accepted in planning, and a later change can add a scroll state.

## Success Criteria (Summary)

- A signed-in user navigates through the top bar on desktop and the drawer on phones, and the current section is always marked in ink.
- Guests see the Spiżarnia split screen with one clear heading per page, and every auth flow (register, login, reset, confirm) still passes E2E.
- The visual gate is green at 58 shots, and every changed PNG has been reviewed.
