# Meal Plan Draft with Recipe Swap Implementation Plan

## Overview

Split meal plan generation into two steps (roadmap S-17, MS-01 + MS-02):
- **Generate.** "Wygeneruj" draws the week into a new `draft` status. The draft has recipes only: no quantities and no shopping list.
- **Approve.** "Zatwierdź" queues the existing AI scaling.

While the plan is a draft, the user can:
- swap the recipe in any cell for another of their recipes in the same category;
- redraw the whole week ("Losuj ponownie").

A done or failed plan can go back to the draft ("Wróć do szkicu") with its recipes kept, and the next approval re-scales the whole plan.

## Current State Analysis

Today generation is one shot:
- `MealPlanController::store` creates a `pending` plan and dispatches `GenerateMealPlanJob` (`app/Http/Controllers/MealPlanController.php:121-149`).
- The job draws the recipes with `MealPlanGenerator::generate()`, scales them with `MealIngredientScaler::scale()`, then marks the plan `done` (`app/Jobs/GenerateMealPlanJob.php:79-136`).
- "Regeneruj" (`update`) claims a `done`/`error` plan with a conditional update and runs the same job again (`MealPlanController.php:155-182`). Nothing in between lets the user see or change the drawn week.

Constraints found:
- **Status column.** `meal_plans.status` is a Laravel `enum`. On PostgreSQL that is a varchar with a CHECK constraint `meal_plans_status_check` (`database/migrations/2025_07_31_145657_create_meal_plans_table.php:19`), so a new value needs a migration. `logs_meal_plan.status` has its own copy of the enum, but only the job writes logs, so it is unaffected.
- **Meals.** `meals.recipe_id` is NOT NULL (`2025_07_31_145701_create_meals_table.php:17`), and there is no unique slot constraint (`:23`). A swap only changes `recipe_id` on an existing row.
- **Scaling reruns cleanly.** `MealIngredientScaler::scale()` deletes the whole plan's `meal_ingredients` and re-inserts them in one transaction (`app/Services/IngredientScaling/MealIngredientScaler.php:136-144`). It also skips meals without a recipe (`:66-68`) and does not assume distinct recipes. Approval and re-approval can reuse it unchanged.
- **Downstream code handles repeats.** `ShoppingListBuilder` sums by ingredient and unit and dedupes "without ingredients" notices by recipe id (`app/Services/ShoppingList/ShoppingListBuilder.php:28-45,85`). The PDF exporter does not assume distinct recipes either. A recipe repeated through a swap needs no downstream change.
- **The draw must not run while the plan is generating.** `MealPlanGenerator::generate()` deletes and re-creates all meals in its own transaction and throws `InsufficientRecipesException` on a shortfall (`app/Services/MealPlanGenerator.php:66-105`).
- **The rate limit protects the AI call.** `GenerateMealPlanRequest` limits generation to 5 per hour under the key `generate-meal-plan:{userId}` (`app/Http/Requests/GenerateMealPlanRequest.php:56-63,96-101`), because generation is what calls the AI today. `E2eMealPlanSeeder` clears that key (`database/seeders/E2eMealPlanSeeder.php:102`).
- **Status strings are hardcoded.**
  - Backend: controller filter labels (`MealPlanController.php:70-74`), `RegenerateMealPlanRequest::REGENERATABLE_STATUSES` (`:19`), `MealPlanPolicy` (`:51,75`), `ScalingNotices` (`:19`), `DashboardController` (`:30`) and the job.
  - Frontend: `types/meal-plan.ts:2`, `Pages/MealPlans/Index.vue:45`, `Components/MealPlans/FiltersBar.vue:26`, `StatusTag.vue:13-33`, `ActionDropdown.vue:126-128`, `Show.vue:37,175-176` and `Pages/Dev/DesignSystem.vue:191`.
- **The grid is display-only.** `MealPlanGrid.vue` is a single `<table>` (`min-w-160`, horizontal scroll on mobile). Its cells have no actions and emit nothing (`:27-64`).
- **A picker primitive exists but is unused.** `Components/ui/` has `dialog` and a reka-ui `combobox`. The combobox is used only in the kitchen sink (`DesignSystem.vue:492-505`). There is no popover primitive and no recipe picker anywhere.

## Desired End State

- **Generate.** "Wygeneruj" (dialog on Index/Dashboard) validates the date and the 7-recipes-per-category shortfall as today. It then creates a plan with status `draft` and 21 drawn meals in the same request, with no job, and redirects to the plan.
- **The draft page shows:**
  - the `Szkic` status tag;
  - an info notice saying quantities and the shopping list come after approval;
  - a "Zatwierdź" primary action and "Losuj ponownie" behind a confirmation;
  - a grid whose cells show the recipe name, kcal and servings, plus a "Zmień" button.
- **Swap.**
  - "Zmień" opens a dialog listing the user's non-deleted recipes of that cell's category, searchable by name.
  - Recipes already in the week carry the label "jest już: <weekday abbreviations>" and remain selectable.
  - Picking one saves immediately, and the change survives a page reload.
