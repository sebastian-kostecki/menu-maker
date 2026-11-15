# Dashboard with Real Data Implementation Plan

## Overview

`/dashboard` today is a route closure that renders `Dashboard.vue` with hard-coded numbers (`12`, `3`, "2 dni temu"), an obsolete "Ostatni PDF" card and an emoji welcome box. This change (roadmap S-16, design system DS-11) replaces the closure with an invokable `DashboardController`. The controller passes owner-scoped data: the recipe and meal plan counts, the newest ready plan and any recipe shortfall.

`Dashboard.vue` is rebuilt on `PageHeader`, `Card`, `Alert` and `EmptyState` in three states:
- a first-run empty state;
- a shortfall warning when a week cannot be generated yet;
- the stats with the "Ostatni jadłospis" card and the quick actions.

The full and empty states are pixel-gated.

## Current State Analysis

- **Route.** `routes/web.php:23-25` is a closure, `Inertia::render('Dashboard')`, behind `auth`, named `dashboard`. It passes no props. The login and registration redirects target `route('dashboard')`:
  - `app/Http/Controllers/Auth/AuthenticatedSessionController.php:36`
  - `RegisteredUserController.php:50`
  - `ConfirmablePasswordController.php:39`

  About ten E2E specs `waitForURL('**/dashboard')` after logging in.
- **Page.** `resources/js/Pages/Dashboard.vue`:
  - It renders its own `<h1>` in the layout's `#header` slot.
  - It has three raw `div` cards with fake values (`:22-70`), using the off-system icons `Calendar` and `FileText`.
  - The quick actions card (`:73-92`) links "Dodaj przepis" to `/recipes/create` (a hard-coded URL) and wraps an outline `Button` in `GenerateMealPlanDialog`.
  - The welcome box at `:95-102` has an emoji and an `h3`.
  - It is already listed in `scripts/lint-tokens.paths:11`.
- **Reusable backend.**
  - `Recipe::scopeForUser(int)` is at `app/Models/Recipe.php:43`. The model uses SoftDeletes, so a plain count excludes trashed recipes.
  - `MealPlan::scopeOwnedBy(User)` is at `app/Models/MealPlan.php:54`. Status is a plain string: `pending|processing|done|error`. There is a unique index on `(user_id, start_date)`.
  - `MealPlanGenerator::missingRecipesPerCategory(int $userId)` (`app/Services/MealPlanGenerator.php:22-35`) returns `[category => lacking]` for short categories only. `shortfallMessages(array)` (`:43-58`) turns that into the same Polish sentences `GenerateMealPlanRequest` (`:81-86`) rejects with: "Za mało przepisów w kategorii Obiad: masz 3, potrzeba 7. Dodaj jeszcze 4.".
  - `MealPlanResource` (`app/Http/Resources/MealPlanResource.php:33-35, 107-110`) emits `start_date` and `end_date` as `Y-m-d`, plus `status`, `links.self`, and `links.pdf`, which is set only when the status is `done`. `MealPlanController@show` resolves it with `(new MealPlanResource($plan))->resolve()`.
- **Reusable frontend.**
  - `Common/PageHeader` has props `title`, `description` and `as`, and slots `breadcrumbs`, `description` and `actions`.
  - `Common/EmptyState` has props `icon`, `title`, `description` and `headingLevel` (2|3, default 2), takes its CTA in the default slot, and leaves padding to the caller.
  - `ui/card` provides `Card`, `CardHeader`, `CardTitle`, `CardDescription`, `CardContent` and `CardFooter`.
  - `ui/alert` has a `warning` variant.
  - `lib/format.js` has `formatNumber`, `formatDateRange(startYmd, endYmd)` (parses `Y-m-d` from its parts) and `pluralize`.
  - `GenerateMealPlanDialog` needs no page props. It takes a single-element trigger in its default slot and posts `start_date` to `meal-plans.store`.
  - The PDF link pattern on `MealPlans/Show.vue:9-14` is `<Button variant="outline" as-child><a :href="links.pdf" target="_blank" rel="noopener"><FileDown/>Pobierz PDF</a></Button>`.
