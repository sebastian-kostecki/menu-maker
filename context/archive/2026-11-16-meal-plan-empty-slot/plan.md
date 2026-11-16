# Empty Meal Slot Implementation Plan

## Overview

Let a meal in the plan be empty (roadmap S-18, MS-03):
- **In the draft.** The user can mark any meal "Nie robimy" and fill an empty meal back with a recipe.
- **After approval.** An empty meal reads "Bez posiłku" on screen and in the PDF. It has no ingredients and adds nothing to the shopping list.

The 7-recipes-per-category requirement goes away:
- A category with fewer recipes leaves the remaining meals empty, on random days, and the draft names the short categories above the grid.
- Generation refuses only a user who has no recipe at all.

## Current State Analysis

S-17 is done. Generation creates a `draft` with 21 drawn meals. The user swaps recipes per meal (`PATCH meal-plans/{plan}/meals/{meal}`) and approves (`POST meal-plans/{plan}/approve`). Approval queues the scaling-only `GenerateMealPlanJob`.

Constraints found:
- **Schema.** `meals.recipe_id` is NOT NULL with `onDelete('cascade')` (`database/migrations/2025_07_31_145701_create_meals_table.php:17`). The only index is a plain one on `(meal_plan_id, meal_date, meal_category)` (`:23`). Recipes are soft-deleted in the app (`RecipeController.php:146-148`). Only the seeder hard-deletes them (`E2eMealPlanSeeder.php:117,143,264-265`), and a hard delete removes the meals through the cascade.
- **The 7-per-category rule.** It is enforced in `MealPlanGenerator` (`app/Services/MealPlanGenerator.php:15,22-58,65-106`). The helpers are reused by:
  - `GenerateMealPlanRequest.php:59-64` and `RegenerateMealPlanRequest.php:59-64`, both on the `recipes` error key;
  - `MealPlanController.php:181-185,217-221`, which converts the run-time exception;
  - `DashboardController.php:43-45` (the `recipeShortfall` prop → `Dashboard.vue:45-56`, warning "Nie można jeszcze wygenerować tygodnia");
  - the seeder (`E2eMealPlanSeeder.php:196,241`).
- **The draw assigns days in order.** `generate()` gives the n-th drawn recipe day offset n (`MealPlanGenerator.php:96-102`). With fewer than 7 recipes, the gaps would always fall on the last days unless the day offsets are shuffled too.
- **Readers that already skip a meal without a recipe:**
  - `MealIngredientScaler.php:63-68`;
  - `ShoppingListBuilder.php:80-82`;
  - `MealPlanResource.php:55` omits the `recipe` key;
  - `RecipeSwapDialog.vue:81,94`;
  - the TS types (`recipe?`).
- **The AI drivers never see meals.** They only receive `list<RefinementItem>` (`IngredientRefiner.php:18`).
- **Kcal shares are per meal.** Each meal's factor is the family kcal × the category share ÷ the recipe kcal (`ScalingCalculator.php:18,31-41`). An empty meal just drops its share, and nothing is redistributed.
- **Readers that drop or block an empty meal:**
  - **PDF.** `MealPlanPdfExporter.php:31-34` filters out meals without a recipe before grouping by day, so a day whose meals are all empty disappears. `pdfMeal()` (`:120-132`) dereferences `$recipe`. Recipe pages are keyed by `recipe_id` (`:56-61`).
  - **Grid.** It renders a cell only `v-if="day.meals[category]?.recipe"` (`MealPlanGrid.vue:28`), and the "Zmień" button sits inside that block (`:45-55`). An empty cell shows `—` (`:75`) and cannot be filled.
  - **Swap endpoint.** It requires `recipe_id` (`UpdateMealRequest.php:38-46`) and writes `$request->integer('recipe_id')` (`MealPlanMealController.php:29-35`).
  - **Approve.** It refuses only a draft with no meal rows (`ApproveMealPlanRequest.php:61-63`).
  - **List.** The meal count counts rows: `withCount(['meals','logs'])` (`MealPlanController.php:49`) → `MealPlanRow.vue:23`.

## Desired End State

- **Draw.**
  - Every draft has 21 meal rows: 7 days × 3 categories.
  - Each category draws up to 7 distinct recipes onto random days. The remaining days of that category get a meal with `recipe_id = NULL`.
  - A category with no recipes gets 7 empty meals.
  - Creating or redrawing a draft is refused only when the user has no non-deleted recipe. The refusal is one `recipes` error, "Nie masz jeszcze żadnego przepisu. Dodaj przepis, żeby wygenerować jadłospis.", shown in the generate dialog with the existing "Dodaj przepis" link.
