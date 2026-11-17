# Spiżarnia Meal Plans UI Implementation Plan

## Overview

Roadmap S-25 (M-4, SP-06). F-05, F-06 and S-22 gave the app Spiżarnia tokens, primitives and the top-bar shell, but the meal-plan views are still Zielnik layouts on the new tokens: the list is a table with a `Select` filter, and the week grid is a bordered table with a 4px category stripe per cell.

This change restyles the views from artifact screens 10–12 and 15 (`design-system.md` §10.1, §10.5):

- **Meal plans list (ekran 10):** full-width week tiles with a date link, a status pill, a 7-square fill bar with an „n/21” label and the „PDF” / „Otwórz” / „…” actions. Above them sit status filter pills (`Toggle`) and a sort pill. The table, `FiltersBar` and „Na stronie” go away.
- **Meal plan page (ekrany 11–12):** a date-range `<h1>` with a „Jadłospisy” breadcrumb. The grid is days × meals, with cells filled in the meal colour. Linked-meal tags are small pills on `card`. A draft shows a „Zmień” pill in each cell, and an empty meal is a dashed frame with „Wybierz”. The shopping list becomes a tile with dotted rows, and a draft shows a dashed placeholder tile next to the grid.
- **Dialogs (ekran 15):** the swap dialog shows the current option on the slot's category colour. The generate and confirm dialogs already match ekran 15 after F-06.

Behaviour, routes, controllers, Inertia props and the backend stay unchanged. The functional E2E spec `tests/e2e/meal-plan.spec.ts` runs unchanged. Only the two visual specs, the kitchen sink and their baselines are updated, deliberately.

## Current State Analysis

**List (`resources/js/Pages/MealPlans/Index.vue`)**
- Order on the page: `PageHeader` with `GenerateMealPlanDialog` in the actions (:6-10), `FiltersBar` (:12), a table wrapper `overflow-hidden rounded-lg border bg-card` (:18, carry-over F-05) around `MealPlanTable`, then `ServerPagination` (:29).
- Filters come from the URL: `filter[status]`, `sort` (default `created_at`), `direction` (default `desc`) and `perPage` (default 15). `buildQuery` writes them back with `router.get`.
- `FiltersBar.vue` holds two controls: „Status” (`StatusFilter.vue`, a `Select` whose „Wszystkie statusy” maps to `''`) and „Na stronie” (`PerPageSelect.vue`).
- `MealPlanTable.vue` builds the table:
  - Columns: `SortableHeader` for „Tydzień” (`start_date`), „Status” and „Utworzono” (`created_at`), plus a plain „Posiłki”.
  - Empty states sit in `TableEmpty`. Filtered: „Brak jadłospisów o wybranym statusie” with „Wyczyść filtry”. Otherwise: „Nie masz jeszcze jadłospisów” with `GenerateMealPlanDialog label="Wygeneruj pierwszy jadłospis"`.
- `MealPlanRow.vue` builds each row:
  - A date `Link` with `hover:text-primary`, which no longer changes colour because `primary` = ink.
  - `StatusTag`, `ChefHat` with `meals_count`, and `formatDateTime(created_at)`.
  - `ActionDropdown`.
- Only `Index.vue` imports these five components.
- `ActionDropdown.vue` has a ghost icon trigger with `aria-label="Akcje: <range>"` (:8). Its items are Szczegóły, PDF z przepisami, PDF bez przepisów, Wróć do szkicu (done or error) and Usuń (not while processing). `Show.vue` reuses it.
- Index data per item (`app/Http/Resources/MealPlanCollection.php`): `id`, `start_date`, `end_date`, `status`, `created_at`, `meals_count` and `links{self, pdf, pdf_with_recipes}`.
  - `meals_count` counts only meals that have a recipe, carried-over ones included (`MealPlanController.php:50`, test `tests/Feature/MealPlanControllerTest.php:754`).
  - A plan always has 21 slots (7 days × 3 categories).
  - There are no per-status counts.

**Meal plan page (`resources/js/Pages/MealPlans/Show.vue`)**
- Header: `PageHeader title="Jadłospis"` with `:description="dateRange"` in `dd.mm.yyyy – dd.mm.yyyy` (:6, :263). `<Head>` uses the same string (:2).
- Header actions: `StatusTag`, `PdfDownloadMenu` (done only), „Zatwierdź” and outline „Losuj ponownie” (draft with meals), outline „Wróć do szkicu” (done with meals) and `ActionDropdown`.
- States:
  - Generating: „Generujemy jadłospis…”, a slow-worker warning after 75 s, and `<MealPlanGrid skeleton>`.
  - Error: a destructive alert with „Wróć do szkicu”.
  - No meals: `rounded-lg border bg-card p-8` with `EmptyState` (:56, carry-over F-05).
  - With meals: the draft info alert, the short-categories alert and the scaling warning (`data-testid="scaling-notice"`), then the grid and `ShoppingList` in `xl:w-72` (:107-118, done only, because `shoppingList` is a done-only lazy prop).
- At the bottom sits an outline „Wróć do jadłospisów” button (:122-129).