- **Tests and gate.**
  - There is no dashboard feature test. Only the auth tests and `TrustedProxyTest` hit `/dashboard`.
  - `tests/e2e/visual/app-shell.spec.ts:25-45` screenshots `dashboard-desktop.png`, `dashboard-mobile.png` and `dashboard-mobile-drawer.png` for `e2e-planner-visual@example.com`, with the browser clock pinned to `2026-06-15T10:00:00` and timezone UTC. The match is pixel-exact (`playwright.config.ts:31`).
  - The visual user (`database/seeders/E2eMealPlanSeeder.php`) has 21 pinned recipes (7 per category) and four plans:
    - done 2026-06-15…21
    - done 2026-06-08…14
    - processing 2026-06-22…28 (the newest start date, but not done)
    - error 2026-06-01…07
  - No seeded user is suitable for the empty state: `recipe-form.spec.ts` creates recipes for the cook users.
- **Docs drift.** `.ai/core/ui-plan.md` §2.1 still says path `/`, "ostatni wygenerowany PDF" and `QuickActionButton` with `aria-pressed`. All three are superseded: by the `/dashboard` URL, by PDF-on-demand (the PDF columns were dropped 2026-11-14), and because `aria-pressed` is for toggles only.

## Desired End State

A logged-in user who opens `/dashboard` sees one of three states.

1. **No recipes and no meal plans (first run).** The `PageHeader` "Pulpit" and below it a single `EmptyState`: icon `ChefHat`, an `h2` title and a description of the first step, with the CTA "Dodaj pierwszy przepis" linking to `recipes.create`. There are no zero-valued cards and no quick actions.
2. **Otherwise:**
   - **Shortfall alert.** Shown only when at least one category has fewer than 7 recipes: a `warning` `Alert` titled with the fact that a week cannot be generated yet. It lists each `shortfallMessages()` sentence and has the link "Dodaj brakujące przepisy" to `recipes.create`.
   - **A 2-column card grid (1 column below `lg`):**
     - "Przepisy": the owned non-deleted recipe count, `ChefHat` icon.
     - "Jadłospisy": all owned plans of any status, `CalendarDays` icon.
     - "Ostatni jadłospis": the newest `done` plan by `start_date desc, id desc`. It shows the date range via `formatDateRange`, plus "Otwórz jadłospis" (`links.self`) and "Pobierz PDF" (`links.pdf`, new tab). When no `done` plan exists, it shows a single sentence saying there is no ready plan yet.
   - **"Szybkie akcje":** "Dodaj przepis" (`recipes.create`) and the existing `GenerateMealPlanDialog` with an outline "Wygeneruj jadłospis" trigger. The request it sends is unchanged.

No emoji, no relative times, no palette literals. One `<h1>` per page.

For the visual user the page shows 21 recipes, 4 plans, the 2026-06-15…21 plan (rendered by `formatDateRange`) and no alert. The new empty-dashboard visual user sees state 1.

Verify with:
- `vendor/bin/sail pest tests/Feature/DashboardControllerTest.php`
- `npm run lint:tokens`
- `npm run test:visual`: 3 changed and 1 new PNG, everything else identical
- the full E2E suite

### Key Discoveries:

- The shortfall logic and its message already exist and are what the generate dialog shows on 422. Reuse it so the dashboard and the dialog never disagree: `app/Services/MealPlanGenerator.php:22-58`.
- `links.pdf` is computed server-side and is `null` unless the plan is `done`, so the dashboard never builds a PDF URL itself: `app/Http/Resources/MealPlanResource.php:107-110`.
- The two owner scopes have different signatures: `Recipe::forUser(int $userId)` and `MealPlan::ownedBy(User $user)` (`app/Models/Recipe.php:43`, `app/Models/MealPlan.php:54`).
- The browser clock is pinned but server `now()` is not, so anything relative to "today" computed on the server would break pixel-exact baselines: `tests/e2e/visual/app-shell.spec.ts:12-15`.
- The design system already places Dashboard's `PageHeader` in the layout `#header` slot. Unifying placement is S-15's job: `context/foundation/design-system.md` §5.2 (PageHeader row).

