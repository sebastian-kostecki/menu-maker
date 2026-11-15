# Meal Plans Index UI Implementation Plan

## Overview

S-10 in roadmap milestone M-2 (DS-05 in `context/foundation/design-system.md` §8). The meal plans list (`resources/js/Pages/MealPlans/Index.vue`) moves off grey palette literals, `dark:` classes and English copy, onto the design tokens and the `ui/` primitives. The list's components move with it: `FiltersBar`, `StatusFilter`, `PerPageSelect`, `MealPlanTable`, `MealPlanRow`, `ActionDropdown` and `GenerateMealPlanDialog`.

S-10 is the first view that needs a page header and an empty state, so it builds `Common/PageHeader` and `Common/EmptyState` (deferred by S-07). It also finishes the filter cleanup carried over from S-07: filters, sort and page size come only from the URL, and the localStorage restore goes away, because today it makes the status select disagree with the list. The change ends with fixture plans for the visual user and a real-page visual spec of the list.

## Current State Analysis

**`Pages/MealPlans/Index.vue` (236 lines)**
- The header lives in the layout's `#header` slot (`:5-22`). That slot renders inside the sticky top bar, next to the user menu (`Layouts/AuthenticatedLayout.vue:70-71`).
- The header text has a hand-made Polish plural (`:14`). It is wrong for 22–24, 32–34 and so on.
- The body is `bg-white shadow-xs dark:bg-gray-800` with `max-w-7xl` (`:24-25`). §4.6 prescribes `max-w-6xl` and borders without shadows.
- Filter state is `localFilters`:
  - It is seeded from `props.filters` (`:134-139`), then overwritten from localStorage key `mm.mealPlans.filters` with no request (`:206-235`). The status select can therefore show "Gotowy" while the list is unfiltered (S-07 `reviews/impl-review.md` F2, left for S-10).
  - Sort and filter changes build their query from `localFilters`, so a stale stored status leaks into the next request.
  - Page changes already build their query from `props.filters` (S-07 Fix A, `:174-196`).

**Filter and table components**
- `FiltersBar.vue` is a grey box (`bg-gray-50`). It has an English "Per page:" label and an English "Showing N results"/"No results found" counter (`:2-38`), which duplicates the count in the header.
- `MealPlanTable.vue`:
  - Arbitrary widths `w-[200px]`/`w-[120px]`/`w-[100px]`.
  - English headers "Date Range / Status / Meals / Logs / Created / Actions".
  - An English empty row "No meal plans found" (`:4-55`).
- `MealPlanRow.vue`:
  - `en-GB` dates and an English duration "7 days" (`:81-113`).
  - `gray-*`/`dark:` text and an internal `logs_count` column (`:28-36`).
  - The plan is reachable only through the ⋯ menu.
- `ActionDropdown.vue` styles "Usuń" with `text-red-600 focus:bg-red-50` (`:40`).
  - Its icons carry `mr-2 h-4 w-4`, although `DropdownMenuItem` already applies `gap-2 [&>svg]:size-4`.
  - It is also used by `MealPlans/Show.vue:114`.
- `GenerateMealPlanDialog.vue`:
  - Uses `text-red-600` for field errors and a `border-red-200 bg-red-50 text-red-700` box for missing recipes (`:23-37`).
  - Its request (`form.post(route('meal-plans.store'))` with `start_date`) is guarded by `tests/e2e/meal-plan.spec.ts:12-17`.
  - It is also used by `Dashboard.vue:85-90` with its own slot trigger.

**Backend contract** (unchanged by this plan)
- `MealPlanController::index` (`app/Http/Controllers/MealPlanController.php:38-77`) echoes only the query keys present, as raw strings: `filters.filter.status`, `sort`, `direction`, `perPage` (`:69`).
- It clamps `perPage` to 5..100 (`:58`).
- It filters by any `filter[status]` value with no allowlist (`:44-46`), so an unknown status returns an empty list.
- `statuses` is a hardcoded Polish list (`:70-75`).
- `logs_count` counts generation attempts (`GenerateMealPlanJob.php:145-163`).

**Tooling**
- `resources/js/lib/` has no date, number or plural helper, although §4.5 asks for one.
- `playwright.config.ts:13` runs every project with `locale: 'en-US'`, so locale-default formatting renders in English.
- `E2eMealPlanSeeder` deletes every planner's plans (`:59`), so the visual user has none.
- `meal-plan.spec.ts:13` clicks `getByRole('button', { name: 'Wygeneruj jadłospis' })`. It is a substring match without `exact`, run on an empty list.

## Desired End State

**`/meal-plans` layout**
- The page renders `Common/PageHeader` at the top of `<main>`. It has the `h1` "Jadłospisy", a description with a correct Polish count ("1 jadłospis", "3 jadłospisy", "5 jadłospisów", "22 jadłospisy") and the "Wygeneruj jadłospis" button in its actions.
- The layout's top bar shows "Menu Maker" as plain text, not a heading, so the page has exactly one `h1`.
- Below the header is a token toolbar with two labelled Selects ("Status" and "Na stronie"), then a bordered `bg-card` table, then `ServerPagination`.

**Filter state**
- Filter, sort and page size are read from `props.filters` alone.
- Changing any of them issues `router.get` with the full current query, minus `page`.
- Reloading or sharing the URL shows the same list with the same Select values.
- Nothing is read from or written to localStorage.