**Grid (`resources/js/Components/MealPlans/MealPlanGrid.vue`)**
- Markup: a real `<table data-testid="meal-plan-grid">` in `overflow-x-auto rounded-lg border bg-card` (:2-3), with the header row on `bg-muted`. Rows have a `th scope="row"` (weekday plus `dd.mm`), and the columns follow `MEAL_CATEGORIES`.
- Filled cell: `border-l-4 pl-3 border-meal-*` (:28, `categoryBarClass`) and a recipe `Link` with `hover:text-primary`.
  - Link markers use `Badge variant="muted"` after the name: „Na 2 dni” / „Z wczoraj” and „Też na śniadanie” / „Z kolacji”, taken from `lib/mealLinks.js` (:41-42).
  - Meta line: „{kcal} kcal (cały przepis) · {n} porcji”. The plural is wrong for 1–4 and the wording comes from `ai-ingredient-scaling`.
  - Draft: an outline sm „Zmień” with `aria-label="Zmień przepis: {weekday} – {Kategoria}"`.
  - A „Składniki (n)” collapsible with `text-primary` (:60, carry-over F-05).
- Empty slot: the same stripe, „Bez posiłku” and an outline sm „Wybierz” with `aria-label="Wybierz przepis: …"` (:77-90).

**Shopping list and swap dialog**
- `ShoppingList.vue`: a `Card` with `CardTitle as="h2"` „Lista zakupów” and a `ul.divide-y data-testid="shopping-list"`. The recipes without ingredients go in a footnote.
- `RecipeSwapDialog.vue`:
  - Layout: an inline reka `Combobox`. Each option is a `ComboboxItem class="flex-col items-start"` with the name in `span.font-medium` and „obecny” on the right (:16-58).
  - Order of options: „Nie robimy (pusty posiłek)”, then the link option, then the recipes.
  - The current option has no fill.

**Tests that lock markup**
- `tests/e2e/meal-plan.spec.ts` (functional, must stay unchanged):
  - Grid structure: `getByTestId('meal-plan-grid')`, `tbody td a` = 21, `columnheader` Śniadanie / Obiad / Kolacja, `tbody tr` / `td` nth with breakfast=0, supper=1, dinner=2.
  - Button names: `Zmień przepis: …` regex, `'Wybierz'` (substring of the aria-label), `^Składniki`, „Zatwierdź”, „Wróć do szkicu”, „Pobierz PDF”.
  - Texts: the four link labels, „Bez posiłku”, „Szkic” and „Generujemy jadłospis…”.
  - Swap dialog: options `span.font-medium` (:41, :54), the `combobox` named „Szukaj przepisu” and the „Zamknij” button.
  - Test ids: `shopping-list` and `meal-ingredients`.
- `tests/e2e/visual/meal-plans-index.spec.ts`:
  - `getByRole('row')` = 6 (:26, :61).
  - `combobox 'Status'` → `option 'Oczekuje'` (:46-55).
  - 4 baselines in `tests/e2e/__screenshots__/visual/meal-plans-index.spec.ts/`.
- `tests/e2e/visual/meal-plan-show.spec.ts`:
  - h1 „Jadłospis” (:30, :70), the `dd.mm.yyyy – dd.mm.yyyy` text, and `tbody td a` = 21 / 20.
  - 5 baselines.
- `tests/e2e/visual/design-system.spec.ts`: 14 section shots, including `section-jadlospis.png` and „Komponenty aplikacji”, plus `chip-focus-visible` on the first filter pill.

**Kitchen sink (`resources/js/Pages/Dev/DesignSystem.vue`)**
- The `chipTones` labels are swapped (:162-163): `supper` shows „Kolacja” and `dinner` shows „Obiad”, the reverse of `lib/mealCategories.js`.
- „Komponenty aplikacji” (:797-940) demos the Index `PageHeader` and both table empty states (:853-880).
- „Jadłospis” (:942-988) demos:
  - a read-only grid and an editable grid with `RecipeSwapDialog demo`, using demo data with both link pairs (:245-248);
  - the skeleton;
  - `PdfDownloadMenu`;
  - the shopping list, filled and empty.

## Desired End State

**List**
- `/meal-plans` shows the toolbar first: a `role="group"` „Status” of `Toggle` pills („Wszystkie”, „Szkic”, „Oczekuje”, „Generowanie”, „Gotowy”, „Błąd”) with exactly one pressed, and a sort pill on the right.
- Below the toolbar is a `<ul aria-label="Jadłospisy">` of week tiles, then `ServerPagination`.
- Each tile shows:
  - the date range as a link in `text-section-title`, with the „Utworzono …” meta under it;
  - a `StatusTag`;
  - 7 squares where `floor(meals_count / 3)` are filled (colours cycle breakfast/supper/dinner, the rest `muted`, the whole bar `aria-hidden`) next to a visible `meals_count/21` label;
  - „PDF” (done only), „Otwórz” and „…”.
- Tile layout: one row from `lg` up, stacked below it.
- Choosing a pill or a sort option changes the URL exactly as the old `Select` and column headers did.

**Meal plan page**
- `/meal-plans/{id}` has an `<h1>` with the long date range („15–21 czerwca 2026”) and a breadcrumb „Jadłospisy” in `<nav aria-label="Ścieżka nawigacji">`. The bottom back button is gone. The header actions are in this order:
  - draft: status, „Losuj ponownie”, „Zatwierdź”, „…”;
  - done: status, ghost „Wróć do szkicu”, „Pobierz PDF”, „…”.
- The grid sits in a tile and stays a `<table>`. Each cell is a `rounded-md` block in `bg-meal-*` with `text-meal-*-foreground`. Linked-meal tags are `Badge variant="tag"` above the name. The draft has an outline xs „Zmień” pill (`RefreshCw`).
- Empty meal: a dashed `muted-foreground` frame with „Bez posiłku” and, in a draft, a „Wybierz” pill (`Plus`).
- „Składniki” is underlined in the cell's foreground colour.
- On a phone the table scrolls sideways inside its tile with a sticky day column.
- The shopping list is a tile with dotted separators beside the grid from `xl` (`xl:w-75`). In a draft, a dashed placeholder tile says when it will appear.