## What We're NOT Doing

- No change to the URL (`/dashboard`) or route name; `/` redirect stays with S-15 (D-7).
- No policy and no `authorizeResource()`. The dashboard is not a resource and authorizes no single model, so owner scoping happens in the queries and is proven by tests. The CLAUDE.md rule covers resource controllers.
- No per-category recipe breakdown when the user is ready (decided: alert only when short).
- No "last generated" ordering by `updated_at`, and no in-progress or failed plan on the "Ostatni jadłospis" card.
- No `done`-only plan count: the count equals the `/meal-plans` total.
- No shared `StatCard` in `Components/Common/` and no kitchen-sink demo. `StatCard` is a feature component (`Components/Dashboard/`), which keeps `DesignSystem.vue` and its overlay baselines untouched.
- No pixel baseline for the shortfall alert. It is covered by Pest props and a manual check.
- No new functional E2E spec for the dashboard.
- No toasts, polling or deferred props. The page is server-rendered with cheap count queries.
- No changes to `GenerateMealPlanDialog`, its request or its validation.

## Implementation Approach

Contract first, then pixels:
- **Phase 1** fixes the props contract with a feature test, so the view is written against real data.
- **Phase 2** rewrites the page against that contract and keeps the token scan green. The visual run is expected to fail from here until Phase 3 re-records the baselines.
- **Phase 3** adds the deterministic empty-state user, records the baselines (3 changed, 1 new) and brings the docs in line.

## Critical Implementation Details

**Accessible names and E2E strict mode.** Playwright `getByRole(..., { name })` matches case-insensitive substrings by default:
- The stat cards are not links: a "Zobacz przepisy" link would also match a sidebar locator `name: 'Przepisy'`.
- The alert link is "Dodaj brakujące przepisy" and the empty-state CTA is "Dodaj pierwszy przepis". Neither contains "Dodaj przepis" as a substring.
- "Wygeneruj jadłospis" stays the only control with that substring on the page.

Before finishing Phase 2, grep `tests/e2e` for `name: 'Przepisy'`, `name: 'Jadłospisy'`, `'Pobierz PDF'` and `'Dodaj przepis'` and confirm that none of those locators runs on `/dashboard` without `exact`.

**Inertia links.** Use `<Button as-child><Link :href="route(...)">` with no `@click` (Link overrides click handlers; recipes-ui precedent). The PDF stays a plain `<a target="_blank" rel="noopener">`, because it is a file response, not an Inertia visit.

**Visual determinism.** The new empty-dashboard user must have no recipes (`withTrashed()->forceDelete()`), no meal plans and no family members after every seeder run. Its only rendered data is "Pulpit" and the user menu name, so pick a stable `name` (e.g. "E2E Nowy").

## Environment (prerequisite for every phase)

This worktree (`.claude/worktrees/dashboard-ui`) starts without `vendor/`, `node_modules/` or `.env`. Every `sail` command, E2E run and visual run must exercise this checkout's code:

- Copy `.env` from the main checkout. Give it its own `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT` and `FORWARD_MAILPIT_DASHBOARD_PORT`, and set `MAILPIT_URL` to match. Keep `AI_SCALING_DRIVER=fake`, `MAIL_MAILER=smtp` and `MAIL_HOST=mailpit`.
- Install `vendor/` with the Sail Composer container (not host PHP). Then run `vendor/bin/sail up -d`, `vendor/bin/sail npm ci` and `vendor/bin/sail artisan migrate`.
- Run the visual gate against this stack with `VISUAL_DOCKER_NETWORK=dashboard-ui_sail VISUAL_BASE_URL=http://laravel.test npm run test:visual`. Check the network name with `docker network ls`.
- Before Phase 1, `vendor/bin/sail ps` lists `dashboard-ui-*` containers, and `curl -s -o /dev/null -w '%{http_code}' http://localhost:${APP_PORT}/login` prints `200`.

---

## Phase 1: Backend contract