- **Approve.** "Zatwierdź" moves the plan to `pending` and queues `GenerateMealPlanJob`, which now only scales. The page polls as today and ends `done` with quantities, the shopping list and the PDF.
  - Approval uses the 5/hour limit.
  - Creating a draft and redrawing use a loose throttle of 30 per minute, in their own bucket.
- **Back to draft.** A `done` or `error` plan shows "Wróć do szkicu" (header, error alert, row menu) where "Regeneruj" used to be.
  - It returns the plan to `draft` with the same meals.
  - It drops the scaled ingredients and the scaling metadata.
  - Approving again re-scales everything.
- **Gone.** "Regeneruj" no longer exists anywhere in the UI.
- **Verification.** Pest, Larastan, Pint, the build, `lint:tokens`, E2E and the visual gate are green. The E2E flow drives draft → swap → reload → approve → done → back to draft → approve.

### Key Discoveries:

- `MealPlanController::update` already uses the race-safe claim pattern: a conditional `whereIn(status)->update()`, with 0 rows turned into a `ValidationException` (`MealPlanController.php:155-170`). Approve and back-to-draft follow it.
- `store` already wraps plan creation in its own `DB::transaction` and converts `UniqueConstraintViolationException` to a `start_date` error (`MealPlanController.php:121-136`). The synchronous draw joins that transaction.
- `Show.vue` polling is `usePoll(3000, { only: ['mealPlan','shoppingList'] })`, started and stopped by `isGenerating = ['pending','processing']` (`Show.vue:175,189-222`). A draft must not poll, so it stays outside that list.
- `ShoppingList` and the PDF link are already gated on `done` (`MealPlanController.php:103`, `MealPlanResource.php:109`, `MealPlanCollection.php:103`). A draft gets neither without new code.
- `Recipe` has `scopeForUser(int)`, `scopeByCategory(string)` and soft deletes (`app/Models/Recipe.php:17,43,51`). `Meal::recipe()` is `withTrashed()` (`app/Models/Meal.php:34-36`), so a recipe deleted after the draw still shows as "(usunięty)" and still scales.
- Badge variants include `outline`, which no other status uses (`Components/ui/badge/index.js:16`).
- The E2E seeder pins identical ingredients and kcal on every planner recipe, so a swap does not change the shopping-list totals asserted in `meal-plan.spec.ts`. Re-check this in Phase 4.

## What We're NOT Doing

- Empty meals, `recipe_id` nullability, or dropping the 7-per-category requirement (S-18).
- A linked "cooked once, eaten twice" repeat (S-19/S-20). A swap that repeats a recipe creates two independent meals, each scaled and shopped separately.
- Offering recipes from other categories in the swap list.
- Swapping in a `done`, `pending`, `processing` or `error` plan. Swapping exists only in `draft`.
- Keeping a one-click "Regeneruj" on done/error plans. Redrawing exists only in the draft.
- Re-scaling only the swapped meals. Every approval re-scales the whole plan.
- A PHP status enum or a shared status constant refactor. `draft` is added to the existing string lists.
- Recording drafts, draws or swaps in `logs_meal_plan` (it logs job runs only).
- Changing the PDF, the shopping list, the Dashboard "latest plan" card or the AI refiner.

## Implementation Approach

The draw is a DB-only operation (no AI), so it moves onto the request path: `store` and the redraw run `MealPlanGenerator::generate()` synchronously. The job keeps its name, its overlap guard and its failure handling, but drops the draw and only scales. That is what keeps the AI off the request path (CLAUDE.md hard rule).

Lifecycle:

```
store ──► draft ──approve──► pending ──job──► processing ──► done
            ▲  │                                  └────────► error
            │  ├─ redraw (draft only, replaces meals)
            │  └─ swap meal (draft only, one meal)
            └──── back to draft (from done | error; meals kept, ingredients + scaling meta dropped)
```

Each swap is saved immediately through a nested resource endpoint, with its own policy as the hard rule requires. The Show page receives the swap candidates as a prop only while the plan is a draft.

## Environment (prerequisite for every phase)

Every `sail`, E2E and visual command must run against this worktree's code. Sail serves the checkout it was started from, and `scripts/visual-test.sh` mounts the checkout it runs from. A fresh worktree has no `.env`, `vendor/` or `node_modules/`.

1. Copy `.env` from the main checkout.
   - Give it its own `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT` and `FORWARD_MAILPIT_DASHBOARD_PORT`, and set `MAILPIT_URL` to match.
   - Keep `AI_SCALING_DRIVER=fake`, `MAIL_MAILER=smtp` and `MAIL_HOST=mailpit`.
2. Install `vendor/` through the Sail Composer container, not host PHP.
3. Run `vendor/bin/sail up -d`, `vendor/bin/sail npm ci` and `vendor/bin/sail artisan migrate`.

Run the visual gate with `VISUAL_DOCKER_NETWORK=<worktree-dir>_sail VISUAL_BASE_URL=http://laravel.test npm run test:visual`.

## Critical Implementation Details