**Swap dialog**
- The current option is filled with the slot's `meal-*` colour.

**Kitchen sink and docs**
- The kitchen sink demos every new piece in its states.
- `design-system.md` §5.1, §5.3 and §7 describe the result.

**Verify by**
- `vendor/bin/sail npm run build`, `lint:tokens`, the Pest suite and the full E2E suite pass with `tests/e2e/meal-plan.spec.ts` untouched.
- `npm run test:visual` is green after a deliberate refresh in which only the PNGs listed in each phase changed.
- The views look right at 1280, 1024 and 390 px.

### Key Discoveries:

- `meals_count` plus a constant 21 is enough for the fill bar, so no backend change is needed (`MealPlanController.php:50`, `app/Services/MealPlanGenerator.php` `MEALS_PER_CATEGORY` = 7).
- `ui/toggle` is a single on/off pill (`modelValue: boolean`, `aria-pressed`). There is no ToggleGroup, so the status group has to keep exactly one pill pressed by hand (`resources/js/Components/ui/toggle/index.js`).
- `ui/dropdown-menu` already ships `DropdownMenuRadioGroup` and `DropdownMenuRadioItem` for the sort pill.
- `Badge` has no variant for a `card` pill without an outline. `outline` has a border and `muted` is beige with muted text, so the mockup's linked-meal tag needs a new `tag` variant (`resources/js/Components/ui/badge/index.js`).
- `meal-*-foreground` is near-black. That made a stripe in it look like ink (F-05 review), which is why full fills with foreground text are now the right use of the tokens, per §10.4 rule 2.
- `primary` = `foreground`, so every `hover:text-primary` in these views (`MealPlanRow.vue:7`, `MealPlanGrid.vue:37`) gives no cue. Use an underline on hover and the §10.4 focus contract (`outline-2 outline-ring outline-offset-2`) instead.
- tailwind-merge drops a type role when a size class sits next to it (F-06 review: `ShoppingList` CardTitle rendered at 400). Use `text-section-title` alone and never add `text-lg` / `text-sm` to it.
- Arbitrary values are banned outside `ui/` (`scripts/lint-tokens.sh`). Tile column widths and square sizes must come from the spacing scale (e.g. `lg:w-38`, `size-5.5`), never `grid-cols-[…]` or `w-[…]`.
- A kitchen-sink section that changes height shifts every section below it, and those shots pick up anti-aliasing noise. Expect and review that noise rather than predicting byte-identical shots (`spizarnia-app-shell/plan.md` addendum, `design-system.md` §7).
- `E2eMealPlanSeeder` seeds five plans for the visual user: done 06-15, draft 06-29, done 06-08, processing and error (`database/seeders/E2eMealPlanSeeder.php:158-162`). `design-system.md` §7 still says „czterech”.

## What We're NOT Doing

- **No backend changes:** no per-status counts on the pills, no per-day completeness for the fill bar, no new props, routes or controllers. The Pest suite must pass untouched.
- **No sort by status in the UI:** the controller keeps accepting `sort=status` / `end_date`, but the sort pill offers only the four created/week options.
- **No per-page control:** „Na stronie” is removed. A `perPage` in the URL is still honoured and preserved when filtering, sorting or paging.
- **No change to the swap or approve flows:** swapping, approving, redrawing, returning to draft, the PDF downloads, the delete flow and their dialogs' copy stay as they are. The swap dialog keeps its search, option order and immediate apply.
- **No CSS-stacked grid on mobile:** no `max-md:block` on table elements. The grid stays a `<table>` and scrolls.
- **No literal reading of the „dashed button” carry-over:** the empty-slot frame is not a `Button variant="dashed"`. The mockup's frame plus pill wins (the user's decision), and the `dashed` variant stays unused here.
- **No mockup-only removals:** the „Składniki” toggle and the „(cały przepis)” kcal note stay, even though ekran 11 omits them, because behaviour and copy stay unchanged.
- **No other pages:** the generate dialog's trigger on the dashboard, the dashboard „Ten tydzień” (S-23), recipes (S-24), family and profile (S-26) are out of scope.
- **No PDF or dark-mode work:** PDF changes, dark mode (D-14) and regenerating `ui/` primitives through the CLI (D-9) are out of scope.
- **No CLAUDE.md wording fix:** the stale „four plans” in `CLAUDE.md` is left for a docs pass. Only `design-system.md` §7 is corrected here.

## Implementation Approach

Two phases, one per visual spec, so each spec's baselines are refreshed exactly once:

- **Phase 1:** the list, plus the shared `ActionDropdown` / `PdfDownloadMenu` trigger tweaks the tiles need.
- **Phase 2:** the meal plan page, grid, shopping list and swap dialog.

`design-system.spec.ts` changes in both phases, because the kitchen sink demos both. Its section shots are refreshed per phase and reviewed for knock-on anti-aliasing noise. The roadmap asks parallel slices to refresh the kitchen-sink baseline once more at the very end of M-4, when S-23…S-26 merge.

New list components go into `resources/js/Components/MealPlans/`. Removed components are deleted, not left orphaned. All styling uses tokens and existing variants. The only primitive change is the `Badge tag` variant, which gets its kitchen-sink demo.

