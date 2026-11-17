# Spiżarnia Primitives Implementation Plan

## Overview

Roadmap F-06 (M-4, SP-02). F-05 put the Spiżarnia tokens into `app.css`, but the `ui/` primitives still carry shadcn shapes: 1px borders, `rounded-md` buttons, `bg-black/80` overlays and a `rounded-xl` card. This change restyles the primitives **in place**, so every screen switches at once and the parallel views S-22…S-26 only re-lay out, never re-style. The shapes are a pill with a 2px ink outline, the sticker shadow, the 20px tile, 48px fields and the 24px dialog. The change also adds three shared patterns that two or more of those views need: the filter chip, pagination pills and the dashed "Dodaj…" button. It designs the interaction states the mock-ups leave out (press-in sticker), demos everything in the kitchen sink, and refreshes and extends the visual gate once, at the end.

## Current State Analysis

- **Primitives are shadcn defaults on Spiżarnia tokens:**
  - `ui/button/index.js:5-33`: `h-9 rounded-md text-sm`, `shadow-sm`/`shadow-xs`, `focus-visible:ring-1`; `outline` is `border border-input bg-background`.
  - `ui/card/Card.vue:12`: `rounded-xl border shadow-sm` (24px, deliberately parked for SP-02, `design-system.md` §10.2).
  - Fields (`ui/input/Input.vue`, `ui/textarea/Textarea.vue`, `ui/select/SelectTrigger.vue`, `ui/combobox/ComboboxInput.vue`): `h-9 rounded-md border border-input`, 1px ring.
  - Dialogs (`ui/dialog/DialogContent.vue:37-49`, `DialogScrollContent.vue`, `ui/alert-dialog/AlertDialogContent.vue:42-51`) and the sheet overlay (`ui/sheet/SheetContent.vue:44`): `bg-black/80`, `shadow-lg`, `sm:rounded-lg`. The dialog's X has the sr-only text "Close" (`DialogContent.vue:54`); the sheet's has "Zamknij".
  - `ui/badge/index.js`: the status variants are borderless pills, and `draft` is rendered as `outline` by `Components/MealPlans/StatusTag.vue`.
  - `ui/alert/index.js`: `rounded-lg border` (1px).
  - `ui/checkbox/Checkbox.vue`: `size-4 rounded-[4px]` plus a `dark:` class.
- **Tokens and utilities exist but primitives don't use them.** `shadow-sticker`, `shadow-dialog` and `bg-overlay` (`resources/css/app.css:114-117`) appear only in kitchen-sink swatches. Radii are `sm` 8, `md` 12, `lg` 20, `xl` 24 px (`app.css:25-28`). The type roles `text-section-title`, `text-label`, `text-meta` and `text-body` exist (`app.css:30-53`), and `cn()` knows them (`resources/js/lib/utils.js:4-11`).
- **Views never override shape or colour on these primitives.** That is why restyling in place is safe. There are about 50 Button usages (default, outline, ghost; `icon`/`sm` sizes) and about 15 conditional `border-destructive` on fields (RecipeForm, IngredientRow, FamilyMemberDialog), which keep working because tailwind-merge resolves border colours to the later class. The only shape override is `GuestLayout.vue:28` (`Card` with `shadow-none`), which S-22 replaces anyway.
- **Mock-up specification** (`bundle.css` of the Design System artifact, §10.1). The mock defines no hover, focus, disabled or loading states.

  | Element | Specification |
  | --- | --- |
  | Button (`.s-btn`) | 44px pill, 2px ink border, 14px/600; `sm` 32px/13px, `lg` 52px/16px |
  | Secondary button | `card` with the sticker shadow |
  | Icon button (`.s-ib`) | 34px round on `muted` |
  | Filter chip (`.s-chip`) | 44px pill on `card`, ink when `aria-pressed`, meal tones for category filters |
  | Page pill (`.s-pages`) | 44px, `card`, 2px border, current page ink, no shadow |
  | Dashed add row (`.s-add`) | 48px, 2px dashed ink, 12px radius |
  | Status pill (`.s-status`) | 32px, 2px ink border, 13px/700, `draft` dashed on `card` |
  | Field (`.s-input`) | 48px, 2px ink, 12px radius, `card`, 15px; error border `destructive` |
  | Checkbox (`.s-box`) | 22px |
  | Alert (`.s-alert`) | 12px radius, 2px ink, 14px/500; destructive is `card` with a `destructive` border |
  | Tile (`.s-tile`) | 2px ink, 20px, sticker shadow |
  | Dialog (`.s-dialog`) | `card`, 2px ink, 24px radius, `shadow-dialog`, 26px padding, title 24/28 800 −0.02em, round close button |