**Table**
- Columns are Tydzień, Status, Posiłki, Utworzono and Akcje.
- Tydzień is a link to the plan, e.g. "15–21 czerwca 2026" formatted in `pl-PL`.
- Posiłki and Utworzono are hidden below `sm`.
- Logs no longer appears.
- "Usuń" in the row menu uses the new `variant="destructive"` of `DropdownMenuItem`.

**Empty states**
- With no plans and no filter, the table body shows `EmptyState` with the CTA "Wygeneruj pierwszy jadłospis", which opens the same dialog.
- With a filter (or a page beyond range) and no rows, it shows "Brak jadłospisów o wybranym statusie" with "Wyczyść filtry", which returns to the bare `/meal-plans`.

**Generate dialog**
- It uses `Alert`/`text-destructive` instead of red literals.
- It sends exactly the same request.

**Gates**
- All migrated files are listed in `scripts/lint-tokens.paths`, and `npm run lint:tokens` is green.
- The kitchen sink demos `PageHeader`, `EmptyState` and the destructive menu item.
- `tests/e2e/visual/meal-plans-index.spec.ts` guards the list (desktop and mobile), the filtered empty state and the open dialog against seeded fixture plans.
- The existing E2E suite passes unchanged.

### Key Discoveries:

- **Shared files outside this view.**
  - `ActionDropdown` is shared with `MealPlans/Show.vue:114`.
  - `GenerateMealPlanDialog` is shared with `Dashboard.vue:85-90` (slot trigger).
  - Restyling either one is visible there too. That is intended, because they are the same components, and neither page's baselines open them.
- **Two candidate hosts for the empty state.**
  - `ui/table` exports `TableEmpty` (a `colspan` prop, centred `py-10` slot), which can host `EmptyState` inside the table body.
  - `ServerPagination` hides itself when `last_page <= 1` (`Common/ServerPagination.vue:3`).
- **Destructive menu item.** `ui/dropdown-menu/DropdownMenuItem.vue` has no `variant`. The new-york-v4 upstream item has `variant: 'default' | 'destructive'`, styled with `data-[variant=destructive]:` token classes. This is the precedent to port, per §3 rule 3 (variant instead of override).
- **Seeding fixture meals.** `MealPlanGenerator::generate()` (`app/Services/MealPlanGenerator.php:65-104`) draws 21 meals from the user's recipes without AI. The seeder already tops up the recipes, so a fixture "done" plan can have a real `meals_count` of 21.
- **Pinning timestamps.** `MealPlan` has no fillable `created_at` (`app/Models/MealPlan.php:22-28`), so fixed timestamps need `forceFill`/`saveQuietly`, or `timestamps = false` on the instance.
- **Kitchen sink.**
  - `design-system.spec.ts` screenshots sections by region name (`:9-33`).
  - Demos added to "Komponenty aplikacji" change `section-komponenty-aplikacji.png`.
  - Adding kitchen-sink content changed the corners of `overlay-dialog.png`/`overlay-alert-dialog.png` in S-07 and S-08 (accepted deviations), so expect the same.
  - A destructive item in the "Nakładki" menu demo changes `overlay-dropdown-menu.png`.
- **The fallback `h1`.** Only `MealPlans/Index` stops using `#header`. Every other authenticated page still fills the slot, so demoting the layout's fallback `<h1>Menu Maker</h1>` affects this page alone.

## What We're NOT Doing

- No backend change to the index:
  - no Form Request;
  - no status allowlist;
  - no `MealPlanStatus` enum;
  - `logs_count` stays in `MealPlanCollection`. It only leaves the UI.
- No restyle of `MealPlans/Show.vue`, `MealPlanGrid` or `ShoppingList` (S-09). They only pick up the shared `ActionDropdown` and `StatusTag` as they are.
- No fix to the same broken plural in `Pages/Recipes/Index.vue:14` (S-11). It can adopt `lib/format.js` later.
- No migration or removal of the stale `mm.mealPlans.filters` key in users' browsers. It is simply no longer read.
- No toggle-group or segmented status filter, and no new `ui/` primitive via CLI.
- No change to `ServerPagination`, `SortableHeader`, `ConfirmDialog` or `StatusTag` contracts.
- No row polling for `processing` plans on the index.
- No change to `meal-plan.spec.ts` selectors.
- No dark-mode work (D-1).

## Implementation Approach

The build follows the /10x-ui order: contract first, then pixels.

1. **Phase 1** adds the reusable pieces and their kitchen-sink demos: the formatting helper, `PageHeader`, `EmptyState`, the destructive menu variant and the layout's non-heading fallback. Phases 2 and 3 only compose them.
2. **Phase 2** rewires the page and its filter state, which is the only behavioural change.
3. **Phase 3** restyles the table, row actions and dialog without touching any request.
4. **Phase 4** adds fixture data and the visual spec, and updates every baseline in one deliberate step. Phases 1–3 will make the kitchen-sink visual run fail until then; that is expected and is not a regression signal.

## Critical Implementation Details

**`lib/format.js` must pass `'pl-PL'` explicitly**
- Playwright runs `en-US`, and a user's browser may too.
- `start_date`/`end_date` arrive as `Y-m-d` strings. Parse them from their parts as local dates, not with `new Date('YYYY-MM-DD')`, which is UTC midnight and shifts the day west of UTC. This is the same reasoning as `nearestUpcomingMonday()` in `GenerateMealPlanDialog.vue:81-89`.
- `created_at` is a full ISO timestamp and formats normally.