## Critical Implementation Details

- **Selectors the functional E2E depends on:**
  - The grid must keep `<table data-testid="meal-plan-grid">` with `thead` column headers named exactly Śniadanie / Obiad / Kolacja (icons `aria-hidden`), a corner header and a `th scope="row"` per day.
  - Each `<td>` must sit in the order breakfast, supper, dinner.
  - The recipe name must be the only `<a>` in a filled cell. Tags and pills are not links.
  - In `RecipeSwapDialog` the recipe and link-option names must stay in a `span` with the `font-medium` class (`meal-plan.spec.ts:41, :54`). Other classes may be added to it, but `font-medium` must not be replaced by `font-semibold`.
  - `data-testid="shopping-list"` exists only on the `<ul>` of the filled list. The draft's `ShoppingList pending` placeholder must not carry it, neither on its root nor on a shared wrapper, because `meal-plan.spec.ts:32, :146` assert it is hidden on a draft.
- **One pressed status pill:** pressing the already-pressed pill must not unpress it. Ignore the `false` update or re-press it, so the group never shows zero pressed. „Wszystkie” maps to `''`, which removes `filter[status]` from the query, exactly as `StatusFilter` did.

## Phase 1: Jadłospisy jako kafle

### Overview

Replace the table list with week tiles, a status pill filter and a sort pill per ekran 10. Restyle the empty states, give `ActionDropdown` the round icon trigger, update the kitchen sink and the list's visual spec, and refresh its baselines once.

### Changes Required:

#### 1. Week tile

**File**: `resources/js/Components/MealPlans/MealPlanTile.vue` (new)

**Intent**: One `Card` per plan, as in ekran 10. The fill bar visualises `meals_count` without a backend change. The two links have distinct accessible names, so a screen reader can tell „Otwórz” apart per week.

**Contract**:
- Prop: `item` (the existing Index item type). The root is a `Card` rendered inside the parent `<li>`.
- Content:
  - The date `Link` to `item.links.self` with `formatDateRange` in `text-section-title`, hover underline and the §10.4 focus outline.
  - The meta „Utworzono {formatDateTime(created_at)}” in `text-meta text-muted-foreground`.
  - `StatusTag`.
  - The fill bar: 7 `aria-hidden` squares (`size-5.5 rounded-sm`). Square *i* < `floor(meals_count / 3)` gets `bg-meal-{breakfast|supper|dinner}[i % 3]`, the rest `bg-muted`. Next to it, a `text-meta tabular-nums` label „{meals_count}/21” with an sr-only prefix „Posiłki: ”.
  - Actions, right-aligned:
    - `PdfDownloadMenu` (only when `links.pdf && links.pdf_with_recipes`) with label „PDF”, size `sm` and `aria-label` „Pobierz PDF: {range}”;
    - `Button size="sm" as-child` → `Link` „Otwórz” with `aria-label` „Otwórz jadłospis: {range}”;
    - `ActionDropdown`.
- Layout: below `lg`, stacked (date block, then status plus bar, then actions). From `lg` up, one row with the date block flexible and fixed spacing-scale widths for the status, bar and actions columns.
- 21 is derived as `MEAL_CATEGORIES.length * 7`, not as a magic number.

#### 2. Status filter pills

**File**: `resources/js/Components/MealPlans/StatusFilterPills.vue` (new)

**Intent**: Replaces `StatusFilter`'s `Select` with the F-06 filter pill (carry-over F-06), without counts, per ekran 10.

**Contract**:
- Props `modelValue: string` (`''` = all) and `statuses: {value, label}[]`. Emits `update:modelValue`.
- Renders `<div role="group" aria-label="Status">` with a `Toggle` „Wszystkie” followed by one `Toggle` per status, all `variant="default"`.
- Exactly one pill is pressed. See Critical Implementation Details.

#### 3. Sort pill

**File**: `resources/js/Components/MealPlans/SortMenu.vue` (new)

**Intent**: Replaces the `SortableHeader` columns with ekran 10's „Najnowsze” pill.

**Contract**:
- Props `sort: string` and `direction: 'asc'|'desc'`. Emits `sort-change({column, direction})`, the same payload `MealPlanTable` emitted.
- Trigger: `Button variant="outline"` with `ArrowUpDown`. Its label is the current option's label, or „Sortuj” when the URL holds a sort outside the four options. `aria-label` is „Sortuj: {label}”.
- Content: `DropdownMenuRadioGroup` with 4 options:

  | Label | Column | Direction |
  | --- | --- | --- |
  | Najnowsze | `created_at` | `desc` |
  | Najstarsze | `created_at` | `asc` |
  | Tydzień: od najpóźniejszego | `start_date` | `desc` |
  | Tydzień: od najwcześniejszego | `start_date` | `asc` |

#### 4. List page

**File**: `resources/js/Pages/MealPlans/Index.vue`

**Intent**: Compose the ekran 10 layout and keep the URL contract.

**Contract**:
- Order on the page:
  1. `PageHeader` (unchanged).
  2. Toolbar `flex flex-wrap items-center justify-between gap-3` with `StatusFilterPills` and `SortMenu`.
  3. If there are items, `<ul aria-label="Jadłospisy" class="flex flex-col gap-3.5">` with one `<li>` per `MealPlanTile`. Otherwise a `Card` with `EmptyState class="py-12"`, using the same two copies and CTAs as `MealPlanTable` today (filtered → „Wyczyść filtry”; none → `GenerateMealPlanDialog label="Wygeneruj pierwszy jadłospis"`).
  4. `ServerPagination`.
