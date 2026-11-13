# Real Weekly Meal Plan (S-03) Implementation Plan

## Overview

Replace the placeholder `GenerateMealPlanJob` with real recipe selection: a 7 days × 3 meals plan drawn at random from the user's own recipes by category, with no recipe repeated within a category in the week. Repair the broken entry points (the Generate button, the dead dashboard link, and regeneration that returns JSON to Inertia), and show the result as a 7×3 week grid that refreshes until the plan is ready. Add a queue worker to Sail and CI so the whole path from click to week view runs in E2E. This is roadmap S-03, the north star: the first moment the app does what it exists for.

## Current State Analysis

- `app/Jobs/GenerateMealPlanJob.php:46-72` is a placeholder: `sleep(2)`, then `status = done` with `generation_meta.version = 'placeholder'`. No `Meal` row is created. Its error path (`catch` → `status = error` + rethrow; `failed()` reports only `MaxAttemptsExceededException`) is F-01 work and must keep its tested behaviour (`tests/Unit/Jobs/GenerateMealPlanJobTest.php`).
- `MealPlanController::store`/`update` (`app/Http/Controllers/MealPlanController.php:101-156`) always return JSON (201/202). Inertia's router cannot consume that, so `router.put` in `resources/js/Components/MealPlans/ActionDropdown.vue:138` breaks. The `update` 409 block is unreachable because `RegenerateMealPlanRequest` already rejects `processing` with 422.
- `resources/js/Pages/MealPlans/Index.vue:20` renders "Wygeneruj nowy jadłospis" as a `<Link>` (GET) to `meal-plans.store`, which is POST-only. `resources/js/Pages/Dashboard.vue:85` links to `/meal-plans/create`, which has no route.
- `resources/js/Pages/MealPlans/Show.vue` reads fields the resource never sends (`recipe.title`, `meal_type`, `planned_date`, `log.level`/`message`). It is a flat list in English.
- `RegenerateMealPlanRequest` limits regeneration to 5/hour with a `force` bypass, which contradicts US-009 "brak limitu regeneracji". `GenerateMealPlanRequest` limits generation to 5/hour and enforces a unique `start_date` per user (also a DB unique key, `meal_plans(user_id, start_date)`).
- Categories are the enum `breakfast | supper | dinner` (`recipes.category`, `meals.meal_category`). No Polish mapping exists. `CategoryBadge.vue` shows "Supper"/"Dinner".
- `Recipe` uses `SoftDeletes`, so `Meal::recipe()` returns `null` for a soft-deleted recipe and `MealPlanResource` would dereference null.
- Queue: `QUEUE_CONNECTION=database`. No worker runs in Sail (only `composer dev` runs `queue:listen`) or in the CI E2E job. `phpunit.xml` uses `sync`.
- Inertia v3 (`@inertiajs/vue3 ^3.11`) provides `usePoll`. Other controllers answer Inertia requests with `redirect()->route(...)` (`RecipeController.php:102`).
- `app/Services/` does not exist; the user approved creating it for this change.

## Desired End State