**Filter state is derived, never stored**
- The echoed `props.filters` values are raw query strings, so normalise them:
  - `perPage` → `Number`, falling back to 15;
  - `direction` → `asc` only when equal to `'asc'`;
  - `sort` falls back to `created_at`.
- An unknown `filter[status]` still counts as "a filter is active" for the empty-state choice, so the user gets "Wyczyść filtry" instead of a misleading first-run CTA.

**E2E strict mode**
- The empty-state CTA must not contain the substring "Wygeneruj jadłospis". "Wygeneruj pierwszy jadłospis" is safe.
- The header button stays the only button whose name contains it, which keeps `meal-plan.spec.ts:13` valid on the empty list.

**Visual determinism**
- Fixture plans need fixed `start_date`/`end_date`, `created_at` and `updated_at`, consistent with the spec's fixed clock (`2026-06-15T10:00:00`).
- Under that clock, the dialog's default date is Monday 2026-06-15.
- The `processing` fixture renders the `StatusTag` spinner. `toHaveScreenshot` disables CSS animations by default, but assert the row is visible before the shot.

## Environment (prerequisite for every phase)

This worktree (`.claude/worktrees/meal-plans-index-u`) starts without `vendor/`, `node_modules/` or `.env`. Every `sail` command, E2E run and visual run must exercise this checkout's code:

- Copy `.env` from the main checkout. Give it its own `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT` and `FORWARD_MAILPIT_DASHBOARD_PORT`, and set `MAILPIT_URL` to match. Keep `AI_SCALING_DRIVER=fake`, `MAIL_MAILER=smtp` and `MAIL_HOST=mailpit`.
- Install `vendor/` with the Sail Composer container (not host PHP). Then run `vendor/bin/sail up -d`, `vendor/bin/sail npm ci` and `vendor/bin/sail artisan migrate`.
- Run the visual gate against this stack with `VISUAL_DOCKER_NETWORK=meal-plans-index-u_sail VISUAL_BASE_URL=http://laravel.test npm run test:visual`.
- Before Phase 1, `vendor/bin/sail ps` lists `meal-plans-index-u-*` containers, and `curl -s -o /dev/null -w '%{http_code}' http://localhost:${APP_PORT}/login` prints `200`.

---

## Phase 1: Shared building blocks

### Overview

Add the formatting helper, the two deferred Common components and the destructive menu-item variant, and make the layout's fallback title a non-heading. Demo all of it in the kitchen sink. No page uses them yet, except through the kitchen sink.

### Changes Required:

#### 1. Polish formatting helper

**File**: `resources/js/lib/format.js` (new)

**Intent**: Provide the single `pl-PL` formatting module required by §4.5, so the list (and later S-09/S-11) stops formatting dates with ad-hoc locales.

**Contract**: Named exports, each passing `'pl-PL'` explicitly:
- `formatDateRange(startYmd, endYmd)` → e.g. "15–21 czerwca 2026". Use `Intl.DateTimeFormat#formatRange` with day, long month and year, so cross-month and cross-year ranges collapse correctly ("29 czerwca – 5 lipca 2026").
- `formatDateTime(iso)` → e.g. "15.06.2026, 10:00".
- `pluralize(count, { one, few, many })` → the form chosen by `Intl.PluralRules('pl-PL')`. `few` covers 2–4 and 22–24; `many` covers 0, 5–21 and 25+.
- `Y-m-d` inputs are parsed as local dates (see Critical Implementation Details).

#### 2. `PageHeader`

**File**: `resources/js/Components/Common/PageHeader.vue` (new)

**Intent**: Replace hand-written page headers with the §5.2 contract, rendered in the page body.

**Contract**:
- Props: `title: string`, `description?: string`.
- Slots: `actions` and `breadcrumbs`.
- It renders exactly one `<h1>` with the §4.5 page-title role (`text-2xl font-semibold tracking-tight`) and the description in `text-sm text-muted-foreground`.
- Layout: actions to the right on `sm+`, stacked below the title on mobile.
- Tokens only.

#### 3. `EmptyState`

**File**: `resources/js/Components/Common/EmptyState.vue` (new)

**Intent**: Give lists one empty-state pattern (§5.2), with an optional call to action.

**Contract**:
- Props: `icon` (a lucide component), `title: string`, `description?: string`.
- Default slot for the CTA.
- The icon is `size-10 text-muted-foreground` and `aria-hidden` (§4.7).
- The title uses the section-title role; the description uses `text-sm text-muted-foreground`.
- Centred column with `gap-2`. Tokens only.

#### 4. Destructive menu item

**File**: `resources/js/Components/ui/dropdown-menu/DropdownMenuItem.vue`

**Intent**: Port the new-york-v4 `variant` prop so destructive actions are a variant, not a call-site colour override (§3 rule 3).

**Contract**:
- New prop `variant: 'default' | 'destructive'` (default `'default'`), exposed as `data-variant`.
- The destructive styling uses only `destructive` tokens (text, focus background at reduced opacity, focus text, svg colour) via `data-[variant=destructive]:` classes.
- Existing callers render identically.

#### 5. Layout fallback title

**File**: `resources/js/Layouts/AuthenticatedLayout.vue`

**Intent**: Keep one `h1` per page once a page renders `PageHeader` in the body instead of filling `#header`.

**Contract**:
- The `v-else` fallback at `:71` becomes a non-heading element with the same visual classes.
- The `#header` slot behaviour is unchanged.

