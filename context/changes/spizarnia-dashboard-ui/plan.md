# Spiżarnia Dashboard UI Implementation Plan

## Overview

Roadmap S-23 (M-4, SP-04) restyles the dashboard to the Spiżarnia direction from artifact screens 05–06 (`context/foundation/design-system.md` §10.1, §10.5). The header gets a greeting and the two actions. A `highlight` hero shows the latest ready plan. Two stat tiles use the breakfast and supper colours, and a „Ten tydzień” card shows that plan's 21 meals in category colours. A brand-new user sees the screen-06 first-run tile. The only backend change is loading the latest plan's meals into the existing `latestMealPlan` prop, which is still scoped to the owner.

## Current State Analysis

- `DashboardController` (`app/Http/Controllers/DashboardController.php:24-41`) passes `stats {recipes, mealPlans}` and `latestMealPlan`. The plan is the newest `done` plan by `start_date`, then `id`, scoped with `MealPlan::ownedBy($user)`, and serialised with `MealPlanResource` without relations, so `latestMealPlan.meals` is `[]`.
- `MealPlanResource` (`app/Http/Resources/MealPlanResource.php:42-86`) already maps meals in the shape `MealPlans/Show` uses once `meals.recipe` is loaded.
  - Meals are sorted by `meal_date`, then category.
  - Each meal carries `id`, `meal_date` (`Y-m-d`), `meal_category`, `feeds_meal_id` and `from_meal_id`.
  - `recipe {deleted, id, name, category, calories, servings}` is present only when `recipe_id` is set; an empty slot has no `recipe` key.
  - `links.pdf` and `links.pdf_with_recipes` are set only for `done` plans.
- `Pages/Dashboard.vue` is still the Zielnik layout.
  - It has `PageHeader title="Pulpit"`, two `StatCard`s, a „Ostatni jadłospis” card and a „Szybkie akcje” card.
  - When `stats.recipes === 0 && stats.mealPlans === 0` it shows an `EmptyState` „Zacznij od pierwszego przepisu”.
- `Components/Dashboard/StatCard.vue` is used only by the dashboard. It puts the icon on a `bg-accent` disc (the F-05 carry-over) and has no own top padding, because `CardContent` is `p-6 pt-0` (the F-06 carry-over).
- Shared pieces that don't fit the hero yet:
  - `ui/button` has no variant for use on `highlight` (`resources/js/Components/ui/button/index.js:10-18`).
  - `PdfDownloadMenu` hardcodes `variant="outline"` on its trigger (`resources/js/Components/MealPlans/PdfDownloadMenu.vue:4`).
  - `Common/EmptyState` and `Common/PageHeader` are still pre-Spiżarnia; `PageHeader` uses `text-2xl`, not `text-page-title`. Both are shared with S-24 and S-26, which run in parallel, so they stay untouched (decision below).
- The plan stores no people count („dla 2 osób” in the mockup has no source), and every seeded user is named `E2E Planner`.

## Desired End State

At 1280 px the dashboard matches screen 05:
- **Header:** `<h1>` „Pulpit”, the subtitle „Dzień dobry, {user.name}.”, and the actions „Wygeneruj jadłospis” (outline, opens the existing dialog) and „Dodaj przepis” (ink).
- **Hero and stats:** below the header, a two-thirds `highlight` hero sits beside two stacked stat tiles.
  - Hero content:
    - „Ostatni jadłospis” and the „Gotowy” status pill.
    - The date range as `<h2>`.
    - „{n} posiłków. Lista zakupów i ilości są już przeliczone.”
    - „Otwórz jadłospis” (card fill) and „Pobierz PDF” (outline in `highlight-foreground`).
  - Stat tiles: „21 przepisów” on `meal-breakfast` and „5 jadłospisów” on `meal-supper`.
- **Week card:** below the hero and stats.
  - Title: „Ten tydzień” when today falls in the plan, otherwise „Tydzień 23–29 listopada 2026”.
  - A legend of the three category badges.
  - 7 day columns, each with three colour blocks.
  - Empty and linked meals as in the meal-plan grid.