- `buildQuery`, `handleFiltersChange`, `handleSortChange`, `handlePageChange` and `handleClearFilters` keep their URL semantics. `perPage` is still read from and written back to the URL, only no longer editable.
- The `rounded-lg border` wrapper (:18) is gone.

#### 5. Removed list components

**File**: `resources/js/Components/MealPlans/{MealPlanTable,MealPlanRow,FiltersBar,StatusFilter,PerPageSelect}.vue` (delete)

**Intent**: They are only used by `Index.vue`. Deleting them avoids dead Zielnik code.

**Contract**: No remaining imports. `SortableHeader` and `ServerPagination` in `Common/` stay, because recipes use them.

#### 6. Action and PDF triggers

**Files**: `resources/js/Components/MealPlans/ActionDropdown.vue`, `resources/js/Components/MealPlans/PdfDownloadMenu.vue`

**Intent**: Ekran 10 draws „…” as the round muted icon button, and the tile needs a compact PDF trigger.

**Contract**:
- `ActionDropdown` trigger: `variant="secondary" size="icon"` with the `Ellipsis` icon. The `aria-label` „Akcje: {range}” and the menu items are unchanged.
- `PdfDownloadMenu` gains optional props `label` (default „Pobierz PDF”), `size` (default `default`) and `ariaLabel`. With no props, the Show page and the Dashboard (`Pages/Dashboard.vue:60-63`) render exactly as today, so the E2E name „Pobierz PDF” holds and `app-shell.spec.ts`'s `dashboard-desktop.png` / `dashboard-mobile.png` must not change.

#### 7. Kitchen sink

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: Demo the new list pieces in their states and fix the swapped pill labels.

**Contract**:
- `chipTones` (:160-165): `supper` → „Obiad”, `dinner` → „Kolacja”.
- „Komponenty aplikacji”: replace the table-based Index demos (:853-880) with:
  - the toolbar (`StatusFilterPills` with one status pressed, and `SortMenu`);
  - five static `MealPlanTile`s covering draft 20/21, processing 21/21, done 21/21 with PDF, error 0/21 and pending;
  - both empty states in their tile.
- No new section, so the section count stays 14.

#### 8. Visual spec and baselines

**Files**: `tests/e2e/visual/meal-plans-index.spec.ts`, `tests/e2e/__screenshots__/visual/meal-plans-index.spec.ts/*.png`, `tests/e2e/__screenshots__/visual/design-system.spec.ts/*.png`

**Intent**: Retarget the spec from table and `Select` to tiles and pills, then refresh the baselines once.

**Contract**:
- `getByRole('row')` = 6 becomes `getByRole('list', { name: 'Jadłospisy' }).getByRole('listitem')` = 5, and the „Generowanie” check targets that list.
- The filter step clicks `getByRole('button', { name: 'Oczekuje' })`. After the reload it asserts that pill has `aria-pressed="true"`.
- „Wyczyść filtry” brings back 5 tiles.
- Expected changed baselines:
  - All 4 `meal-plans-index-*` PNGs. The generate dialog itself is unchanged; its shot changes because the page under the overlay changed.
  - In `design-system.spec.ts`: `section-komponenty-aplikacji.png`, `section-przyciski.png` and `focus-visible.png` (the chip labels; `focus-visible` screenshots the whole „Przyciski” section, `design-system.spec.ts:63-72`), plus anti-aliasing-only noise on sections below a section whose height changed. `chip-focus-visible.png` covers only „Wszystkie” and must not change.
- Any other changed PNG is a regression to fix, not to accept.

#### 9. Docs

**File**: `context/foundation/design-system.md`

**Intent**: Keep §5 and §7 true to the code.

**Contract**:
- §5.2/§5.3: add `MealPlanTile` (fill-bar rule `floor(meals_count/3)` plus the „n/21” label, actions), `StatusFilterPills` (one pressed, no counts) and `SortMenu` (4 options). Note that `MealPlanTable`, `MealPlanRow`, `FiltersBar` and „Na stronie” are gone.
- §7: describe the 4 list shots on five seeded plans (2 done, 1 draft, 1 processing, 1 error), replacing „czterech”. The total stays 58.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token lint passes and its self-test passes: `vendor/bin/sail npm run lint:tokens` and `bash scripts/lint-tokens.test.sh`
- Removed list components are gone: `grep -rnw -e MealPlanTable -e MealPlanRow -e FiltersBar -e StatusFilter -e PerPageSelect resources/js tests/e2e` returns nothing
- Pest suite passes unchanged: `vendor/bin/sail composer test`
- Full E2E suite passes after re-seeding: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder` then `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- Visual gate is green after a deliberate refresh (`npm run test:visual -- --update-snapshots`, then `npm run test:visual`), with only the PNGs listed in change 8 changed

#### Manual Verification:

- `/meal-plans` matches ekran 10 at 1280 px (tiles, pills, sort pill, fill bar „20/21” on the draft) and stays usable at 1024 and 390 px (stacked tile, wrapped toolbar, no horizontal page scroll)
- Status pills: Tab reaches each pill with a visible 2px ring, Space toggles it, exactly one pill is pressed at any time, and the URL `filter[status]` follows the choice; „Wszystkie” clears it
- Sort pill: opens with Enter, arrow keys move between options, choosing one updates `sort` and `direction` in the URL, and the trigger label follows; `perPage` in the URL survives filtering, sorting and paging
- Tile actions: „Otwórz” and the date link open the plan, „PDF” offers both PDFs on done plans only, „…” keeps all its items, and the delete confirm still works
- Every changed baseline PNG reviewed in the diff, with anti-aliasing-only shots confirmed as noise

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Siatka i strona jadłospisu