#### 6. Kitchen-sink demos

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: Show the new patterns in their states, as CLAUDE.md requires for a new shared pattern.

**Contract**:
- In section "Komponenty aplikacji", add:
  - a bordered box with a `PageHeader` (title, an actions button, and a description built with `pluralize(22, …)`, so the demo reads "22 jadłospisy");
  - two `EmptyState`s: one with a CTA button, one without (the filtered variant with "Wyczyść filtry").
- In the "Nakładki" dropdown-menu demo, add a separator and a `variant="destructive"` item "Usuń".
- The dev page then has a second `h1`, inside the demo box. This is accepted on this dev-only page; the spec's `heading level 1 'Design system'` assertion is by name.
- Existing section names are unchanged.

#### 7. Token scan list

**File**: `scripts/lint-tokens.paths`

**Intent**: Scan the new helper like every other migrated file.

**Contract**: Append `resources/js/lib/format.js`. The new Common components are already covered by the `resources/js/Components/Common` entry.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token scan stays green (new Common files are covered by the existing `resources/js/Components/Common` entry): `vendor/bin/sail npm run lint:tokens`
- `resources/js/lib/format.js` is listed in `scripts/lint-tokens.paths`, and `bash scripts/lint-tokens.test.sh` passes
- Kitchen sink still renders: `vendor/bin/sail pest --filter=DesignSystemPageTest`
- Full Pest suite passes: `vendor/bin/sail composer test`

#### Manual Verification:

- `/dev/design-system` shows `PageHeader` (desktop: actions on the right; 390px: stacked), both `EmptyState` variants, and the red "Usuń" menu item with a visible focus background when reached by keyboard
- The kitchen-sink `PageHeader` demo reads "22 jadłospisy", and `pluralize` maps 1/2/5/12/22/25 to jadłospis/jadłospisy/jadłospisów/jadłospisów/jadłospisy/jadłospisów (checked by temporarily changing the demo count)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Page, toolbar and URL-driven filters

### Overview

Move `Index.vue` onto `PageHeader` and tokens, make the URL the only source of filter state, turn `FiltersBar` into a token toolbar with two labelled Selects, and add both empty states.

### Changes Required:

#### 1. Page composition

**File**: `resources/js/Pages/MealPlans/Index.vue`

**Intent**: Render the page body as `PageHeader` → toolbar → bordered table → pagination, without palette literals, `dark:` or the `#header` slot.

**Contract**:
- `PageHeader`:
  - `title="Jadłospisy"`;
  - description "Przeglądaj wygenerowane jadłospisy" with " · N <plural>" via `pluralize` when the total is above 0;
  - `actions` slot holding `<GenerateMealPlanDialog />` (default label "Wygeneruj jadłospis").
- Content width `max-w-6xl`, `gap-6` between sections (§4.6).
- The table wrapper is `rounded-lg border bg-card` with no shadow.

#### 2. URL-only filter state

**File**: `resources/js/Pages/MealPlans/Index.vue`

**Intent**: Remove the select/list disagreement by deriving every control value from `props.filters` and dropping localStorage entirely.

**Contract**:
- Delete `localFilters`, the localStorage `watch` and `loadFiltersFromStorage`.
- A computed `currentFilters` normalises `props.filters` (see Critical Implementation Details).
- `FiltersBar` is bound with `:value="currentFilters"` and `@update:value`, without `v-model` (a read-only computed cannot be written).
- One query builder serialises `currentFilters` merged with a change into `filter[status]`, `sort`, `direction` and `perPage`, and omits an empty status.
- Status, page-size and sort changes call `router.get(route('meal-plans.index'), query, { preserveScroll: true, replace: true })` without `page`.
- Page changes keep the S-07 behaviour (current query plus `page`).
- "Wyczyść filtry" visits the bare `route('meal-plans.index')`.
- The query parameter names and values sent are unchanged from today.

#### 3. Toolbar

**Files**: `resources/js/Components/MealPlans/FiltersBar.vue`, `StatusFilter.vue`, `PerPageSelect.vue`

**Intent**: Turn the grey box into a plain token toolbar in Polish and drop the duplicate results counter.

**Contract**:
- `FiltersBar`:
  - a row (stacked on mobile) of `ui/label` + `StatusFilter` ("Status") and `ui/label` + `PerPageSelect` ("Na stronie");
  - labels bound to the triggers by `id`;
  - the `resultsCount` prop and counter markup removed;
  - the `value`/`update:value` contract kept.
- `StatusFilter`: the "Wszystkie statusy" option and option labels from the `statuses` prop.
- `PerPageSelect`: options `[5, 10, 15, 25, 50, 100]`.
- Both get an explicit `id` prop forwarded to `SelectTrigger`. Today the `id` FiltersBar passes is dropped, because `ui/select/Select.vue` wraps reka `SelectRoot` (`inheritAttrs: false`), so the labels are bound to nothing. reka `SelectTrigger` honours `attrs.id`.
- No arbitrary widths, using scale widths such as `w-40`/`w-20`. Tokens only.

#### 4. Empty states

**File**: `resources/js/Components/MealPlans/MealPlanTable.vue` (empty branch only; the rest of the table is Phase 3)

**Intent**: Replace the English empty row with the two agreed states.