- **Mobile:** below `lg` the days stack as rows.
- **No ready plan yet:** a user with recipes or plans but no `done` plan sees the hero with „Nie masz jeszcze gotowego jadłospisu” and no week card.
- **First run:** a user with no recipes and no plans sees the screen-06 tile and a header without actions.

Verification: the Pest suite is green with the new meals assertions, the kitchen sink has a „Pulpit” section with every state, the four dashboard baselines in `visual/app-shell.spec.ts` are deliberately refreshed and reviewed, and `lint:tokens`, Larastan, Pint and the build pass.

### Key Discoveries:

- Inertia branch of `MealPlanController::show()` loads `meals.recipe`, `meals.ingredients.ingredient` and `meals.ingredients.unit` (`app/Http/Controllers/MealPlanController.php:109`); the dashboard needs only `meals.recipe`.
- `MealPlanGrid.vue:197-215` builds 7 day rows from `startDate`; linked-meal tags come from `cookedPairFor` / `carriedPairFor` in `resources/js/lib/mealLinks.js:1-23` („Na 2 dni” / „Z wczoraj”, „Też na śniadanie” / „Z kolacji”), and a deleted recipe shows „(usunięty)” (`MealPlanGrid.vue:43-53`).
- `lib/format.js`: `formatDateRange` („15–21 czerwca 2026”), `pluralize(n, {one, few, many})`, `formatDayMonth` („15.06”), `formatWeekdayShort` („pon.”).
- `auth.user` (with `name`) is shared by `HandleInertiaRequests` (`app/Http/Middleware/HandleInertiaRequests.php:35-36`).
- `GenerateMealPlanDialog` takes its trigger through the default slot (`DialogTrigger as-child`); `CategoryBadge` (`Components/recipes/CategoryBadge.vue`) and `StatusTag` (`Components/MealPlans/StatusTag.vue`) already exist.
- Dashboard shots: `tests/e2e/visual/app-shell.spec.ts:34-60`, which saves `dashboard-desktop.png`, `dashboard-empty-desktop.png`, `dashboard-mobile.png` and `dashboard-mobile-drawer.png` under `tests/e2e/__screenshots__/visual/app-shell.spec.ts/`. The clock is pinned to `2026-06-15T10:00:00`, the Monday the seeded done plan starts, and the timezone is UTC.
- The seeded visual plan (06-15) is a plain 7×3 week whose meals equal the mockup. It has no empty slot and no linked meals, so those states are covered by the kitchen sink.
- The kitchen-sink spec screenshots each entry in the `sections` array (`tests/e2e/visual/design-system.spec.ts:9`, `:54-58`). A new section adds one `section-<slug>.png` and leaves the others alone.
- Tests build plans with `mealPlanWithRecipes()` (`tests/Pest.php:48`); an empty slot is a `Meal::query()->create([... 'recipe_id' => null])`.

## What We're NOT Doing

- No change to `Common/PageHeader` (the `text-2xl` → `text-page-title` gap is logged under `## Parked` in the roadmap) or `Common/EmptyState`. Both are shared with S-24 and S-26, which run in parallel.
- No people count („dla N osób”): the plan does not store one, and today's family may differ from the one the plan was scaled for.
- No vocative or status line in the greeting: it is „Dzień dobry, {user.name}.” with the stored name.
- No recipe links inside the week blocks; the hero links to the full plan.
- No „Szybkie akcje” card: its two actions move into the header as in the mockup.
- No new backend query, prop or resource beyond loading `meals.recipe`; no ingredients on the dashboard.
- No change to `MealPlanGrid`; the week grouping is a small separate helper, not a refactor of the S-25 grid.
- No seeder change: linked and empty meals are demoed in the kitchen sink, so the meal-plan baselines don't move.
- No changes to the generate, PDF or recipe flows, and no dark mode or PDF changes.

## Implementation Approach