**Draft edits must not race approval.**
- **Swap.** Use one conditional statement: update the meal only `whereHas('mealPlan', status = 'draft')`. If it updates 0 rows, return a `status` validation error. A swap that loses to an approval then fails instead of changing a meal the job is scaling.
- **Redraw.** Lock the plan row (`lockForUpdate`) in a transaction, re-check `draft`, then call `generate()`. An approval's conditional update waits on the lock and then scales the new meals.
- **Approve and back to draft.** Both are single conditional updates (`draft → pending`, `done|error → draft`), exactly like today's `update`.

**Back to draft clears scaled state in the same transaction.** Delete the plan's `meal_ingredients` and set `generation_meta` to null while flipping the status. A draft then never shows stale quantities, and `ScalingNotices` and the shopping list stay empty without extra branches.

**Phases 1–3 leave the UI and E2E temporarily inconsistent.**
- After Phase 1, generation lands in a draft that has no "Zatwierdź" button until Phase 3.
- `meal-plan.spec.ts` stays red until Phase 4.

Only merge the branch after Phase 4. Pest, Larastan and the build must be green at the end of every phase.

**Queued jobs at deploy.** The job's constructor loses its `regenerate` argument and its draw. A job queued by the old code for a plan without meals would scale nothing and mark the plan `done` with an empty week, so deploy with an empty queue (see Migration Notes).

## Phase 1: Draft Lifecycle (Backend)

### Overview

Add the `draft` status. `store` creates a drawn draft, `update` becomes the draft-only redraw, and two new actions are added: approve and back to draft. The job becomes scaling-only.

### Changes Required:

#### 1. Status migration

**File**: `database/migrations/2026_11_16_xxxxxx_add_draft_status_to_meal_plans_table.php` (via `vendor/bin/sail artisan make:migration`)

**Intent**: Allow `draft` in `meal_plans.status`. The column default stays `pending`, so existing rows and inserts that omit the status keep working.

**Contract**: Drop and re-create the CHECK constraint `meal_plans_status_check` with the values `draft, pending, processing, done, error`. `down()` restores the four-value constraint after moving any `draft` rows to `error`. Use raw `DB::statement` for PostgreSQL. Verify the actual constraint name with `\d meal_plans` before writing it.

#### 2. Factory state

**File**: `database/factories/MealPlanFactory.php`

**Intent**: Tests need draft plans.

**Contract**: Add a `draft()` state. Keep the default random status to the existing four values, so no existing test silently starts producing drafts.

#### 3. Create a draft (store)

**Files**: `app/Http/Controllers/MealPlanController.php`, `app/Http/Requests/GenerateMealPlanRequest.php`

**Intent**: Creating a plan draws the week right away into a `draft`, with no job and no AI. The 5/hour limit moves to approval (item 5).

**Contract**:
- `store` creates the plan with `status = 'draft'` and calls `MealPlanGenerator::generate()` inside the same `DB::transaction` that already guards the unique `start_date`.
- An `InsufficientRecipesException` thrown inside the transaction (recipes deleted after validation) rolls back the plan and becomes a `recipes` validation error carrying the exception message.
- Redirect to `meal-plans.show` as today. JSON keeps 201 with `MealPlanResource`; change its `message` to "Meal plan draft created."
- `GenerateMealPlanRequest` drops the `RateLimiter` check and hit (`withValidator` head, `passedValidation`), plus the dead `'regenerate' => ['boolean']` rule (`:37`) and its `prepareForValidation` merge (`:47`). The shortfall and duplicate-date checks stay.
- The `store` and `update` routes get `throttle:30,1,meal-plan-draw` through the resource registrar's `middlewareFor(['store','update'], 'throttle:30,1,meal-plan-draw')` in `routes/web.php`. The third argument is the key prefix. Without it, Laravel keys a signed-in user by `sha1(userId)` alone, the same bucket that `client-errors.store` (`throttle:10,1`) uses, so draft spam would 429 client error reports.

#### 4. Redraw a draft (update)

**Files**: `MealPlanController::update`, `app/Http/Requests/RegenerateMealPlanRequest.php`

**Intent**: `PUT meal-plans/{meal_plan}` with `regenerate: true` becomes "Losuj ponownie": it replaces the meals of a draft synchronously, and the status stays `draft`.

**Contract**:
- `REGENERATABLE_STATUSES = ['draft']`.
- `NOT_REGENERATABLE_MESSAGE = 'Losować ponownie można tylko szkic jadłospisu.'` Drop the separate processing message, since every non-draft status gets this one.
- The shortfall check stays.
- The controller locks the plan row and re-checks the status (see Critical Implementation Details), then calls `generate()`. Any other status becomes a `status` validation error.
- A run-time `InsufficientRecipesException` becomes a `recipes` validation error, and the existing meals are untouched (`generate()` already rolls back).
- Inertia: redirect back to show. JSON: 200 with `MealPlanResource` (meals loaded).
- No job dispatch and no rate-limit hit. The route throttle from item 3 applies.

#### 5. Approve a draft

**Files**: `app/Http/Requests/ApproveMealPlanRequest.php` (new), `MealPlanController::approve` (new), `routes/web.php`

**Intent**: "Zatwierdź" starts the AI scaling. This is the only path into the AI and carries the 5/hour limit.