### Overview

Replace the route closure with an invokable controller that passes owner-scoped dashboard props, and pin that contract with a feature test. The page still renders as before, because it ignores the new props.

### Changes Required:

#### 1. Dashboard controller

**File**: `app/Http/Controllers/DashboardController.php` (new; `php artisan make:controller DashboardController --invokable --no-interaction` via Sail, then add `declare(strict_types=1);`)

**Intent**: Gather the four dashboard facts for the authenticated user with owner-scoped queries, and render the `Dashboard` page with them. Reuse `MealPlanGenerator` for the shortfall instead of re-deriving the rule.

**Contract**: `__invoke(Request $request, MealPlanGenerator $generator): Inertia\Response`. It renders `Dashboard` with these props:
- `stats`: `{ recipes: int, mealPlans: int }`
  - `recipes`: `Recipe::forUser($user->id)->count()` (soft-deleted excluded).
  - `mealPlans`: `MealPlan::ownedBy($user)->count()` (all statuses).
- `latestMealPlan`: the result of `(new MealPlanResource($plan))->resolve()` or `null`.
  - `$plan` is `MealPlan::ownedBy($user)->where('status', 'done')->orderByDesc('start_date')->orderByDesc('id')->first()`.
  - Do not eager-load meals: the card needs only dates and links.
- `recipeShortfall`: `list<string>`, `$generator->shortfallMessages($generator->missingRecipesPerCategory($user->id))`. It is empty when the user can generate a week.

Add PHPDoc array shapes for the props. PHPStan level 5 passes with no baseline entries.

#### 2. Route

**File**: `routes/web.php`

**Intent**: Point `/dashboard` at the controller without changing its URL, middleware or name, so login redirects and E2E `waitForURL('**/dashboard')` keep working.

**Contract**: `Route::get('/dashboard', DashboardController::class)->middleware(['auth'])->name('dashboard');`. Import the controller, and drop the `Application` import only if it becomes unused (it is still used by `/`, so keep it).

#### 3. Feature test

**File**: `tests/Feature/DashboardControllerTest.php` (new; `php artisan make:test --pest DashboardControllerTest`)

**Intent**: Prove the props contract and owner scoping, including the edge cases decided in planning.

**Contract**: Pest with `RefreshDatabase`, factories (`Recipe::factory()->breakfast()/supper()/dinner()`, `MealPlan::factory()` with explicit `status` and `start_date`), and `assertInertia(fn ($page) => $page->component('Dashboard', false)->...)`. Cases:
- A guest is redirected to `login`.
- **Counts are owner-scoped.** Another user's recipes and plans are not counted. A soft-deleted own recipe is not counted. Own plans in every status (`pending`, `processing`, `done`, `error`) are all counted.
- **`latestMealPlan` is the newest `done` plan by `start_date`.**
  - It ignores a newer-dated `processing` or `error` plan and another user's newer `done` plan.
  - When two own `done` plans exist and the older-dated one has the later `updated_at`, the newer-dated one wins (the 22–28 Jun vs 8–14 Jun case).
  - `latestMealPlan.links.pdf` equals `route('meal-plans.pdf', id)`.
- `latestMealPlan` is `null` when the user has plans but none are `done`, and when the user has no plans.
- `recipeShortfall` holds exactly one message per short category, with the exact text from `shortfallMessages` (e.g. 3 supper recipes yields the "Obiad: masz 3, potrzeba 7" sentence). It is `[]` with 7 recipes in each category.
- A brand-new user gets `stats.recipes = 0` and `stats.mealPlans = 0`, `latestMealPlan = null`, and three shortfall messages. The view, not the server, decides the empty state.

### Success Criteria:

#### Automated Verification:

- Dashboard feature tests pass: `vendor/bin/sail pest tests/Feature/DashboardControllerTest.php`
- Auth redirect tests still pass: `vendor/bin/sail pest tests/Feature/Auth`
- Full suite passes: `vendor/bin/sail composer test`
- Larastan passes with no new baseline entries: `vendor/bin/sail composer phpstan` and `git diff --exit-code phpstan-baseline.neon`
- Code style passes: `vendor/bin/sail composer pint-test`
- Route is wired to the controller: `vendor/bin/sail artisan route:list --name=dashboard` shows `DashboardController`