Phase 1 delivers the data, guarded by Pest. Phase 2 builds every dashboard piece as a component with all its states in a new kitchen-sink section „Pulpit”, appended last, so existing section baselines and the parallel slices' sections are unaffected. Phase 3 assembles the page from those pieces and refreshes only the four dashboard baselines. Shared code is changed additively only: two new `Button` variants and a `variant` prop on `PdfDownloadMenu`, both defaulting to today's behaviour.

Before Phase 1, the worktree needs its own `.env` (copy the main checkout's), with `COMPOSE_PROJECT_NAME=mm-dashboard-ui`, `APP_PORT=8093`, `VITE_PORT=5193`, `FORWARD_DB_PORT=5493`, `FORWARD_MAILPIT_PORT=1093` and `FORWARD_MAILPIT_DASHBOARD_PORT=8094`. These ports are free of the other worktrees. Each `FORWARD_*` key must appear once, and the file keeps `AI_SCALING_DRIVER=fake`, `MAIL_MAILER=smtp` and `MAIL_HOST=mailpit`. Run the visual gate with `VISUAL_DOCKER_NETWORK=mm-dashboard-ui_sail`, and confirm with `docker inspect mm-dashboard-ui-laravel.test-1` that `/var/www/html` is this worktree before any `--update-snapshots` (`lessons.md`).

## Critical Implementation Details

**Focus ring on `highlight`.** `--ring` and `--highlight` are the same colour (`#4f5bd5`, §10.2). With the base `outline-ring outline-offset-2`, the 2px gap shows `highlight` and the ring disappears into it. The new `inverse` and `inverse-outline` variants must set `focus-visible:outline-highlight-foreground`, and the result must be checked in the rendered class list: the variant's outline colour has to survive `cn()` next to the base `outline-ring`.

**„Today” is client-side and local.** The week title compares the browser's local `Y-m-d` with the plan's `start_date`/`end_date` strings. Build that string from `getFullYear/getMonth/getDate`, not `toISOString()`, which is UTC. Iterate the 7 days in UTC from the `start_date` string, so a DST change never skips or repeats a day. The visual spec pins `2026-06-15T10:00` in UTC, so the screenshot shows „Ten tydzień”.

**Colour classes must be static.** Map tones and categories to complete class strings (`bg-meal-breakfast text-meal-breakfast-foreground`, …) in an object, never `` `bg-meal-${tone}` ``. Tailwind only generates classes it finds literally in the source.

## Phase 1: Posiłki ostatniego planu w propsach

### Overview

The dashboard's `latestMealPlan` now includes its meals with recipes, in the same shape as `MealPlans/Show`. It stays owner-scoped and `done`-only.

### Changes Required:

#### 1. Dashboard controller

**File**: `app/Http/Controllers/DashboardController.php`

**Intent**: Eager-load `meals.recipe` on the selected plan before resolving `MealPlanResource`, so the page can draw the week without another request. Update the props docblock.

**Contract**: The `latestMealPlan` prop is unchanged except that `meals` is now the full array of meal items (`id`, `meal_date`, `meal_category`, `feeds_meal_id`, `from_meal_id`, optional `recipe`), sorted by date and category. `meals[].ingredients` is an empty object, because the `ingredients` relation is not loaded: `MealPlanResource.php:70` emits the key as `{}`, the same shape the JSON endpoints that load only `meals.recipe` already return (`MealPlanController.php:224,260,341`). The resource is not changed. The plan selection query is unchanged. Two queries for the plan and its meals, no N+1.

#### 2. Dashboard controller tests

**File**: `tests/Feature/DashboardControllerTest.php`

**Intent**: Pin the new prop and its edge cases next to the existing selection tests.

**Contract**: New tests:
- `exposes the latest plan's meals in date and category order with their recipes`: a `done` plan built with `mealPlanWithRecipes()`. Assert the `meals` count, the first meal's `meal_date`, `meal_category` and `recipe.name`, and that `meals.0.ingredients` is empty (`->has('latestMealPlan.meals.0.ingredients', 0)`; `missing()` would fail, see the Contract above).
- `exposes an empty slot without a recipe and the link markers`: in one plan, a meal with `recipe_id` null has no `recipe` key, and a meal whose `feeds_meal_id` points to another gives that other meal `from_meal_id`.
- `never exposes meals of another user's or a non-done plan`: every exposed meal id belongs to the selected own `done` plan, while another user's newer `done` plan and an own newer `draft`, both with meals, exist.

### Success Criteria:

#### Automated Verification:

- Dashboard tests pass: `vendor/bin/sail pest --filter=DashboardControllerTest`
- Full Pest suite passes: `vendor/bin/sail composer test`
- Larastan passes: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`

**Implementation Note**: Phase 1 has no UI change and no manual check; continue to Phase 2 once the automated checks pass.

---

## Phase 2: Klocki pulpitu w kitchen sinku

### Overview

Every piece of screens 05–06 exists as a component with its states demoed in a new kitchen-sink section „Pulpit”, guarded by its own baseline. The live dashboard does not use them yet.

### Changes Required:

#### 1. Button variants on `highlight`

**File**: `resources/js/Components/ui/button/index.js`

**Intent**: Add the two button styles the mockup uses on the hero (`.s-btn.on-hl`, `.s-btn.on-hl-outline`), named after `AppBrand tone="inverse"`.

**Contract**: New `variant` values:
- `inverse`: `bg-card text-foreground border-card`, hover `bg-accent`.
- `inverse-outline`: transparent, `text-highlight-foreground border-highlight-foreground`; on hover it inverts to a `highlight-foreground` fill with `highlight` text.

Both use `focus-visible:outline-highlight-foreground` (see Critical Implementation Details) and no sticker shadow. Existing variants are untouched.

#### 2. PDF menu trigger variant

**File**: `resources/js/Components/MealPlans/PdfDownloadMenu.vue`

**Intent**: Let the hero render the trigger as `inverse-outline`.

**Contract**: New prop `variant: 'outline' | 'inverse-outline'`, default `'outline'`, passed to the trigger `Button`. On `inverse-outline` the `ChevronDown` drops `text-muted-foreground` and inherits the text colour. Existing callers and `overlay-pdf-download-menu.png` must not change.

#### 3. Stat tile

**File**: `resources/js/Components/Dashboard/StatCard.vue`

**Intent**: Rebuild as the screen-05 stat tile (`.s-stat`): a full meal-colour tile with a big number and a pluralised label, closing both carry-overs (`bg-accent` disc, missing top padding).

**Contract**:
- Props:
  - `value: Number` (required).
  - `forms: {one, few, many}` (required, passed to `pluralize`).
  - `icon` (required).
  - `tone: 'breakfast' | 'supper' | 'dinner'` (required).
- Markup: a `Card` with the tone's `bg-meal-* text-meal-*-foreground` from a static map, its own padding (`px-6 py-5`, no `CardContent`) and a vertical `justify-between`.
- Value: `text-stat tabular-nums` with `formatNumber`.
- Label row: `text-base font-semibold`, the pluralised noun on the left and the icon `size-6 aria-hidden` on the right.
- The prop `label` is removed; its only caller is `Dashboard.vue` (Phase 3).

#### 4. Latest-plan hero

**File**: `resources/js/Components/Dashboard/LatestPlanHero.vue` (new)

**Intent**: The `highlight` hero (`.s-hero`), including the state with no ready plan.

**Contract**:
- Prop `mealPlan: Object | null`.
- Root: `Card` with `bg-highlight text-highlight-foreground`, `p-7`, vertical `gap-5`.
- With a plan:
  - A row with `<p class="text-label">Ostatni jadłospis</p>` and `<StatusTag value="done" />`.
  - `<h2 class="text-page-title">` with `formatDateRange(start_date, end_date)`.
  - `<p class="max-w-xl">` reading „{n} {posiłek|posiłki|posiłków}. Lista zakupów i ilości są już przeliczone.”, where n = meals with a `recipe`.
  - `Button variant="inverse" as-child` → Inertia `Link` to `links.self` with „Otwórz jadłospis” and `ArrowRight`.
  - `PdfDownloadMenu variant="inverse-outline"` when both PDF links are present.
- Without a plan: the same label row without the status, `<h2 class="text-section-title">Nie masz jeszcze gotowego jadłospisu</h2>` and „Wygeneruj pierwszy, a pojawi się tutaj.”. No buttons, because the header already offers „Wygeneruj jadłospis”.
- Mockup deviation: the hero `h2` uses `text-page-title` (36/40) instead of the mockup's 40/44, because there is no role at that size and arbitrary values are banned.

#### 5. Week overview

**File**: `resources/js/Components/Dashboard/WeekOverview.vue` (new)

**Intent**: The „Ten tydzień” card (`.s-week-card`): the plan's meals grouped into 7 days × 3 categories, with an honest title, the grid's empty and linked-meal vocabulary, and rows on mobile.

**Contract**:
- Props:
  - `startDate: String` and `endDate: String` (`Y-m-d`).
  - `meals: Array` (the resource items).
  - `today: String` (optional `Y-m-d`; defaults to the browser's local date and exists so kitchen-sink demos are deterministic).
- Root: `Card` with `p-6`, vertical `gap-4`.
- Header row: `<h2 class="text-section-title">`, „Ten tydzień” when `startDate <= today <= endDate` (string compare), else `Tydzień ${formatDateRange(startDate, endDate)}`. On the right, a legend of three `CategoryBadge`s in `MEAL_CATEGORIES` order.
- Days: an `<ol>` of 7 `<li>`, `grid gap-4` with `lg:grid-cols-7 lg:gap-2`.
  - Each day is `grid grid-cols-3 gap-1.5` below `lg` and `lg:grid-cols-1`.
  - The day label (`col-span-3 lg:col-span-1`, `text-meta font-bold`) shows the capitalised `formatWeekdayShort` with `formatDayMonth` in `text-muted-foreground font-medium`.
  - Below the label, one slot per category.
- Filled slot:
  - `rounded-md` block in the category's static `bg-meal-* text-meal-*-foreground`, `px-2.5 py-2 text-meta min-h-13`.
  - A `sr-only` prefix „{Śniadanie|Obiad|Kolacja}: ”, because colour alone carries the category.
  - A `Badge variant="tag"` above the name with `cookedPairFor(cat).cookedLabel` when `feeds_meal_id` is set, else `carriedPairFor(cat).carriedLabel` when `from_meal_id` is set.
  - The recipe name as plain text, with „ (usunięty)” when `recipe.deleted`.
- Empty slot: the same box with `border-2 border-dashed border-muted-foreground text-muted-foreground` and „Bez posiłku” (with the `sr-only` category prefix).
- Grouping helper: a small `resources/js/lib/mealWeek.js` exporting `weekDays(startYmd)` (7 `Y-m-d` strings, computed in UTC) and `todayYmd()` (local).
- Mockup deviations:
  - The day label reads „Pon. 15.06” (existing formatter) instead of „Pon 15.06”.
  - On mobile the label sits above its three blocks, not beside them, because a label column would need an arbitrary grid template.

#### 6. First-run tile

**File**: `resources/js/Components/Dashboard/FirstRunTile.vue` (new)

**Intent**: Screen 06 (`.s-empty`) as a dashboard-local tile, leaving `Common/EmptyState` untouched.

**Contract**:
- No props.
- Root: `Card` with `flex flex-col items-center text-center gap-3.5 px-6 py-14`.
- Icon disc: `size-18 rounded-full border-2 border-foreground bg-meal-breakfast text-meal-breakfast-foreground grid place-items-center` with `ChefHat size-8 aria-hidden`.
- `<h2 class="text-dialog-title">Zacznij od pierwszego przepisu</h2>`: the heading the empty-dashboard spec looks up by role and name.
- Description in `max-w-md text-muted-foreground`, same copy as today.
- `Button size="lg" as-child` → `Link` to `recipes.create`, „Dodaj pierwszy przepis” with `Plus`.

#### 7. Kitchen-sink section „Pulpit”

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: Demo every new piece in all its states, appended as the last section so it doesn't move or conflict with the sections S-24/S-26 edit.

**Contract**: A new `<section aria-labelledby="ds-pulpit">` after „Jadłospis”, with an `h2` „Pulpit” like its siblings. Each demo has a `text-xs text-muted-foreground` caption. Demos:
- `LatestPlanHero` with a demo plan (PDF links `#`) and with `null`.
- `StatCard` in both tones, with values 1 and 21 to show the plural forms.
- `WeekOverview` with a demo week containing:
  - an empty slot;
  - a repeated supper (Mon → Tue: „Na 2 dni” / „Z wczoraj”);
  - a dinner carried to breakfast („Też na śniadanie” / „Z kolacji”);
  - a deleted recipe.

  It also gets a `today` outside the range, so the date-range title shows. „Ten tydzień” is covered by the dashboard shot.
- `FirstRunTile`.
- A `bg-highlight` strip with `inverse` and `inverse-outline` buttons in their default and disabled states, labelled „inverse”, „inverse wyłączony”, „inverse-outline” and „inverse-outline wyłączony”.

Demo data are local constants; nothing sends requests. Button names in „Pulpit” must not contain the names the overlay tests look up page-wide without `exact` (`design-system.spec.ts:138-241`: „Otwórz dialog”, „Otwórz potwierdzenie”, „Otwórz panel”, „Otwórz menu”, „Pokaż powiadomienie”, „Menu użytkownika”), or those tests fail in strict mode.

#### 8. Kitchen-sink visual spec

**File**: `tests/e2e/visual/design-system.spec.ts`

**Intent**: Guard the new section and the focus ring on `highlight`.

**Contract**: Add `{ name: 'Pulpit', slug: 'pulpit' }` to `sections`, which produces `section-pulpit.png`. Add a test that tabs to the `inverse` button in „Pulpit” and saves `inverse-focus-visible.png` with the same `focusRingClip` approach as `chip-focus-visible.png`.

### Success Criteria:

#### Automated Verification:

- Token lint and its self-test pass: `vendor/bin/sail npm run lint:tokens` and `bash scripts/lint-tokens.test.sh`
- Client and SSR build passes: `vendor/bin/sail npm run build`
- Kitchen-sink baselines are created after a deliberate review: `npm run test:visual -- --update-snapshots visual/design-system.spec.ts` (with `VISUAL_DOCKER_NETWORK=mm-dashboard-ui_sail`), then `npm run test:visual` is green
- Only `section-pulpit.png` and `inverse-focus-visible.png` are new under `tests/e2e/__screenshots__/visual/design-system.spec.ts/`, and no existing PNG there changed: `git status --short tests/e2e/__screenshots__/visual/design-system.spec.ts`
- Pest suite still passes: `vendor/bin/sail composer test`

#### Manual Verification:

- At `/dev/design-system` „Pulpit” shows the hero (with and without a plan), both stat tiles, the week with empty, repeated, carried and deleted meals, the first-run tile, and the inverse buttons.
- Tabbing through the hero shows a visible white focus ring on both inverse buttons and on the PDF trigger.
- The hover of `inverse-outline` inverts colours; `PdfDownloadMenu` elsewhere (section „Jadłospis”) looks unchanged.
- At 390 px width the week demo stacks days as rows with readable names.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Strona pulpitu i bramka

### Overview

`Pages/Dashboard.vue` is assembled from the Phase 2 pieces and matches screens 05–06. Only the four dashboard baselines change, and the docs and roadmap record the result.

### Changes Required:

#### 1. Dashboard page

**File**: `resources/js/Pages/Dashboard.vue`

**Intent**: Lay out screens 05–06 with the new components, removing the „Ostatni jadłospis” and „Szybkie akcje” cards.

**Contract**:
- `PageHeader title="Pulpit"` with `description` = `Dzień dobry, ${usePage().props.auth.user.name}.`.
- When not first run, the `actions` slot holds:
  - a `GenerateMealPlanDialog` whose slot is `Button variant="outline"` with `CalendarDays` and „Wygeneruj jadłospis”;
  - `Button as-child` → `Link` `recipes.create` with `Plus` and „Dodaj przepis”.
- First run (`stats.recipes === 0 && stats.mealPlans === 0`, unchanged): only `FirstRunTile`.
- Otherwise:
  - A `grid gap-6 lg:grid-cols-3` with `LatestPlanHero :meal-plan="latestMealPlan"` in `lg:col-span-2`.
  - A stats column, `grid gap-6 sm:grid-cols-2 lg:grid-cols-1`, holding:
    - `StatCard tone="breakfast"` with `ChefHat` and the forms przepis/przepisy/przepisów;
    - `StatCard tone="supper"` with `CalendarDays` and the forms jadłospis/jadłospisy/jadłospisów.
  - Then `WeekOverview` with the plan's dates and meals, rendered only when `latestMealPlan` is set.
- The width container `max-w-6xl` and `<Head title="Pulpit">` are unchanged.

#### 2. Dashboard baselines

**File**: `tests/e2e/__screenshots__/visual/app-shell.spec.ts/`

**Intent**: Refresh exactly the four dashboard shots after review; the spec file itself does not change. Its empty-dashboard heading assertion still resolves through `FirstRunTile`.

**Contract**: Changed: `dashboard-desktop.png`, `dashboard-empty-desktop.png`, `dashboard-mobile.png`, `dashboard-mobile-drawer.png`. `guest-login*.png` and every other spec's PNGs must not change; any other diff is a regression.

#### 3. Design-system docs

**File**: `context/foundation/design-system.md`

**Intent**: Record the new contracts where the other slices recorded theirs.

**Contract**:
- §5.1: add `Button` `inverse` / `inverse-outline` (for `highlight`, focus outline in `highlight-foreground`).
- §5.3: add entries for `StatCard`, `LatestPlanHero`, `WeekOverview` and `FirstRunTile` (props, states, the honest week title, the mobile rows).
- Note the `PdfDownloadMenu` `variant` prop.

#### 4. Roadmap note

**File**: `context/foundation/roadmap.md`

**Intent**: Keep the `PageHeader` title gap from getting lost.

**Contract**: One bullet under `## Parked`: `PageHeader` still uses `text-2xl font-semibold`, not `text-page-title` (§10.3, 36 px). Parked because changing it moves every page's baselines and S-23…S-26 ran in parallel; to be done once after they merge. Status fields are left to the lifecycle skills.

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Token lint passes: `vendor/bin/sail npm run lint:tokens`
- Pest suite passes: `vendor/bin/sail composer test`
- Larastan and Pint pass: `vendor/bin/sail composer phpstan` and `vendor/bin/sail composer pint-test`
- After `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`, the dashboard baselines are refreshed after review with `npm run test:visual -- --update-snapshots visual/app-shell.spec.ts` (with `VISUAL_DOCKER_NETWORK=mm-dashboard-ui_sail`), and then the full `npm run test:visual` is green
- Exactly the four `dashboard-*.png` files changed in this phase: `git status --short tests/e2e/__screenshots__/`
- Functional E2E passes after re-seeding: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:8093 MAILPIT_URL=http://localhost:8094 npm run test:e2e`

#### Manual Verification:

- At 1280 px the dashboard as `e2e-planner-visual@example.com` matches screen 05:
  - the greeting;
  - the actions on the right of the header;
  - the hero with „15–21 czerwca 2026” and „21 posiłków…”;
  - „21 przepisów” and „5 jadłospisów”;
  - „Ten tydzień” with the seeded meals in the right columns and colours.
- „Otwórz jadłospis” opens the plan, and „Pobierz PDF” offers both PDFs, which download.
- „Wygeneruj jadłospis” in the header opens the generate dialog, and „Dodaj przepis” opens the recipe form.
- `e2e-dashboard-empty-visual@example.com` sees screen 06 with no header actions. A user with recipes but no ready plan sees the „Nie masz jeszcze gotowego jadłospisu” hero and no week card.
- At 1024 px and 390 px the layout holds: stats beside each other on `sm`, days as rows on mobile, no horizontal page scroll.
- With the browser date outside the plan's week, the week title shows the date range instead of „Ten tydzień”.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful.

---

## Testing Strategy

### Unit Tests:

- No new unit tests: the controller is covered by feature tests, and there are no new policies or Form Requests.

### Integration Tests:

- `DashboardControllerTest`: meals shape and order, empty slot without `recipe`, `from_meal_id` / `feeds_meal_id` markers, and no meals from another user's or a non-`done` plan.
- Visual: `section-pulpit.png` and `inverse-focus-visible.png` (new), and the four dashboard shots (refreshed).
- Functional E2E unchanged; it only waits for `/dashboard` and checks the nav link.

### Manual Testing Steps:

1. Re-seed, log in as the visual planner at 1280 px and compare with screen 05 side by side.
2. Tab through the header and hero; every control shows a ring, including on `highlight`.
3. Log in as the empty-dashboard user and compare with screen 06.
4. Resize to 390 px and check the week rows and the stats.

## Performance Considerations

One extra query (`meals` with `recipe`, 21 rows) on the dashboard; no ingredients are loaded.

## Migration Notes

None: no schema or data change.

## References

- Roadmap item: `context/foundation/roadmap.md` S-23
- Design: `context/foundation/design-system.md` §10.2–10.5; artifact screens 05–06 (`project/components/Spizarnia05Dashboard`, `Spizarnia06DashboardEmpty`)
- Lessons: `context/foundation/lessons.md` (worktree Sail stack for E2E and the visual gate)
- Similar implementation: `context/archive/2026-11-17-spizarnia-meal-plans-ui/plan.md` (phase-per-spec baselines, kitchen-sink demos)
- Data shape: `app/Http/Resources/MealPlanResource.php:42-86`, `resources/js/Components/MealPlans/MealPlanGrid.vue:197-215`, `resources/js/lib/mealLinks.js:1-23`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Posiłki ostatniego planu w propsach

#### Automated

- [x] 1.1 Dashboard tests pass: `vendor/bin/sail pest --filter=DashboardControllerTest` — 4b597d0
- [x] 1.2 Full Pest suite passes: `vendor/bin/sail composer test` — 4b597d0
- [x] 1.3 Larastan passes: `vendor/bin/sail composer phpstan` — 4b597d0
- [x] 1.4 Pint passes: `vendor/bin/sail composer pint-test` — 4b597d0

### Phase 2: Klocki pulpitu w kitchen sinku

#### Automated

- [x] 2.1 Token lint and its self-test pass
- [x] 2.2 Client and SSR build passes
- [x] 2.3 Kitchen-sink baselines created after review and `npm run test:visual` green
- [x] 2.4 Only `section-pulpit.png` and `inverse-focus-visible.png` are new; no existing kitchen-sink PNG changed
- [x] 2.5 Pest suite still passes

#### Manual

- [ ] 2.6 „Pulpit” section shows every piece and state
- [ ] 2.7 Visible focus ring on inverse buttons and PDF trigger on `highlight`
- [ ] 2.8 `inverse-outline` hover inverts; PdfDownloadMenu elsewhere unchanged
- [ ] 2.9 Week demo stacks days as rows at 390 px

### Phase 3: Strona pulpitu i bramka

#### Automated

- [ ] 3.1 Build passes
- [ ] 3.2 Token lint passes
- [ ] 3.3 Pest suite passes
- [ ] 3.4 Larastan and Pint pass
- [ ] 3.5 Dashboard baselines refreshed after review and full `npm run test:visual` green
- [ ] 3.6 Exactly the four `dashboard-*.png` files changed
- [ ] 3.7 Functional E2E passes after re-seeding

#### Manual

- [ ] 3.8 Dashboard at 1280 px matches screen 05
- [ ] 3.9 Hero links open the plan and both PDFs
- [ ] 3.10 Header actions open the generate dialog and the recipe form
- [ ] 3.11 Empty-dashboard user sees screen 06; no-ready-plan hero without week card
- [ ] 3.12 Layout holds at 1024 and 390 px
- [ ] 3.13 Week title shows the date range outside the plan's week