**Contract**:
- `MealPlanTable` gets a `filtered: boolean` prop, set by the page when a status filter is present or the current page is above 1.
- It renders `TableEmpty` with the full column count and `class="whitespace-normal"` (it overrides the `whitespace-nowrap` in `ui/table/TableEmpty.vue`, which would otherwise keep the description on one line and scroll the table sideways at 390px), holding `EmptyState`:
  - **Not filtered:**
    - icon `CalendarDays`, title "Nie masz jeszcze jadłospisów";
    - description "Wygeneruj tydzień 7 dni × 3 posiłki z własnych przepisów.";
    - CTA `<GenerateMealPlanDialog label="Wygeneruj pierwszy jadłospis" />`.
  - **Filtered:**
    - title "Brak jadłospisów o wybranym statusie";
    - an outline button "Wyczyść filtry", which emits `clear-filters`.

#### 5. Token scan list

**File**: `scripts/lint-tokens.paths`

**Intent**: Lock the migrated files against new literals.

**Contract**: Append `resources/js/Pages/MealPlans/Index.vue`, `resources/js/Components/MealPlans/FiltersBar.vue`, `StatusFilter.vue` and `PerPageSelect.vue`.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token scan is green with the four new paths: `vendor/bin/sail npm run lint:tokens`
- No localStorage reference remains in the page: `grep -n "localStorage" resources/js/Pages/MealPlans/Index.vue` prints nothing
- Index feature tests pass: `vendor/bin/sail pest --filter=MealPlanControllerTest`

#### Manual Verification:

- `/meal-plans?filter[status]=done&perPage=5` shows "Gotowy" and "5" in the Selects and only done plans. A reload keeps all three. Stale data under `mm.mealPlans.filters` in DevTools has no effect.
- Changing status, then sorting, then changing page size keeps all earlier choices in the URL and returns to page 1 each time
- A user with no plans sees "Nie masz jeszcze jadłospisów" and "Wygeneruj pierwszy jadłospis", which opens the generate dialog. `?filter[status]=pending` with no pending plans shows "Brak jadłospisów o wybranym statusie", and "Wyczyść filtry" returns to the bare URL. At 390px both empty states wrap, with no horizontal scroll.
- Exactly one `h1` on `/meal-plans` (DevTools), and the top bar shows plain "Menu Maker"

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Table, actions and generate dialog

### Overview

Restyle the table header, rows, row menu and generate dialog onto tokens and Polish, with the agreed columns. Keep every request (`store`, `update` with `regenerate`, `destroy`, PDF link) byte-for-byte the same.

### Changes Required:

#### 1. Table header

**File**: `resources/js/Components/MealPlans/MealPlanTable.vue`

**Intent**: Polish column set without Logs, and without arbitrary widths or palette literals.

**Contract**:
- Columns:
  - `SortableHeader` "Tydzień" (`start_date`);
  - `SortableHeader` "Status" (`status`);
  - `TableHead` "Posiłki" (right-aligned, `hidden sm:table-cell`);
  - `SortableHeader` "Utworzono" (`created_at`, `hidden sm:table-cell`);
  - `TableHead` "Akcje" (right-aligned, the visible label can be `sr-only`).
- Header row on `bg-muted`.
- The sort allowlist and the `sort-change` event are unchanged.
- The empty colspan matches the column count (5).

#### 2. Row

**File**: `resources/js/Components/MealPlans/MealPlanRow.vue`

**Intent**: Make the week the primary link to the plan and use the shared formatter.

**Contract**:
- Tydzień is an Inertia `Link` to `item.links.self`, showing `formatDateRange(start_date, end_date)`, in `font-medium` with token link/hover/focus-visible styles.
- The duration line is removed (always 7 days).
- Status is `StatusTag` (unchanged).
- Posiłki is `meals_count` with `tabular-nums`, right-aligned, plus a `ChefHat` `size-4 text-muted-foreground` icon.
- Utworzono is `formatDateTime(created_at)` as metadata text.
- Akcje is `ActionDropdown`.
- `logs_count` is no longer rendered; the type may keep the field.
- Hidden cells mirror the header's `hidden sm:table-cell`.

#### 3. Row menu

**File**: `resources/js/Components/MealPlans/ActionDropdown.vue`

**Intent**: Drop palette literals and redundant icon classes, using the new destructive variant.

**Contract**:
- The trigger is a ghost icon button with `size-8` (no `h-8 w-8 p-0`). The `sr-only` "Otwórz menu" span is removed (an aria-label would override it anyway). Its only accessible name is an `aria-label` naming the week, e.g. "Akcje: 15–21 czerwca 2026", so screen-reader users can tell rows apart. The name must not start with "Otwórz menu", because the kitchen-sink spec clicks `{ name: 'Otwórz menu' }` without `exact`.
- Item icons lose their `mr-2 h-4 w-4` classes.
- "Usuń" uses `variant="destructive"`.
- Items, labels, enablement rules, both `ConfirmDialog`s, toasts and requests are unchanged.

#### 4. Generate dialog

**File**: `resources/js/Components/MealPlans/GenerateMealPlanDialog.vue`

**Intent**: Move the error styling to tokens and `Alert` without changing the form or the request.

**Contract**:
- `start_date` error: `text-sm text-destructive`, linked to the input via `aria-describedby`/`aria-invalid`.
- Missing-recipes messages: `<Alert variant="destructive">` with the existing messages and the "Dodaj przepis" link.
- `rate_limit`: `text-sm text-destructive`.
- The trigger icon loses `mr-2 h-4 w-4`.
- The submit button shows a `Loader2` spinner (`animate-spin motion-reduce:animate-none`) while `form.processing`.
- The props `label`/`variant`, the default slot and the dialog title/description/button names ("Wygeneruj", "Anuluj") are unchanged.
- `form.post(route('meal-plans.store'))` with `{ start_date }` is unchanged.