**Contract**:
- Route: `POST meal-plans/{meal_plan}/approve`, named `meal-plans.approve`.
- `ApproveMealPlanRequest::authorize()` returns `Gate::allows('update', $mealPlan)`.
- `withValidator`:
  - Rate limit check on the unchanged key `generate-meal-plan:{userId}`, 5 attempts, same message, thrown eagerly as today.
  - `after()` callback: status must be `draft`, else a `status` error 'Zatwierdzić można tylko szkic jadłospisu.'
  - `after()` callback: the plan must have meals, else a `meals` error 'Szkic jest pusty. Wylosuj przepisy ponownie.'
- `passedValidation` hits the limiter for 3600 s.
- The controller claims the plan with a conditional `draft → pending` update that also sets `generation_meta = null`. 0 rows becomes a `status` validation error. Then it dispatches `GenerateMealPlanJob::dispatch($mealPlan)`.
- Inertia: redirect to show. JSON: 202 with "Meal plan approved; scaling started." and `MealPlanResource`.

#### 6. Back to draft

**Files**: `app/Http/Requests/ReturnMealPlanToDraftRequest.php` (new), `MealPlanController::backToDraft` (new), `routes/web.php`

**Intent**: Reopen a `done` or `error` plan for editing with the same recipes.

**Contract**:
- Route: `POST meal-plans/{meal_plan}/draft`, named `meal-plans.draft`.
- The request authorizes `update`. Its `after()` check requires status `done` or `error`, else a `status` error 'Do szkicu można wrócić tylko z gotowego lub nieudanego jadłospisu.'
- The controller runs one transaction: a conditional `whereIn(status, ['done','error'])` update to `draft` with `generation_meta = null`, then deletes the plan's `meal_ingredients`. 0 rows becomes a `status` error.
- A legacy error plan without meals becomes an empty draft. The UI offers "Losuj ponownie" there.
- Inertia: redirect to show. JSON: 200 with `MealPlanResource`.

#### 7. Scaling-only job

**File**: `app/Jobs/GenerateMealPlanJob.php`

**Intent**: The job scales the meals already in the plan and no longer draws.

**Contract**:
- The constructor is `__construct(public MealPlan $mealPlan)`, with no `regenerate`.
- `handle(MealIngredientScaler $scaler)` sets `processing`, scales, then sets `done` with `generation_meta = ['generated_at', 'version' => 'scaled-v1', 'scaling' => …]`.
- The `InsufficientRecipesException` branch is removed.
- Unchanged: the overlap guard, the generic error branch, the logs and `failed()`.

#### 8. Index filter labels

**File**: `MealPlanController::index`

**Intent**: The status filter offers drafts.

**Contract**: Prepend `['value' => 'draft', 'label' => 'Szkic']` to `statuses`.

#### 9. Policy

**File**: `app/Policies/MealPlanPolicy.php`

**Intent**: No rule change is needed. `update` (owner) covers approve, back to draft and redraw, and `delete` already allows anything except `processing`, so drafts can be deleted.

**Contract**: Unchanged. Extend the policy tests' status datasets with `draft` (allowed for update and delete).

#### 10. Agent docs

**File**: `CLAUDE.md`

**Intent**: The hard rule describing the pipeline must stay true.

**Contract**: Rewrite the `GenerateMealPlanJob` bullet:
- `MealPlanGenerator` draws recipes synchronously when a draft is created or redrawn (DB only).
- The job, dispatched on approval, runs `MealIngredientScaler` and then an `IngredientRefiner` driver.
- The AI call never runs on a request path.

#### 11. Tests

**Files**: `tests/Feature/MealPlanControllerTest.php`, `tests/Unit/Jobs/GenerateMealPlanJobTest.php`, `tests/Unit/MealPlanRequestValidationTest.php`, `tests/Unit/MealPlanPolicyTest.php`

**Intent**: Pin the new lifecycle and its failure modes. Rewrite the tests that assumed generate = job.

**Contract**:

*Feature, store:*
- `store creates a draft with 21 drawn meals and queues nothing`: asserts `draft`, 21 meals, 7 per category, `Queue::assertNothingPushed()`.
- `store is not limited to 5 per hour`.
- `store turns a run-time shortfall into a recipes error and creates no plan`.
- Shortfall and duplicate tests stay.
- The `generate-meal-plan:` limiter-key assertions on the store path (`:321,327`) move to the approve tests. Store asserts that the key stays untouched.

*Feature, dashboard:* add `draft` to the status loop in `tests/Feature/DashboardControllerTest.php:21-30` (counted in `mealPlans`, never the `latestMealPlan`).

*Feature, redraw:*
- `redraw replaces the meals of a draft`.
- `redraw is refused for done, error, pending, processing`.
- `redraw keeps the meals on a shortfall`.
- Replaces the "regenerate" tests (`:170-246,330-412`).

*Feature, approve:*
- `approve moves a draft to pending and queues the job`.
- `approve is refused for every non-draft status`.
- `approve is refused for an empty draft`.
- `approve is limited to 5 per hour`.
- `approve that lost the race is refused`.
- `non-owner cannot approve`.
- `Inertia approve redirects to show`.