#### Manual Verification:

- Logged in as `e2e-planner-visual@example.com`, `/dashboard` still renders (old static view), and the Inertia page props in Vue devtools or the `data-page` attribute show `stats {recipes: 21, mealPlans: 4}`, the 2026-06-15 plan and `recipeShortfall: []`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Dashboard view

### Overview

Rewrite `Dashboard.vue` against the Phase 1 props in three states, on shared components and tokens only, with a small feature `StatCard`. From here until Phase 3 the visual run fails on the three dashboard PNGs, as expected.

### Changes Required:

#### 1. Stat card

**File**: `resources/js/Components/Dashboard/StatCard.vue` (new)

**Intent**: One presentational tile shared by the two count cards, so the grid reads consistently and the page stays short.

**Contract**:
- Props: `label: string`, `value: number`, `icon: Component`.
- Built on `ui/card`. The icon sits in a `bg-accent` chip and is coloured `text-primary`, `aria-hidden`.
- Label `text-sm font-medium text-muted-foreground`; value `text-2xl font-semibold tabular-nums`, formatted with `formatNumber`.
- Not interactive. Tokens only (§3 rules).

#### 2. Dashboard page

**File**: `resources/js/Pages/Dashboard.vue`

**Intent**: Replace the fake cards, "Ostatni PDF", the emoji welcome box and the hard-coded URL with the three decided states.

**Contract**:
- **Props** (`defineProps`): `stats`, `latestMealPlan` (nullable) and `recipeShortfall` (array), as in Phase 1.
- **Header.** `<template #header><PageHeader title="Pulpit" /></template>`, the only `h1`. `<Head title="Pulpit" />` stays.
- **First-run state.** Show it when `stats.recipes === 0 && stats.mealPlans === 0`: only an `EmptyState` with `class="py-12"`, `icon=ChefHat`, `headingLevel` 2, a title and description about adding the first recipe, and a CTA `<Button as-child><Link :href="route('recipes.create')">Dodaj pierwszy przepis</Link></Button>`.
- **Otherwise,** inside a `flex flex-col gap-6` wrapper (content width per §4.6):
  - **Shortfall.** When `recipeShortfall.length`, show an `Alert variant="warning"` with a title, one line per message, and a "Dodaj brakujące przepisy" `Link` to `recipes.create`.
  - **The grid** `grid grid-cols-1 gap-6 lg:grid-cols-2` holds:
    - `StatCard` "Przepisy" (`ChefHat`)
    - `StatCard` "Jadłospisy" (`CalendarDays`)
    - a "Ostatni jadłospis" `Card`. When `latestMealPlan` is set it shows `formatDateRange(start_date, end_date)` and two actions:
      - "Otwórz jadłospis": `Button variant="outline" as-child` + `Link :href="latestMealPlan.links.self"`;
      - "Pobierz PDF": `Button variant="outline" as-child` + `<a :href="latestMealPlan.links.pdf" target="_blank" rel="noopener">` with `FileDown class="size-4" aria-hidden="true"`.

      When it is `null`, the card shows `text-muted-foreground` text saying there is no ready plan yet.
  - **"Szybkie akcje"** `Card` (title as an `h2`) with "Dodaj przepis" (`Button as-child` + `Link :href="route('recipes.create')"`, `Plus class="size-4"`) and `<GenerateMealPlanDialog><Button variant="outline">` + `CalendarDays class="size-4"` + "Wygeneruj jadłospis" `</Button></GenerateMealPlanDialog>`.
- **Icons** come only from §4.7 (`ChefHat`, `CalendarDays`, `FileDown`, `Plus`), with `size-4` in buttons. No `mr-2 h-5 w-5`, no emoji, no `h3` directly under the `h1`.

#### 3. Token scan list

**File**: `scripts/lint-tokens.paths`