#### 5. Token scan list

**File**: `scripts/lint-tokens.paths`

**Intent**: Lock the rest of the view's files.

**Contract**: Append `resources/js/Components/MealPlans/MealPlanTable.vue`, `MealPlanRow.vue`, `ActionDropdown.vue` and `GenerateMealPlanDialog.vue`.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token scan is green with all eight view files listed: `vendor/bin/sail npm run lint:tokens`
- No English table copy remains: `grep -nE "Date Range|Meals|Logs|Created|Actions|No meal plans|results" resources/js/Components/MealPlans/{MealPlanTable,MealPlanRow,FiltersBar}.vue` prints nothing
- Generate flow E2E passes unchanged, after `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npx playwright test tests/e2e/meal-plan.spec.ts --project=chromium --project=firefox`
- Full Pest suite passes: `vendor/bin/sail composer test`

#### Manual Verification:

- At 1280px the table shows Tydzień, Status, Posiłki, Utworzono and Akcje with Polish dates ("15–21 czerwca 2026", "15.06.2026, 10:00"); at 390px only Tydzień, Status and Akcje show, with no horizontal page scroll
- Clicking the week opens the plan. The ⋯ menu works by keyboard (Enter, arrows, Esc). "Usuń" is red, and its confirm dialog deletes the plan with the success toast.
- Submitting the dialog without enough recipes shows the destructive Alert with "Dodaj przepis". With a throttled network, the submit shows the spinner and is disabled.
- `MealPlans/Show` header menu and Dashboard "Wygeneruj jadłospis" still work, and look the same apart from the token styling

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: Fixtures, visual gate and docs

### Overview

Seed fixed meal plans for the visual user only, add a real-page visual spec for the list, regenerate every affected baseline in one deliberate step, run the full E2E suite, and update the design-system doc.

### Changes Required:

#### 1. Visual-user fixture plans

**File**: `database/seeders/E2eMealPlanSeeder.php`

**Intent**: Give `e2e-planner-visual@example.com` a stable, populated list. The chromium and firefox planners keep starting with zero plans, as `meal-plan.spec.ts` requires.

**Contract**:
- After the visual user's recipes, ingredients, calories and family are synced, create four plans with fixed values:
  - `done`, 2026-06-15…21;
  - `done`, 2026-06-08…14;
  - `processing`, 2026-06-22…28;
  - `error`, 2026-06-01…07.
- Each plan has a fixed `created_at`/`updated_at` within 2026-05/06, distinct so the default `created_at desc` order is deterministic.
- `done` plans get their meals via `MealPlanGenerator::generate()` (21 each); the others have none.
- No `pending` plan, so the "Oczekuje" filter yields the filtered empty state.
- It stays idempotent: the existing `mealPlans()->delete()` runs first.
- No AI or queue involvement.
- An extracted private method keeps `run()` readable. `declare(strict_types=1)` is already present.

#### 2. Real-page visual spec

**File**: `tests/e2e/visual/meal-plans-index.spec.ts` (new)

**Intent**: Guard the migrated view and its states with screenshots, following `app-shell.spec.ts`.

**Contract**:
- Same skip guard, `beforeEach` fixed clock `2026-06-15T10:00:00`, `logIn` as the visual user and `expectFigtreeLoaded`. Then `goto('/meal-plans')`, assert the four rows are visible, and call `page.mouse.move(0, 0)` before each shot.
- Four full-page shots:
  - `meal-plans-index-desktop.png`;
  - `meal-plans-index-mobile.png` (390×844);
  - `meal-plans-index-filtered-empty.png`: pick "Oczekuje" in the "Status" Select through the UI (open it with `getByRole('combobox', { name: 'Status' })`), wait for the URL to contain `filter%5Bstatus%5D=pending` (or the decoded form), and assert "Brak jadłospisów o wybranym statusie";
  - `meal-plans-index-generate-dialog.png`: click the header's "Wygeneruj jadłospis" (exact) and assert the dialog's date input holds `2026-06-15`.
- After the filtered-empty shot, assert that "Wyczyść filtry" restores four rows. This is an assertion only, with no screenshot.

#### 3. Baselines

**Files**: `tests/e2e/__screenshots__/visual/meal-plans-index.spec.ts/*.png` (new), `tests/e2e/__screenshots__/visual/design-system.spec.ts/*.png` (changed)

**Intent**: Record the intended pixels once, deliberately.

**Contract**:
- Generate only through the wrapper, after `vendor/bin/sail npm run build` (no `public/hot`) and the seeder: `npm run test:visual -- --update-snapshots`.
- Expected changes:
  - 4 new `meal-plans-index-*.png`;
  - changed `section-komponenty-aplikacji.png` and `overlay-dropdown-menu.png`;
  - possibly the corners of `overlay-dialog.png`/`overlay-alert-dialog.png` (precedent: S-07/S-08 accepted deviation).
- Any other changed PNG is a regression to investigate, not to accept.

#### 4. Docs

**File**: `context/foundation/design-system.md`

**Intent**: Keep the contract document truthful.