*Feature, back to draft:*
- `back to draft keeps the meals, drops the ingredients and the scaling meta`.
- `back to draft is refused for draft, pending, processing`.
- `non-owner cannot`.
- `a done plan that went back to draft and was approved again shows scaled ingredients`. Run the job synchronously with the fake refiner.

*Feature, show:* `a draft gets no shopping list and no PDF link`.

*Job:*
- The tests start from a plan with drawn meals (`mealPlanWithRecipes` helper).
- They assert scaling only and that meals are untouched.
- The run-time shortfall test (`:198`) is removed, since the job no longer draws.

*Unit:* request validation tests for approve (status, empty draft, rate limit) and back-to-draft.

#### 12. Legacy scaling notice

**Files**: `app/Services/IngredientScaling/ScalingNotices.php`, `tests/Unit/Services/ScalingNoticesTest.php`

**Intent**: The notice for plans made before scaling (`version` null or `random-v1`, `:49-52`) tells the user to "zregeneruj" the plan, an action this change removes.

**Contract**:
- New text: 'Ten jadłospis powstał przed przeliczaniem składników — wróć do szkicu i zatwierdź go ponownie, aby dopasować ilości do rodziny.'
- Update the pinned text in `ScalingNoticesTest.php:16,53`.
- Add `draft` to the test's non-done status dataset (`:74-76`).

### Success Criteria:

#### Automated Verification:

- Migration applies and rolls back cleanly: `vendor/bin/sail artisan migrate:fresh` then `vendor/bin/sail artisan migrate:rollback --step=1` then `vendor/bin/sail artisan migrate`
- Pest suite passes: `vendor/bin/sail composer test`
- Larastan passes with no new baseline entries: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`
- Routes register: `vendor/bin/sail artisan route:list --name=meal-plans` lists `meal-plans.approve` and `meal-plans.draft`

#### Manual Verification:

- Through Tinker or HTTP against Sail, generating a plan creates a `draft` with 21 meals and leaves the `queue` worker log idle until approval

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Recipe Swap Endpoint

### Overview

A nested resource saves one swapped meal immediately, and the Show page gets the swap candidates while the plan is a draft.

### Changes Required:

#### 1. Meal policy

**File**: `app/Policies/MealPolicy.php` (new, via `vendor/bin/sail artisan make:policy MealPolicy --model=Meal`)

**Intent**: The hard rule requires a policy for every resource controller. A meal belongs to whoever owns its plan.

**Contract**: `update(User $user, Meal $meal): bool` returns `$meal->mealPlan->user_id === $user->id`. No other abilities: the controller exposes only `update`. The draft status check lives in the request, as in `MealPlanPolicy::update`.

#### 2. Swap request

**File**: `app/Http/Requests/UpdateMealRequest.php` (new)

**Intent**: Accept only the owner's non-deleted recipe from the meal's category, and only while the plan is a draft.

**Contract**:
- `authorize()` uses `Gate::allows('update', $meal)`.
- `rules()`: `recipe_id` is required and an integer, plus `Rule::exists('recipes', 'id')` scoped with `user_id` = the plan owner, `category` = `$meal->meal_category` and `whereNull('deleted_at')`. Error message: 'Wybierz jeden ze swoich przepisów z tej kategorii.'
- `after()`: the plan status must be `draft`, else a `status` error 'Przepisy można zmieniać tylko w szkicu jadłospisu.'
- Picking the current recipe again is valid (a no-op).

#### 3. Swap controller and route

**Files**: `app/Http/Controllers/MealPlanMealController.php` (new), `routes/web.php`

**Intent**: Save one swap.

**Contract**:
- Route: `Route::resource('meal-plans.meals', MealPlanMealController::class)->only(['update'])->scoped()`, which gives `PATCH meal-plans/{meal_plan}/meals/{meal}` named `meal-plans.meals.update`. Scoped bindings 404 a meal from another plan.
- The constructor calls `$this->authorizeResource(Meal::class, 'meal')`.
- `update` performs the conditional update from Critical Implementation Details. 0 rows becomes a `status` validation error.
- Inertia: `back()` (the page reloads only `mealPlan`). JSON: 200 with `MealPlanResource` of the plan, `meals.recipe` loaded.

#### 4. Swap candidates on Show

**File**: `MealPlanController::show`

**Intent**: The swap dialog needs the user's recipes per category, but only for a draft.

**Contract**:
- Add the Inertia prop `recipeOptions`, a closure returning `null` unless the status is `draft`.
- For a draft it returns `{ breakfast: Option[], supper: Option[], dinner: Option[] }` with `Option = { id: int, name: string, calories: string|null }`.
- The source is `Recipe::forUser($mealPlan->user_id)` (soft-deleted excluded), ordered by name.
- Every category key is always present, as an empty list if needed.

#### 5. Tests

**Files**:
- `tests/Feature/MealPlanMealControllerTest.php` (new: one file per controller)
- `tests/Unit/MealPolicyTest.php` (new)
- `tests/Unit/MealRequestValidationTest.php` (new)
- `tests/Feature/MealPlanControllerTest.php`

**Intent**: Pin the swap rules and the ownership boundary.

**Contract**:
- **Controller tests:**
  - `owner swaps a draft meal to another recipe of the same category`
  - `swap to a recipe already used that week is allowed and leaves both meals`
  - `swap is refused for a recipe of another category`
  - `swap is refused for a deleted recipe`
  - `swap is refused for another user's recipe`
  - `swap is refused when the plan is not a draft` (pending, processing, done, error)
  - `non-owner gets 403`
  - `a meal of another plan in the URL gets 404`
  - `Inertia swap redirects back`