- **Draft UI.**
  - A filled cell keeps "Zmień".
  - An empty cell shows muted "Bez posiłku", keeps its category stripe, and has a "Wybierz" button.
  - Both open the swap dialog. Its first item, "Nie robimy (pusty posiłek)", empties the meal and is marked "obecny" on an empty meal. Picking a recipe fills it.
  - Each pick is saved immediately, as in S-17.
- **Short-category notice.** When a draft has an empty meal in a category where the user has fewer than 7 recipes, an info notice above the grid names those categories with "<label> (<count> z 7)" and links "Dodaj przepis".
- **Approve.** "Zatwierdź" is refused when no meal has a recipe ("Szkic nie ma żadnego posiłku z przepisem. Wybierz przepis albo wylosuj ponownie.").
- **Done view, list and PDF.**
  - An empty meal shows "Bez posiłku" with no ingredients and contributes nothing to the shopping list or to the recipe pages.
  - Every day stays in the PDF table.
  - The plans list counts only meals with a recipe.
- **Dashboard.** It no longer shows the shortfall warning or receives `recipeShortfall`. The first-run copy no longer says "Gdy będzie ich dość".
- **Verification.** Pest, Larastan, Pint, the build, `lint:tokens`, E2E and the visual gate are green. The E2E flow empties a meal, approves it (shopping list for 20 meals), goes back to the draft, fills the meal back and approves again (21 meals).

### Key Discoveries:

- **Scaling and the shopping list need no change.**
  - `MealIngredientScaler` already skips null recipes (`MealIngredientScaler.php:63-68`).
  - `ShoppingListBuilder` does too (`:80-82`), so an empty meal yields no `meal_ingredients` rows.
  - `ScalingNotices` and `recipes_without_ingredients` key on recipes, so empty meals never appear there.
- `MealPlanResource` already leaves out `recipe` for a null recipe (`MealPlanResource.php:55`). The meal JSON needs no new field: "empty" means `recipe` is absent.
  - **Addendum (impl review):** it did not. `$this->when(...)` still emitted an empty array, which is truthy in JS, so the resource now spreads the `recipe` key only for a non-null recipe (`MealPlanResource.php`). Pinned by `assertJsonMissingPath('data.meals.0.recipe')`.
- `recipeOptions` already returns every category key, even when its list is empty (`MealPlanController.php:126-148`). The swap dialog for a category with zero recipes therefore lists only "Nie robimy", plus "Brak przepisów".
- **The reka-ui 2.11 Combobox filters all items or none.** An item cannot opt out of the search filter (`node_modules/reka-ui/dist/Combobox/ComboboxItem.js:42-46`). With `ignore-filter`, `ComboboxEmpty` renders only when there are no items at all (`ComboboxEmpty.js:21`), so it would never show next to an always-present "Nie robimy". `handleSelect` also returns early on a falsy value (`RecipeSwapDialog.vue:102`). The dialog therefore filters its recipe list itself (Phase 2 §2).
- The kitchen-sink grid fixture already drops one slot (meal id 8, `DesignSystem.vue:203-248`) to demo the `—` state. It becomes a meal without a recipe.
- `tests/Unit/Services/MealPlanPdfExporterTest.php:108-123` pins today's filtering of null recipes and must be rewritten, not deleted.
- The E2E seeder pins identical ingredients (100 g Mąka, 50 ml Mleko, 1 Jajka) and a ×2 scale on every recipe (`E2eMealPlanSeeder.php:325-386`). One empty meal therefore changes the totals from 42 szt. / 4,2 kg / 2,1 l to 40 szt. / 4 kg / 2 l. `QuantityFormatter` uses `MIN_FRACTION_DIGITS` 0 (`app/Services/ShoppingList/QuantityFormatter.php:18`), so whole numbers render without a fraction: "40 szt.", "4 kg", "2 l".

## What We're NOT Doing