**Contract**:
- In §5.2, the `PageHeader` and `EmptyState` rows change from "Odroczony" to implemented in S-10. Note that `PageHeader` is rendered in the page body (not the `#header` slot) and that the layout fallback is no longer an `h1`.
- §5.1 notes the `destructive` variant of `dropdown-menu` item.
- §7.3 counts the new spec and its 4 screenshots.

### Success Criteria:

#### Automated Verification:

- Seeder runs twice in a row without error: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`
- Larastan and Pint are clean: `vendor/bin/sail composer phpstan` and `vendor/bin/sail composer pint-test`
- Visual gate passes twice in a row after baselines are written: `npm run test:visual && npm run test:visual` (with `VISUAL_DOCKER_NETWORK=meal-plans-index-u_sail VISUAL_BASE_URL=http://laravel.test` in this worktree)
- `git status --porcelain -uall tests/e2e/__screenshots__` shows exactly 4 `??` entries under `meal-plans-index.spec.ts/`, and ` M` only for the expected design-system PNGs listed in Changes Required 3
- Full E2E suite passes after re-seeding: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- Token scanner self-test and scan are green: `bash scripts/lint-tokens.test.sh && vendor/bin/sail npm run lint:tokens`
- Full Pest suite passes: `vendor/bin/sail composer test`

#### Manual Verification:

- Each new and changed PNG reviewed in the diff: rows, badges (Gotowy/Generowanie/Błąd), Polish dates, toolbar labels, empty state and dialog look as intended
- 7-state matrix confirmed on `/meal-plans`:
  - default (seeded list);
  - hover (row, link, menu trigger);
  - focus-visible (Tab through header button, Selects, sort headers, week links, menu triggers);
  - disabled (menu items "Pobierz PDF"/"Regeneruj" on a processing plan);
  - loading (dialog submit spinner under a throttled network);
  - error (dialog missing-recipes Alert);
  - empty (first-run and filtered).
- `design-system.md` §5.1, §5.2 and §7.3 read consistently with what shipped

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- None new. The backend contract is unchanged (`MealPlanControllerTest` already covers `filters.filter.status`, `perPage` and pagination meta), and the repo has no JS unit runner.
- `lib/format.js` is checked by the console spot-check in Phase 1 and by the Polish dates and counts in the visual baselines.

### Integration Tests:

- `tests/e2e/meal-plan.spec.ts` runs unchanged against the empty list. It proves that the header button stays unique and that the generate request did not change.
- `tests/e2e/visual/meal-plans-index.spec.ts` drives the status Select through the UI and asserts the URL. It proves that filters are URL-driven and that "Wyczyść filtry" resets them, on top of the screenshots.
- The existing `design-system.spec.ts` and `app-shell.spec.ts` guard the shared pieces and the layout.

### Manual Testing Steps:

1. Put stale JSON under `mm.mealPlans.filters` in DevTools, open `/meal-plans`, and confirm the Selects show "Wszystkie statusy"/15 and the full list.
2. Open `/meal-plans?filter[status]=done&perPage=5&sort=start_date&direction=asc`, change page size, and confirm status and sort survive in the URL and in the Selects/headers.
3. Open `/meal-plans?filter[status]=bogus` and confirm the filtered empty state with "Wyczyść filtry", not the first-run CTA.
4. Delete the last plan of a fresh user and confirm the first-run empty state appears. Generate from its CTA.
5. Walk the 7-state matrix at 1280px and 390px.

## Performance Considerations

None. The page renders the same paginated data. Removing the localStorage watch drops a deep watcher, and `Intl` formatters can be created per call at this list size (≤100 rows).

## Migration Notes

- Users' browsers may keep the `mm.mealPlans.filters` key. It is no longer read, so it is harmless and is left in place.
- A bookmarked `/meal-plans` without query parameters now opens unfiltered at 15 per page. That is the agreed behaviour change.

## References

- Roadmap slice: `context/foundation/roadmap.md` S-10
- Design-system contract: `context/foundation/design-system.md` §4.5–4.7, §5.2, §7.2–7.4, §8 (DS-05)
- Carry-over: `context/archive/2026-11-15-shared-app-components/plan.md:126-130`, `reviews/impl-review.md:45-62` (F2)
- Real-page visual spec precedent: `context/archive/2026-11-15-app-shell-restyle/plan.md` Phase 3, `tests/e2e/visual/app-shell.spec.ts`
- Original feature spec: `.ai/ui/meal-plans-view-implementation-plan.md` (its localStorage requirement is superseded by this plan)
- Backend: `app/Http/Controllers/MealPlanController.php:38-77`, `app/Http/Resources/MealPlanCollection.php:86-111`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Shared building blocks

#### Automated

- [x] 1.1 Client and SSR bundles build: `vendor/bin/sail npm run build` — 8cd2ab4
- [x] 1.2 Token scan stays green (new Common files are covered by the existing `resources/js/Components/Common` entry): `vendor/bin/sail npm run lint:tokens` — 8cd2ab4
- [x] 1.3 `resources/js/lib/format.js` is listed in `scripts/lint-tokens.paths`, and `bash scripts/lint-tokens.test.sh` passes — 8cd2ab4
- [x] 1.4 Kitchen sink still renders: `vendor/bin/sail pest --filter=DesignSystemPageTest` — 8cd2ab4
- [x] 1.5 Full Pest suite passes: `vendor/bin/sail composer test` — 8cd2ab4

#### Manual