### Overview

Restyle the meal plan page per ekrany 11–12: a date `<h1>` with breadcrumb, a filled-cell grid, linked-meal tags, „Zmień” / „Wybierz” pills, the dashed empty slot, sticky days on mobile, the shopping list tile and the draft placeholder. Highlight the current option in the swap dialog per ekran 15, add the `Badge tag` variant, update the kitchen sink, docs and the show spec, and refresh its baselines once.

### Changes Required:

#### 1. Badge `tag` variant

**File**: `resources/js/Components/ui/badge/index.js`

**Intent**: The mockup's linked-meal tag is a small `card` pill with ink text and no outline. It must read on all three meal fills.

**Contract**: Add `variant: "tag"` = `border-transparent bg-card text-foreground`, used with the default size. Other variants are unchanged.

#### 2. Week grid

**File**: `resources/js/Components/MealPlans/MealPlanGrid.vue`

**Intent**: Ekran 11–12 cells, coloured by meal. This replaces the F-05 stripe and the `text-primary` toggle (carry-overs F-05 / F-06), with table semantics and E2E selectors intact.

**Contract**:
- Props, emits and `data-testid` are unchanged.
- Wrapper: a `Card` (`p-4`, `overflow-x-auto`) holding `<table class="w-full min-w-160 border-separate border-spacing-x-0 border-spacing-y-2">`. The horizontal gap lives inside the cells (`pr-2` on the day `th`, `pl-2` on each meal `th`/`td`) rather than in `border-spacing`, so the sticky day column's opaque `bg-card` keeps an 8px gutter while the cells scroll under it.
- Header row:
  - The corner `th` keeps the text „Dzień” as `sr-only`.
  - Each meal `th` is `text-meta font-bold` with no fill and an `aria-hidden` icon: `Sun` / `Soup` / `Moon`.
- Day `th scope="row"`: `sticky left-0 z-10 bg-card pr-2`, the weekday in bold (`font-extrabold`, capitalised) and the `dd.mm` in `text-meta text-muted-foreground` under it.
- Filled cell: a block `rounded-md p-2.5 min-h-18 bg-meal-{c} text-meal-{c}-foreground` (a class map replaces `categoryBarClass`). In order:
  1. `Badge variant="tag"` with `cookedLabel` / `carriedLabel` (same conditions as :41-42).
  2. The recipe `Link` (`font-semibold`, hover underline, §10.4 focus outline), or the name with „(usunięty)” for a deleted recipe.
  3. Unless carried, the meta line „{kcal} kcal (cały przepis) · {servings} {pluralize(servings, porcja/porcje/porcji)}” in `text-xs font-medium tabular-nums opacity-85`.
  4. In a draft, `Button variant="outline" size="xs"` with `RefreshCw` „Zmień” and the same `aria-label`.
  5. Unless carried, the „Składniki (n)” collapsible, its trigger `font-semibold underline underline-offset-2` in the cell's foreground colour and the ingredient list inheriting it. `data-testid="meal-ingredients"` stays.
- Empty slot: `rounded-md p-2.5 min-h-18 border-2 border-dashed border-muted-foreground bg-card text-muted-foreground` with „Bez posiłku” and, in a draft, `Button variant="outline" size="xs"` with `Plus` „Wybierz” and the same `aria-label`.
- Skeleton: the same table, with each cell a `Skeleton class="h-18 rounded-md"` (`motion-reduce:animate-none`).
- No `border-l-4`, `border-meal-*` or `hover:text-primary` left in the file.

#### 3. Shopping list

**File**: `resources/js/Components/MealPlans/ShoppingList.vue`

**Intent**: Ekran 11's tile with dotted rows, plus ekran 12's draft placeholder.

**Contract**:
- New optional prop `pending: boolean`. When `list` is absent and `pending` is true, render a placeholder: `rounded-lg border-2 border-dashed border-muted-foreground p-5`, no shadow, an `h2` „Lista zakupów” with `ShoppingBasket` in `text-section-title`, and the muted text „Pojawi się po zatwierdzeniu — wtedy przeliczymy ilości na Twoją rodzinę.”
- Otherwise the `Card`:
  - `CardTitle as="h2"` uses `text-section-title` with no size override.
  - Rows use `border-b-2 border-dotted border-border last:border-b-0`, replacing `divide-y`.
  - The amount is `font-bold tabular-nums`.
  - `data-testid="shopping-list"`, the empty text and the footnote are unchanged.

#### 4. Meal plan page

**File**: `resources/js/Pages/MealPlans/Show.vue`

**Intent**: Ekran 11–12 header and layout. These are the user's decisions: the date as h1 with a breadcrumb, and no bottom back button.

**Contract**:
- `PageHeader :title="formatDateRange(start_date, end_date)"` with no description. The `breadcrumbs` slot holds an `ArrowLeft` link „Jadłospisy” to `meal-plans.index` (`text-label text-muted-foreground`, hover `text-foreground`, §10.4 focus outline).
- `<Head>` keeps today's `Jadłospis dd.mm.yyyy – dd.mm.yyyy` title, so the existing `dateRange` stays for `<Head>` only.
- Action order:
  - draft: `StatusTag`, outline „Losuj ponownie”, „Zatwierdź”, `ActionDropdown`;
  - done: `StatusTag`, `variant="ghost"` „Wróć do szkicu”, `PdfDownloadMenu`, `ActionDropdown`.
