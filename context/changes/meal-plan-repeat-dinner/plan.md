# Repeat Dinner (Cooked Once, Eaten Twice) Implementation Plan

## Overview

Let the user plan the same Obiad two days in a row and cook it once (roadmap S-19, MS-04):
- **In the draft.** The Obiad dialog of day D+1 offers "Jak wczoraj: <recipe of day D>". Picking it links the two meals. Day D reads "Na 2 dni", and day D+1 shows the same dish with "Z wczoraj".
- **After approval.** Day D's ingredients are scaled for both meals (×2). Day D+1 has no ingredients of its own. The shopping list therefore contains the dish once, in double quantity. Screen and PDF carry the same two markers.
- **Unlinking.** Swapping or emptying day D removes the link and empties day D+1. Swapping or emptying day D+1 only removes the link.

The link is the "cooked once, eaten twice" mechanism that S-20 later extends to Kolacja → next-day Śniadanie.

## Current State Analysis

S-17 and S-18 are done:
- A draft has 21 `meals` rows. `recipe_id` NULL means an empty meal.
- One endpoint swaps, empties and fills a meal: `PATCH meal-plans/{plan}/meals/{meal}`.
- Approval queues the scaling-only `GenerateMealPlanJob`.

**Naming trap: Obiad is `supper`.** The stored categories are `breakfast | supper | dinner`, and `supper` is shown as Obiad, `dinner` as Kolacja (`app/Enums/MealCategory.php:10-12,25-29`; `resources/js/lib/mealCategories.js:2-8`). S-19 links `supper` → `supper`. S-20 will link `dinner` → `breakfast`.

Constraints found:
- **Schema.**
  - `meals`: `id`, `meal_plan_id` (cascade), `recipe_id` (nullable, `nullOnDelete`), `meal_date`, `meal_category`. There is a plain index on `(meal_plan_id, meal_date, meal_category)` and no unique constraint (`database/migrations/2025_07_31_145701_create_meals_table.php:15-23`; `2026_11_16_122505_make_meals_recipe_id_nullable.php:17-27`).
  - `meal_ingredients` has `unique(meal_id, ingredient_id)` and cascades on meal delete (`2026_11_14_111711_create_meal_ingredients_table.php:17-28`).
  - There is no `MealFactory`. Tests create meals with `Meal::query()->create` (`tests/Pest.php:48-61`).