- **Verification surface:**
  - There is no JS unit runner (`package.json` scripts).
  - Primitive styling is guarded only by the visual gate (52 PNGs in 6 specs, `maxDiffPixels: 0`, `playwright.config.ts:25-35`) and `lint:tokens`, which skips `Components/ui/` but scans the kitchen sink.
  - `tests/e2e/visual/design-system.spec.ts:45-55` (`focus-visible.png`) Tabs from the first `destructive` to the first `outline` button, so it depends on the variant order in the Przyciski row.
  - `tests/e2e/meal-plan.spec.ts:237` closes `RecipeSwapDialog` by the X's sr-only name `'Close'`. The dialog has no other close control.

## Desired End State

Every `ui/` primitive that screens 02–15 use has the Spiżarnia shape, and the app picks it up without a single view edit. The exceptions are `StatusTag`, which opts into the status pill, and the E2E locator rename. Two new patterns are available for the slices: the filter chip (`ui/toggle`) and the `dashed` button. Pagination pills come through `ui/pagination`. The kitchen sink shows each variant in its states (§5.4). The visual gate pins the hover, pressed and focus-visible states this change designs, and all baselines are regenerated and reviewed. `design-system.md` §5.1 and §10 describe the new variants, and the roadmap slices carry the follow-ups they inherit.

Verify by:
- `/dev/design-system` in the browser;
- `vendor/bin/sail npm run build`, `lint:tokens`, Pest, the full E2E suite and `npm run test:visual` all green.

### Key Discoveries:

- Variant names already map 1:1 onto the mock: `default` → ink pill, `outline` → card with sticker, `secondary` → round muted icon button, `destructive` → red pill with ink border (`ui/button/index.js:9-22`).
- `AlertDialogAction`/`AlertDialogCancel` and every `ui/pagination` item render through `buttonVariants` (`AlertDialogCancel.vue`, `PaginationItem.vue:28-31`, `PaginationNext.vue`). `FamilyMemberPagination.vue:15-44` calls `buttonVariants` directly, so all of them follow the Button restyle.
- `ServerPagination.vue` supplies Polish labels ("Poprzednia", "Następna", "Strona N"); the English fallbacks inside `ui/pagination` are never shown.
- reka-ui (`^2.11.0`, already a dependency) ships `Toggle` with `aria-pressed` and `data-state="on|off"`, which matches the mock's chips. shadcn-vue's `toggle` wraps it, so adding it through the CLI is not expected to add packages.
- `RecipeSwapDialog` relies on the built-in X (no footer, Escape is captured by the open listbox), so the close button must stay in `DialogContent`.
- Tailwind 4 spacing is multiplier-based: `size-8.5` (34px), `h-13` (52px), `p-6.5` (26px), `px-4.5`, `py-3.5`, `min-h-33` (132px) and `translate-y-0.75` (3px) are scale classes, not arbitrary values.
- §10.3 already gives dialogs 24px titles but no role carries 24/28/800. A new role must be added to `--text-*` in `app.css` **and** to the `text` list in `extendTailwindMerge` (`utils.js:7`), or `cn()` treats it as a colour.

## What We're NOT Doing

- **Pill search field** (only S-24 uses it) and **buttons on `highlight`** (`on-hl`, only S-23 uses it). They stay in their slices.
- **Re-pointing view variants** (e.g. table actions from `ghost size="icon"` to `secondary`), replacing `Select` filters with chips, tile layouts, the family-member dashed tile and the empty-meal cell. These are S-22…S-26 work, recorded as carry-overs in the roadmap.
- **An interactive (link) variant of `Card`.** The mock has no whole-tile link; its tiles contain links.
- **Restyling `table`, `skeleton`, `avatar`, `collapsible`, `separator`, `label` and `sonner`**, and the sheet's panel layout (S-22 redesigns the mobile drawer).
- **A `loading` prop on `Button`.** Loading stays "disabled + `Loader2` spinner in the slot", as the kitchen sink already shows.
- **Pressed motion on flat controls** (ink/destructive buttons, chips, page pills). Only controls that carry the sticker shadow press in.
- Dark mode (D-1/D-14), regenerating older CLI components or moving to `@lucide/vue` (D-9), and a JS unit test runner.