- The no-meals block (:56) becomes a `Card` with `EmptyState class="py-12"`.
- The grid/list row becomes `xl:flex-row` with the aside in `xl:w-75 xl:shrink-0`. Done shows `ShoppingList :list`. A draft with meals shows `ShoppingList pending`.
- The bottom „Wróć do jadłospisów” block (:122-129) is removed.

#### 5. Swap dialog options

**File**: `resources/js/Components/MealPlans/RecipeSwapDialog.vue`

**Intent**: Ekran 15. The current option sits on the slot's category colour, and options read as rounded 12px rows.

**Contract**:
- Each `ComboboxItem` is `rounded-md px-3.5 py-2.5`.
- The option marked „obecny” gets `bg-meal-{meal.meal_category} text-meal-{c}-foreground`, via a class map rather than string interpolation, so Tailwind sees full class names. Its secondary lines use `opacity-85` instead of `text-muted-foreground`. When highlighted it keeps the meal colour and uses `brightness-95` (§10.4 tone hover): the class map entry also carries `data-highlighted:bg-meal-{c} data-highlighted:text-meal-{c}-foreground data-highlighted:brightness-95`, because `ui/combobox/ComboboxItem.vue:25` sets `data-highlighted:bg-accent data-highlighted:text-accent-foreground`, which would otherwise replace the fill (tailwind-merge drops the accent pair, since the variant and group match).
- „Nie robimy (pusty posiłek)” is italic and muted when it is not the current option.
- Names stay in `span.font-medium` (see Critical Implementation Details). Search, order, warnings and the PATCH are unchanged.

#### 6. Kitchen sink

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: Demo the new grid states, the tag variant and the placeholder.

**Contract**:
- „Odznaki”: add `Badge variant="tag"` on each of the three `bg-meal-*` swatches.
- „Jadłospis”:
  - The existing read-only, editable and skeleton grids, whose demo data already include both link pairs (:245-248). Make sure one slot is empty, so the dashed frame shows in both modes.
  - `ShoppingList pending` next to the filled and empty lists.
- No new section.

#### 7. Visual spec and baselines

**Files**: `tests/e2e/visual/meal-plan-show.spec.ts`, `tests/e2e/__screenshots__/visual/meal-plan-show.spec.ts/*.png`, `tests/e2e/__screenshots__/visual/design-system.spec.ts/*.png`

**Intent**: Point the h1 assertions at the date heading, then refresh the baselines once.

**Contract**:
- :30 and :70 expect `heading` level 1 named „15–21 czerwca 2026” (done) and „29 czerwca – 5 lipca 2026” (draft). The `dd.mm.yyyy – dd.mm.yyyy` text check is dropped, because that text is now only in `<title>`. The `tbody td a` counts are unchanged.
- Expected changed baselines:
  - All 5 `meal-plan-*` PNGs.
  - In `design-system.spec.ts`: `section-odznaki.png` and `section-jadlospis.png`, plus anti-aliasing-only noise below changed sections. `overlay-pdf-download-menu.png` must not change.
- Any other changed PNG is a regression.

#### 8. Docs and roadmap evidence

**File**: `context/foundation/design-system.md`

**Intent**: §5 describes the new grid. The old stripe contract is superseded.

**Contract**:
- §5.1 `badge`: add the `tag` variant (linked-meal tags on meal fills).
- §5.3 `MealPlanGrid`: filled `meal-*` cells with `meal-*-foreground` text, `rounded-md`, the dashed `muted-foreground` empty frame with the „Wybierz” pill, tags above the name, a sticky day column with horizontal scroll on mobile, and the skeleton. This replaces the `border-l-4` sentence.
- §5.3 `ShoppingList`: dotted rows, `xl:w-75`, and the `pending` placeholder in a draft.
- §7: the show spec asserts the date `<h1>`.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token lint passes and its self-test passes: `vendor/bin/sail npm run lint:tokens` and `bash scripts/lint-tokens.test.sh`
- Stripe, primary-hover and bottom back button are gone: `grep -rn "border-l-4\|categoryBarClass\|hover:text-primary\|Wróć do jadłospisów" resources/js/Components/MealPlans resources/js/Pages/MealPlans` returns nothing
- Functional E2E spec untouched: `git diff --exit-code develop -- tests/e2e/meal-plan.spec.ts` exits 0
- Pest suite passes unchanged: `vendor/bin/sail composer test`
- Full E2E suite passes after re-seeding: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder` then `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- Visual gate is green after a deliberate refresh (`npm run test:visual -- --update-snapshots`, then `npm run test:visual`), with only the PNGs listed in change 7 changed

#### Manual Verification:

- Done plan (06-15) matches ekran 11 at 1280 px: date h1, breadcrumb, filled cells, tags „Na 2 dni” / „Z wczoraj” / „Też na śniadanie” / „Z kolacji” readable on all three fills, shopping list tile with dotted rows beside the grid
- Draft (06-29) matches ekran 12: „Zmień” in every filled cell, the empty Kolacja as a dashed frame with „Wybierz”, the info alert, the dashed shopping placeholder
- At 390 px the grid scrolls sideways inside its tile with the day column pinned, and the page itself does not scroll horizontally; at 1024 px the shopping list sits under the grid
- Keyboard: recipe links, „Zmień”, „Wybierz” and „Składniki” show the 2px ring on every meal fill; „Składniki” expands and collapses
- Swap dialog: the current option is on the slot's colour for each of breakfast, supper and dinner; arrow-key highlight stays visible on it; choosing an option still applies immediately
- Generating plan shows the new skeleton; error and no-meals states render in Spiżarnia style
- Every changed baseline PNG reviewed in the diff, with anti-aliasing-only shots confirmed as noise

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding.