- **Policy tests:** the owner may update; a non-owner may not.
- **Show tests:**
  - `a draft passes recipe options per category without deleted recipes`
  - `a done plan passes no recipe options`

### Success Criteria:

#### Automated Verification:

- Pest suite passes: `vendor/bin/sail composer test`
- Larastan passes with no new baseline entries: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`
- Route registers: `vendor/bin/sail artisan route:list --name=meal-plans.meals` lists `PATCH meal-plans/{meal_plan}/meals/{meal}`

**Implementation Note**: After completing this phase and all automated verification passes, proceed to Phase 3.

---

## Phase 3: Draft UI

### Overview

The Show page gets a draft state with approve, redraw and per-cell swap. Done and error plans get "Wróć do szkicu", and every status list learns `draft`.

### Changes Required:

#### 1. Status type and tag

**Files**:
- `resources/js/types/meal-plan.ts`
- `Components/MealPlans/StatusTag.vue`
- `Pages/MealPlans/Index.vue`
- `Components/MealPlans/FiltersBar.vue`

**Intent**: `draft` is a known status everywhere.

**Contract**:
- Add `'draft'` to the `MealPlanStatus` union. The three local unions import it from `types/meal-plan.ts` instead of redeclaring it.
- `StatusTag` maps `draft` to the label 'Szkic' and the Badge variant `outline`.

#### 2. Grid edit mode

**File**: `resources/js/Components/MealPlans/MealPlanGrid.vue`

**Intent**: Draft cells offer a swap action, and every other mode renders exactly as today.

**Contract**:
- New prop `editable?: boolean` (default `false`) and new emit `swap(meal: GridMeal)`.
- When `editable`, each filled cell shows a small "Zmień" `Button` under the kcal line, with `aria-label` "Zmień przepis: <day> – <category label>".
- The recipe name stays a link to the recipe.
- Token utilities only. The `tbody td a` count stays 21, because the button is not an `<a>`.

#### 3. Swap dialog

**File**: `resources/js/Components/MealPlans/RecipeSwapDialog.vue` (new)

**Intent**: Pick a replacement recipe for one meal and save it immediately.

**Contract**:
- Props: `open`, `mealPlanId`, `meal`, `options` (that category's `Option[]`), `weekMeals` (all meals of the plan). Emits `update:open`.
- Built from `ui/dialog` and `ui/combobox`: a search input that filters names case-insensitively, then the list.
- Each item shows the name and kcal.
- The current recipe is marked "obecny".
- A recipe used in other cells of the week shows "jest już: <weekday short names>". Take the weekday names from `lib/format.js`, adding a `formatWeekdayShort` helper if missing.
- An empty filter result shows "Brak przepisów".
- Selecting an item calls `router.patch(route('meal-plans.meals.update', { meal_plan, meal }), { recipe_id }, { preserveScroll: true, only: ['mealPlan'] })` and closes on success. On error it shows a toast with `errors.recipe_id ?? errors.status`.

#### 4. Show page states

**File**: `resources/js/Pages/MealPlans/Show.vue`

**Intent**: The draft is editable and approvable, and done/error plans offer going back to the draft instead of regenerating.

**Contract**:
- **New prop** `recipeOptions: Record<MealCategory, Option[]> | null`.
- **`draft` with meals:**
  - an info `Alert`: "To szkic jadłospisu. Możesz podmienić przepisy; ilości i listę zakupów policzymy po zatwierdzeniu.";
  - header actions "Zatwierdź" (primary) and "Losuj ponownie" (secondary, behind `ConfirmDialog`: "Wylosować tydzień od nowa? Twoje zmiany przepisów przepadną.");
  - `MealPlanGrid editable`, wired to `RecipeSwapDialog`.
- **`draft` without meals:** `EmptyState` with "Losuj ponownie" as its CTA.
- **Approve:** `router.post(route('meal-plans.approve', …))`. Errors go to a toast with `errors.rate_limit ?? errors.status ?? errors.meals`.
- **Redraw:** `router.put(route('meal-plans.update', …), { regenerate: true })`.
- **`done` and `error`:** every "Regeneruj" (header, error alert, empty state) becomes "Wróć do szkicu".
  - It sits behind a `ConfirmDialog`: "Wrócić do szkicu? Przepisy zostaną, a ilości i listę zakupów policzymy ponownie po zatwierdzeniu."
  - It calls `router.post(route('meal-plans.draft', …))`.
  - Each state still shows that control exactly once (see the archived meal-plan-show-ui rule on one button per state).
- **Unchanged:** `isGenerating` stays `['pending','processing']`, so a draft is not polled.

#### 5. Row menu

**File**: `resources/js/Components/MealPlans/ActionDropdown.vue`

**Intent**: The list matches the Show page.

**Contract**:
- The "Regeneruj" item becomes "Wróć do szkicu" for `done`/`error` (same confirm text and POST as Show).
- A draft gets no extra item.
- Delete is unchanged.

#### 6. Generate dialog copy

**File**: `resources/js/Components/MealPlans/GenerateMealPlanDialog.vue`

**Intent**: Tell the user that generation produces a draft.

**Contract**:
- One description line: "Wylosujemy przepisy na tydzień. Przed zatwierdzeniem możesz je podmienić."
- Button labels stay ("Wygeneruj"), because E2E and the visual specs select them.
- The `rate_limit` error display can stay; the error no longer occurs there.

#### 7. Kitchen sink

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: The new shared pattern variants get demos (CLAUDE.md verification rule).

**Contract**:
- Add `draft` to `appStatuses`.
- Add a `MealPlanGrid editable` demo next to the existing grid demos.
- Add a `RecipeSwapDialog` demo opened by a button, with sample options including one "jest już" and one "obecny" item.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token lint passes: `vendor/bin/sail npm run lint:tokens`
- Pest suite still passes (Inertia prop and page tests): `vendor/bin/sail composer test`

#### Manual Verification:

- On a seeded user, "Wygeneruj" lands on a draft with the `Szkic` tag, 21 cells with "Zmień", no ingredients and no shopping list
- "Zmień" on Monday "Obiad" lists only "Obiad" recipes; the drawn ones carry "jest już: …" and the current one "obecny"; search filters the list; picking one updates the cell, and it persists after a browser reload
- "Losuj ponownie" asks for confirmation and replaces the week
- "Zatwierdź" shows "Generujemy jadłospis…", then the done plan with quantities, the shopping list and "Pobierz PDF"
- "Wróć do szkicu" on the done plan keeps the swapped recipe and hides the quantities; approving again restores them
- The swap dialog works at phone width (grid scrolls horizontally; dialog fits the screen) and is keyboard-operable

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: E2E and Visual Gate

### Overview

The browser tests drive the new flow, and the visual gate covers the draft and the dialog. Baselines are updated deliberately.

### Changes Required:

#### 1. E2E flow

**File**: `tests/e2e/meal-plan.spec.ts`

**Intent**: Prove the whole lifecycle in a real browser against the queue worker.

**Contract**:
1. Generate as today. Expect `/meal-plans/\d+$`, the `Szkic` tag, 21 `tbody td a`, no `shopping-list`, and no "Pobierz PDF".
2. Open "Zmień" on the first "Obiad" cell and pick a recipe labelled "jest już". Expect the cell name to change.
3. Reload. Expect the swapped name to remain.
4. "Zatwierdź". Expect "Generujemy jadłospis…", then 21 cells, the shopping list (same three totals, which works because the seeder pins identical ingredients — verify), the scaled grid, and the PDF download checks.
5. "Wróć do szkicu", then confirm. Expect `Szkic`, the swapped name still present, and no shopping list.
6. "Zatwierdź" again. Expect done with the shopping list.

The steps replace the "Regeneruj" part (`:62` onward).

#### 2. Seeder: a pinned draft for the visual user

**Files**: `database/seeders/E2eMealPlanSeeder.php`, `tests/Unit/Seeders/E2eMealPlanSeederTest.php`

**Intent**: The visual gate needs a deterministic draft.

**Contract**:
- `syncVisualMealPlans` adds a `draft` plan starting `2026-06-29`, with pinned recipe names per slot (reuse the `buildVisualPlan` pinning unchanged, without scaling or ingredients) and pinned `created_at`/`updated_at`.
- Choose a `created_at` that keeps the existing index rows' order. Place it right after the 2026-06-15 done plan.
- No forced repeat: the visual user has 7 recipes per category and the draft uses all of them, so every non-current item in the swap dialog already shows "jest już".
- No `pending` plan is seeded, so the filtered-empty shot still holds.
- The done plan's fixture no longer writes `'regenerate' => false` into `generation_meta` (`:216`).
- The seeder test asserts the five statuses and that the draft has 21 meals and no `meal_ingredients`.

#### 3. Visual specs

**Files**: `tests/e2e/visual/meal-plan-show.spec.ts`, `tests/e2e/visual/meal-plans-index.spec.ts`

**Intent**: Screenshot the new states.

**Contract**:
- `meal-plan-show.spec.ts` gains `meal-plan-draft-desktop.png`, `meal-plan-draft-mobile.png` and `meal-plan-draft-swap-dialog.png`. The last one opens "Zmień" on a cell whose list contains "obecny" and "jest już".
- `meal-plans-index.spec.ts` expects 6 rows instead of 5 in both `toHaveCount(5)` assertions (`:25` and `:61`).

#### 4. Baseline updates

**Intent**: Regenerate only the baselines this change is expected to alter, and review every changed PNG.

**Contract**:
- Run `npm run test:visual -- --update-snapshots`, then review each PNG in the diff.
- Expected to change:
  - the three new draft shots;
  - `meal-plans-index-desktop/mobile` (new draft row);
  - `meal-plans-index-generate-dialog` (new description line, Phase 3 §6);
  - `meal-plan-show-desktop/mobile` (the done plan's header button "Regeneruj" becomes "Wróć do szkicu"; nothing else in those shots may change);
  - the design-system shots (draft tag, editable grid, dialog trigger);
  - `dashboard-desktop` and `dashboard-mobile` (plan count 4 → 5).
- Every other baseline must stay unchanged.

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Seeder runs and its test passes: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder` and `vendor/bin/sail pest --filter=E2eMealPlanSeeder`
- E2E passes: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- Visual gate passes after the deliberate update: `npm run test:visual`
- Full Pest suite passes: `vendor/bin/sail composer test`