- **Swap endpoint.**
  - `MealPlanMealController::update` does one conditional update that sets `recipe_id`, guarded by `whereHas('mealPlan', status = draft)`. When it updates 0 rows it answers `status` → `NOT_DRAFT_MESSAGE` (`app/Http/Controllers/MealPlanMealController.php:25-53`).
  - `UpdateMealRequest` requires `recipe_id` (`present`, `nullable`, `integer`, plus an `exists` rule scoped to the owner, the meal's category and non-deleted recipes; `:37-46`). It checks the draft status in `after()` (`:67-78`).
  - Scoped route binding ties `{meal}` to `{meal_plan}` (`routes/web.php:30-32`). `MealPolicy::update` checks ownership only (`app/Policies/MealPolicy.php:16-19`).
- **Lifecycle.**
  - **Redraw** deletes and re-inserts every meal, so meal ids change (`app/Services/MealPlanGenerator.php:59,73-83`).
  - **Back to draft** keeps the meals and their ids and deletes every `meal_ingredients` row (`MealPlanController.php:310-347`).
  - **Approve** is a conditional `draft → pending` update that requires a meal with a recipe (`:271-305`).
- **Scaling.**
  - `MealIngredientScaler::scale` loops over meals, skips `recipe === null` (`app/Services/IngredientScaling/MealIngredientScaler.php:62-68`), and computes one factor per meal (`:70-72`).
  - The factor is `family kcal × share ÷ recipe kcal`, with shares 0.30 / 0.40 / 0.30. It is null above `MAX_FACTOR` 50 or for 0 kcal (`ScalingCalculator.php:18,24,31-41`). With no family, or a null factor, amounts stay as written with `source = as_written` (`MealIngredientScaler.php:81,90`).
  - `RefinementItem`s are keyed by ingredient, unit and computed quantity, and deduplicated per plan (`:96-132`), so a doubled quantity flows through the AI refiner unchanged.
  - The writer deletes all of the plan's `meal_ingredients` and bulk-inserts the new rows (`:136-144`).
- **Shopping list.** `ShoppingListBuilder::forMealPlan` sums ingredient rows across meals. It skips null recipes (`app/Services/ShoppingList/ShoppingListBuilder.php:80-82`). A meal that has a recipe but no ingredient rows lands in `recipes_without_ingredients` (`:84-87`), which is shown on screen and in the PDF (`resources/views/pdf/meal-plan.blade.php:113-114`).
- **PDF.**
  - `MealPlanPdfExporter::pdfMeal` builds a `PdfMeal(category, categoryValue, recipeName, recipeDeleted, ingredients, empty)` (`app/Services/MealPlanPdf/MealPlanPdfExporter.php:119-135`; `PdfMeal.php`).
  - Recipe pages are keyed by `recipe_id`. Each page groups its uses by printed ingredient lines, and a use without lines forms its own "—" group (`MealPlanPdfExporter.php:50-60`; `tests/Unit/Services/MealPlanPdfExporterTest.php:305`).
- **Resource and props.**
  - `MealPlanResource` emits `{id, meal_date, meal_category, recipe?, ingredients?}` per meal (`app/Http/Resources/MealPlanResource.php:42-81`).
  - `meals_count` counts meals with a recipe (`MealPlanController.php:49-50`).
  - `shortCategories` reacts only to `recipe_id` NULL (`:130-152`).
- **UI.**
  - Each grid cell has exactly one action button, "Zmień" or "Wybierz", which emits `swap(meal)`. There is no badge slot yet (`resources/js/Components/MealPlans/MealPlanGrid.vue:28-88,106-141`).
  - `RecipeSwapDialog` already has a fixed first item, `EMPTY_OPTION` ("Nie robimy"), hidden while searching (impl review F1 of S-18). It receives `weekMeals`, and `handleSelect` maps the marker to the PATCH body (`RecipeSwapDialog.vue:15-26,90,112-169`).
  - The meal shape is declared locally three times: Grid `:106-126`, Show `:180-200`, and `SwapMeal` in the dialog `:70-75`.
  - The Badge variants include `muted` (`resources/js/Components/ui/badge/index.js`). The design system requires text, not colour alone, as the carrier of meaning (`context/foundation/design-system.md:124`).

## Desired End State

- **Link data.** A meal can feed exactly one other meal of the same plan through `meals.feeds_meal_id`:
  - The fed meal ("carried-over meal") keeps the same `recipe_id` as the feeding meal ("cooked meal").
  - The only allowed pair in S-19 is `supper` on day D → `supper` on day D+1.
  - Chains are refused both ways. A carried-over meal cannot feed another meal, and a cooked meal cannot itself be carried over.
- **Draft UI.**
  - The Obiad dialog of day D+1 shows the fixed item "Jak wczoraj: <name>" ("Gotujemy raz na 2 dni"), but only while the search box is empty, day D has a recipe, day D is not itself carried over, and day D+1 does not feed day D+2.
  - Picking it saves immediately. Day D's cell shows a "Na 2 dni" badge. Day D+1's cell shows the dish link with a "Z wczoraj" badge, with no kcal line and no ingredients. Both cells keep their single "Zmień" button.
  - On a linked day D+1, "obecny" marks the "Jak wczoraj" item.
  - The dialog of a cooked meal warns: "Zmiana opróżni obiad z <wt. 30.06>, który jest „z wczoraj”."
- **Unlinking.**
  - Changing the cooked meal's recipe (swap or "Nie robimy") clears the link and empties the carried-over meal.
  - Changing the carried-over meal's recipe clears the link and leaves the cooked meal as it is.
  - Picking the recipe a meal already has changes nothing and keeps the link.
  - "Losuj ponownie" drops all links, and its confirm says so. "Wróć do szkicu" keeps them.
- **After approval.**
  - The cooked meal's ingredients are scaled to `(single-meal factor, or 1 when it is unscaled or there is no family) × 2`. With a single-meal factor they are `source = computed` (or refined). Without one (no family, or an unscaled recipe) they stay `source = as_written`, doubled, and never reach the AI refiner.
  - The carried-over meal has no `meal_ingredients` rows and is not listed in "przepisy bez składników".
  - The shopping list therefore holds the dish once, in double quantity.
  - The PDF table prints "Na 2 dni" on the cooked row and "Z wczoraj" on the carried-over row (with an empty ingredients cell). The recipe page lists the pair as one use, labelled "(na 2 dni)".
- **List.** The plans list counts the carried-over meal as a planned meal (21 for a full week).
- **Verification.** Pest, Larastan, Pint, the build, `lint:tokens`, E2E and the visual gate are green. The E2E flow links Tuesday's Obiad to Monday's and approves. It then sees "Mąka — 400 g" on Monday's Obiad, no "Składniki" on Tuesday's, and unchanged totals (42 szt. / 4,2 kg / 2,1 l). Finally it swaps Monday's Obiad and sees Tuesday's Obiad become "Bez posiłku".

### Key Discoveries:

- **The shopping list needs no new aggregation.** It already sums ingredient rows across meals (`ShoppingListBuilder.php:29-44`). Doubled rows on the cooked meal plus no rows on the carried-over meal are exactly "once, in double quantity". The only change is to skip the carried-over meal before the `withoutIngredients` check (`:84-87`).
- **The E2E totals do not change when two Obiady are linked.** The seeder pins identical ingredients on every recipe and a ×2 factor on every meal (`E2eMealPlanSeeder.php:325-386`). 19 normal meals ×1, plus the cooked meal ×2, plus the carried-over meal ×0, still make 21 units: 42 szt. / 4,2 kg / 2,1 l. The visible difference is where the amounts sit, Mąka 400 g on the cooked Obiad versus 200 g elsewhere.
- **The cap must apply to the single-meal factor.** Multiply by 2 after `ScalingCalculator::factor()`, so `MAX_FACTOR` keeps its meaning (an implausible recipe kcal) and the "unscaled" notice is unchanged. The worst case (50 × 2) stays far inside `decimal(12,2)`.
- **Keeping `recipe_id` on the carried-over meal makes most readers correct for free.** The grid link, `meals_count`, the approve check, `shortCategories` and the PDF name all keep working. Only the scaler, the shopping list, the PDF recipe pages and the two markers need to know about the link.
- **The resource can derive `from_meal_id` without a query.** It can invert `feeds_meal_id` over the already-loaded `meals`.
- **The "approval race" test must keep passing** (`tests/Feature/MealPlanMealControllerTest.php:165`). The new transactional update locks the plan row, so a concurrent approve, which is a conditional `UPDATE … WHERE status = 'draft'`, serialises behind it and re-evaluates the status.

## What We're NOT Doing

- Chains (one pot for 3+ days). Pairs only.
- Links for other categories. Kolacja → next-day Śniadanie is S-20; Śniadanie → Śniadanie and Kolacja → Kolacja are not planned.
- Links across plans, such as the previous week's Sunday feeding Monday. Monday's Obiad never offers "Jak wczoraj", and Sunday's Obiad cannot feed anything.
- A second action on the cooked cell ("Na 2 dni" button). The link is set only from day D+1's dialog.
- Letting the draw create repeats. The draw stays without repeats (roadmap "Parked").
- Linking or unlinking outside the draft.
- Consolidating the three local meal type declarations into `types/meal-plan.ts`.
- Any change to the AI refiner drivers, `RefinementGuard`, `ShoppingList::build()` or the kcal shares.
- Clearing a dangling link after a recipe is hard-deleted. Only the seeder hard-deletes recipes, and both meals then become empty through `nullOnDelete`. Readers check the recipe before the link.

## Implementation Approach

The link is a nullable, unique self-reference on the cooked meal, `meals.feeds_meal_id`. The carried-over meal keeps the same `recipe_id`.
- **Pair rules live in one place.** A small `MealLinkRules` service names the allowed pairs and implements `canFeed()`, and the frontend mirrors the pair list (the `mealCategories.js` pattern). S-20 adds one pair to each.
- **One endpoint.** The S-17 endpoint takes either `recipe_id` (swap, empty or fill, as today) or the new `from_meal_id` (link). It runs in one transaction that locks the plan row.
- **Readers.** The scaler doubles the cooked meal and skips the carried-over one. The shopping list and the PDF recipe pages skip the carried-over meal. The resource exposes `feeds_meal_id` and `from_meal_id`, and the grid, the dialog and the PDF render the two markers from them.

## Environment (prerequisite for every phase)

Every `sail`, E2E and visual command must run against this worktree's code. Sail serves the checkout it was started from, and `scripts/visual-test.sh` mounts the checkout it runs from. A fresh worktree has no `.env`, `vendor/` or `node_modules/`.

1. Copy `.env` from the main checkout.
   - Give it its own `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT` and `FORWARD_MAILPIT_DASHBOARD_PORT`, and set `MAILPIT_URL` to match.
   - Keep `AI_SCALING_DRIVER=fake`, `MAIL_MAILER=smtp` and `MAIL_HOST=mailpit`.
2. Install `vendor/` through the Sail Composer container, not host PHP.
3. Run `vendor/bin/sail up -d`, `vendor/bin/sail npm ci` and `vendor/bin/sail artisan migrate`.

Run the visual gate with `VISUAL_DOCKER_NETWORK=<worktree-dir>_sail VISUAL_BASE_URL=http://laravel.test npm run test:visual`.

## Critical Implementation Details

**Every recipe write goes through one transaction that maintains the link.** Lock the plan row (`lockForUpdate`, as redraw does at `MealPlanController.php:240`) and refuse with `status` → `NOT_DRAFT_MESSAGE` unless it is a draft. Then apply the cases in this order:
1. **Same recipe.** If the new `recipe_id` equals the current one, change nothing (S-17 no-op; keeps the link).
2. **Cooked meal.** If the meal feeds another, set that meal's `recipe_id` to NULL and clear `feeds_meal_id`.
3. **Carried-over meal.** If another meal feeds this one, clear that meal's `feeds_meal_id`.
4. Write the new `recipe_id`.

The single conditional update of today is not enough, because two rows change together.

**Re-check the pair inside the lock.** `from_meal_id` is validated in the Form Request, but another tab can empty day D or link day D+2 in between. Re-run `MealLinkRules::canFeed()` on the locked, refreshed rows, and answer `from_meal_id` → `LINK_NOT_ALLOWED_MESSAGE` when it fails.

**Double after the cap, also for as-written amounts, but keep the multiplier apart from the factor.** The cooked meal's quantity is `recipe quantity × (calculator->factor(...) ?? 1) × servingsFor(meal)`. `source`, `RefinementItem`s and `unscaledRecipes` keep keying on the single-meal factor being non-null, exactly as today:
- With a factor, the rows are `source = computed` and produce `RefinementItem`s with the doubled quantity.
- Without one (no family, or a recipe unscaled for a family), the rows are `source = as_written` with the doubled quantity and produce no `RefinementItem`. A family-less plan therefore still has no items, never calls the refiner, and keeps `STATUS_AS_WRITTEN`, so `ScalingNotices` still shows "Dodaj członków rodziny…" (`MealIngredientScaler.php:109-134`, `ScalingNotices.php:32-34`). An unscaled recipe is still added to `unscaledRecipes`.
- `as_written` rows of a cooked meal hold `quantity = recipe_quantity × 2`, so `as_written` means "not scaled to the family", not "equal to the recipe".

## Phase 1: Linked Meals in Data, Edits and Scaling (Backend)

### Overview

Add the link column and the pair rules. Link and unlink through the swap endpoint, expose the link in the resource, double the cooked meal in the scaler, and keep the carried-over meal out of the shopping list.

### Changes Required:

#### 1. Link migration

**File**: `database/migrations/2026_11_16_xxxxxx_add_feeds_meal_id_to_meals_table.php` (via `vendor/bin/sail artisan make:migration`)

**Intent**: A meal can point to the one meal that eats it.

**Contract**:
- `up()`: add a nullable `feeds_meal_id` `foreignId` constrained to `meals.id` with `nullOnDelete()`, plus a unique index on it.
- `down()`: drop the FK, the index and the column.

#### 2. Meal model

**File**: `app/Models/Meal.php`

**Intent**: Name the link in both directions.

**Contract**:
- `feeds_meal_id` becomes fillable, with `@property int|null $feeds_meal_id`.
- `feedsMeal(): BelongsTo` points to the meal it feeds (`feeds_meal_id`).
- `fedBy(): HasOne` points to the meal whose `feeds_meal_id` is this id.

#### 3. Pair rules

**File**: `app/Services/MealLinks/MealLinkRules.php` (new, `declare(strict_types=1);`)

**Intent**: One place that decides which meal may feed which, so S-20 adds a pair instead of a branch.

**Contract**:
- `public const PAIRS = [['from' => 'supper', 'to' => 'supper', 'days' => 1]]`.
- `canFeed(Meal $from, Meal $to): bool` is true only when all of these hold:
  - both meals belong to the same plan;
  - the categories and the day distance match a pair;
  - `$from` has a recipe (a soft-deleted one is allowed: it is the same pot, and `Meal::recipe()` is `withTrashed()`, `Meal.php:36-39`; the `whereNull('deleted_at')` rule stays on the `recipe_id` path only);
  - `$from` is not fed by another meal;
  - `$to` does not feed another meal;
  - `$from` feeds nothing, or already feeds `$to`.
- `fedMealIds(iterable<Meal> $meals): array<int, true>` returns the ids of carried-over meals among already-loaded meals. The scaler, the shopping list, the resource and the PDF use it.
- `servingsFor(Meal $meal): int` returns 2 when the meal feeds another, otherwise 1.

#### 4. Link and unlink through the swap endpoint

**Files**: `app/Http/Requests/UpdateMealRequest.php`, `app/Http/Controllers/MealPlanMealController.php`

**Intent**: The S-17 endpoint also links a meal to the previous day's meal, and every recipe change keeps the link consistent.

**Contract**:
- **Request body.** Exactly one of two keys: `recipe_id` (int|null, today's rules) or `from_meal_id` (int). Both or neither is refused with 422. The existing rule chain stays on `recipe_id` and applies only when `from_meal_id` is absent:
  - `recipe_id`: `Rule::when(! $this->has('from_meal_id'), ['present'])` (refuses neither), then `nullable`, `integer` and today's scoped `exists`;
  - `from_meal_id`: `missing_with:recipe_id` (refuses both, by key presence), `integer`, and `Rule::exists('meals', 'id')->where('meal_plan_id', $meal->meal_plan_id)`;
  - a null `recipe_id` sent together with `from_meal_id` is still "both" and is refused. Do not rely on `prohibits` here: it does not fire for an empty (null) field.
- **`from_meal_id`.** It must be an existing meal of the same plan. In `after()` it must pass `MealLinkRules::canFeed($from, $meal)`, else `from_meal_id` → `LINK_NOT_ALLOWED_MESSAGE = 'Tego obiadu nie można połączyć z poprzednim dniem.'`. The draft check in `after()` is unchanged.
- **Controller.** One `DB::transaction` with the plan row locked, as described in Critical Implementation Details.
  - **Read everything fresh inside the lock.** The status comes from `MealPlan::query()->whereKey(...)->lockForUpdate()->first()`, and the meal, the source meal and their links come from fresh queries after the lock. Never use the route-bound `$mealPlan`, `$meal->mealPlan` or relations cached by the Form Request: they were loaded as `draft` before the approval-race test (`MealPlanMealControllerTest.php:165-178`) flips the status through `afterResolving`.
  - **Linking.** Set the meal's `recipe_id` to the cooked meal's recipe and the cooked meal's `feeds_meal_id` to this meal. If they are already linked, change nothing.
- **Responses.** Unchanged: `back()` for Inertia, and the JSON `MealPlanResource` (now with the link fields).

#### 5. Resource fields

**File**: `app/Http/Resources/MealPlanResource.php`

**Intent**: The UI can render both markers and the dialog item.

**Contract**:
- Every meal gets `feeds_meal_id: int|null` and `from_meal_id: int|null`.
- `from_meal_id` is derived by inverting `feeds_meal_id` over the loaded meals, with no extra query.

#### 6. Scaling the cooked meal for two

**File**: `app/Services/IngredientScaling/MealIngredientScaler.php`

**Intent**: The cooked meal gets the amounts for both meals; the carried-over meal gets none.

**Contract**:
- Skip meals in `MealLinkRules::fedMealIds()` together with null recipes.
- For a meal with `servingsFor() = 2`, the quantity is `recipe quantity × (calculator->factor(...) ?? 1) × 2` (see Critical Implementation Details).
- `source`, the `RefinementItem`s and the `unscaledRecipes` logic keep reading the single-meal factor, so a family-less plan never calls the refiner and keeps `STATUS_AS_WRITTEN`.
- `ScalingCalculator` is unchanged.

#### 7. Shopping list

**File**: `app/Services/ShoppingList/ShoppingListBuilder.php`

**Intent**: The carried-over meal is neither summed nor reported as a recipe without ingredients.

**Contract**: In `forMealPlan()`, skip meals in `fedMealIds()` before the `withoutIngredients` check. `build()` is unchanged.

#### 8. Tests

**Files**:
- `tests/Unit/Services/MealLinkRulesTest.php` (new)
- `tests/Feature/MealPlanMealControllerTest.php`
- `tests/Unit/MealRequestValidationTest.php`
- `tests/Feature/MealPlanControllerTest.php`
- `tests/Unit/Services/MealIngredientScalerTest.php`
- `tests/Unit/Services/ShoppingListBuilderTest.php`
- `tests/Unit/Jobs/GenerateMealPlanJobTest.php`

**Intent**: Pin the pair rules, every link and unlink path, the ×2 scaling and the shopping-list skip.

**Contract**:

*Rules:*
- `supper on day D can feed supper on day D+1`.
- `canFeed refuses another category, a non-consecutive day, an empty source, another plan`.
- `canFeed refuses chains in both directions`.
- `canFeed allows a source whose recipe is soft-deleted`.

*Endpoint:*
- `owner links Tuesday's Obiad to Monday's`: same `recipe_id`; Monday's `feeds_meal_id` is Tuesday's id; the JSON has `feeds_meal_id` / `from_meal_id`.
- `linking replaces the carried-over meal's own recipe`.
- `swapping the cooked meal empties the carried-over meal and drops the link`.
- `emptying the cooked meal empties the carried-over meal`.
- `picking the cooked meal's current recipe keeps the link`.
- `swapping or emptying the carried-over meal drops the link and keeps the cooked meal's recipe`.
- `linking is refused for a breakfast, a non-consecutive day, an empty previous day, and a chain`.
- `linking a meal of another plan is refused`.
- `linking is refused when the plan is not a draft`.
- The existing approval-race test keeps passing.

*Request:* `a body with both recipe_id and from_meal_id (also a null recipe_id), or neither, is refused`.

*Controller:*
- `back to draft keeps the link`.
- `redraw drops every link`.
- `meals_count counts the carried-over meal`.
- `show exposes feeds_meal_id and from_meal_id`.

*Scaler:*
- `a cooked meal gets twice the single-meal amounts and the carried-over meal gets no rows` (two-adult family: Mąka 100 g → 400 g).
- `without a family a cooked meal gets twice the written amounts as written, the refiner is not called and the status stays as_written`.
- `an unscaled recipe cooked for two days is doubled as written, gets no refinement item and is still reported as unscaled`.
- `refinement items carry the doubled quantity`.

*Shopping list:* `a linked pair adds the dish once in double quantity and lists no recipe without ingredients`.

*Job:* `a plan with a linked pair ends done with rows only on the cooked meal`.

### Success Criteria:

#### Automated Verification:

- Migration applies and rolls back cleanly: `vendor/bin/sail artisan migrate:fresh` then `vendor/bin/sail artisan migrate:rollback --step=1` then `vendor/bin/sail artisan migrate`
- Pest suite passes: `vendor/bin/sail composer test`
- Larastan passes with no new baseline entries: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human before proceeding to the next phase.

---

## Phase 2: Linked Meals in the UI and PDF

### Overview

The grid shows "Na 2 dni" and "Z wczoraj". The dialog of day D+1 offers "Jak wczoraj" and the dialog of a cooked meal warns before unlinking. The kitchen sink demos both states, and the PDF prints the markers with one recipe-page use per pair.

### Changes Required:

#### 1. Pair list on the frontend

**File**: `resources/js/lib/mealLinks.js` (new)

**Intent**: The dialog decides when to offer "Jak wczoraj" by the same pairs as the server.

**Contract**:
- Exports `MEAL_LINK_PAIRS` (a mirror of `MealLinkRules::PAIRS`, with a "keep in sync" comment like `mealCategories.js`).
- Exports `linkSourceFor(meal, weekMeals)`. It returns the meal that may feed `meal` under the same conditions as `canFeed()`, or null.

#### 2. Grid cell markers

**File**: `resources/js/Components/MealPlans/MealPlanGrid.vue`

**Intent**: Both linked cells say what happens, in text.

**Contract**:
- `GridMeal` gains `feeds_meal_id?: number | null` and `from_meal_id?: number | null`.
- **Cooked meal.** A `Badge variant="muted"` "Na 2 dni" sits next to the recipe name. Everything else is unchanged; its "Składniki" show the doubled amounts after approval.
- **Carried-over meal.** The recipe link, plus a `Badge variant="muted"` "Z wczoraj". There is no kcal line and no "Składniki" collapsible.
- The single "Zmień" button stays on both. Token utilities only, and no new Badge variant.

#### 3. "Jak wczoraj" in the swap dialog

**File**: `resources/js/Components/MealPlans/RecipeSwapDialog.vue`

**Intent**: Day D+1's dialog links it to day D, and a cooked meal's dialog warns that a change empties the next day.

**Contract**:
- `SwapMeal` gains the two link fields.
- **The item.** A second fixed `ComboboxItem` sits below "Nie robimy". It is rendered only when `linkSourceFor(meal, weekMeals)` returns a meal and the search box is empty (same hiding rule as "Nie robimy").
  - Label: "Jak wczoraj: <source recipe name>". Secondary line: "Gotujemy raz na 2 dni".
  - It carries "obecny" when `meal.from_meal_id` is the source's id. In that case "obecny" is not shown on the recipe list item.
  - Its value is a non-null marker object holding the source meal. `handleSelect` maps it to `{ from_meal_id: source.id }` through the existing `router.patch`.
  - The `onError` toast also reads `errors.from_meal_id`.
- **The warning.** When `meal.feeds_meal_id` is set, a muted line under the description reads "Zmiana opróżni obiad z <formatWeekdayShort + formatDayMonth of the fed meal>, który jest „z wczoraj”."
- `demo` mode still only closes.

#### 4. Show page types and redraw copy

**File**: `resources/js/Pages/MealPlans/Show.vue`

**Intent**: The link fields reach the grid and the dialog, and the redraw confirm names what is lost.

**Contract**:
- The local `Meal` interface gains the two link fields.
- The redraw confirm description becomes "Twoje zmiany przepisów i powtórki obiadów przepadną."

#### 5. Kitchen sink

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: The new cell and dialog states have demos (CLAUDE.md verification rule).

**Contract**:
- In the `planMeals` fixture, link Thursday's Obiad to Friday's Obiad. Friday gets the same recipe, no ingredients and `from_meal_id`. Pick meals that are neither the empty fixture meal nor the deleted-recipe one.
- The editable grid's `@swap` opens the dialog for the clicked meal, so Friday's Obiad shows "Jak wczoraj … obecny" and Thursday's shows the warning.
- The section caption names the linked pair.

#### 6. PDF markers and recipe pages

**Files**:
- `app/Services/MealPlanPdf/PdfMeal.php`
- `app/Services/MealPlanPdf/MealPlanPdfExporter.php`
- `resources/views/pdf/meal-plan.blade.php`
- `tests/Unit/Services/MealPlanPdfExporterTest.php`

**Intent**: The printed plan says which Obiad is cooked for two days, and the recipe page counts the pair once.

**Contract**:
- `PdfMeal` gains `public ?string $marker = null`: 'Na 2 dni' for a cooked meal, 'Z wczoraj' for a carried-over meal.
- `pdfMeal()` gives a carried-over meal its recipe name, its marker and no ingredients.
- The template prints the marker after the name in the existing muted `meta` style. A carried-over meal's ingredients cell stays empty.
- Recipe pages skip carried-over meals. The cooked meal's use label ends with " (na 2 dni)".
- **Tests:**
  - `a carried-over meal prints Z wczoraj with no ingredients`;
  - `a cooked meal prints Na 2 dni with its doubled lines`;
  - `a linked pair is one use on the recipe page labelled na 2 dni`.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token lint passes: `vendor/bin/sail npm run lint:tokens`
- Pest suite passes (PDF exporter): `vendor/bin/sail composer test`
- Larastan passes with no new baseline entries: `vendor/bin/sail composer phpstan`

#### Manual Verification:

- In a draft, Tuesday's Obiad "Zmień" → "Jak wczoraj: <Monday's dish>" shows "Na 2 dni" on Monday and "Z wczoraj" on Tuesday, and it persists after reload
- Monday's Obiad dialog shows the warning, and swapping Monday's Obiad turns Tuesday's into "Bez posiłku"
- Swapping Tuesday's Obiad keeps Monday's dish and removes both badges
- "Jak wczoraj" is absent on Monday's Obiad, on non-Obiad cells, when the previous Obiad is empty, and in a chain (Wednesday after a linked Tuesday)
- After approval, Monday's Obiad shows doubled "Składniki", Tuesday's has none, and the shopping list and the PDF (with and without recipes) show the markers, with one recipe-page use for the pair
- The badges and the dialog item work at phone width and with the keyboard

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: E2E and Visual Gate

### Overview

The browser test drives link, approve and unlink. The visual gate covers both badges and the dialog item through a pinned link in the seeded draft. Baselines are updated deliberately.

### Changes Required:

#### 1. E2E flow

**File**: `tests/e2e/meal-plan.spec.ts`

**Intent**: Prove linking, persistence, ×2 scaling with unchanged totals, and unlinking in a real browser against the queue worker.

**Contract**: After the last `totalsFull` check:
1. **Back to draft and link.** Return to the draft. Open "Zmień" on Tuesday's Obiad (`tbody tr` nth(1), `td` nth(1)) and pick "Jak wczoraj". Expect Tuesday to show Monday's dish name with "Z wczoraj", Monday to show "Na 2 dni", and `tbody td a` to stay at 21. The badges are the proof of the link: `swappedName` may already have been Tuesday's recipe, so the name alone proves nothing.
2. **Reload.** Expect the link to persist.
3. **Approve.**
   - Expect done with `totalsFull` unchanged (see Key Discoveries).
   - Monday's Obiad "Składniki" contain "Mąka — 400 g".
   - Tuesday's Obiad has no "Składniki" button.
4. **Back to draft and unlink.** Swap Monday's Obiad to another recipe, picked with `filter({ hasText: 'jest już', hasNotText: 'obecny' })`. The current recipe is also "jest już: wt." now, and picking it is the no-op that keeps the link. Expect Tuesday's Obiad to read "Bez posiłku" with "Wybierz", both badges to be gone, and `tbody td a` to be 20.

#### 2. Seeder: one pinned link in the visual draft

**Files**: `database/seeders/E2eMealPlanSeeder.php`, `tests/Unit/Seeders/E2eMealPlanSeederTest.php`

**Intent**: The visual draft shows both badges deterministically.

**Contract**:
- `attachPinnedMeals` gets an optional list of links, as `[category, from offset]`. The meal at offset + 1 gets the source's recipe, and the source's `feeds_meal_id` points to it.
- The draft starting 2026-06-29 links Monday's Obiad (offset 0) to Tuesday's (offset 1). It keeps its empty Wednesday breakfast.
- The done plan of 2026-06-15 and every other plan are unchanged.
- The seeder test asserts the link and the shared `recipe_id`, and still asserts 21 meals with 20 carrying a recipe.

#### 3. Visual specs

**File**: `tests/e2e/visual/meal-plan-show.spec.ts`

**Intent**: The specs wait for the new states and capture the dialog item.

**Contract**:
- `openSeededDraft` keeps 20 `tbody td a` and also waits for "Z wczoraj" and "Na 2 dni".
- The comment above `openSeededDraft` (`:58`, "uses every recipe … each non-current one is 'jest już'") is updated: the link turns Tuesday's "Kotlety mielone z ziemniakami" into Monday's "Rosół z makaronem", so Kotlety is no longer "jest już" in the Obiad dialog.
- The swap-dialog shot opens Tuesday's Obiad (`Zmień przepis: .* – Obiad`, second match) and expects "Nie robimy (pusty posiłek)", "Jak wczoraj", "obecny" and "jest już".

#### 4. Baseline updates

**Intent**: Regenerate only the baselines this change is expected to alter, and review every changed PNG.

**Contract**:
- Run `npm run test:visual -- --update-snapshots`, then review each PNG in the diff.
- Expected to change:
  - `meal-plan-draft-desktop` and `meal-plan-draft-mobile`: the two badges, and Tuesday's Obiad reading "Rosół z makaronem" instead of "Kotlety mielone z ziemniakami" (mobile only where they are in frame);
  - `meal-plan-draft-swap-dialog`: Tuesday's dialog with "Jak wczoraj", and "Kotlety mielone z ziemniakami" without "jest już";
  - `section-jadlospis`: the linked pair in both kitchen-sink grids.
- Every other baseline must stay unchanged, including `meal-plans-index-*` (the draft still counts 20 meals) and `meal-plan-show-*`. Record any other difference in the review instead of accepting it silently.

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Seeder runs and its test passes: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder` and `vendor/bin/sail pest --filter=E2eMealPlanSeeder`
- E2E passes: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- Visual gate passes after the deliberate update: `npm run test:visual`
- Full Pest suite passes: `vendor/bin/sail composer test`

#### Manual Verification:

- Every changed PNG in the diff was reviewed, and only the baselines listed in §4 changed

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful.

---

## Testing Strategy

### Unit Tests:

- **Pair rules:** the allowed pair, wrong category, wrong distance, empty source, another plan, chains both ways.
- **Scaler:** ×2 with a family, ×2 as written without one (no refiner call, status `as_written`), ×2 as written for an unscaled recipe (still reported, no refinement item), no rows for the carried-over meal, doubled refinement items.
- **Shopping list:** the pair counted once in double quantity; no "without ingredients" entry.
- **Swap request:** exactly one of `recipe_id` / `from_meal_id`.
- **PDF exporter:** both markers, an empty ingredients cell on the carried-over row, one recipe-page use labelled "(na 2 dni)".
- **Job:** rows only on the cooked meal.
- **Seeder:** the pinned link in the visual draft.

### Integration Tests:

- Feature tests cover:
  - linking, re-linking (no-op), replacing the carried-over meal's recipe;
  - unlinking from either side, including emptying;
  - every refusal (category, distance, empty source, chain, foreign plan, not a draft, malformed body);
  - back to draft keeping the link, and redraw dropping it;
  - `meals_count` and the resource fields.
- E2E: … → approve (21 meals) → back to draft → link Tuesday's Obiad → reload → approve (Mąka 400 g on Monday's Obiad, no Składniki on Tuesday's, totals unchanged) → back to draft → swap Monday's Obiad → Tuesday "Bez posiłku".

### Manual Testing Steps:

1. Generate a draft. On Tuesday's Obiad pick "Jak wczoraj". Both badges appear.
2. Try Wednesday's Obiad. No "Jak wczoraj" (chain). Try Monday's Obiad, a breakfast and a kolacja. No "Jak wczoraj".
3. Open Monday's Obiad. The warning names Tuesday. Swap it. Tuesday becomes "Bez posiłku".
4. Link again and approve. Monday's Składniki are doubled, Tuesday has none, the shopping list is unchanged in total, and the PDF shows both markers and one recipe-page use.
5. "Wróć do szkicu" keeps the link. "Losuj ponownie" removes it, as its confirm says.

## Performance Considerations

- A swap now runs a short transaction with a plan-row lock and at most three row updates. Drafts are edited one click at a time.
- `fedMealIds()` and the resource's `from_meal_id` work on the already-loaded meals, with no extra queries.

## Migration Notes

- `up()` adds a nullable column, so existing meals are unaffected.
- `down()` drops the column. Carried-over meals then become ordinary meals with the same recipe, scaled on their own at the next approval.
- No queue concern: the job and its payload are unchanged, and a plan queued before the migration has no links.

## References

- Roadmap item: `context/foundation/roadmap.md` (S-19, MS-04; Unknowns settled 2026-11-16)
- Prior change: `context/archive/2026-11-16-meal-plan-empty-slot/plan.md` (empty meals, swap endpoint with null, E2E totals)
- Prior change: `context/archive/2026-11-16-meal-plan-draft-with-swap/plan.md` (draft lifecycle, conditional updates)
- Swap endpoint: `app/Http/Controllers/MealPlanMealController.php:25-53`, `app/Http/Requests/UpdateMealRequest.php:37-78`
- Scaler loop: `app/Services/IngredientScaling/MealIngredientScaler.php:62-94`; cap: `ScalingCalculator.php:24,31-41`
- Shopping-list trap: `app/Services/ShoppingList/ShoppingListBuilder.php:84-87`
- PDF recipe-page grouping: `app/Services/MealPlanPdf/MealPlanPdfExporter.php:50-60`
- Grid cell: `resources/js/Components/MealPlans/MealPlanGrid.vue:28-88`; dialog marker: `RecipeSwapDialog.vue:15-26,90,132-169`
- Category naming: `app/Enums/MealCategory.php:10-29`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Linked Meals in Data, Edits and Scaling (Backend)

#### Automated

- [ ] 1.1 Migration applies and rolls back cleanly
- [ ] 1.2 Pest suite passes
- [ ] 1.3 Larastan passes with no new baseline entries
- [ ] 1.4 Pint passes

### Phase 2: Linked Meals in the UI and PDF

#### Automated

- [ ] 2.1 Client and SSR bundles build
- [ ] 2.2 Token lint passes
- [ ] 2.3 Pest suite passes
- [ ] 2.4 Larastan passes with no new baseline entries

#### Manual

- [ ] 2.5 Jak wczoraj links Tuesday's Obiad to Monday's with both badges, persisting after reload
- [ ] 2.6 Monday's dialog warns and swapping Monday's Obiad empties Tuesday's
- [ ] 2.7 Swapping Tuesday's Obiad keeps Monday's dish and removes both badges
- [ ] 2.8 Jak wczoraj is absent on Monday, non-Obiad cells, after an empty Obiad and in a chain
- [ ] 2.9 After approval the doubled amounts, shopping list and PDF markers are correct
- [ ] 2.10 The badges and dialog item work at phone width and with the keyboard

### Phase 3: E2E and Visual Gate

#### Automated

- [ ] 3.1 Build passes
- [ ] 3.2 Seeder runs and its test passes
- [ ] 3.3 E2E passes
- [ ] 3.4 Visual gate passes after the deliberate update
- [ ] 3.5 Full Pest suite passes

#### Manual

- [ ] 3.6 Every changed PNG was reviewed and only the listed baselines changed