- Distinguishing a user-emptied meal from a shortfall gap. Both are `recipe_id = NULL` and read "Bez posiłku".
- Letting the draw repeat recipes to fill gaps. The draw stays without repeats (roadmap "Parked"). The user may fill a gap by hand with a recipe already in the week (S-17 behaviour).
- A dashboard hint about short categories, or any shortfall message before generating other than the zero-recipe refusal.
- A separate "Opróżnij" button in the cell. Emptying exists only as the dialog item.
- Emptying or filling meals outside the draft.
- Redistributing an empty meal's kcal share to other meals, or changing the scaler, the refiner drivers or `ShoppingListBuilder`.
- Linked "cooked once, eaten twice" meals (S-19/S-20).
- Changing the generate dialog copy ("7 dni po 3 posiłki" stays true: the plan still has 21 meals).
- Refactoring `MealPlanFactory`'s stale `'total_recipes' => 21` meta.

## Implementation Approach

An empty meal is a regular `meals` row with `recipe_id = NULL`. Keeping the row means:
- every cell stays addressable by meal id, so emptying and filling reuse the S-17 endpoint with `recipe_id: null` or an id;
- S-19 can later link meals to meals.

The draw always writes 21 rows and shuffles day offsets per category, so the gaps land on random days. The zero-recipe check replaces the per-category shortfall everywhere it was used. The UI change is local to the grid cell, the swap dialog, one notice on Show, and the PDF row.

## Environment (prerequisite for every phase)

Every `sail`, E2E and visual command must run against this worktree's code. Sail serves the checkout it was started from, and `scripts/visual-test.sh` mounts the checkout it runs from. A fresh worktree has no `.env`, `vendor/` or `node_modules/`.

1. Copy `.env` from the main checkout.
   - Give it its own `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT` and `FORWARD_MAILPIT_DASHBOARD_PORT`, and set `MAILPIT_URL` to match.
   - Keep `AI_SCALING_DRIVER=fake`, `MAIL_MAILER=smtp` and `MAIL_HOST=mailpit`.
2. Install `vendor/` through the Sail Composer container, not host PHP.
3. Run `vendor/bin/sail up -d`, `vendor/bin/sail npm ci` and `vendor/bin/sail artisan migrate`.

Run the visual gate with `VISUAL_DOCKER_NETWORK=<worktree-dir>_sail VISUAL_BASE_URL=http://laravel.test npm run test:visual`.

## Critical Implementation Details

**Null must survive the swap endpoint.** `MealPlanMealController::update` writes `$request->integer('recipe_id')`, which turns `null` into `0` and would violate the FK. Write the validated value as is (`int|null`). Validate it with `present` + `nullable`, so an omitted key is still refused while an explicit `null` empties the meal.

**Shuffle the days, not only the recipes.** Today the n-th recipe from `inRandomOrder()` lands on day offset n. Shuffle the 7 day offsets per category, give the drawn recipes the first offsets, and give the rest `recipe_id = NULL`. Otherwise the gaps are always Saturday/Sunday.

**The run-time race still rolls back only at zero.** Recipes deleted between validation and the draw now just produce more gaps. `generate()` throws `InsufficientRecipesException` only when it draws no recipe at all, inside its transaction, so the existing meals of a redrawn draft survive.

## Phase 1: Empty Meals in Data and Draw (Backend)

### Overview

Make `recipe_id` nullable, draw with random-day gaps, replace the per-category shortfall with a zero-recipe refusal, let the swap endpoint empty a meal, widen the approve check, and give Show the short-category data.

### Changes Required:

#### 1. Nullable recipe migration

**File**: `database/migrations/2026_11_16_xxxxxx_make_meals_recipe_id_nullable.php` (via `vendor/bin/sail artisan make:migration`)

**Intent**: Allow a meal without a recipe. A hard-deleted recipe then leaves its meals empty instead of deleting them.

**Contract**:
- `up()`: drop the FK `meals_recipe_id_foreign`, make `recipe_id` nullable (`->change()`), and re-add the FK with `nullOnDelete()`.
- `down()`: delete meals whose `recipe_id` is NULL, then restore NOT NULL and `cascadeOnDelete()`.
- Verify the FK name with `\d meals` before writing it.

#### 2. Meal model

**File**: `app/Models/Meal.php`

**Intent**: The type documents the empty case.

**Contract**: The `@property` for `recipe_id` becomes `int|null`. `recipe()` is unchanged (`withTrashed()`, already `Recipe|null`). Add `declare(strict_types=1);` if it is missing.

#### 3. Draw with gaps

**Files**: `app/Services/MealPlanGenerator.php`, `app/Services/InsufficientRecipesException.php`