## Implementation Approach

The restyle happens inside `Components/ui/` (cva variants and base classes), where `lint:tokens` does not look, so the policy lives in one place per primitive. Views keep their props. Phases 1–3 each restyle one family of primitives and its kitchen-sink section; they are verified by build, `lint:tokens`, Pest and targeted E2E, while the visual gate is knowingly red. Phase 4 adds the state shots, regenerates every baseline once, and syncs the docs. The shared interaction contract for every control:

- **hover**:
  - card-style controls (`outline`, `ghost`, `secondary`, `dashed`, the default chip) go to `accent`;
  - ink and destructive buttons go to `/90`.
- **active** (only on sticker-shadow controls): shift down 3px and drop the shadow, with no motion under `motion-reduce`.
- **focus-visible**: a 2px `outline-ring` with a 2px offset.
- **disabled**: `opacity-50`, no pointer events, no shadow.

## Critical Implementation Details

- **Focus uses `outline`, not `ring`.** The sticker is a `box-shadow`, and `active:shadow-none` / `disabled:shadow-none` must not also remove the focus indicator. The mock's accessibility rule asks for a 2px ring in `--ring` (≥ 3:1 on `background`, `card` and meal tones).
- **Badge status border needs `compoundVariants`.** cva emits variant classes before size classes, and every colour variant sets `border-transparent`. The 2px `border-foreground` of the `status` size must come from `compoundVariants` (or end up last through `cn`), otherwise tailwind-merge keeps `border-transparent`.
- **shadcn CLI side effects (D-9).** `vendor/bin/sail npx shadcn-vue@2 add toggle` may overwrite existing `ui/` files and add `@lucide/vue`/`cn`. Review the diff:
  - restore every pre-existing `ui/` file it touched;
  - rewrite imports to `@/lib/utils` and `lucide-vue-next`;
  - run `vendor/bin/sail npm uninstall cn @lucide/vue` if they appeared;
  - confirm `package.json` and the lockfile end up without new dependencies.

## Phase 1: Buttons, chips and pagination

### Overview

`Button` and everything built on `buttonVariants` gets the Spiżarnia pill, sizes and the shared state contract. A `dashed` variant is added, the filter chip is added as `ui/toggle`, and pagination becomes pills. The kitchen sink's Przyciski and Paginacja sections show all of it.

### Changes Required:

#### 1. Button variants

**File**: `resources/js/Components/ui/button/index.js`

**Intent**: Restyle `buttonVariants` in place to the mock's `.s-btn` family, add the `dashed` pattern, and apply the shared hover, press, focus and disabled contract, so every Button, `AlertDialogAction`/`Cancel` and pagination link follows.

**Contract**:
- **Base**: `rounded-full`, `border-2 border-foreground`, `text-label`, `gap-2`; focus-visible is `outline-2 outline-offset-2 outline-ring`; disabled adds `opacity-50 pointer-events-none shadow-none`; `[&_svg]:size-4` stays.
- **Variants** (names unchanged, plus `dashed`):
  - `default`: `bg-primary text-primary-foreground`, hover `/90`.
  - `destructive`: `bg-destructive text-destructive-foreground`, keeps the ink border, hover `/90`.
  - `outline`: `bg-card text-foreground shadow-sticker`, hover `accent`; active `translate-y-0.75 shadow-none`, `motion-reduce` keeps translate at 0.
  - `secondary`: `bg-muted`, transparent border, hover `accent`.
  - `ghost`: transparent bg and border, hover `accent`.
  - `link`: no border, no height, no padding, underlined `text-foreground`, hover thickens the underline. Today's `text-primary` hover is a no-op since `--primary` = `--foreground`.
  - **new** `dashed`: `rounded-md border-dashed bg-transparent text-foreground`, hover `accent`.
- **Sizes**:
  - `default`: `h-11 px-5`.
  - `sm`: `h-8 px-3 text-meta font-semibold`.
  - `lg`: `h-13 px-6.5 text-base`.
  - `xs`: `h-7 px-2.5 text-xs`, kept for compatibility; only the kitchen sink uses it.
  - `icon`: round `size-8.5 p-0`.

#### 2. Filter chip (`ui/toggle`)

**File**: `resources/js/Components/ui/toggle/` (new, via `vendor/bin/sail npx shadcn-vue@2 add toggle`)

**Intent**: The chip that S-24 (category filter with counts) and S-25 (status filter) both need, as one primitive with `aria-pressed` semantics instead of two hand-built pills.