**Intent**: Gate the new feature folder (`Dashboard.vue` is already listed).

**Contract**: Append `resources/js/Components/Dashboard`.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token scan is green, including the new folder: `vendor/bin/sail npm run lint:tokens`
- Token-scan self-test passes: `bash scripts/lint-tokens.test.sh`
- Dashboard feature tests still pass: `vendor/bin/sail pest tests/Feature/DashboardControllerTest.php`
- No dashboard-reachable E2E locator collides with the new names: `grep -rnE "name: '(Przepisy|Jadłospisy|Pobierz PDF|Dodaj przepis)'" tests/e2e` reviewed, with no unanchored match executed on `/dashboard`

#### Manual Verification:

- As `e2e-planner-visual@example.com`: the dashboard shows 21 / 4, the 15–21 June 2026 plan with working "Otwórz jadłospis" and "Pobierz PDF" (the PDF opens in a new tab), and no alert
- "Wygeneruj jadłospis" opens the existing dialog, and submitting it lands on the new plan's page (same request as on `/meal-plans`)
- A fresh registered user sees only the empty state, and "Dodaj pierwszy przepis" opens the recipe form
- A user with 3 supper recipes and none elsewhere sees the warning alert with three sentences, the stats, and "Ostatni jadłospis" saying there is no ready plan
- Below `lg` the cards stack in one column. Keyboard tab order is header, alert link, card actions, then quick actions, each with a visible focus ring

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Fixtures, visual gate and docs

### Overview

Add a deterministic empty-dashboard user and its screenshot, re-record the three dashboard baselines deliberately, and bring the docs in line with the shipped dashboard.

### Changes Required:

#### 1. Empty-dashboard seeded user

**File**: `database/seeders/E2eMealPlanSeeder.php`

**Intent**: Give the visual gate a user that always renders the first-run state, isolated from the E2E specs that create recipes for the cook users.

**Contract**:
- Add `public const DASHBOARD_EMPTY_EMAIL = 'e2e-dashboard-empty-visual@example.com';`.
- In `run()`, `firstOrCreate` it with the shared password, a stable name and `email_verified_at`. Then force-delete its recipes `withTrashed()`, delete its meal plans and delete its family members.
- Extend the class PHPDoc with one sentence about this user.

#### 2. Seeder test

**File**: `tests/Unit/Seeders/E2eMealPlanSeederTest.php`

**Intent**: Lock the empty-user fixture so a later seeder change cannot silently give it data.

**Contract**: New `it(...)` case. Seed it twice, and after the second run the empty user exists with 0 recipes (including trashed), 0 meal plans and 0 family members, even if recipes, plans and family members were created for it between the runs.

#### 3. Visual spec

**Files**: `tests/e2e/visual/support/auth.ts`, `tests/e2e/visual/app-shell.spec.ts`

**Intent**: Screenshot the empty state next to the existing dashboard shots.

**Contract**:
- `logIn(page, email = 'e2e-planner-visual@example.com')`. Existing callers are unchanged.
- New test `dashboard empty desktop` in `app-shell.spec.ts`: `logIn(page, 'e2e-dashboard-empty-visual@example.com')`, then assert the empty-state heading is visible, move the mouse to 0,0 and take `toHaveScreenshot('dashboard-empty-desktop.png', { fullPage: true })`.

#### 4. Baselines

**Files**: `tests/e2e/__screenshots__/visual/app-shell.spec.ts/*`

**Intent**: Deliberately accept the new dashboard pixels and nothing else.

**Contract**:
- Run `vendor/bin/sail npm run build` (with no `public/hot`), then `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`, then `npm run test:visual -- --update-snapshots`.
- Expected delta:
  - ` M` `dashboard-desktop.png`, `dashboard-mobile.png` and `dashboard-mobile-drawer.png`
  - `??` `dashboard-empty-desktop.png`

  Any other changed PNG is a regression to fix, not to accept.
- Merge `develop` into the branch before recording, if it moved since planning.

#### 5. Docs

**Files**: `.ai/core/ui-plan.md`, `CLAUDE.md`, `context/foundation/design-system.md`