**Intent**: Every draft gets 21 meals. Each category fills as many random days as it has recipes, up to 7. Only a user without recipes is refused.

**Contract**:
- **Constants.** Keep `MEALS_PER_CATEGORY = 7` (the slots per category; the seeder uses it). Add `NO_RECIPES_MESSAGE = 'Nie masz jeszcze żadnego przepisu. Dodaj przepis, żeby wygenerować jadłospis.'`.
- **`hasAnyRecipe(int $userId): bool`.** Replaces `missingRecipesPerCategory()` and `shortfallMessages()`, both removed together with their callers.
- **`recipeCountsPerCategory(int $userId): array<string, int>`.** Non-deleted recipes per category, every category key present, for the Show notice (item 8).
- **`generate()`:**
  - throws `InsufficientRecipesException(NO_RECIPES_MESSAGE)` up front when `! hasAnyRecipe()`;
  - in its transaction it deletes the meals, then for each category draws up to 7 random recipes and shuffles the offsets 0–6;
  - drawn recipes take the first offsets, the rest become meals with `recipe_id = null`;
  - if the whole draw found no recipe (a race), it throws inside the transaction so the old meals are kept (see Critical Implementation Details).
- **`InsufficientRecipesException`** keeps the message. Drop the `$missing` payload if nothing reads it any more.

#### 4. Generate and redraw validation

**Files**: `app/Http/Requests/GenerateMealPlanRequest.php`, `app/Http/Requests/RegenerateMealPlanRequest.php`, `app/Http/Controllers/MealPlanController.php`

**Intent**: Both refuse only a user without recipes, with one message.

**Contract**:
- Each request's `after()` replaces its shortfall loop with one `recipes` error carrying `MealPlanGenerator::NO_RECIPES_MESSAGE` when `! hasAnyRecipe()`.
- The controller's exception-to-`recipes` conversion in `store` and `update` stays as is, with the new message.

#### 5. Approve needs a filled meal

**Files**: `app/Http/Requests/ApproveMealPlanRequest.php`, `app/Http/Controllers/MealPlanController.php` (`approve`)

**Intent**: Do not spend an approval or an AI call on a draft where every meal is empty.

**Contract**:
- The `meals` check becomes "no meal with a non-null `recipe_id`". This also covers no rows at all.
- `EMPTY_DRAFT_MESSAGE = 'Szkic nie ma żadnego posiłku z przepisem. Wybierz przepis albo wylosuj ponownie.'`.
- The rate-limit hit still happens first (S-17 F2).
- **The claim re-checks it.** `MealPlanController::approve`'s conditional update (`:241-248`) also gets `->whereHas('meals', fn ($query) => $query->whereNotNull('recipe_id'))`, so emptying the last meal from another tab between the validation and the claim cannot approve an all-empty draft. When the claim updates nothing, answer `meals` → `EMPTY_DRAFT_MESSAGE` if the plan is still a `draft`, otherwise `status` → `NOT_DRAFT_MESSAGE` as today.

#### 6. Empty or fill a meal

**Files**: `app/Http/Requests/UpdateMealRequest.php`, `app/Http/Controllers/MealPlanMealController.php`

**Intent**: The S-17 swap endpoint also empties a meal (`recipe_id: null`) and fills an empty one.

