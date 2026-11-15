# Meal Plan Show View in the New Design Implementation Plan

## Overview

Move the meal plan view (S-09, DS-04, the product's "north star") onto design tokens and primitives:
- the files are `MealPlans/Show.vue`, `Components/MealPlans/MealPlanGrid.vue` and `Components/MealPlans/ShoppingList.vue`;
- the change builds the first `Common/PageHeader` and `Common/EmptyState`, which S-10 and S-11 will reuse;
- it is guarded by kitchen-sink demos, real-page screenshots of a seeded plan, and a deliberately reviewed E2E selector change.

Behaviour stays the same: polling, regeneration and the PDF link work as before.

## Current State Analysis

`Show.vue` has 26 palette literals and dead `dark:` classes (`design-system.md` §2):
- a hand-written header in the layout's `#header` slot (`resources/js/Pages/MealPlans/Show.vue:5-30`);
- amber, red and yellow boxes for the AI warning (`:65-80`), the error (`:43-51`) and the slow-generation notice (`:37-39`);
- an ad hoc empty state (`:53-62`).

`MealPlanGrid.vue` is a `<table>` with grey literals:
- an arbitrary `min-w-[40rem]` width (`:3`);
- grey `div` skeletons (`:14-19`);
- a native `<details>` around the ingredients (`:41-50`).

`ShoppingList.vue` is a grey `<section>` (`:2-25`).

`StatusTag` is already on `Badge` variants (S-07) and stays untouched.

What is load-bearing in `tests/e2e/meal-plan.spec.ts`:
- `getByTestId('meal-plan-grid')` on the `<table>`;
- exactly 21 `tbody td a` (`:20-21`);
- `columnheader` Śniadanie/Obiad/Kolacja (`:22-24`);
- `scaling-notice` hidden (`:27`);
- **`grid.locator('details').first().locator('summary').click()` (`:28`)**;
- `meal-ingredients` containing `Mąka — 200 g` (`:29`);
- three `shopping-list` `listitem`s with `42 szt.` / `4,2 kg` / `2,1 l` (`:32-44`);
- the link "Pobierz PDF" with `target=_blank` (`:48-51`);
- a single (non-exact) button "Regeneruj" in the done state (`:62`);
- the text `Generujemy jadłospis…` (`:66`).

Polish quantity formatting already happens server-side. `App\Services\ShoppingList\QuantityFormatter` uses `NumberFormatter('pl_PL')`, and Pest pins the `display` strings in `tests/Feature/MealPlanControllerTest.php:437-535`. The view only prints `display`.

No visual spec covers the Show page. `E2eMealPlanSeeder` deletes every planner's plans (`database/seeders/E2eMealPlanSeeder.php:59`). Recipe names are faker words, and the generator draws with `inRandomOrder()` (`app/Services/MealPlanGenerator.php:82`). A real-page baseline therefore needs a deterministic, seeded plan.

## Desired End State

When a user opens `/meal-plans/{id}`, they see the following.

At the top of the content:
- a `PageHeader` with `<h1>Jadłospis</h1>`;
- the date range as its description;
- `StatusTag`, "Pobierz PDF", "Regeneruj" and the row menu as actions.

The sticky top bar shows only the "Menu Maker" brand text, not a heading.

From `xl` up, the week grid and the shopping list sit side by side. Below `xl` the list sits under the grid.

In the grid:
- every filled cell has a 4px left bar in its category's saturated token (`border-meal-*-foreground`);
- ingredients open from a "Składniki (n)" button, a `Collapsible` with `aria-expanded`;
- while generating, the grid is drawn with `ui/skeleton`.

The shopping list is a `Card` with quantities right-aligned in `tabular-nums`.

The other states:

| State | Rendering |
| --- | --- |
| AI warning | `Alert variant="warning"` |
| Generation error | `Alert variant="destructive"` with the "Regeneruj" action |
| Slow generation | `Alert variant="warning"` |
| Done plan with no meals | `Common/EmptyState` with the "Regeneruj" CTA |

The view, the grid, the list and both new `Common` components contain no palette, hex, arbitrary or `dark:` literals, and `npm run lint:tokens` scans them.

Verification:
- `npm run test:visual` passes with the new section and page baselines;
- `npm run test:e2e` passes with the updated selector;
- `composer test` passes, including a new seeder test.

### Key Discoveries:

- The layout's `#header` slot renders inside the sticky bar. Without the slot, the bar shows the fallback `<h1>Menu Maker</h1>` (`resources/js/Layouts/AuthenticatedLayout.vue:69-72`). All 9 pages on this layout fill `#header`, so the fallback is never rendered today. Turning it into non-heading text affects no current page and no baseline.
- `design-system.md` §5.2 says `PageHeader` replaces "ręczne nagłówki w każdej stronie **i slot `#header`**". Its contract is `title`, `description?`, slots `actions` and `breadcrumbs`, and one `<h1>` per page. The title role is `text-2xl font-semibold tracking-tight` (§4.5). `EmptyState` has an icon, a title, a description and a CTA slot.
- `@theme inline` in `resources/css/app.css` defines `--color-meal-{breakfast,supper,dinner}` and their `-foreground` (`:78-83`), so `border-meal-*-foreground` is a valid utility. The base `meal-*` tokens are pale fills (L≈0.95), almost invisible as a 4px line on white (user decision: use `-foreground`).
- The primitives already exist:
  - `ui/collapsible`, `ui/skeleton`, `ui/card`;
  - `ui/alert`, with variants `default`, `destructive`, `warning`, `info` and `success` (`Components/ui/alert/index.js`).
- reka-ui `CollapsibleContent` unmounts its content when closed, unlike `<details>`. E2E clicks before it reads `meal-ingredients`, so this is safe.
- Layout width: with the expanded sidebar (`w-64`) and `p-6`, the content is about 976px wide at a 1280px viewport and about 720px at 1024px.
  - The grid needs 40rem (640px).
  - A `w-72` (288px) list plus `gap-6` leaves about 664px for the grid at `xl`, which fits.
  - At `lg` there is no room, so the columns start at `xl`.
- The kitchen sink (`resources/js/Pages/Dev/DesignSystem.vue`) has 13 sections. Each is screenshotted by its region name, `tests/e2e/visual/design-system.spec.ts:9-23`. "Komponenty aplikacji" (`:631`) holds the `Common` demos.
- Adding a kitchen-sink section has twice changed the corner pixels of `overlay-dialog.png` and `overlay-alert-dialog.png` (archived app-shell-restyle impl-review F1, shared-app-components impl-review F4). Restoring the old PNGs fails at `threshold: 0`, so the expected PNG delta must allow these two.
- `GenerateMealPlanJob::handle` (`app/Jobs/GenerateMealPlanJob.php:77-104`) runs `MealPlanGenerator::generate` and then `MealIngredientScaler::scale`. It stores `generation_meta` = `{generated_at, regenerate, version: 'scaled-v1', scaling}`. The scaler calls the bound `IngredientRefiner`, and `FakeIngredientRefiner` is deterministic.
- The Dashboard takes no data props (`routes/web.php:24`). A plan seeded for `e2e-planner-visual` therefore leaves the `dashboard-*` baselines unchanged.
- The Index rows have no link to the plan; "Szczegóły" is in `ActionDropdown`. S-10 restyles the Index in parallel, so the visual spec must not depend on Index UI. The Index prop `mealPlans` (`app/Http/Controllers/MealPlanController.php:67-68`) carries each plan's `links.self`.

## What We're NOT Doing

- `ActionDropdown.vue`: its `red-*` literals on "Usuń" are shared with the Index rows and belong to S-10. It is not added to `lint-tokens.paths` here.
- `StatusTag` and `CategoryBadge`: already migrated in S-07.
- Other pages' `#header` usage: Dashboard and the 7 unmigrated pages keep their headers until their own slices. `AuthenticatedLayout` keeps the `#header` slot API.
- A JS number-formatting helper (`design-system.md` §4.5): amounts arrive formatted from PHP, kcal is an integer, and dates already use `toLocaleDateString('pl-PL')`.
- Changes to the generation pipeline, polling, `SLOW_AFTER_MS`, the regenerate request or Inertia props.
- Rewriting the grid as cards or divs: the `<table>` stays because E2E and screen readers rely on its column headers.
- Breadcrumbs in `PageHeader`: the slot exists per the contract but Show does not fill it.
- Dark mode (D-1).

## Implementation Approach

The order follows `/10x-ui` and the S-08 precedent: contract before pixels, then the state matrix and the gate.
1. Phase 1 builds the two `Common` components and their kitchen-sink demos.
2. Phase 2 migrates the two domain components, because they own the E2E-visible structure (table, collapsible, list). It also updates the one selector that changes meaning.
3. Phase 3 assembles the page from these parts.
4. Phase 4 makes the page reproducible for screenshots and records the baselines.

Each phase ends green on build, `lint:tokens` and the affected tests.

## Environment (prerequisite for every phase)

Every `sail`, E2E and visual command must run against this worktree's code. Sail serves the checkout it was started from, and `scripts/visual-test.sh` mounts the checkout it runs from. The worktree `.claude/worktrees/meal-plan-show-ui` starts with no `.env`, `vendor/` or `node_modules/`.

Set up the stack as in the archived app-shell-restyle plan:
1. Copy `.env` from the main checkout.
   - Give it its own `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT` and `FORWARD_MAILPIT_DASHBOARD_PORT`, and set `MAILPIT_URL` to match.
   - Keep `AI_SCALING_DRIVER=fake`, `MAIL_MAILER=smtp` and `MAIL_HOST=mailpit`.
2. Install `vendor/` through the Sail Composer container, not host PHP.
3. Run `vendor/bin/sail up -d`, `vendor/bin/sail npm ci` and `vendor/bin/sail artisan migrate`.

Run the visual gate as `VISUAL_DOCKER_NETWORK=meal-plan-show-ui_sail VISUAL_BASE_URL=http://laravel.test npm run test:visual`.

Before Phase 1, both of these must hold:
- `vendor/bin/sail ps` lists `meal-plan-show-ui-*` containers;
- `curl -s -o /dev/null -w '%{http_code}' http://localhost:${APP_PORT}/login` prints `200`.

## Critical Implementation Details

**One "Regeneruj" per state.** E2E finds the button with a non-exact `getByRole('button', { name: 'Regeneruj' })` (`meal-plan.spec.ts:62`), and a second match fails in strict mode. Each state must show exactly one visible control named "Regeneruj":

| State | Where "Regeneruj" appears |
| --- | --- |
| Done with meals | `PageHeader` actions |
| Error | Inside the error `Alert` |
| Done without meals | The `EmptyState` CTA |

So the header button is shown only when the plan is done and has meals. Today it shows for `done` and `error` (`Show.vue:174`), which already duplicates it in the error state. The row menu's "Regeneruj" is a `menuitem` inside a closed dropdown, so it does not count.

**Static category classes.** Tailwind only generates classes it finds as complete strings. The cell bar therefore comes from a static map of full class names (category → `border-meal-breakfast-foreground` etc.), never from string interpolation.

## Phase 1: Shared PageHeader and EmptyState

### Overview

Build the two deferred `Common` components from `design-system.md` §5.2. Make the layout's fallback brand non-heading so that a page using `PageHeader` has one `<h1>`. Demo both components in the kitchen sink.

### Changes Required:

#### 1. PageHeader

**File**: `resources/js/Components/Common/PageHeader.vue` (new)

**Intent**: Page title block rendered at the top of a page's content, replacing hand-written headers and the `#header` slot for migrated views.

**Contract**:
- Props: `title: string`, `description?: string` and `as?: 'h1' | 'h2'` (default `'h1'`; only the kitchen sink passes `'h2'`).
- Slots: `actions` (right-aligned, wraps on narrow screens) and `breadcrumbs` (above the title, only rendered when filled).
- Renders exactly one title heading (the `as` element) with `text-2xl font-semibold tracking-tight` and a description `<p>` in `text-sm text-muted-foreground`.
- Stacks vertically below `sm` and goes title-left, actions-right from `sm`.
- Tokens only. `lint:tokens` already scans `Components/Common/`.

#### 2. EmptyState

**File**: `resources/js/Components/Common/EmptyState.vue` (new)

**Intent**: Shared "nothing to show" block (`design-system.md` §5.2) for the Show page's done-without-meals state now, and for the S-10/S-11 lists later.

**Contract**:
- Props: `icon` (a lucide component), `title: string` and `description?: string`.
- Slot: `action` for the CTA.
- Centered on a `bg-card` bordered block. The icon is `size-10 text-muted-foreground` and `aria-hidden`; the title is `text-lg font-semibold` but not a heading level above `h2`; the description is `text-sm text-muted-foreground`.
- Tokens only.

#### 3. Layout fallback brand

**File**: `resources/js/Layouts/AuthenticatedLayout.vue`

**Intent**: When a page does not fill `#header`, the sticky bar shows the brand as plain text, so the page's own `PageHeader` `<h1>` is the only heading level 1.

**Contract**: The `v-else` fallback at `:71` becomes a non-heading element (same visual classes, text "Menu Maker"). The `header` slot API is unchanged.

#### 4. Kitchen-sink demos

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: CLAUDE.md requires a demo with states for every new shared pattern.

**Contract**: The "Komponenty aplikacji" section gains:
- a `PageHeader` with title, description and actions (a `StatusTag` plus an outline `Button`);
- a `PageHeader` with title only;
- an `EmptyState` with icon, title, description and a CTA `Button`.

The kitchen sink already renders an `<h1>Design system</h1>`, which `tests/e2e/visual/design-system.spec.ts:27` uses with `level: 1`. The demos must therefore not add a second `<h1>`. `PageHeader` gets an optional prop `as?: 'h1' | 'h2'` (default `'h1'`) for its title element, and both kitchen-sink demos pass `as="h2"`. Pages never pass it.

The demos must not contain `ActionDropdown` or any button whose name contains a locator the visual spec clicks non-exactly: `Otwórz menu` (`design-system.spec.ts:125`; it is also ActionDropdown's trigger name, `ActionDropdown.vue:6`), `Otwórz dialog` (`:80`), `Otwórz panel` (`:116`), `Otwórz potwierdzenie` (`:89`). A duplicate match fails in strict mode. The same applies to the Phase 2 "Jadłospis" section.

#### 5. Roadmap ownership of the shared components

**File**: `context/foundation/roadmap.md`

**Intent**: S-10 and S-11 run in parallel and their Unknowns still say "the first of S-09/S-10/S-11 that needs them builds `PageHeader`/`EmptyState`". Without a claim, a parallel session could build a second `PageHeader.vue`.

**Contract**: At the start of Phase 1, replace that Unknown in S-09, S-10 and S-11 with "`PageHeader` i `EmptyState` buduje S-09 (`meal-plan-show-ui`); S-10 i S-11 ich używają." Change nothing else in those items.

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Token scan and its self-test pass: `vendor/bin/sail npm run lint:tokens && bash scripts/lint-tokens.test.sh`
- Kitchen-sink route test passes: `vendor/bin/sail pest --filter=DesignSystemPageTest`
- App-shell E2E still passes (re-seed first): `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npx playwright test tests/e2e/app-shell.spec.ts --project=chromium --project=firefox`

#### Manual Verification:

- On `/dev/design-system`, the PageHeader demos wrap their actions below the title at 390px and sit side by side at desktop width, and the EmptyState CTA has a visible focus ring on Tab.
- The kitchen sink's heading outline (browser devtools accessibility tree) still has exactly one level-1 heading.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Grid and Shopping List on Tokens

### Overview

Migrate `MealPlanGrid` and `ShoppingList` to tokens and primitives (`design-system.md` §5.3), swap `<details>` for `Collapsible`, update the one E2E selector this changes, and add a "Jadłospis" kitchen-sink section with fixture data.

### Changes Required:

#### 1. MealPlanGrid

**File**: `resources/js/Components/MealPlans/MealPlanGrid.vue`

**Intent**: The week grid uses tokens and matches §5.3, while keeping the table semantics E2E and screen readers rely on.

**Contract**:
- Stays a `<table data-testid="meal-plan-grid">` inside an `overflow-x-auto` bordered `bg-card` wrapper:
  - `<th scope="col">` column headers (Dzień, Śniadanie, Obiad, Kolacja) on a `bg-muted` row;
  - `<th scope="row">` day cells;
  - exactly one `<a>` (the recipe `Link`) per filled `td`.
- `min-w-[40rem]` becomes `min-w-160`.
- Filled cells wrap their content in a block with `border-l-4` and the category's `border-meal-{category}-foreground` class, taken from a static map (see Critical Implementation Details). Empty cells show "—" in `text-muted-foreground`.
- Text:
  - recipe link uses `text-foreground`, with `hover:text-primary hover:underline` and a visible `focus-visible` ring;
  - "(usunięty)" and the kcal/servings line use `text-xs text-muted-foreground`, with kcal in `tabular-nums`;
  - the visible texts stay unchanged.
- Ingredients:
  - a `Collapsible` (default closed) whose `CollapsibleTrigger` is a `<button>` labelled "Składniki (n)", in `text-primary`, with a `ChevronDown` (`size-4`, `aria-hidden`, rotated while open, `motion-reduce:` respected);
  - the `<ul data-testid="meal-ingredients">` sits in `CollapsibleContent`, with items still rendered as `{name} — {display}`.
- Skeleton mode:
  - draws 7 rows of `ui/skeleton` blocks in place of the grey `div`s;
  - the table carries `aria-busy="true"`;
  - the skeleton row markup contains no links.
- No `dark:` and no palette literals.

#### 2. ShoppingList

**File**: `resources/js/Components/MealPlans/ShoppingList.vue`

**Intent**: Shopping list as a `Card` with right-aligned numeric quantities (§4.5, §5.3).

**Contract**:
- Built from `Card`, `CardHeader` and `CardTitle` ("Lista zakupów", with a `ShoppingBasket` icon, `size-4`, `aria-hidden`) plus `CardContent`.
- Items stay `<li>` inside `<ul data-testid="shopping-list">`, with token dividers (`divide-border`). The name is `font-medium`; `display` is right-aligned, `tabular-nums` and `whitespace-nowrap`.
- The empty text and the "Bez składników…" note use `text-muted-foreground`. Texts are unchanged.
- Props and exported types (`ShoppingListPayload`, `ShoppingListItem`) are unchanged.

#### 3. E2E selector (semantic change, reviewed on purpose)

**File**: `tests/e2e/meal-plan.spec.ts`

**Intent**: The ingredient disclosure changes from native `<details>`/`<summary>` to a button with `aria-expanded`. The spec follows the new meaning rather than a structural tag (`design-system.md` §8, roadmap S-09 Risk).

**Contract**:
- Line 28 becomes a role-based click on the first trigger, scoped to the grid: `grid.getByRole('button', { name: /^Składniki/ }).first()`.
- Add an assertion that it has `aria-expanded="true"` after the click.
- Leave line 29 (`Mąka — 200 g`) and every other locator unchanged.

#### 4. Kitchen-sink "Jadłospis" section

**Files**: `resources/js/Pages/Dev/DesignSystem.vue`, `tests/e2e/visual/design-system.spec.ts`

**Intent**: Demo the migrated domain components in their states with deterministic fixture data (§5.4: grid `pending/processing` skeleton, `done`).

**Contract**:
- A new `<section aria-labelledby="ds-jadlospis">` titled "Jadłospis", placed after "Komponenty aplikacji". It contains:
  - a `MealPlanGrid` with a fixture `startDate` of `2026-06-15` and 21 fixture meals, including one deleted recipe and one meal with ingredients;
  - one fixture slot left empty, to show "—";
  - a skeleton `MealPlanGrid`;
  - a `ShoppingList` with items and `recipes_without_ingredients`;
  - an empty `ShoppingList`.
- Fixtures live in the page's script. No backend change.
- `sections` in the visual spec gains `{ name: 'Jadłospis', slug: 'jadlospis' }`. Baselines are recorded in Phase 4, not here.

#### 5. Token scan list

**File**: `scripts/lint-tokens.paths`

**Intent**: Every migrated file is scanned.

**Contract**: Append `resources/js/Components/MealPlans/MealPlanGrid.vue` and `resources/js/Components/MealPlans/ShoppingList.vue`.

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Token scan passes with the two new paths: `vendor/bin/sail npm run lint:tokens`
- The two files have no literals left: `grep -nE '#[0-9a-fA-F]{3,8}\b|-\[[0-9.]+(px|rem)\]|dark:|\b(bg|text|border|ring|divide)-(gray|red|blue|green|yellow|amber|indigo|white|black)\b' resources/js/Components/MealPlans/MealPlanGrid.vue resources/js/Components/MealPlans/ShoppingList.vue` prints nothing
- No `<details>` remains in the grid: `! grep -n '<details\|<summary' resources/js/Components/MealPlans/MealPlanGrid.vue`
- Meal plan E2E passes in both browsers with the updated selector (re-seed first): `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npx playwright test tests/e2e/meal-plan.spec.ts --project=chromium --project=firefox`

#### Manual Verification:

- The diff of `tests/e2e/meal-plan.spec.ts` is reviewed as a change of meaning, not just a fix. It touches only the disclosure line plus the `aria-expanded` assertion, and the reviewer agrees that "button that expands ingredients" is the intended semantics.
- In the browser:
  - the category bars are clearly visible in all three colours;
  - "Składniki (n)" opens and closes with Enter and Space, with a visible focus ring;
  - the chevron rotates, and does not animate with reduced motion enabled.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Show View on Tokens

### Overview

Assemble the page from the new parts: `PageHeader` in the content area, a two-column layout from `xl`, `Alert`s for warning, error and slow generation, and `EmptyState` for a plan without meals. Then add the page to the scan and update the design-system doc.

### Changes Required:

#### 1. Show page

**File**: `resources/js/Pages/MealPlans/Show.vue`

**Intent**: The north-star view carries no literals and shows every state on shared variants. Behaviour stays identical: polling, slow timer, regenerate request, toasts.

**Contract**:
- **Header**:
  - Drop `<template #header>` and render `PageHeader` first in the default slot, with `title="Jadłospis"` and `:description="dateRange"`.
  - Actions, in order:
    1. `StatusTag`;
    2. "Pobierz PDF", an outline `Button as-child` around the existing `<a target="_blank" rel="noopener">`, icon `FileDown` (§4.7);
    3. "Regeneruj", outline, icon `RefreshCw`, shown only when done with meals (see Critical Implementation Details);
    4. `ActionDropdown`, unchanged.
  - `<Head>` title unchanged.
- **Body layout**: `gap-6` rhythm.
  - In the done-with-meals state: the AI warning (when present) above a container that is one column below `xl`. From `xl` it is a row with the grid `min-w-0 flex-1` and the `ShoppingList` at `xl:w-72 shrink-0`.
  - At a 1280px viewport with the expanded sidebar, the grid must not scroll horizontally.
- **Generating**:
  - Keep `<p role="status">Generujemy jadłospis…</p>` (exact text, ellipsis character) in `text-muted-foreground`, with a `Loader2` `animate-spin motion-reduce:animate-none` icon (`aria-hidden`).
  - The slow notice becomes `Alert variant="warning"` with the same text.
  - Then the skeleton grid.
- **Error**: `Alert variant="destructive"` with an `AlertTitle` ("Generowanie nie powiodło się"), an `AlertDescription` holding `errorMessage`, and the "Regeneruj" `Button`.
- **Done without meals**: `EmptyState` with the `CalendarDays` icon and the title "Brak posiłków". The description is "Ten jadłospis nie ma jeszcze posiłków." and the `action` slot holds the "Regeneruj" `Button`.
- **AI warning**:
  - `Alert variant="warning"` keeps `data-testid="scaling-notice"` and renders each notice.
  - The "Przejdź do profilu rodziny" `Link` keeps its condition (`scaling.status === 'as_written'`) and uses token classes (`font-medium underline`, with a focus-visible ring).
- **Back link**: "Wróć do jadłospisów" stays as an outline `Button` with `ArrowLeft`.
- **Icons and literals**:
  - Icons are `size-4`; remove the `mr-2 h-4 w-4` overrides where `Button` already spaces its icon.
  - No palette, `dark:` or arbitrary literals remain.
  - The `as any` cast on `StatusTag` may stay; types are out of scope.

#### 2. Token scan list

**File**: `scripts/lint-tokens.paths`

**Intent**: The migrated view is scanned.

**Contract**: Append `resources/js/Pages/MealPlans/Show.vue`.

#### 3. Design-system doc

**File**: `context/foundation/design-system.md`

**Intent**: The contract reflects what was built and decided.

**Contract**:
- §5.2: `PageHeader` and `EmptyState` move from "Odroczony" to built. Record the props and slots as implemented, including the `as` heading-level prop from Phase 1. Note that `PageHeader` renders in the content area and that the layout fallback is non-heading.
- §5.3: the `MealPlanGrid` bar is `border-l-4 border-meal-*-foreground`, with the reason that the pale fill is invisible as a line. `ShoppingList` sits beside the grid from `xl`.

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Token scan passes with `Show.vue` added: `vendor/bin/sail npm run lint:tokens`
- No literals remain in the view: the Phase 2 `grep -nE` pattern on `resources/js/Pages/MealPlans/Show.vue` prints nothing
- Show-page Pest coverage still passes: `vendor/bin/sail pest tests/Feature/MealPlanControllerTest.php`
- The full E2E suite passes in both browsers (re-seed first): `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`

#### Manual Verification:

- Each state is checked in the browser:
  - done with meals: header actions, grid and list side by side at 1280px, stacked at 1024px and 390px, no horizontal page scroll;
  - pending/processing: status line, skeleton grid, and the slow warning after 75 s with the worker stopped (`vendor/bin/sail stop queue`);
  - error, with a single "Regeneruj": force it by stopping the queue and failing the job, or set `status=error` in tinker on a local plan;
  - done without meals: `EmptyState` with a working "Regeneruj" CTA;
  - AI warning: plan generated with no family members, so the `as_written` notice and the family link show.
- Exactly one level-1 heading on the page (accessibility tree), and the sticky bar shows "Menu Maker" as plain text.
- "Pobierz PDF" opens the PDF in a new tab; "Regeneruj" opens the confirm dialog and regenerates.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: Visual Gate for the Meal Plan View

### Overview

Seed a deterministic done plan for the visual user, screenshot the real page at desktop (`xl`, first cell expanded) and mobile, record the new kitchen-sink baselines, and review the exact PNG delta.

### Changes Required:

#### 1. Deterministic visual plan

**File**: `database/seeders/E2eMealPlanSeeder.php`

**Intent**: `e2e-planner-visual@example.com` gets one reproducible done plan, without the queue and without any AI call, so the real page can be screenshotted at `threshold: 0`.

**Contract**:
- Only for the visual user, after the existing ingredient, calorie and family syncs and after `mealPlans()->delete()`:
  1. **Recipe names and servings**: update the user's first 7 recipes per category (by `id`) in place, giving them a fixed list of 21 Polish names and fixed `servings`, held in a constant, 7 per category. Include one long name, to show wrapping. The grid prints `· {servings} porcji` in every cell, and `RecipeFactory` draws `servings` from 1–8. CI starts from a fresh DB, so unpinned servings would change the page baselines on every run. Calories are already pinned by `syncPinnedCalories`. Update in place; do not `firstOrCreate` by name, which would add recipes next to the random ones.
  2. **Plan**: create a `MealPlan` with `start_date` `2026-06-15` (the visual specs' fixed clock) and `end_date` `2026-06-21`.
  3. **Meals**: create 21 meals with day offset = position in that id order, mirroring `MealPlanGenerator::generate`'s `Meal::create` shape.
  4. **Scaling**: run `MealIngredientScaler::scale` resolved with `FakeIngredientRefiner`, whatever `AI_SCALING_DRIVER` says, for example `app()->make(MealIngredientScaler::class, ['refiner' => new FakeIngredientRefiner])`.
  5. **Finish**: set `status` to `done` and `generation_meta` to the same shape `GenerateMealPlanJob::handle` writes (`generated_at` fixed to the plan start, `regenerate: false`, `version: 'scaled-v1'`, `scaling: $outcome->toArray()`).
- The chromium and firefox planners and the cooks behave exactly as before. In particular, the planners still end with zero plans.
- Re-running stays idempotent.
- Update the class docblock to mention the visual plan.

#### 2. Seeder test

**File**: `tests/Unit/Seeders/E2eMealPlanSeederTest.php` (new)

**Intent**: Guard the fixture invariants that both the E2E and visual specs depend on.

**Contract**: The file declares `uses(RefreshDatabase::class)`; `tests/Pest.php:20` applies only `TestCase`. Before both seeder runs, the test sets `config(['services.ingredient_refiner.driver' => 'openrouter'])` and calls `Http::preventStrayRequests()`. Setting the `AI_SCALING_DRIVER` env does nothing here: `phpunit.xml:22` pins it to `fake`, and the binding reads the config key (`AppServiceProvider.php:31-48`). Then:
- the visual user has exactly one `done` plan, with 21 meals, start `2026-06-15`, and the fixed recipe names and servings;
- the shopping list (via `ShoppingListBuilder`/`ShoppingListResource`) displays `42 szt.`, `4,2 kg` and `2,1 l`;
- the chromium and firefox planners have zero plans.

#### 3. Real-page visual spec

**File**: `tests/e2e/visual/meal-plan-show.spec.ts` (new)

**Intent**: Lock the composed north-star page: header placement, two-column `xl` layout, cell bars, expanded `Collapsible`, `Card` list, mobile stacking.

**Contract**:
- Same skeleton as `tests/e2e/visual/app-shell.spec.ts`:
  - `PLAYWRIGHT_VISUAL_DOCKER` skip;
  - `page.clock.setFixedTime(new Date('2026-06-15T10:00:00'))` in `beforeEach`;
  - login as `e2e-planner-visual@example.com`;
  - `expectFigtreeLoaded`;
  - `page.mouse.move(0, 0)` before each full-page screenshot.
- Resolve the plan URL from the Index's Inertia page props, not from Index UI, which S-10 restyles in parallel. Inertia 3 (with SSR) writes the initial page JSON to `<script data-page="app" type="application/json">`, not to `#app[data-page]`, and it holds only the initial load. Right after `page.goto('/meal-plans')`, read `JSON.parse(await page.locator('script[data-page="app"]').textContent()).props.mealPlans.data[0].links.self` (an absolute URL) and `page.goto()` it.
- Before screenshotting, assert `getByRole('heading', { level: 1, name: 'Jadłospis' })` and the date range text `15.06.2026 – 21.06.2026`.
- Screenshots:
  - `meal-plan-show-desktop.png`: 1280px-wide viewport, first "Składniki" trigger expanded, full page;
  - `meal-plan-show-mobile.png`: 390×844, full page.

#### 4. Baselines

**Files**: `tests/e2e/__screenshots__/visual/**`

**Intent**: Record baselines deliberately, through the wrapper only.

**Contract**:
- First, sync with develop: `git merge develop`. Parallel slices touch the same files: S-13 (`auth-profile-ui`) changes `DesignSystem.vue` ("Komponenty aplikacji"), `design-system.spec.ts`, `design-system.md` and kitchen-sink PNGs; S-12 (`family-members-ui`) changes `E2eMealPlanSeeder.php` and the CLAUDE.md E2E bullet. Resolve conflicts and take develop's version of every conflicting PNG. Re-run the seeder, then judge the expected delta and the §7 counts against that merge.
- Run `npm run test:visual -- --update-snapshots` after `vendor/bin/sail npm run build` (no `public/hot`) and after the seeder.
- New: `section-jadlospis.png`, `meal-plan-show-desktop.png`, `meal-plan-show-mobile.png`.
- Changed: `section-komponenty-aplikacji.png` (Phase 1 demos).
- Allowed to change, corner pixels only: `overlay-dialog.png`, `overlay-alert-dialog.png`.
- Nothing else changes.

#### 5. Docs

**Files**: `context/foundation/design-system.md` (§7.2, §7.3), `CLAUDE.md` (Visual gate bullet)

**Intent**: Docs match the gate.

**Contract**:
- §7.2: 14 kitchen-sink sections, including "Jadłospis".
- §7.3: the zrzut count, plus the new spec `meal-plan-show.spec.ts` with its 2 screenshots and the seeded visual plan.
- CLAUDE.md: note that the visual user now owns a seeded done plan (fixed start `2026-06-15`).

### Success Criteria:

#### Automated Verification:

- PHP suite, style and static analysis pass: `vendor/bin/sail composer test`, `vendor/bin/sail composer pint-test`, `vendor/bin/sail composer phpstan`
- Seeder test passes: `vendor/bin/sail pest tests/Unit/Seeders/E2eMealPlanSeederTest.php`
- Seeder runs twice without error: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder` (twice)
- Build and token scan pass: `vendor/bin/sail npm run build && vendor/bin/sail npm run lint:tokens`
- Baseline generation matches the expected delta. The branch contains current develop (`git merge-base --is-ancestor develop HEAD` succeeds). After `VISUAL_DOCKER_NETWORK=meal-plan-show-ui_sail VISUAL_BASE_URL=http://laravel.test npm run test:visual -- --update-snapshots`, `git status --porcelain -uall tests/e2e/__screenshots__` shows:
  - exactly 3 `??`: `section-jadlospis.png`, `meal-plan-show-desktop.png`, `meal-plan-show-mobile.png`;
  - ` M` only for `section-komponenty-aplikacji.png` and at most `overlay-dialog.png` and `overlay-alert-dialog.png`.
- The visual gate passes twice in a row without updates: `npm run test:visual && npm run test:visual` (with the same `VISUAL_*` variables)
- The full E2E suite still passes (re-seed first): `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`

#### Manual Verification:

- Each new or changed PNG is reviewed in the diff:
  - `meal-plan-show-desktop`: `PageHeader` at the top of the content, grid and list side by side with no horizontal scrollbar, three bar colours, first cell's ingredients open, quantities right-aligned;
  - `meal-plan-show-mobile`: header actions wrap, the list sits under the grid, the grid scrolls inside its card;
  - `section-jadlospis`: grid, skeleton, list and empty list;
  - `section-komponenty-aplikacji`: both `PageHeader` demos and `EmptyState`;
  - any `overlay-*` change limited to the dialog's rounded corners.
- The 7-state matrix is confirmed:

  | State | Evidence |
  | --- | --- |
  | default | `meal-plan-show-desktop`, `section-jadlospis` |
  | hover | recipe link hover, checked manually |
  | focus-visible | "Składniki" trigger and header buttons, checked manually in Phase 2–3 |
  | disabled | N/A: the view has no disabled control; the row menu's disabled items are S-10's |
  | loading | skeleton in `section-jadlospis` |
  | error | destructive `Alert` state, checked manually in Phase 3 |
  | empty | `EmptyState` in `section-komponenty-aplikacji` and the empty list in `section-jadlospis` |

- `CLAUDE.md` and `design-system.md` read correctly, and `.claude/skills/10x-ui/references/ui-quality-checklist.md` is walked through before merge.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- `E2eMealPlanSeederTest`:
  - repeatable;
  - visual plan is deterministic (status, 21 meals, start date, names, shopping list `display` strings);
  - other planners keep zero plans;
  - no AI call under a non-fake driver.
- No JS unit runner exists (`package.json` has no vitest or jest). The component behaviour is covered by Playwright.

### Integration Tests:

- `tests/e2e/meal-plan.spec.ts` (chromium and firefox): generate, the 21-link grid, column headers, the Collapsible ingredient disclosure (new role-based locator plus `aria-expanded`), shopping list amounts, PDF, regenerate through the pending state.
- `tests/Feature/MealPlanControllerTest.php`: props unchanged. No edits expected.
- Visual: `design-system.spec.ts` (with "Jadłospis") and `meal-plan-show.spec.ts` (desktop and mobile) at zero tolerance.

### Manual Testing Steps:

1. Generate a plan from `/meal-plans` and watch the status line and skeleton, then the grid and list appearing beside each other at 1280px.
2. Stop the queue (`vendor/bin/sail stop queue`), regenerate and wait 75 s for the slow warning. Restart it with `vendor/bin/sail start queue`.
3. Delete all family members, regenerate, and confirm the warning `Alert` with "Przejdź do profilu rodziny".
4. Force an error state locally and confirm a single "Regeneruj" inside the destructive `Alert`.
5. Tab through the page: header actions, recipe links, "Składniki" triggers and the back link all show a focus ring.

## Performance Considerations

None beyond today. `Collapsible` unmounts closed content, which slightly reduces DOM size compared with `<details>`.

## Migration Notes

No schema or data migration. The seeder change affects only the visual user's fixture data in local, CI and testing environments, and the seeder refuses to run in production (`E2eMealPlanSeeder.php:46-48`).

## References

- Roadmap item: `context/foundation/roadmap.md` (S-09, `meal-plan-show-ui`)
- Design contract: `context/foundation/design-system.md` §4.4–4.7, §5.2–5.4, §7, §8
- Precedent: `context/archive/2026-11-15-app-shell-restyle/plan.md` (environment, real-page visual spec, PNG-delta criteria), `context/archive/2026-11-15-shared-app-components/plan.md` (kitchen-sink section and gate phase)
- E2E: `tests/e2e/meal-plan.spec.ts:19-71`; visual: `tests/e2e/visual/app-shell.spec.ts`, `tests/e2e/visual/design-system.spec.ts:9-41`
- Pipeline: `app/Jobs/GenerateMealPlanJob.php:77-104`, `app/Services/MealPlanGenerator.php:65-110`, `app/Services/IngredientScaling/MealIngredientScaler.php:31`, `app/Services/IngredientScaling/Drivers/FakeIngredientRefiner.php`
- Formatting: `app/Services/ShoppingList/QuantityFormatter.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Shared PageHeader and EmptyState

#### Automated

- [x] 1.1 Build passes — ece4dfe
- [x] 1.2 Token scan and its self-test pass — ece4dfe
- [x] 1.3 Kitchen-sink route test passes — ece4dfe
- [x] 1.4 App-shell E2E still passes — ece4dfe

#### Manual

- [ ] 1.5 PageHeader and EmptyState demos wrap, align and focus correctly
- [ ] 1.6 Kitchen sink still has exactly one level-1 heading

### Phase 2: Grid and Shopping List on Tokens

#### Automated

- [x] 2.1 Build passes
- [x] 2.2 Token scan passes with the two new paths
- [x] 2.3 The two files have no literals left
- [x] 2.4 No details element remains in the grid
- [x] 2.5 Meal plan E2E passes in both browsers with the updated selector

#### Manual

- [ ] 2.6 E2E selector diff reviewed as a change of meaning
- [ ] 2.7 Category bars visible and Składniki disclosure keyboard-accessible

### Phase 3: Show View on Tokens

#### Automated

- [ ] 3.1 Build passes
- [ ] 3.2 Token scan passes with Show.vue added
- [ ] 3.3 No literals remain in the view
- [ ] 3.4 Show-page Pest coverage still passes
- [ ] 3.5 The full E2E suite passes in both browsers

#### Manual

- [ ] 3.6 Every view state checked in the browser
- [ ] 3.7 Exactly one level-1 heading and plain-text brand in the bar
- [ ] 3.8 PDF and regenerate still work

### Phase 4: Visual Gate for the Meal Plan View

#### Automated

- [ ] 4.1 PHP suite, style and static analysis pass
- [ ] 4.2 Seeder test passes
- [ ] 4.3 Seeder runs twice without error
- [ ] 4.4 Build and token scan pass
- [ ] 4.5 Baseline generation matches the expected delta
- [ ] 4.6 The visual gate passes twice in a row without updates
- [ ] 4.7 The full E2E suite still passes

#### Manual

- [ ] 4.8 Each new or changed PNG reviewed in the diff
- [ ] 4.9 7-state matrix confirmed
- [ ] 4.10 Docs read correctly and ui-quality-checklist walked through