---

## Testing Strategy

### Unit Tests:

- None added. There is no backend or logic change. The Pest suite (`tests/Feature/MealPlanControllerTest.php` checks Inertia props, not markup) must pass unchanged, which proves the props contract held.

### Integration Tests:

- `tests/e2e/meal-plan.spec.ts` runs unchanged and covers swap, empty slot, repeated dinner, dinner→breakfast, approve, return to draft and PDF links against the new markup.
- `tests/e2e/visual/meal-plans-index.spec.ts` covers the list desktop/mobile, the filtered empty state via the „Oczekuje” pill, and the generate dialog.
- `tests/e2e/visual/meal-plan-show.spec.ts` covers done and draft, desktop and mobile, and the swap dialog.
- `tests/e2e/visual/design-system.spec.ts` covers the kitchen-sink demos of tiles, pills, sort, grid states, tag badges and the shopping placeholder.

### Manual Testing Steps:

1. Seed `E2eMealPlanSeeder`, log in as `e2e-planner-visual@example.com` and open `/meal-plans` at 1280, 1024 and 390 px.
2. Click through every status pill and every sort option. Check the URL and that `perPage=5` in the URL survives them.
3. Open the draft 06-29. Swap a dinner, pick a recipe for the empty slot, approve, and wait for done.
4. On the done plan, expand „Składniki”, download both PDFs and return to draft.
5. Tab through the grid on each meal colour and check the ring.

## Performance Considerations

None. The changes are presentational. The fill bar is computed client-side from an existing integer.

## Migration Notes

None. There are no data or route changes. Bookmarked URLs with `perPage`, `sort=status` or `sort=end_date` keep working; the sort pill then reads „Sortuj”.

## References

- Roadmap item: `context/foundation/roadmap.md` S-25 (carry-overs F-05, F-06; unknown about the mobile grid and link markers)
- Design: `context/foundation/design-system.md` §5.1–5.3, §7, §10.1–10.5; artifact screens 10, 11, 12, 15 (https://claude.ai/artifact/Py7fzUajDk1Ny57FG7oZMx)
- Prior plans: `context/archive/2026-11-17-spizarnia-app-shell/plan.md` (baseline-per-phase pattern, AA-noise addendum), `context/archive/2026-11-17-spizarnia-primitives/reviews/impl-review.md` (tailwind-merge roles, focus-ring clip), `context/archive/2026-11-16-meal-plan-empty-slot/plan.md`, `context/archive/2026-11-16-meal-plan-repeat-dinner/plan.md`, `context/archive/2026-11-17-dinner-to-breakfast-carryover/plan.md` (link labels and cell rules)
- Code: `resources/js/Pages/MealPlans/{Index,Show}.vue`, `resources/js/Components/MealPlans/*`, `resources/js/Pages/Dev/DesignSystem.vue`, `tests/e2e/meal-plan.spec.ts`, `tests/e2e/visual/{meal-plans-index,meal-plan-show,design-system}.spec.ts`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Jadłospisy jako kafle

#### Automated

- [x] 1.1 Client and SSR bundles build — e31400d
- [x] 1.2 Token lint passes and its self-test passes — e31400d
- [x] 1.3 Removed list components are gone — e31400d
- [x] 1.4 Pest suite passes unchanged — e31400d
- [x] 1.5 Full E2E suite passes after re-seeding — e31400d
- [x] 1.6 Visual gate is green after a deliberate refresh, with only the listed PNGs changed — e31400d

#### Manual

- [ ] 1.7 `/meal-plans` matches ekran 10 at 1280 px and stays usable at 1024 and 390 px
- [ ] 1.8 Status pills: keyboard, single pressed pill, URL follows
- [ ] 1.9 Sort pill: keyboard, URL and label follow, `perPage` survives
- [ ] 1.10 Tile actions: Otwórz, PDF (done only), „…” items and delete still work
- [ ] 1.11 Every changed baseline PNG reviewed

### Phase 2: Siatka i strona jadłospisu

#### Automated

- [x] 2.1 Client and SSR bundles build
- [x] 2.2 Token lint passes and its self-test passes
- [x] 2.3 Stripe, primary-hover and bottom back button are gone
- [x] 2.4 Functional E2E spec untouched
- [x] 2.5 Pest suite passes unchanged
- [x] 2.6 Full E2E suite passes after re-seeding
- [x] 2.7 Visual gate is green after a deliberate refresh, with only the listed PNGs changed

#### Manual

- [ ] 2.8 Done plan matches ekran 11 at 1280 px
- [ ] 2.9 Draft matches ekran 12
- [ ] 2.10 Mobile grid scrolls inside its tile with the day column pinned; 1024 px stacks the shopping list
- [ ] 2.11 Keyboard focus ring visible on every meal fill; „Składniki” toggles
- [ ] 2.12 Swap dialog current option on the slot colour, highlight visible, immediate apply
- [ ] 2.13 Generating, error and no-meals states render in Spiżarnia style
- [ ] 2.14 Every changed baseline PNG reviewed