A logged-in user with at least 7 recipes in each category clicks "Wygeneruj jadłospis" on the meal-plan list or on the dashboard. A dialog opens with the start date prefilled to the nearest upcoming Monday. On confirmation the user lands on the plan view, which shows a pending state and polls every 3 s. Within seconds it shows a 7×3 grid: rows are days (Polish weekday + date), columns are Śniadanie / Obiad / Kolacja, and each cell shows the recipe name (linking to the recipe), kcal and servings. No recipe appears twice in a column. "Regeneruj" (on the view and in the list's menu) replaces all 21 meals with a fresh random draw, with no rate limit. If any category has fewer than 7 recipes, generation and regeneration are refused before any job is queued. The refusal is a Polish message naming each short category and how many recipes are missing.

Verify with: the Pest suite (service/job/request/controller tests), Larastan, Pint, `npm run build`, and the new Playwright spec `tests/e2e/meal-plan.spec.ts` (click → week grid → regenerate), run against Sail with the new `queue` service.

### Key Discoveries:

- The job error contract from F-01 is tested: the first `MealPlan` update in `handle()` must stay the `processing` update, because `GenerateMealPlanJobTest.php:84-89` forces a failure on the first `updating` event. `failed()` must not re-report plain exceptions (`GenerateMealPlanJobTest.php:119-130`).
- `GenerateMealPlanRequest::withValidator` checks the rate limit eagerly, before rules (`GenerateMealPlanRequest.php:51-59`); `tests/Feature/MealPlanControllerTest.php:128` depends on that.
- Sail's `docker/8.5/start-container` runs any `command:` through `gosu $WWWUSER`, so a second service on the `sail-8.5/app` image can run an artisan worker.
- `config/app.php` timezone is `UTC` and locale is `pl`; `lang/pl/validation.php` exists.
- Existing test bug: `MealPlanControllerTest.php:214` is named "user cannot regenerate other user's meal plan" but sends `deleteJson` to `destroy`.
- Error tracking (F-01) is logs + mail alerts, not a SaaS tracker (`context/archive/2026-11-13-error-tracking-baseline/change.md`).

## What We're NOT Doing

- AI or family-based ingredient scaling (S-04), shopping list (S-05), PDF generation (S-06). "Pobierz PDF" stays disabled until a plan has a `pdf_path`.
- Showing ingredients in the week grid (they arrive scaled with S-04/S-05).
- Allowing repeats when recipes are short: the plan refuses instead.
- Guaranteeing that a regeneration differs from the previous version: it is an independent draw, so with exactly 7 recipes per category only the day order changes.
- Renaming the `breakfast/supper/dinner` enum. The mapping is display-only: `supper` = Obiad, `dinner` = Kolacja.
- Removing the 5/hour limit on creating new plans.
- Dashboard statistics (hard-coded numbers stay; parked in the roadmap), the generation-log UI (the `logs` section is dropped from Show), keeping old plan versions (US-009 c.3).
- A production queue worker (F-02).

## Implementation Approach

The new `App\Services\MealPlanGenerator` is the one tested unit for selection. It reports missing recipes per category and replaces a plan's meals with a random 7-per-category draw inside a transaction. The two Form Requests use its shortfall check to refuse early, with field errors. The job becomes a thin queue wrapper: it calls the service and turns a run-time shortfall (recipes deleted after the click) into an `error` status without reporting it, because that is user data and not an app fault. The controller keeps JSON for `expectsJson()` callers and redirects Inertia callers to the plan view. The view polls with Inertia v3 `usePoll` while the plan is `pending`/`processing`. A Sail `queue` service and a CI worker step make the async path real in E2E. A dedicated seeder gives each Playwright browser its own user with 21 recipes.

## Critical Implementation Details

- **Local date for the default Monday.** Build the dialog's default `YYYY-MM-DD` from local date parts, not `toISOString()`: UTC conversion shifts the date back a day for Polish users after midnight. Nearest upcoming Monday includes today if today is Monday. Server-side `after_or_equal:today` runs in UTC, which is never ahead of the Polish local date, so a valid local default is never rejected.
- **Validation order in the regenerate request.** Status checks (`processing`, then "only done/error") come before the shortfall check, so a processing plan still answers with a `status` error as tested today.
- **Stopping the poll.** Start `usePoll` only while `mealPlan.status` is `pending` or `processing`, reload only the `mealPlan` prop, and stop it once the status becomes `done` or `error`. A regenerate from the view must restart it, since the redirect returns the plan as `pending`.

## Phase 1: Selection service and job

### Overview

Real recipe selection, unit-tested, wired into the queued job. After this phase, a job run creates 21 meals.

### Changes Required:

#### 1. Meal plan generator service

**File**: `app/Services/MealPlanGenerator.php` (new; `declare(strict_types=1)`)

**Intent**: Single place for the selection rules, so the Form Requests (early refusal) and the job (run-time) apply the same check, and S-04/S-05/S-06 extend it instead of the job.

**Contract**:
- Constants: categories in display order `['breakfast', 'supper', 'dinner']`, a category → Polish label map (`breakfast` → "Śniadanie", `supper` → "Obiad", `dinner` → "Kolacja"), and `MEALS_PER_CATEGORY = 7`.
- `missingRecipesPerCategory(int $userId): array<string, int>` returns only the short categories, mapped to how many recipes they lack. It counts the user's non-deleted recipes (`Recipe::forUser()->byCategory()`), and an empty array means the user has enough.
- `shortfallMessages(array<string, int> $missing): list<string>` returns one Polish message per short category, e.g. "Za mało przepisów w kategorii Obiad: masz 4, potrzeba 7. Dodaj jeszcze 3."
- `generate(MealPlan $mealPlan): void` throws `InsufficientRecipesException` (carrying the missing map) when anything is short. Otherwise, in one DB transaction, it deletes the plan's existing meals and creates 21: for each category it takes 7 distinct random recipes of the plan owner (`inRandomOrder()->limit(7)`) and assigns them to `start_date + 0..6` days. It touches only recipes where `user_id = $mealPlan->user_id`.

**File**: `app/Services/InsufficientRecipesException.php` (new)

**Intent**: Typed signal for "not enough recipes", so the job can tell user-data shortfalls from app failures.

**Contract**: extends `RuntimeException`; holds `array<string, int> $missing`; the message is the joined Polish shortfall messages.

#### 2. Job uses the service

**File**: `app/Jobs/GenerateMealPlanJob.php`

**Intent**: Replace the placeholder body with a call to the service and remove `sleep(2)` and the TODO comment. The F-01 error contract is kept.

**Contract**:
- `handle(MealPlanGenerator $generator)`, injected by the container.
- Order: `Context::add`, create the log, update `status = processing` (keep it the first update), then `$generator->generate()`, then `status = done` with `generation_meta = {generated_at, regenerate, version: 'random-v1'}`, then finish the log as `done`.
- `InsufficientRecipesException`: set `status = error` and `generation_meta = {error_at, error_message (Polish), missing, regenerate}`, finish the log as `error`, and **return without rethrowing**, so nothing is reported.
- Any other exception keeps today's path: `error` status, then rethrow.
- `failed()` is unchanged.
- `public bool $deleteWhenMissingModels = true;`: a plan deleted while `pending` (the policy allows it, and the E2E seeder does it too) drops the job silently. Without it, the worker throws `ModelNotFoundException`, reports it, and F-01 sends an alert for a normal user action.

#### 3. Meals of soft-deleted recipes still render

**File**: `app/Models/Meal.php`

**Intent**: Older plans keep showing a recipe the user later soft-deleted, instead of crashing the resource. Also add `declare(strict_types=1)` while touching the file.

**Contract**: `recipe(): BelongsTo` returns `$this->belongsTo(Recipe::class)->withTrashed()`.

#### 4. Tests

**File**: `tests/Unit/Services/MealPlanGeneratorTest.php` (new), `tests/Unit/Jobs/GenerateMealPlanJobTest.php`, `tests/Pest.php`

**Intent**: Prove the selection invariants and keep the job contract green.

**Contract**:
- A shared test helper (e.g. `seedFullWeekOfRecipes(User $user, int $perCategory = 7)` in `tests/Pest.php`, built on `RecipeFactory` category states).
- Generator tests:
  - 21 meals, 7 per category.
  - Within each category, 7 distinct recipe ids, all of that category.
  - Each `meal_date` from `start_date` to `+6` appears exactly once per category.
  - Only the owner's recipes are used, even when another user has many.
  - Soft-deleted recipes are excluded.
  - Regeneration replaces the meals: the count stays 21 and the old meal ids are gone.
  - The shortfall map and messages: with 4 dinners and 0 breakfasts the result is `['breakfast' => 7, 'dinner' => 3]`, and the dinner message names "Kolacja".
  - `generate()` throws on shortfall and leaves existing meals untouched.
- Job tests:
  - Update "processes meal plan successfully" to seed recipes and assert 21 meals + `version = 'random-v1'`.
  - New: a shortfall at run time gives `status = error`, a Polish `error_message`, the log is `error`, no exception escapes, and `Exceptions::assertNothingReported()`.
  - New: a job whose plan was deleted before processing (dispatched on the sync queue after `$mealPlan->delete()`) ends without an exception and with nothing reported.
  - The existing F-01 tests keep their assertions and stay green. Calls to `->handle()` (e.g. `GenerateMealPlanJobTest.php:92`) pass `app(MealPlanGenerator::class)`, because the new signature would otherwise throw `ArgumentCountError`.

### Success Criteria:

#### Automated Verification:

- Generator and job tests pass: `vendor/bin/sail pest tests/Unit/Services/MealPlanGeneratorTest.php tests/Unit/Jobs/GenerateMealPlanJobTest.php`
- Larastan passes without baseline changes: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`

#### Manual Verification:

- With 7+ recipes per category in Sail, a plan whose job is processed by `vendor/bin/sail artisan queue:work --once` yields 21 `meals` rows with no repeated `recipe_id` per category. The Sail `queue` service arrives only in Phase 4. Create the plan through the existing UI/session or a feature test, not a raw JSON POST (needs session + CSRF).

**Implementation Note**: After completing this phase and all automated verification passes, pause for manual confirmation before proceeding to the next phase.

---

## Phase 2: HTTP generate and regenerate

### Overview

Requests refuse short categories early with Polish messages, regeneration becomes unlimited, and the controller works for Inertia (redirect) and for JSON callers.

### Changes Required:

#### 1. Generate request

**File**: `app/Http/Requests/GenerateMealPlanRequest.php`

**Intent**: Refuse before a plan row or job exists when recipes are short, and speak Polish.

**Contract**:
- In the `after` callback, add one error on key `recipes` per message from `MealPlanGenerator::shortfallMessages(missingRecipesPerCategory(user))`.
- Polish messages for the duplicate start date ("Masz już jadłospis zaczynający się tego dnia.") and for the rate limit ("Zbyt wiele prób generowania. Spróbuj ponownie za {s} s.").
- The 5/hour limit and its eager check stay. The limiter is hit only after validation passes, so a refused shortfall does not consume an attempt.

#### 2. Regenerate request

**File**: `app/Http/Requests/RegenerateMealPlanRequest.php`

**Intent**: Implement US-009 "no limit" and refuse short categories early.

**Contract**:
- Remove the `force` rule, `prepareForValidation`, the rate-limit check and `passedValidation`. Rules become `regenerate: required|accepted`.
- `after` order: `processing` → `status` error; status not in done/error → `status` error; then the shortfall → `recipes` errors.
- All messages are in Polish ("Nie można regenerować jadłospisu w trakcie generowania.", "Regenerować można tylko gotowy lub nieudany jadłospis.").

#### 3. Controller responses

**File**: `app/Http/Controllers/MealPlanController.php`

**Intent**: Make the Inertia flows work while keeping the JSON API (`.ai/core/api-plan.md`, existing tests).

**Contract**:
- `store(): JsonResponse|RedirectResponse`: JSON 201 as today when `$request->expectsJson()`, otherwise `redirect()->route('meal-plans.show', $mealPlan)`.
- `update(): JsonResponse|RedirectResponse`: JSON 202 as today, otherwise a redirect to `meal-plans.show`. Remove the unreachable 409 block.
- `destroy(): Response|RedirectResponse`: 204 as today when `$request->expectsJson()`, otherwise `redirect()->route('meal-plans.index')`. Today `router.delete` in `ActionDropdown.vue:184` gets a 204 without `X-Inertia`, and Inertia v3 shows an error modal (the same defect class as the PUT).
- `show`: meals are returned sorted by `meal_date` and then category display order (do the sorting in `MealPlanResource`).

**File**: `app/Http/Resources/MealPlanResource.php`

**Intent**: Give the grid a stable order and stop the null-recipe crash.

**Contract**:
- Sort meals by date, then by `breakfast, supper, dinner`, and return a list (`->values()`).
- `recipe.deleted` (bool) tells the UI a recipe was soft-deleted.

#### 4. Tests

**File**: `tests/Feature/MealPlanControllerTest.php`, `tests/Unit/MealPlanRequestValidationTest.php`

**Intent**: Cover the changed behaviour and its failure modes.

**Contract**:
- Generate/regenerate happy paths seed a full week of recipes.
- New feature tests:
  - Generation with 4 dinners → 422 with `recipes` errors, no `meal_plans` row, no job pushed.
  - Regeneration with a shortfall → 422 `recipes`, no job.
  - Six or more regenerations in a row all succeed (the old limit is gone).
  - An Inertia (non-JSON) POST redirects to `meal-plans.show` of the new plan, and an Inertia PUT redirects to the same plan.
  - An Inertia (non-JSON) DELETE redirects to `meal-plans.index`; a JSON DELETE still returns 204.
- Fix the mislabelled test at `MealPlanControllerTest.php:214` so it sends `putJson` to `meal-plans.update` and asserts 403.
- Replace `test_regenerate_meal_plan_request_validates_force_flag` (the flag is removed by decision) with a test that `force` is ignored and no limiter is touched.
- Existing tests that turn red and must be updated (seed with `seedFullWeekOfRecipes()` unless noted):
  - `MealPlanControllerTest.php:128` "respects rate limiting on generation". Without recipes the 5 POSTs fail on `recipes`, so the limiter is never hit and `rate_limit` never appears.
  - `MealPlanRequestValidationTest` `test_regenerate_meal_plan_request_allows_done_status` and `…_allows_error_status` fail on the shortfall.
  - `MealPlanRequestValidationTest.php:219` ("Cannot regenerate…") and `:120` ("already have a meal plan") assert English messages. Change them to the new Polish strings.
- In the "six or more regenerations" test, `Queue::fake()` leaves the plan `pending` after each PUT, so the next one is refused. Reset `status = done` between PUTs, or run without `Queue::fake()` (sync queue + seeded recipes).

### Success Criteria:

#### Automated Verification:

- Meal-plan feature and request tests pass: `vendor/bin/sail pest tests/Feature/MealPlanControllerTest.php tests/Unit/MealPlanRequestValidationTest.php`
- Full suite passes: `vendor/bin/sail composer test`
- Larastan passes without baseline changes: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`

#### Manual Verification:

- A JSON `POST /meal-plans` from a user with 3 dinners (e.g. an ad-hoc feature test with `actingAs`, or an authenticated session with the CSRF token) returns the Polish `recipes` message naming Kolacja and "Dodaj jeszcze 4"

**Implementation Note**: After completing this phase and all automated verification passes, pause for manual confirmation before proceeding to the next phase.

---

## Phase 3: Generate dialog and week view

### Overview

One-click entry from the list and the dashboard, a 7×3 week grid with polling, and Polish labels on the touched components.

### Changes Required:

#### 1. Shared category labels

**File**: `resources/js/lib/mealCategories.js` (new), `resources/js/Components/recipes/CategoryBadge.vue`, `app/Http/Controllers/RecipeController.php`

**Intent**: One frontend source for display order and Polish labels, matching the service's mapping. Every place where the user sees or picks a category uses the same mapping. Otherwise the shortfall message ("kategorii Obiad") points at an option the recipe form does not offer, and users classify evening meals as English "Supper", which then shows up in the Obiad column.

**Contract**:
- `mealCategories.js` exports the ordered `['breakfast', 'supper', 'dinner']` and labels `Śniadanie` / `Obiad` / `Kolacja`. `CategoryBadge` uses them instead of "Supper"/"Dinner".
- `RecipeController` category options (`:56-57`, `:71-72`, `:130-131`, which include the "Super" typo) take their labels from the `MealPlanGenerator` category → Polish label map instead of hard-coded English. Update any `RecipeControllerTest` assertions on those labels.
- Check `resources/js/Components/recipes/RecipeRow.vue:92-94` for category copy and use `mealCategories.js` if it shows labels.

#### 2. Generate dialog

**File**: `resources/js/Components/MealPlans/GenerateMealPlanDialog.vue` (new)

**Intent**: A single entry point for both buttons: confirm one prefilled date and submit.

**Contract**:
- Props: a trigger slot or label.
- Uses the existing `ui/dialog`, `ui/input`, `ui/label` and `ui/button`, with `useForm({ start_date })` defaulting to the nearest upcoming Monday (local date; see Critical Implementation Details).
- Submit = `form.post(route('meal-plans.store'))`. On success Inertia follows the redirect to the plan view.
- Errors shown inline:
  - `start_date`;
  - each `recipes` message, plus a link to `recipes.create`;
  - `rate_limit`.
- The confirm button is labelled "Wygeneruj" and is disabled while processing.

**File**: `resources/js/Pages/MealPlans/Index.vue`, `resources/js/Pages/Dashboard.vue`

**Intent**: Replace the broken GET `<Link>` (Index) and the dead `/meal-plans/create` link (Dashboard) with the dialog.

**Contract**: both render `GenerateMealPlanDialog` with the trigger label "Wygeneruj jadłospis". Nothing else on the dashboard changes.

#### 3. Week view

**File**: `resources/js/Pages/MealPlans/Show.vue` (rewrite), `resources/js/Components/MealPlans/MealPlanGrid.vue` (new)

**Intent**: Show the generated week the way US-008 asks: visible in the UI and refreshing until ready.

**Contract**:
- Types match `MealPlanResource`: `meals[].meal_date`, `meal_category`, `recipe.{id,name,calories,servings,deleted}`.
- Header: Polish date range, `StatusTag`, "Regeneruj" button (`ConfirmDialog` → `router.put(route('meal-plans.update', id), { regenerate: true })`, errors shown as toasts) and `ActionDropdown`.
- `pending`/`processing`: animated skeleton grid plus "Generujemy jadłospis…", polled with `usePoll(3000, { only: ['mealPlan'] }, { autoStart: false })` started/stopped from the status.
- `pending`/`processing` for longer than ~30 s (counted from page load): an extra note "Generowanie trwa dłużej niż zwykle. Sprawdź, czy działa worker kolejki, albo odśwież stronę." Polling continues.
- `done` with no meals (placeholder-era plans): the message "Ten jadłospis nie ma jeszcze posiłków." plus the Regenerate button instead of the grid.
- `done`: `MealPlanGrid` has 7 rows (Polish weekday + `dd.mm`) × 3 columns (Śniadanie / Obiad / Kolacja). Each cell shows the recipe name linking to `recipes.show` (plain text with an "usunięty" note when `deleted`), kcal and servings.
- `error`: `generation_meta.error_message` plus the Regenerate button.
- The logs section and English copy are removed.

#### 4. Action menu

**File**: `resources/js/Components/MealPlans/ActionDropdown.vue`, `resources/js/Components/MealPlans/StatusTag.vue` (labels only if English)

**Intent**: Polish copy, and PDF download offered only when a file exists.

**Contract**:
- Labels: "Szczegóły", "Pobierz PDF", "Regeneruj", "Usuń", plus Polish dialog and toast texts.
- `canDownloadPdf = status === 'done' && !!pdf_path`, with `pdf_path` added to the item type.
- Regenerate error toasts read `errors.status` / `errors.recipes` / `errors.regenerate`.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Full Pest suite, Larastan and Pint still pass (Phase 3 touches `RecipeController`): `vendor/bin/sail composer test && vendor/bin/sail composer phpstan && vendor/bin/sail composer pint-test`

#### Manual Verification:

- From /meal-plans and from /dashboard, "Wygeneruj jadłospis" opens the dialog prefilled with the nearest upcoming Monday, and confirming lands on the plan view
- The plan view shows the pending skeleton, then the 7×3 grid without a manual refresh (queue worker running: `vendor/bin/sail artisan queue:listen --tries=1` in a second terminal, since the Sail `queue` service arrives in Phase 4), with no recipe repeated in a column
- Generation with a short category shows the Polish per-category messages in the dialog and creates no plan
- "Regeneruj" on the view and in the list menu replaces the meals; repeated more than 5 times in an hour without a limit error
- "Pobierz PDF" is disabled for a done plan without a file; no English copy remains on the list menu or plan view

**Implementation Note**: After completing this phase and all automated verification passes, pause for manual confirmation before proceeding to the next phase.

---

## Phase 4: Queue worker and E2E

### Overview

Make the async path run locally and in CI, and prove it with one browser test from click to week view to regenerate.

### Changes Required:

#### 1. Sail queue service

**File**: `docker-compose.yml`

**Intent**: `sail up -d` also processes jobs, so the dev app and the E2E runs behave like production with a worker.

**Contract**:
- A new `queue` service on image `sail-8.5/app` with `command: php artisan queue:listen --tries=1`, which reloads code like `composer dev` does.
- Same `WWWUSER`/`LARAVEL_SAIL` env, the `.:/var/www/html` volume and the `sail` network, with `depends_on: pgsql (service_healthy)`.
- No published ports.

#### 2. E2E seeder

**File**: `database/seeders/E2eMealPlanSeeder.php` (new; `declare(strict_types=1)`)

**Intent**: Deterministic, re-runnable fixtures. Each Playwright browser project gets its own user, so parallel runs never collide on the unique start date or the 5/hour limit.

**Contract**:
- Refuses to run in production.
- For `e2e-planner-chromium@example.com` and `e2e-planner-firefox@example.com` (password `Password123!`):
  - firstOrCreate the user;
  - delete that user's meal plans;
  - top up to 7 recipes per category via `RecipeFactory`;
  - clear `generate-meal-plan:{id}` in the `RateLimiter`.

#### 3. Playwright spec

**File**: `tests/e2e/meal-plan.spec.ts` (new)

**Intent**: The roadmap's required E2E: the whole path from the click to the week view, plus a regeneration.

**Contract**:
- Log in (Polish labels as in `login.spec.ts`) as `e2e-planner-${testInfo.project.name}@example.com`.
- Go to `/meal-plans`, click "Wygeneruj jadłospis", keep the default date, confirm.
- Expect the URL `/meal-plans/{id}`. Within 15 s expect the grid with headers Śniadanie/Obiad/Kolacja and 21 recipe cells.
- Click "Regeneruj", confirm, expect the grid again with 21 cells.

#### 4. CI and docs

**File**: `.github/workflows/master.yml` (job `e2e-tests`)

**Intent**: CI E2E has a worker and the fixtures.

**Contract**:
- Add `QUEUE_CONNECTION=database` to the generated `.env`.
- After migrate, run `php artisan db:seed --class=E2eMealPlanSeeder --no-interaction`.
- Add a step before the tests that starts `php artisan queue:work --tries=1 > storage/logs/queue.log 2>&1 &`.

**File**: `CLAUDE.md` (Commands → E2E line)

**Intent**: The documented local E2E command must include the seed step, and must say the Sail `queue` service processes generation.

**Contract**: the E2E bullet becomes: build, `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`, then the existing Playwright command.

### Success Criteria:

#### Automated Verification:

- Sail starts the queue service: `vendor/bin/sail up -d && docker compose ps queue` shows it running
- Seeder runs twice without error: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder` (twice)
- Client and SSR bundles build: `vendor/bin/sail npm run build`
- E2E suite passes in both browsers: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- Full Pest suite, Larastan and Pint pass: `vendor/bin/sail composer test && vendor/bin/sail composer phpstan && vendor/bin/sail composer pint-test`

#### Manual Verification:

- From the click on "Wygeneruj" to the visible grid takes under 10 s in Sail (US-008)
- The CI `e2e-tests` job passes on the PR into `main`

**Implementation Note**: After completing this phase and all automated verification passes, pause for manual confirmation.

---

## Testing Strategy

### Unit Tests:

- `MealPlanGenerator`:
  - selection invariants (counts, distinct ids per category, date coverage);
  - owner scoping and exclusion of soft-deleted recipes;
  - regeneration replaces the meals;
  - the shortfall map and Polish messages;
  - throws on shortfall without touching the meals.
- `GenerateMealPlanJob`: real success path; a run-time shortfall becomes `error` without a report; a deleted plan drops the job without a report; the F-01 contract tests keep their assertions.
- Form Requests: the shortfall is refused with `recipes` errors; `force` is removed; no regeneration limit.

### Integration Tests:

- `MealPlanControllerTest`:
  - JSON and Inertia responses for store/update/destroy;
  - the shortfall creates no plan and pushes no job;
  - unlimited regeneration;
  - another user's plan → 403 on update (fixed test).
- Playwright `meal-plan.spec.ts`: click → dialog → week grid → regenerate, against Sail with the queue service.

### Manual Testing Steps:

1. In Sail with the `queue` service up, create a user with 7 recipes per category, then generate from the dashboard; confirm the grid appears without a refresh.
2. Soft-delete one recipe used in the plan, reload the plan view: the cell still shows the name marked as deleted; regenerating now refuses with "Dodaj jeszcze 1".
3. Regenerate more than 5 times within a few minutes: no limit error.

## Performance Considerations

Selection is 3 queries of `ORDER BY random() LIMIT 7` over one user's recipes plus 21 inserts in a transaction, far below the 10 s US-008 budget. The perceived time is dominated by the worker pick-up (`queue:listen` polls about every 3 s) and the 3 s UI poll. The Manual criterion in Phase 4 checks the budget end to end.

## Migration Notes

No schema changes. Existing `done` plans created by the placeholder have no meals; they show the empty/regenerate state and can be regenerated. Developers must re-run `vendor/bin/sail up -d` to start the new `queue` service.

## References

- Roadmap item: `context/foundation/roadmap.md` → S-03 `real-weekly-meal-plan`
- PRD: `.ai/core/prd.md` FR-4, US-008, US-009; UI flow: `.ai/core/ui-plan.md:53-54,83-84`
- F-01 job contract: `context/archive/2026-11-13-error-tracking-baseline/`, `tests/Unit/Jobs/GenerateMealPlanJobTest.php`
- Redirect pattern: `app/Http/Controllers/RecipeController.php:102`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Selection service and job

#### Automated

- [x] 1.1 Generator and job tests pass — 26d8538
- [x] 1.2 Larastan passes without baseline changes — 26d8538
- [x] 1.3 Pint passes — 26d8538

#### Manual

- [ ] 1.4 Job run in Sail yields 21 meals with no repeated recipe per category

### Phase 2: HTTP generate and regenerate

#### Automated

- [x] 2.1 Meal-plan feature and request tests pass
- [x] 2.2 Full suite passes
- [x] 2.3 Larastan passes without baseline changes
- [x] 2.4 Pint passes

#### Manual

- [ ] 2.5 JSON generate with 3 dinners returns the Polish Kolacja shortfall message

### Phase 3: Generate dialog and week view

#### Automated

- [ ] 3.1 Client and SSR bundles build
- [ ] 3.2 Full Pest suite, Larastan and Pint still pass

#### Manual

- [ ] 3.3 Dialog from list and dashboard is prefilled with next Monday and lands on the plan view
- [ ] 3.4 Pending skeleton turns into the 7×3 grid without refresh, no repeats per column
- [ ] 3.5 Short category shows Polish per-category messages and creates no plan
- [ ] 3.6 Regenerate works from view and list menu, more than 5 times without a limit error
- [ ] 3.7 PDF disabled without a file; no English copy on list menu or plan view

### Phase 4: Queue worker and E2E

#### Automated

- [ ] 4.1 Sail starts the queue service
- [ ] 4.2 Seeder runs twice without error
- [ ] 4.3 Client and SSR bundles build
- [ ] 4.4 E2E suite passes in both browsers
- [ ] 4.5 Full Pest suite, Larastan and Pint pass

#### Manual

- [ ] 4.6 Click to visible grid under 10 s in Sail
- [ ] 4.7 CI e2e-tests job passes on the PR into main