**Intent**: Remove the superseded dashboard spec and point future agents at the new fixture user.

**Contract**:
- **`ui-plan.md` §2.1:**
  - path `/dashboard`;
  - key information: recipe count, all-status plan count, the newest ready plan with "Pobierz PDF", and the shortfall alert;
  - components: `PageHeader`, `StatCard` (feature), `Card`, `Alert`, `EmptyState` and `GenerateMealPlanDialog`;
  - drop `aria-pressed` and `Toast`;
  - also update the journey line §3.2 if it contradicts this.
- **`CLAUDE.md` visual-gate bullet:** add that the empty-dashboard shot logs in as `e2e-dashboard-empty-visual@example.com`, which the seeder leaves without recipes, plans or family members.
- **`design-system.md` §7.3:** if it lists the dashboard screenshots, add `dashboard-empty-desktop.png`.

### Success Criteria:

#### Automated Verification:

- Seeder is idempotent: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`
- Seeder tests pass: `vendor/bin/sail pest tests/Unit/Seeders/E2eMealPlanSeederTest.php`
- Full suite, Larastan and style pass: `vendor/bin/sail composer test`, `vendor/bin/sail composer phpstan`, `vendor/bin/sail composer pint-test`
- Bundles build and the token scan is green: `vendor/bin/sail npm run build && vendor/bin/sail npm run lint:tokens`
- Baseline delta is exactly 3 modified and 1 new: `git status --porcelain -uall tests/e2e/__screenshots__` lists only the four app-shell dashboard PNGs
- Visual gate is stable twice in a row: `npm run test:visual && npm run test:visual` (with this worktree's `VISUAL_DOCKER_NETWORK`/`VISUAL_BASE_URL`)
- Full E2E passes after a re-seed: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`

#### Manual Verification:

- Each of the four PNGs reviewed in the diff:
  - full state with 21 / 4 / 15–21 June 2026 and no alert;
  - mobile stacked in one column;
  - drawer over the new page;
  - empty state with a single CTA.
- Dashboard states mapped to evidence:
  - first run → PNG;
  - full → PNGs;
  - shortfall and no ready plan → Pest props + Phase 2 manual check;
  - loading and error → n/a (server-rendered, no async data).
- `ui-plan.md` §2.1 and the `CLAUDE.md` bullet read correctly against the shipped page.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- `E2eMealPlanSeederTest`: the empty-dashboard user stays empty across re-seeds.

### Integration Tests:

- `DashboardControllerTest` (feature):
  - guest redirect;
  - owner-scoped counts, including soft-deleted recipes and all statuses;
  - newest `done` by `start_date` beating a later `updated_at`;
  - `null` with no `done` plan;
  - exact shortfall messages and the empty array when ready;
  - new-user zeros.
- Existing `tests/Feature/Auth/*`: the redirect to `route('dashboard')` still works.
- E2E (unchanged specs): every login lands on `/dashboard`, and `meal-plan.spec.ts` still generates via `/meal-plans`.
- Visual: four dashboard PNGs (three updated, one new).

### Manual Testing Steps:

1. Log in as the visual user: check 21 / 4 and the 15–21 June plan, open the plan, then download the PDF.
2. Register a new account: you land on the empty state, and "Dodaj pierwszy przepis" opens the form.
3. Add 3 supper recipes only: the alert lists three shortfall sentences, and "Wygeneruj jadłospis" → submit shows the same sentences in the dialog.
4. Resize below `lg` and tab through the page to check the layout and focus rings.

## Performance Considerations

Each request runs five cheap queries: two `count()`, one `first()` without eager loads, and three per-category counts inside `missingRecipesPerCategory`. All of them are on the indexed `user_id` and `status` columns, so no caching is needed. If the dashboard ever grows, the three category counts can collapse into one grouped query inside `MealPlanGenerator`, which is out of scope here.

## Migration Notes

No schema or data migration. Rollback means reverting the route to the closure; the old page ignores the props.

## References