#### Manual Verification:

- Every changed or new PNG in the diff was reviewed, and only the baselines listed in §4 changed (the done plan show shots differ only in the header button)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful.

---

## Testing Strategy

### Unit Tests:

- `MealPolicy` update: owner versus non-owner.
- `MealPlanPolicy`: datasets extended with `draft`.
- Form Requests:
  - approve: status, empty draft, rate limit;
  - back to draft: status;
  - swap: category, ownership, soft delete, draft only;
  - redraw: draft only.
- Job: scales the existing meals only; failure paths unchanged.
- Seeder: the pinned draft exists without ingredients.

### Integration Tests:

- Feature tests per controller cover:
  - every transition and its refusals;
  - the race-loss paths (approve and swap against a non-draft);
  - the rate limit moving from store to approve;
  - re-approval re-scaling after back to draft.
- E2E: draft → swap (repeat) → reload → approve → done → back to draft → approve.

### Manual Testing Steps:

1. Generate a plan. It is a `Szkic` with 21 swappable cells and no quantities.
2. Swap Monday "Obiad" to a recipe already in the week, then reload. The swap persists.
3. "Losuj ponownie", then confirm. The whole week changes.
4. Approve. Watch it go through generating to done, with quantities, the shopping list and the PDF.
5. "Wróć do szkicu", then confirm. The recipes stay and the quantities are gone. Approve again.
6. Approve six times within an hour (going back to draft in between). The sixth is refused with the rate-limit message.