**Contract**:
- `recipe_id` rules: `present`, `nullable`, `integer`, plus the existing scoped `exists` (owner, the meal's category, `whereNull('deleted_at')`).
- The draft check in `after()` is unchanged.
- The controller's conditional update writes the validated `int|null` (see Critical Implementation Details).
- The responses are unchanged.

#### 7. List meal count

**File**: `app/Http/Controllers/MealPlanController.php` (`index`)

**Intent**: The list shows how many meals are actually planned.

**Contract**: `withCount(['meals' => fn ($query) => $query->whereNotNull('recipe_id'), 'logs'])`. `MealPlanCollection`'s `meals_count` is unchanged.

#### 8. Short-category data on Show

**File**: `app/Http/Controllers/MealPlanController.php` (`show`)

**Intent**: The draft notice is computed on the server, so Pest can pin it.

**Contract**:
- A new Inertia prop `shortCategories`: a closure returning `null` unless the status is `draft`.
- For a draft it returns `list<array{category: string, label: string, recipes: int}>`, in display order. It contains only the categories where `recipeCountsPerCategory()` < 7 **and** the plan has at least one meal of that category with `recipe_id` NULL.

#### 9. Dashboard

**File**: `app/Http/Controllers/DashboardController.php`

**Intent**: Generation is no longer blocked per category, so the dashboard stops saying so.

**Contract**: Remove the `recipeShortfall` prop, its docblock line and the `MealPlanGenerator` injection. The `stats` and `latestMealPlan` props are unchanged.

#### 10. Tests

**Files**:
- `tests/Unit/Services/MealPlanGeneratorTest.php`
- `tests/Feature/MealPlanControllerTest.php`
- `tests/Feature/MealPlanMealControllerTest.php`
- `tests/Feature/DashboardControllerTest.php`
- `tests/Unit/MealPlanRequestValidationTest.php`
- `tests/Unit/MealRequestValidationTest.php`
- `tests/Unit/Jobs/GenerateMealPlanJobTest.php`

**Intent**: Pin the new draw, the refusals and the empty-meal edits. Rewrite the tests that assumed 7 per category; do not just delete them.

**Contract**:

*Generator:*
- `a full week draws 7 distinct recipes per category` (kept).
- `a category with 5 recipes fills 5 distinct days and leaves 2 empty`: 21 rows, each date once per category.
- `a category without recipes gets 7 empty meals`.
- `generate refuses a user without recipes and keeps the old meals`.
- `recipes deleted after the check leave gaps, all deleted rolls back`.
- `recipe counts per category include every category`.
- Remove the shortfall-message tests (`:97-111`).

*Controller, store / redraw:*
- `store with 6 kolacje creates a draft with one empty kolacja`.
- `store refuses a user without recipes and creates no plan`. It replaces the "Dodaj jeszcze 3" test (`:533-548`), and the rate-limit-untouched test (`:550-558`) uses the zero-recipe case.
- `redraw refuses a user without recipes and keeps the meals`. It replaces `:240-252`.

*Controller, approve:*
- `approve is refused when every meal is empty`.
- `approve succeeds with one filled meal and the job scales only it`.

*Controller, index:* `meals_count counts only meals with a recipe`.

*Controller, show:*
- `a draft with a gap in a short category passes shortCategories`.
- `shortCategories omits a short category without empty meals`.
- `a done plan passes null shortCategories`.

*Swap:*
- `owner empties a draft meal with recipe_id null`.
- `owner fills an empty meal`.
- `a missing recipe_id key is refused`. It replaces "swap requires a recipe id" (`:109`).
- `emptying is refused when the plan is not a draft`.

*Dashboard:* drop the shortfall tests (`:101-134`) and assert that the `recipeShortfall` prop is gone.

*Requests:*
- The regenerate shortfall test (`:220-245`) becomes a single zero-recipe message.
- The approve empty-draft test (`:266`) gains an all-empty-meals case.
- The swap request accepts `null` and refuses a missing key.

*Job:* `a plan with an empty meal writes ingredients only for filled meals and ends done`.

### Success Criteria:

#### Automated Verification:

- Migration applies and rolls back cleanly: `vendor/bin/sail artisan migrate:fresh` then `vendor/bin/sail artisan migrate:rollback --step=1` then `vendor/bin/sail artisan migrate`
- Pest suite passes: `vendor/bin/sail composer test`
- Larastan passes with no new baseline entries: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`

#### Manual Verification:

- Against Sail, a user with 6 kolacje and 7 of each other category generates a draft with 21 meals, one of them a kolacja with `recipe_id` NULL, and a user with no recipes is refused with "Nie masz jeszcze żadnego przepisu…"

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Empty Meals in the UI and PDF

### Overview

Empty cells read "Bez posiłku" and can be filled in the draft. The swap dialog can empty a meal, the draft names short categories, the Dashboard drops the shortfall warning, and the PDF keeps empty meals as rows.

### Changes Required:

#### 1. Grid cell

**File**: `resources/js/Components/MealPlans/MealPlanGrid.vue`

**Intent**: An empty meal is a visible, deliberate state, and in the draft it can be filled.

**Contract**:
- A cell whose meal has no `recipe`, or which has no meal row (legacy), keeps the category stripe and shows muted "Bez posiłku" instead of `—`.
- When `editable` and a meal row exists, the empty cell shows a "Wybierz" `Button` with `aria-label` "Wybierz przepis: <day> – <category label>", emitting `swap(meal)`.
- Filled cells are unchanged ("Zmień", the link, kcal, ingredients).
- Each cell has exactly one action control. Token utilities only.

#### 2. Swap dialog

**File**: `resources/js/Components/MealPlans/RecipeSwapDialog.vue`

**Intent**: The same dialog empties a meal or fills it.

**Contract**:
- **Filter in the component** (see Key Discoveries, reka-ui):
  - set `ignore-filter` and `v-model:search-term` on the `Combobox`;
  - filter `items` in a computed property by recipe name, case-insensitive with `toLocaleLowerCase('pl')`;
  - replace `ComboboxEmpty` with a plain `<p v-if="!filteredItems.length">Brak przepisów</p>` (same classes), shown under the "Nie robimy" item.
- A first `ComboboxItem` "Nie robimy (pusty posiłek)" is rendered above the filtered recipes while the search box is empty, and hidden while the user types (impl review F1: reka-ui highlights the first item on every keystroke, so Enter after typing would otherwise empty the meal). It carries "obecny" when the meal has no recipe.
- Its `value` is a non-null marker object (for example `const EMPTY_OPTION = { empty: true }`), because `handleSelect` returns early on a falsy value. `handleSelect` maps the marker to `{ recipe_id: null }` and a recipe to `{ recipe_id: picked.id }`, both through the existing `router.patch`.
- For an empty meal, the dialog title reads "Wybierz przepis" instead of the swap title.
- The `demo` mode still only closes the dialog.

#### 3. Short-category notice on Show

**Files**: `resources/js/Pages/MealPlans/Show.vue`, `resources/js/types/meal-plan.ts`

**Intent**: Tell the user why some draft meals are empty and how to fill them.

**Contract**:
- A new prop `shortCategories: ShortCategory[] | null`, with `ShortCategory = { category: MealCategory, label: string, recipes: number }` added to `types/meal-plan.ts`.
- When the list is non-empty in a draft, show an info `Alert` (`data-testid="short-categories-notice"`) under the existing draft notice:
  - "Za mało przepisów, żeby wypełnić cały tydzień: <label> (<recipes> z 7), …"
  - "Puste posiłki możesz uzupełnić przepisem, który już jest w tygodniu, albo dodać nowy przepis."
  - a `Link` "Dodaj przepis" to `recipes.create`.
- The approve error toast already reads `errors.meals`, so it shows the new message unchanged.

#### 4. Dashboard

**File**: `resources/js/Pages/Dashboard.vue`

**Intent**: No warning that generation is impossible when it is not.

**Contract**:
- Remove the `recipeShortfall` prop, the warning `Alert` and the unused imports.
- The first-run `EmptyState` description becomes "Dodaj przepisy do śniadań, obiadów i kolacji, a potem wygeneruj z nich jadłospis na cały tydzień."

#### 5. PDF rows and recipe pages

**Files**:
- `app/Services/MealPlanPdf/MealPlanPdfExporter.php`
- `app/Services/MealPlanPdf/PdfMeal.php`
- `resources/views/pdf/meal-plan.blade.php`
- `tests/Unit/Services/MealPlanPdfExporterTest.php`

**Intent**: An empty meal prints as "Bez posiłku" in its slot. Every day stays, and empty meals add no recipe page.

**Contract**:
- The exporter no longer filters out meals without a recipe before grouping.
- `pdfMeal()` handles a null recipe: `recipeName` 'Bez posiłku', `recipeDeleted` false, no ingredients, and a new `PdfMeal::$empty` (bool) set to true.
- The recipe-page collection skips empty meals before touching `recipe_id`.
- The template renders an empty meal's name with the existing muted `meta` style and leaves its ingredients cell empty.
- The test at `:108-123` is rewritten: an empty meal yields an `empty` `PdfMeal`, its day is kept, and it adds no recipe page.
- Add a test: a day with three empty meals still appears.

#### 6. Kitchen sink

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: The new cell and dialog states have demos (CLAUDE.md verification rule).

**Contract**:
- The dropped slot in the `planMeals` fixture (meal id 8) becomes a meal without a recipe. The read-only grid then shows "Bez posiłku", the editable grid shows "Wybierz" on it, and the caption names the empty meal.
- The `RecipeSwapDialog` demo shows the "Nie robimy" item.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token lint passes: `vendor/bin/sail npm run lint:tokens`
- Pest suite passes (PDF exporter and Inertia props): `vendor/bin/sail composer test`
- Larastan passes with no new baseline entries: `vendor/bin/sail composer phpstan`

#### Manual Verification:

- In a draft, "Zmień" → "Nie robimy (pusty posiłek)" turns the cell into "Bez posiłku" with "Wybierz", it persists after reload, and "Wybierz" → a recipe fills it again
- A user with 6 kolacje sees the short-category notice "Kolacja (6 z 7)" with "Dodaj przepis" above the draft grid, and it disappears once the empty kolacja is filled by hand
- Emptying every meal and pressing "Zatwierdź" shows "Szkic nie ma żadnego posiłku z przepisem…"
- After approval, the empty meal reads "Bez posiłku" with no ingredients, the shopping list lacks its ingredients, and the PDF (with and without recipes) shows the row as "Bez posiłku" and no recipe page for it
- The Dashboard shows no shortfall warning, and the first-run copy reads as specified
- The empty cell and the dialog item work at phone width and with the keyboard

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: E2E and Visual Gate

### Overview

The browser test drives empty and refill. The visual gate covers the empty cell and the dialog item through a pinned empty meal in the seeded draft. Baselines are updated deliberately.

### Changes Required:

#### 1. E2E flow

**File**: `tests/e2e/meal-plan.spec.ts`

**Intent**: Prove emptying, persistence, the shopping list for an empty meal, and refilling in a real browser against the queue worker.

**Contract**:
1. **Empty a meal.** After the existing swap step, open "Zmień" on the first "Śniadanie" cell and pick "Nie robimy (pusty posiłek)". Expect "Bez posiłku", a "Wybierz" button and 20 `tbody td a`.
2. **Reload.** Expect the empty meal to persist.
3. **Approve.** Expect done with the shopping list for 20 meals: exactly "40 szt.", "4 kg" and "2 l" for Jajka, Mąka and Mleko (see Key Discoveries, `QuantityFormatter`). Expect "Bez posiłku" still in the grid. The PDF checks stay, with the recipe-page expectation adjusted if it counts pages.
4. **Back to draft.** Expect the empty meal kept. Then "Wybierz" on it, pick a recipe, and expect 21 `tbody td a`.
5. **Approve again.** Expect done with today's totals: 42 szt. / 4,2 kg / 2,1 l.

#### 2. Seeder: one pinned empty meal in the visual draft

**Files**: `database/seeders/E2eMealPlanSeeder.php`, `tests/Unit/Seeders/E2eMealPlanSeederTest.php`

**Intent**: The visual draft shows an empty cell deterministically.

**Contract**:
- `attachPinnedMeals` gets an optional list of empty slots. The draft starting 2026-06-29 leaves Wednesday "Śniadanie" (offset 2) with `recipe_id` NULL. Its 7 breakfasts still exist, so no short-category notice appears.
- The done plan of 2026-06-15 and every other plan are unchanged.
- The seeder test asserts that the draft has 21 meals, 20 with a recipe, and the empty one at that slot.

#### 3. Visual specs

**File**: `tests/e2e/visual/meal-plan-show.spec.ts`

**Intent**: The specs match the seeded draft and wait for the new states.

**Contract**:
- `openSeededDraft` expects 20 `tbody td a` instead of 21 and waits for "Bez posiłku".
- The swap-dialog shot also expects "Nie robimy (pusty posiłek)".
- `openSeededPlan` keeps 21.

#### 4. Baseline updates

**Intent**: Regenerate only the baselines this change is expected to alter, and review every changed PNG.

**Contract**:
- Run `npm run test:visual -- --update-snapshots`, then review each PNG in the diff.
- Expected to change:
  - `meal-plan-draft-desktop`, `meal-plan-draft-mobile`: the empty Wednesday breakfast;
  - `meal-plan-draft-swap-dialog`: the "Nie robimy" item;
  - `meal-plans-index-desktop`: the draft row count 21 → 20 (the count column is hidden on mobile);
  - `meal-plans-index-generate-dialog`: a full-page shot over the same list, so the draft row count changes behind the dialog (added by impl review);
  - `section-jadlospis`: "Bez posiłku", "Wybierz" and the dialog item;
  - `dashboard-empty-desktop`: the first-run copy.
- Every other baseline must stay unchanged. Record any other difference in the review instead of accepting it silently.

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

- **Generator:** random-day gaps, distinct recipes, an all-empty category, the zero-recipe refusal, the run-time race.
- **Form Requests:**
  - generate and redraw: the zero-recipe message;
  - approve: no filled meal;
  - swap: `null` accepted, a missing key refused.
- **PDF exporter:** an empty meal row, a fully empty day kept, no recipe page.
- **Job:** ingredients only for filled meals.
- **Seeder:** the pinned empty slot in the visual draft.

### Integration Tests:

- Feature tests cover:
  - store and redraw with a short category and with no recipes;
  - approve refused when every meal is empty;
  - empty and fill through the swap endpoint, including non-draft refusals;
  - `meals_count` and `shortCategories`;
  - the Dashboard without `recipeShortfall`.
- E2E: draft → swap → empty → reload → approve (20 meals) → back to draft → fill → approve (21 meals).

### Manual Testing Steps:

1. With 6 kolacje, generate. One kolacja on a random day reads "Bez posiłku", and the notice says "Kolacja (6 z 7)".
2. Redraw a few times. The empty kolacja moves between days.
3. Empty a breakfast with "Nie robimy", then reload. It stays empty. Fill it with "Wybierz".
4. Approve. The empty meals have no ingredients, the shopping list shrinks accordingly, and the PDF shows "Bez posiłku" rows and no recipe page for them.
5. Delete all recipes and try to generate. The refusal shows the "Dodaj przepis" link.

## Performance Considerations

- The draw still makes 3 recipe queries plus 21 inserts in one transaction.
- `shortCategories` adds one grouped count query plus one empty-meal check per draft view. A draft is not polled.

## Migration Notes

- `up()` only relaxes the column and changes the FK action. Existing meals keep their recipes.
- No queue concern: the job and its payload are unchanged.
- Rollback deletes empty meals before restoring NOT NULL. A draft or done plan then loses those slots; the grid and the PDF tolerate missing rows.

## References

- Roadmap item: `context/foundation/roadmap.md` (S-18, MS-03; Unknowns settled 2026-11-16)
- Prior change: `context/archive/2026-11-16-meal-plan-draft-with-swap/plan.md` (draft lifecycle, swap endpoint, conditional updates)
- Draw: `app/Services/MealPlanGenerator.php:65-106`
- Null-safe scaling: `app/Services/IngredientScaling/MealIngredientScaler.php:63-68`
- PDF filter: `app/Services/MealPlanPdf/MealPlanPdfExporter.php:31-34`
- Grid cell: `resources/js/Components/MealPlans/MealPlanGrid.vue:28,45-55,75`
- One-button-per-state rule: `context/archive/2026-11-15-meal-plan-show-ui/plan.md` (Critical Implementation Details)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Empty Meals in Data and Draw (Backend)

#### Automated

- [x] 1.1 Migration applies and rolls back cleanly — 48e0e58
- [x] 1.2 Pest suite passes — 48e0e58
- [x] 1.3 Larastan passes with no new baseline entries — 48e0e58
- [x] 1.4 Pint passes — 48e0e58

#### Manual

- [ ] 1.5 A short category yields a draft with empty meals and a user without recipes is refused

### Phase 2: Empty Meals in the UI and PDF

#### Automated

- [x] 2.1 Client and SSR bundles build — 65d6d18
- [x] 2.2 Token lint passes — 65d6d18
- [x] 2.3 Pest suite passes — 65d6d18
- [x] 2.4 Larastan passes with no new baseline entries — 65d6d18

#### Manual

- [ ] 2.5 Nie robimy empties a draft meal, it persists, and Wybierz fills it again
- [ ] 2.6 The short-category notice appears with Dodaj przepis and disappears once the gap is filled
- [ ] 2.7 Approving an all-empty draft is refused with the new message
- [ ] 2.8 After approval the empty meal reads Bez posiłku on screen, in the shopping list and in the PDF
- [ ] 2.9 The Dashboard has no shortfall warning and the new first-run copy
- [ ] 2.10 The empty cell and dialog item work at phone width and with the keyboard

### Phase 3: E2E and Visual Gate

#### Automated

- [x] 3.1 Build passes — 9fc8dfd
- [x] 3.2 Seeder runs and its test passes — 9fc8dfd
- [x] 3.3 E2E passes — 9fc8dfd
- [x] 3.4 Visual gate passes after the deliberate update — 9fc8dfd
- [x] 3.5 Full Pest suite passes — 9fc8dfd

#### Manual

- [ ] 3.6 Every changed PNG was reviewed and only the listed baselines changed