- Roadmap: `context/foundation/roadmap.md` S-16
- Design system: `context/foundation/design-system.md` §4.6, §4.7, §5.2, §8 (DS-11)
- UI spec being superseded: `.ai/core/ui-plan.md` §2.1
- Similar implementations:
  - `app/Http/Controllers/MealPlanController.php:82-106` (resource resolve into Inertia props)
  - `app/Http/Controllers/ClientErrorController.php:16` (invokable controller)
  - `resources/js/Pages/MealPlans/Show.vue:9-14` (PDF button)
  - `resources/js/Pages/Recipes/Index.vue:5-30` (`#header` PageHeader + EmptyState CTA)
- Sibling plans: `context/archive/2026-11-15-meal-plans-index-ui/plan.md`, `context/archive/2026-11-15-meal-plan-show-ui/plan.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Backend contract

#### Automated

- [x] 1.1 Dashboard feature tests pass: `vendor/bin/sail pest tests/Feature/DashboardControllerTest.php`
- [x] 1.2 Auth redirect tests still pass: `vendor/bin/sail pest tests/Feature/Auth`
- [x] 1.3 Full suite passes: `vendor/bin/sail composer test`
- [x] 1.4 Larastan passes with no new baseline entries: `vendor/bin/sail composer phpstan` and `git diff --exit-code phpstan-baseline.neon`
- [x] 1.5 Code style passes: `vendor/bin/sail composer pint-test`
- [x] 1.6 Route is wired to the controller: `vendor/bin/sail artisan route:list --name=dashboard` shows `DashboardController`

#### Manual

- [ ] 1.7 Logged in as `e2e-planner-visual@example.com`, `/dashboard` still renders and the page props show `stats {recipes: 21, mealPlans: 4}`, the 2026-06-15 plan and `recipeShortfall: []`

### Phase 2: Dashboard view

#### Automated

- [ ] 2.1 Client and SSR bundles build: `vendor/bin/sail npm run build`
- [ ] 2.2 Token scan is green, including the new folder: `vendor/bin/sail npm run lint:tokens`
- [ ] 2.3 Token-scan self-test passes: `bash scripts/lint-tokens.test.sh`
- [ ] 2.4 Dashboard feature tests still pass: `vendor/bin/sail pest tests/Feature/DashboardControllerTest.php`
- [ ] 2.5 No dashboard-reachable E2E locator collides with the new names (grep reviewed)

#### Manual

- [ ] 2.6 Visual user sees 21 / 4, the 15–21 June 2026 plan with working "Otwórz jadłospis" and "Pobierz PDF", and no alert
- [ ] 2.7 "Wygeneruj jadłospis" opens the existing dialog and submitting lands on the new plan's page
- [ ] 2.8 A fresh registered user sees only the empty state, and "Dodaj pierwszy przepis" opens the recipe form
- [ ] 2.9 A user with 3 supper recipes only sees the warning alert with three sentences and the "no ready plan" card
- [ ] 2.10 Below `lg` the cards stack in one column, and the keyboard tab order has visible focus rings

### Phase 3: Fixtures, visual gate and docs

#### Automated

- [ ] 3.1 Seeder is idempotent (two runs in a row)
- [ ] 3.2 Seeder tests pass: `vendor/bin/sail pest tests/Unit/Seeders/E2eMealPlanSeederTest.php`
- [ ] 3.3 Full suite, Larastan and style pass
- [ ] 3.4 Bundles build and the token scan is green
- [ ] 3.5 Baseline delta is exactly 3 modified and 1 new app-shell dashboard PNG (`git status --porcelain -uall tests/e2e/__screenshots__`)
- [ ] 3.6 Visual gate is stable twice in a row: `npm run test:visual && npm run test:visual`
- [ ] 3.7 Full E2E passes after a re-seed

#### Manual

- [ ] 3.8 Each of the four PNGs reviewed in the diff
- [ ] 3.9 Dashboard states mapped to evidence (first run, full, shortfall, no ready plan, loading/error n/a)
- [ ] 3.10 `ui-plan.md` §2.1 and the `CLAUDE.md` bullet read correctly against the shipped page