- [ ] 1.6 `/dev/design-system` shows `PageHeader` (desktop: actions on the right; 390px: stacked), both `EmptyState` variants, and the red "Usuń" menu item with a visible focus background when reached by keyboard
- [ ] 1.7 The kitchen-sink `PageHeader` demo reads "22 jadłospisy", and `pluralize` maps 1/2/5/12/22/25 to jadłospis/jadłospisy/jadłospisów/jadłospisów/jadłospisy/jadłospisów (checked by temporarily changing the demo count)

### Phase 2: Page, toolbar and URL-driven filters

#### Automated

- [x] 2.1 Client and SSR bundles build: `vendor/bin/sail npm run build` — b325fba
- [x] 2.2 Token scan is green with the four new paths: `vendor/bin/sail npm run lint:tokens` — b325fba
- [x] 2.3 No localStorage reference remains in the page: `grep -n "localStorage" resources/js/Pages/MealPlans/Index.vue` prints nothing — b325fba
- [x] 2.4 Index feature tests pass: `vendor/bin/sail pest --filter=MealPlanControllerTest` — b325fba

#### Manual

- [ ] 2.5 `/meal-plans?filter[status]=done&perPage=5` shows "Gotowy" and "5" in the Selects and only done plans. A reload keeps all three. Stale data under `mm.mealPlans.filters` in DevTools has no effect.
- [ ] 2.6 Changing status, then sorting, then changing page size keeps all earlier choices in the URL and returns to page 1 each time
- [ ] 2.7 A user with no plans sees "Nie masz jeszcze jadłospisów" and "Wygeneruj pierwszy jadłospis", which opens the generate dialog. `?filter[status]=pending` with no pending plans shows "Brak jadłospisów o wybranym statusie", and "Wyczyść filtry" returns to the bare URL. At 390px both empty states wrap, with no horizontal scroll.
- [ ] 2.8 Exactly one `h1` on `/meal-plans` (DevTools), and the top bar shows plain "Menu Maker"

### Phase 3: Table, actions and generate dialog

#### Automated

- [x] 3.1 Client and SSR bundles build: `vendor/bin/sail npm run build` — 096cddf
- [x] 3.2 Token scan is green with all eight view files listed: `vendor/bin/sail npm run lint:tokens` — 096cddf
- [x] 3.3 No English table copy remains: `grep -nE "Date Range|Meals|Logs|Created|Actions|No meal plans|results" resources/js/Components/MealPlans/{MealPlanTable,MealPlanRow,FiltersBar}.vue` prints nothing — 096cddf
- [x] 3.4 Generate flow E2E passes unchanged, after `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npx playwright test tests/e2e/meal-plan.spec.ts --project=chromium --project=firefox` — 096cddf
- [x] 3.5 Full Pest suite passes: `vendor/bin/sail composer test` — 096cddf

#### Manual

- [ ] 3.6 At 1280px the table shows Tydzień, Status, Posiłki, Utworzono and Akcje with Polish dates ("15–21 czerwca 2026", "15.06.2026, 10:00"); at 390px only Tydzień, Status and Akcje show, with no horizontal page scroll
- [ ] 3.7 Clicking the week opens the plan. The ⋯ menu works by keyboard (Enter, arrows, Esc). "Usuń" is red, and its confirm dialog deletes the plan with the success toast.
- [ ] 3.8 Submitting the dialog without enough recipes shows the destructive Alert with "Dodaj przepis". With a throttled network, the submit shows the spinner and is disabled.
- [ ] 3.9 `MealPlans/Show` header menu and Dashboard "Wygeneruj jadłospis" still work, and look the same apart from the token styling

### Phase 4: Fixtures, visual gate and docs

#### Automated

- [x] 4.1 Seeder runs twice in a row without error: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder` — db119ec
- [x] 4.2 Larastan and Pint are clean: `vendor/bin/sail composer phpstan` and `vendor/bin/sail composer pint-test` — db119ec
- [x] 4.3 Visual gate passes twice in a row after baselines are written: `npm run test:visual && npm run test:visual` (with `VISUAL_DOCKER_NETWORK=meal-plans-index-u_sail VISUAL_BASE_URL=http://laravel.test` in this worktree) — db119ec
- [x] 4.4 `git status --porcelain -uall tests/e2e/__screenshots__` shows exactly 4 `??` entries under `meal-plans-index.spec.ts/`, and ` M` only for the expected design-system PNGs listed in Changes Required 3 — db119ec
- [x] 4.5 Full E2E suite passes after re-seeding: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e` — db119ec
- [x] 4.6 Token scanner self-test and scan are green: `bash scripts/lint-tokens.test.sh && vendor/bin/sail npm run lint:tokens` — db119ec
- [x] 4.7 Full Pest suite passes: `vendor/bin/sail composer test` — db119ec

#### Manual

- [ ] 4.8 Each new and changed PNG reviewed in the diff: rows, badges (Gotowy/Generowanie/Błąd), Polish dates, toolbar labels, empty state and dialog look as intended
- [ ] 4.9 7-state matrix confirmed on `/meal-plans`: default (seeded list), hover (row, link, menu trigger), focus-visible (Tab through header button, Selects, sort headers, week links, menu triggers), disabled (menu items "Pobierz PDF"/"Regeneruj" on a processing plan), loading (dialog submit spinner under a throttled network), error (dialog missing-recipes Alert), empty (first-run and filtered)
- [ ] 4.10 `design-system.md` §5.1, §5.2 and §7.3 read consistently with what shipped