## Performance Considerations

- The synchronous draw is 3 queries plus 21 inserts in one transaction, well within a request.
- `recipeOptions` is one query per draft view, never polled, because a draft does not poll.

## Migration Notes

- The migration only widens the CHECK constraint. Existing plans keep their status.
- **Deploy with an empty queue.** Let pending generations finish, or stop the worker and wait. The new job no longer draws, so a job queued by the old code for a plan without meals would mark the plan `done` with an empty week.
- Rollback: `migrate:rollback` turns drafts into `error` before restoring the four-value constraint. A `done`/`error` plan can then be regenerated with the old code.

## References

- Roadmap item: `context/foundation/roadmap.md` (S-17, MS-01, MS-02; Unknowns settled 2026-11-16)
- Claim pattern: `app/Http/Controllers/MealPlanController.php:155-182`
- Draw: `app/Services/MealPlanGenerator.php:66-105`
- Idempotent scaling: `app/Services/IngredientScaling/MealIngredientScaler.php:136-144`
- Polling: `resources/js/Pages/MealPlans/Show.vue:175,189-222`
- One-button-per-state rule: `context/archive/2026-11-15-meal-plan-show-ui/plan.md` (Critical Implementation Details)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Draft Lifecycle (Backend)

#### Automated

- [ ] 1.1 Migration applies and rolls back cleanly
- [ ] 1.2 Pest suite passes
- [ ] 1.3 Larastan passes with no new baseline entries
- [ ] 1.4 Pint passes
- [ ] 1.5 Routes register

#### Manual

- [ ] 1.6 Generating a plan creates a draft with 21 meals and leaves the queue worker idle until approval

### Phase 2: Recipe Swap Endpoint

#### Automated

- [ ] 2.1 Pest suite passes
- [ ] 2.2 Larastan passes with no new baseline entries
- [ ] 2.3 Pint passes
- [ ] 2.4 Route registers

### Phase 3: Draft UI

#### Automated

- [ ] 3.1 Client and SSR bundles build
- [ ] 3.2 Token lint passes
- [ ] 3.3 Pest suite still passes

#### Manual

- [ ] 3.4 Wygeneruj lands on a draft with the Szkic tag, 21 cells with Zmień, no ingredients and no shopping list
- [ ] 3.5 Zmień lists same-category recipes with labels, search works, and the swap persists after reload
- [ ] 3.6 Losuj ponownie asks for confirmation and replaces the week
- [ ] 3.7 Zatwierdź generates the done plan with quantities, shopping list and PDF
- [ ] 3.8 Wróć do szkicu keeps the swapped recipe and re-approval restores quantities
- [ ] 3.9 The swap dialog works at phone width and is keyboard-operable

### Phase 4: E2E and Visual Gate

#### Automated

- [ ] 4.1 Build passes
- [ ] 4.2 Seeder runs and its test passes
- [ ] 4.3 E2E passes
- [ ] 4.4 Visual gate passes after the deliberate update
- [ ] 4.5 Full Pest suite passes

#### Manual

- [ ] 4.6 Every changed or new PNG was reviewed and only the listed baselines changed