**Contract**:
- `Toggle` (reka-ui) with `toggleVariants` restyled to `.s-chip`.
- **Base**: `h-11 px-4 gap-2 rounded-full border-2 border-foreground text-label bg-card text-foreground`, plus the shared focus and disabled contract.
- **Pressed**: `data-[state=on]:bg-primary data-[state=on]:text-primary-foreground` overrides any tone.
- **`variant`**:
  - `default`: hover `accent`.
  - `breakfast` / `supper` / `dinner`: `bg-meal-*` with `text-meal-*-foreground`, hover `brightness-95`.
- **`size`**: `default` (44px) and `sm` (`h-9 px-3.5`, the mock's 36px pill).
- No sticker shadow and no press motion.
- Imports normalised per D-9.

#### 3. Pagination pills

**Files**: `resources/js/Components/ui/pagination/PaginationItem.vue`, `PaginationPrevious.vue`, `PaginationNext.vue`, `PaginationFirst.vue`, `PaginationLast.vue`

**Intent**: Page links become the mock's `.s-pages` pills for `ServerPagination` and `FamilyMemberPagination` without touching either wrapper.

**Contract**:
- Items:
  - active page is the `default` (ink) variant, inactive pages are `outline`;
  - `shadow-none`, `h-11 min-w-11 px-3`, no press motion.
- Prev/next/first/last use `outline` with `shadow-none`.
- Ellipsis is unchanged.

#### 4. Kitchen sink: Przyciski and Paginacja

**File**: `resources/js/Pages/Dev/DesignSystem.vue` (Przyciski :373-397, Paginacja :629-655)

**Intent**: Demo the restyled Button variants (including `dashed`) with disabled, sizes, icon and loading, plus a chip group, without renaming any section heading. The visual spec finds sections by their `<h2>` text.

**Contract**:
- `buttonVariants` list gains `dashed`.
- New "Pigułki filtra" group inside Przyciski:
  - unpressed chips: default, breakfast, supper and dinner, each with a count in a `<span>`;
  - one pressed chip and one disabled chip;
  - an `sm` chip.
- Paginacja is unchanged (it renders `ServerPagination`).
- Tokens only (the file is scanned by `lint:tokens`).

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token scan and its self-test pass: `vendor/bin/sail npm run lint:tokens` and `bash scripts/lint-tokens.test.sh`
- Kitchen sink page test passes: `vendor/bin/sail pest --filter=DesignSystemPageTest`
- No new dependency: `git diff --stat develop -- package.json package-lock.json` shows no added packages

#### Manual Verification:

- On `/dev/design-system`, Przyciski shows the following, all consistent with the mock's `.s-btn`/`.s-chip`:
  - 44px ink pills, the outline button with its sticker shadow that presses in on click, and the round muted icon button;
  - the dashed button;
  - chips in four tones, with the pressed one in ink;
  - the 2px focus ring when tabbing.
- Paginacja shows card pills with the current page in ink.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Badges, alerts, cards and form fields

### Overview

This phase covers the status pill (with dashed `draft`), the 2px alert, `Card` as the 20px sticker tile, 48px form fields with a 2px outline and 12px radius, the 22px checkbox, and Spiżarnia popover surfaces for menus and listboxes.

### Changes Required:

#### 1. Badge status pill

**File**: `resources/js/Components/ui/badge/index.js`

**Intent**: Give meal plan statuses the mock's outlined `.s-status` pill without changing the category badges, which stay borderless pills.

**Contract**:
- New `size` dimension:
  - `default`: today's `px-2.5 py-0.5 text-xs font-semibold`;
  - `status`: `h-8 gap-1.5 px-3 text-meta font-bold`, plus a 2px `border-foreground` from `compoundVariants` (see Critical Implementation Details).
- New `draft` variant: `border-dashed bg-card text-foreground`.
- The badge's `focus:ring` becomes the shared outline contract.

**File**: `resources/js/Components/MealPlans/StatusTag.vue`

**Intent**: Use the status pill: `draft` maps to `draft` (was `outline`) and every status passes `size="status"`. The spinner for `processing` stays.

**Contract**: The label texts are unchanged; E2E matches `getByText('Szkic', { exact: true })`.

#### 2. Alert

**File**: `resources/js/Components/ui/alert/index.js` (and `AlertTitle.vue`)

**Intent**: Alerts become the mock's `.s-alert`.

**Contract**:
- **Base**: `rounded-md border-2 border-foreground px-4.5 py-3.5 text-sm font-medium`; the icon grid is unchanged.
- `destructive` is `bg-card text-destructive border-destructive`.
- The `info`/`warning`/`success` colour pairs are unchanged.
- `AlertTitle` is `font-semibold`.

#### 3. Card as tile

**Files**: `resources/js/Components/ui/card/Card.vue`, `CardTitle.vue`

**Intent**: `Card` becomes the 20px sticker tile (`.s-tile`), closing the radius parked in §10.2.

**Contract**:
- `Card`: `rounded-lg border-2 border-foreground bg-card shadow-sticker`.
- `CardTitle`: `text-section-title`.
- Padding is unchanged. `role`/`aria-labelledby` pass-through stays; `profile.spec.ts` uses it.

#### 4. Form fields and checkbox

**Files**: `resources/js/Components/ui/input/Input.vue`, `textarea/Textarea.vue`, `select/SelectTrigger.vue`, `combobox/ComboboxInput.vue`, `checkbox/Checkbox.vue`

**Intent**: Fields match `.s-input`, and the error state works for every field without relying on view classes.

**Contract**:
- **Fields**: `h-12 rounded-md border-2 border-foreground bg-card px-4 text-body`, placeholder `text-muted-foreground`, the shared focus-visible outline (`SelectTrigger` moves from `focus:` to `focus-visible:`), `aria-invalid:border-destructive`, disabled `opacity-50 cursor-not-allowed`.
- **Textarea**: `min-h-33 py-3`. This replaces `min-h-[60px]`.
- **Views' `border-destructive`** must still win (same tailwind-merge group, later class).
- **Checkbox**:
  - `size-5.5 rounded-sm border-2 border-foreground bg-card`, checked `bg-primary text-primary-foreground`;
  - the shared focus outline and `aria-invalid:border-destructive`;
  - remove the `dark:` class and `rounded-[4px]`;
  - indicator `size-3.5`.
- The `autofocus` local edit in `Input.vue` stays (marked "keep on D-9 regeneration").

#### 5. Popover surfaces

**Files**: `resources/js/Components/ui/select/SelectContent.vue`, `combobox/ComboboxList.vue`, `dropdown-menu/DropdownMenuContent.vue`, `dropdown-menu/DropdownMenuSubContent.vue`

**Intent**: Menus and listboxes opened from Spiżarnia controls read as the same family. This is a small addition beyond the SP-02 list, so S-22 (user menu) and S-25 (swap list) don't each restyle them.

**Contract**: The surface is `rounded-md border-2 border-foreground bg-popover shadow-sticker`. Item radius (`rounded-sm`, 8px) and the `accent` focus are unchanged.

#### 6. Kitchen sink: Odznaki, Komunikaty, Formularze, Karta i tabela

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: Show the new badge size and variant. Every other section picks up the restyle by itself.

**Contract**:
- Odznaki gets a "Status" row with `size="status"`:
  - `draft` (Szkic);
  - `muted` (Oczekuje);
  - `info` (Generowanie, with spinner);
  - `success` (Gotowy);
  - `destructive` (Błąd).
- Komunikaty, Formularze (error field already present via `ds-field-error`) and Karta i tabela: structure unchanged.
- Section headings unchanged.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token scan passes: `vendor/bin/sail npm run lint:tokens`
- Kitchen sink page test passes: `vendor/bin/sail pest --filter=DesignSystemPageTest`
- Field-heavy E2E specs pass (after `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`): `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npx playwright test tests/e2e/recipe-form.spec.ts tests/e2e/profile.spec.ts`

#### Manual Verification:

- Kitchen sink shows the following, with the 2px focus outline visible on fields:
  - outlined status pills with a dashed "Szkic";
  - 2px alerts with a 12px radius;
  - the card as a 20px tile with the sticker shadow;
  - 48px fields with a red border on the error field;
  - the 22px checkbox;
  - select, combobox and menu surfaces with an outline and shadow.
- On `/recipes/create`, a validation error still turns the right fields red.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Dialogs and overlays

### Overview

Dialogs and alert dialogs become the mock's `.s-dialog`, every overlay uses the `overlay` token, the close button becomes a round "Zamknij", and the one E2E locator that depends on the old label is updated.

### Changes Required:

#### 1. Dialog title type role

**Files**: `resources/css/app.css` (`@theme`, next to `--text-section-title`), `resources/js/lib/utils.js:7`, `resources/js/Pages/Dev/DesignSystem.vue` (`typographyRoles` :130-138)

**Intent**: Give dialogs the 24/28/800 title from the mock as a named role, following the §10.3 rule for new roles.

**Contract**:
- `--text-dialog-title: 1.5rem` with `--line-height: 1.75rem`, `--letter-spacing: -0.02em` and `--font-weight: 800`.
- `'dialog-title'` is added to the `text` list in `extendTailwindMerge`.
- The kitchen sink Typografia section lists the role.

#### 2. Dialog content

**Files**: `resources/js/Components/ui/dialog/DialogContent.vue`, `DialogScrollContent.vue`, `DialogHeader.vue`, `DialogFooter.vue`, `DialogTitle.vue`

**Intent**: Turn dialogs into `.s-dialog`. The close button stays built in because `RecipeSwapDialog` depends on it.

**Contract**:
- **Overlay**: `bg-overlay`.
- **Content**:
  - `bg-card border-2 border-foreground rounded-xl shadow-dialog p-6.5 gap-4` at every breakpoint;
  - width `max-w-[calc(100%-2rem)] sm:max-w-lg` as in `AlertDialogContent`. Views' `sm:max-w-md` still overrides.
- **Close**:
  - a `DialogClose` styled `buttonVariants({ variant: 'secondary', size: 'icon' })`, at `right-5 top-5`;
  - sr-only text "Zamknij".
- **Header**: left-aligned at every breakpoint with right padding clearing the close button.
- **Footer**: `gap-2.5`.
- **Title**: `text-dialog-title`.

#### 3. Alert dialog

**Files**: `resources/js/Components/ui/alert-dialog/AlertDialogContent.vue`, `AlertDialogHeader.vue`, `AlertDialogTitle.vue`

**Intent**: Give confirmations the same shell (screen 15, "Usuń przepis"). There is no close button, as in the mock; `ConfirmDialog` keeps focus on "Anuluj".

**Contract**:
- **Overlay**: `bg-overlay`.
- **Content**: `bg-card border-2 border-foreground rounded-xl shadow-dialog p-6.5`.
- **Header**: left-aligned.
- **Title**: `text-dialog-title`.

#### 4. Sheet overlay and close

**Files**: `resources/js/Components/ui/sheet/SheetContent.vue`, `resources/js/Components/ui/sheet/index.js` (`sheetVariants`)

**Intent**: The mobile drawer uses the same overlay, close button and edge as the dialogs. The panel layout itself is S-22's.

**Contract**:
- **Overlay**: `bg-overlay`.
- **Close**: the same round secondary "Zamknij" button.
- **Panel edge**: the side border is `border-2 border-foreground`, on the inner edge only.

#### 5. E2E locator

**File**: `tests/e2e/meal-plan.spec.ts:237`

**Intent**: Follow the close button's Polish name.

**Contract**:
- `getByRole('button', { name: 'Zamknij', includeHidden: true })`.
- No other spec matches `'Close'`; confirm with a grep of `tests/e2e`.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token scan passes: `vendor/bin/sail npm run lint:tokens`
- No E2E locator uses the old close label: `grep -rn "'Close'" tests/e2e` returns nothing
- Dialog-related E2E specs pass (after re-seeding): `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npx playwright test tests/e2e/meal-plan.spec.ts tests/e2e/confirm-dialog.spec.ts tests/e2e/app-shell.spec.ts`

#### Manual Verification:

- Dialog (kitchen sink and "Wygeneruj jadłospis"), alert dialog and `ConfirmDialog` look like screen 15:
  - 24px radius, 2px outline and 6px hard shadow;
  - big title;
  - round close button top-right (dialog only), announced as "Zamknij".
- The swap dialog (`RecipeSwapDialog`) still closes with the X.
- On a phone-width window, the dialog keeps a 16px margin to the screen edge, and the mobile drawer uses the indigo-tinted overlay.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: Visual gate and docs

### Overview

This phase pins the states this change designed, regenerates and reviews every baseline once, runs the whole suite, and records the new contract in `design-system.md` and the roadmap.

### Changes Required:

#### 1. State shots and the focus-visible anchor

**File**: `tests/e2e/visual/design-system.spec.ts`

**Intent**: The mock leaves hover, pressed and focus undefined, so the states this change chose need a regression guard. The existing focus-visible shot needs to survive the new Przyciski layout.

**Contract**:
- `focus-visible.png` still Tabs onto the first `outline` button. The anchor it Tabs from is re-pointed to whatever precedes it in the final variant order.
- **New shots** (element- or row-level screenshots with stable locators; add a `data-testid` on a kitchen-sink row only if role/name is ambiguous):
  - `button-hover.png`: hover on the outline button;
  - `button-pressed.png`: `mouse.down()` held on the outline button, released afterwards;
  - `input-focus-visible.png`: Tab into `#ds-input-default`;
  - `chip-focus-visible.png`: Tab onto a chip.
- The spec goes from 29 to 33 PNGs, and the visual gate from 52 to 56.

#### 2. Baselines

**Files**: `tests/e2e/__screenshots__/visual/**`

**Intent**: Regenerate every baseline deliberately, since the restyle touches every page shot.

**Contract**:
1. `vendor/bin/sail npm run build` with no `public/hot`.
2. `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`.
3. `npm run test:visual -- --update-snapshots`.
4. Review each changed PNG in the diff.

#### 3. Design system and roadmap docs

**Files**: `context/foundation/design-system.md`, `context/foundation/roadmap.md`

**Intent**: Make the new contract discoverable for S-22…S-26 and keep the visual-gate description true.

**Contract**:
- **`design-system.md`**:
  - a dated "SP-02 done" note in the header, like SP-01;
  - §5.1: Button variants and sizes including `dashed`; `toggle` as the filter chip with tones; Badge `size="status"` and `draft`; Card as tile; fields; popover surfaces; dialog shell; pagination pills;
  - §5.3: `StatusTag` on the status pill;
  - §7.3: shot counts (33 in the kitchen-sink spec, 56 total) and the four new state shots;
  - §10.2: the "`ui/card` stays `rounded-xl` until SP-02" line becomes past tense;
  - §10.3: the `text-dialog-title` role;
  - §10.4: the interaction-state contract (hover, press-in on sticker controls, 2px outline focus, disabled).
- **`roadmap.md`**: "Carry-over z F-06" bullets. Status fields are left to the skills.

  | Slice | Carry-overs |
  | --- | --- |
  | S-22 | Drop `shadow-none` on the guest `Card`; position the sheet close button over the new drawer header |
  | S-23 | `StatCard` content has no top padding (`CardContent` is `p-6 pt-0` with no header) |
  | S-24 | Category filter → `Toggle` tones with counts; ingredient add → `dashed`; table actions `ghost size="icon"` → `secondary`; pill search field |
  | S-25 | Status filter → `Toggle`; empty meal and "Wybierz" → `dashed` |
  | S-26 | Dashed "Dodaj członka rodziny" tile on `dashed` |

### Success Criteria:

#### Automated Verification:

- Visual gate passes on the regenerated baselines: `npm run test:visual`
- Full E2E suite passes (after re-seeding): `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- Pest suite passes: `vendor/bin/sail composer test`
- Build and token scan pass: `vendor/bin/sail npm run build` and `vendor/bin/sail npm run lint:tokens`
- Kitchen-sink spec has 33 baselines: `ls tests/e2e/__screenshots__/visual/design-system.spec.ts | wc -l` prints 33

#### Manual Verification:

- Every changed PNG reviewed in the diff:
  - page shots show Spiżarnia primitives in the old layouts;
  - no clipped text in 44px buttons or 48px fields;
  - no layout break on mobile shots.
- The four new state shots show the intended hover, pressed and focus states.
- `design-system.md` §5.1 and §10 read as the single place a slice author needs for the new variants.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- None. There is no JS unit runner, and the change has no PHP logic. `DesignSystemPageTest` keeps guarding the kitchen-sink route.

### Integration Tests:

- **E2E** (role-based) proves behaviour is unchanged:
  - focus on "Anuluj" in `ConfirmDialog`;
  - Escape and focus return in the drawer;
  - closing the swap dialog with the X ("Zamknij");
  - the Select listbox closing in the recipe form.
- **Visual gate**: 14 kitchen-sink sections, 12 overlays, 3 existing and 4 new state shots, and 23 page shots.

### Manual Testing Steps:

1. Open `/dev/design-system` and Tab through Przyciski, the chips and Formularze. Every control shows the 2px ring offset from its outline.
2. Press and hold the outline button: it sinks 3px and loses its shadow. With "reduce motion" enabled in the OS, only the shadow disappears.
3. Open every overlay in Nakładki and compare it with screen 15 of the artifact.
4. Submit an empty recipe form and check that the red borders sit on the 2px fields.
5. On a 390px-wide window, open the "Wygeneruj jadłospis" dialog and the mobile drawer.

## Performance Considerations

None. Class-only changes; no new runtime dependencies.

## Migration Notes

None (no data). Views between F-06 and their slice show Spiżarnia primitives inside the Zielnik layouts. Buttons are taller (44px against 36px) and fields 48px, which is accepted and visible in the refreshed baselines.

## References

- Roadmap item: `context/foundation/roadmap.md` F-06
- Design system: `context/foundation/design-system.md` §5, §7.3, §10.1–10.6
- Previous change: `context/archive/2026-11-17-spizarnia-tokens/plan.md`
- Mock-up source: Design System artifact "Menu Maker — Spiżarnia" (`project/components/bundle.css`, screens 02, 07, 09, 10, 12, 13, 15)
- Kitchen sink: `resources/js/Pages/Dev/DesignSystem.vue`; visual spec `tests/e2e/visual/design-system.spec.ts:45-55`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Buttons, chips and pagination

#### Automated

- [x] 1.1 Client and SSR bundles build: `vendor/bin/sail npm run build` — 88ccd84
- [x] 1.2 Token scan and its self-test pass: `vendor/bin/sail npm run lint:tokens` and `bash scripts/lint-tokens.test.sh` — 88ccd84
- [x] 1.3 Kitchen sink page test passes: `vendor/bin/sail pest --filter=DesignSystemPageTest` — 88ccd84
- [x] 1.4 No new dependency: `git diff --stat develop -- package.json package-lock.json` shows no added packages — 88ccd84

#### Manual

- [ ] 1.5 Przyciski shows Spiżarnia pills, sticker press-in, round icon button, dashed button, chips in four tones and the 2px focus ring
- [ ] 1.6 Paginacja shows card pills with the current page in ink

### Phase 2: Badges, alerts, cards and form fields

#### Automated

- [x] 2.1 Client and SSR bundles build: `vendor/bin/sail npm run build` — ffb653a
- [x] 2.2 Token scan passes: `vendor/bin/sail npm run lint:tokens` — ffb653a
- [x] 2.3 Kitchen sink page test passes: `vendor/bin/sail pest --filter=DesignSystemPageTest` — ffb653a
- [x] 2.4 Field-heavy E2E specs pass: `recipe-form.spec.ts` and `profile.spec.ts` — ffb653a

#### Manual

- [ ] 2.5 Kitchen sink shows status pills with dashed Szkic, 2px alerts, the 20px tile card, 48px fields, the 22px checkbox and outlined popover surfaces
- [ ] 2.6 Recipe form validation errors still turn the right fields red

### Phase 3: Dialogs and overlays

#### Automated

- [x] 3.1 Client and SSR bundles build: `vendor/bin/sail npm run build` — 61cb918
- [x] 3.2 Token scan passes: `vendor/bin/sail npm run lint:tokens` — 61cb918
- [x] 3.3 No E2E locator uses the old close label: `grep -rn "'Close'" tests/e2e` returns nothing — 61cb918
- [x] 3.4 Dialog-related E2E specs pass: `meal-plan.spec.ts`, `confirm-dialog.spec.ts` and `app-shell.spec.ts` — 61cb918

#### Manual

- [ ] 3.5 Dialogs, alert dialogs and ConfirmDialog match screen 15 (24px, 2px outline, 6px shadow, big title, round Zamknij)
- [ ] 3.6 RecipeSwapDialog still closes with the X
- [ ] 3.7 Phone-width dialog keeps its margin and the drawer uses the overlay token

### Phase 4: Visual gate and docs

#### Automated

- [x] 4.1 Visual gate passes on the regenerated baselines: `npm run test:visual`
- [x] 4.2 Full E2E suite passes: `npm run test:e2e`
- [x] 4.3 Pest suite passes: `vendor/bin/sail composer test`
- [x] 4.4 Build and token scan pass: `vendor/bin/sail npm run build` and `vendor/bin/sail npm run lint:tokens`
- [x] 4.5 Kitchen-sink spec has 33 baselines

#### Manual

- [ ] 4.6 Every changed PNG reviewed (no clipped text, no mobile layout break)
- [ ] 4.7 The four new state shots show the intended hover, pressed and focus states
- [ ] 4.8 design-system.md §5.1 and §10 describe the new variants for slice authors
