# Shopping List (S-05) Implementation Plan

## Overview

Show a shopping list on the meal-plan view (`/meal-plans/{id}`), below the 7×3 grid. It sums the ingredients of the plan's meals, converts g→kg and ml→l above 1000, rounds to 2 decimal places and sorts alphabetically in Polish order (FR-6, US-011). The list is computed when the plan is read, through a service with a neutral input seam. Today that seam takes the recipe quantities; S-04 will feed it scaled quantities, and S-06 will reuse the same result for the PDF. This is roadmap S-05 `shopping-list`.

## Current State Analysis

- A plan stores no quantities. `meals` holds only `meal_plan_id`, `recipe_id`, `meal_date` and `meal_category` (`app/Models/Meal.php:14-19`), so before S-04 the list can only come from the `recipe_ingredients` of each meal's recipe, one whole recipe per meal.
- `Meal::recipe()` includes soft-deleted recipes (`app/Models/Meal.php:31-34`). Soft delete keeps the pivot rows, so a deleted recipe's ingredients can still be read.
- Units are exactly `g, kg, ml, l, szt` with `conversion_factor_to_base` g=1, kg=1000, ml=1, l=1000, szt=1, written by `database/migrations/2026_11_13_120000_normalize_units_and_ingredient_catalog.php:68-74`. `Unit::ALLOWED_CODES` mirrors them (`app/Models/Unit.php:15`).
- An ingredient name maps to one global `ingredients.id`, compared case-insensitively and with whitespace normalized (unique `lower(name)` index, `Ingredient::resolveIdByName`, `app/Models/Ingredient.php:55-70`), so `ingredient_id` is a safe key for summing.
- `recipe_ingredients.quantity` is `decimal(10,2)` with a `decimal:2` cast (`app/Models/RecipeIngredient.php:19-21`), so quantities arrive as exact 2-decimal strings.
- Old recipes saved before S-02 may have no ingredients. They still feed generation (S-02 decision, `context/archive/2026-11-13-recipe-required-ingredients/plan-brief.md`).
- `MealPlanController::show` loads only `meals.recipe` for Inertia (`app/Http/Controllers/MealPlanController.php:94-99`). `Show.vue` polls with `usePoll(3000, { only: ['mealPlan'] })` while the plan is `pending`/`processing` (`resources/js/Pages/MealPlans/Show.vue:131`).
- `api-plan.md:107` names a `ShoppingListService` ("Sumowanie i konwersja jednostek"). Nothing exists yet.
- The planner users in `E2eMealPlanSeeder` get recipes without ingredients (`database/seeders/E2eMealPlanSeeder.php:57-66`; `RecipeFactory` creates none).
- The Sail image ships `php8.5-intl` and `php8.5-bcmath` (`docker/8.5/Dockerfile:47-49`).

## Desired End State

A logged-in user opens a `done` plan. Below the grid there is a "Lista zakupów" card. It has one row per ingredient and unit group, with the name and a Polish-formatted quantity ("Jajka — 21 szt.", "Mąka — 2,1 kg", "Mleko — 1,05 l"), sorted Polish-alphabetically. If some of the plan's recipes have no ingredients, a note under the list names them. While the plan is generating, no list is shown, and the list appears without a manual refresh once the poll sees `done`. After "Regeneruj" the list shows the new draw.

Verify with: Pest (service unit tests, a controller feature test), Larastan, Pint, `npm run build`, and the extended `tests/e2e/meal-plan.spec.ts`.

### Key Discoveries:

- Floats are not safe for the rounding rule: `round(1005 / 1000, 2)` on a float can give `1.00` because 1.005 has no exact binary form. bcmath (or `BcMath\Number`, PHP 8.4+) keeps the sum and the half-up rounding exact.
- A byte-wise sort puts "Łosoś" after "Ziemniaki". `Collator('pl_PL')` (intl) gives L < Ł < M and a < ą < b.
- ICU `pl` does not group 4-digit numbers but does group 5-digit ones with a no-break space. Turning grouping off keeps the output deterministic ("12345 szt.").
- A closure Inertia prop is evaluated only when the visit requests it, so `shoppingList` costs nothing on a partial reload that doesn't ask for it. But it is also missing from a poll that requests only `mealPlan` (`Show.vue:131`).
- Existing pattern for a lean per-view payload: the comment at `MealPlanController.php:96` ("The week view … needs only the recipe names"). It must be updated, because the view now needs ingredients too.

## What We're NOT Doing

- Scaling quantities to the family or AI rounding (S-04). Before S-04 every meal contributes its recipe's full quantities as written.
- PDF export (S-06). S-06 reuses `ShoppingListBuilder` and the `display` strings.
- Storing the list: no table, no JSON column, no job change. Editing a recipe after generation changes the list of an existing plan, the same way the grid already shows live recipe names.
- Converting between unit groups (szt. ↔ g, ml ↔ g) or warning about mixed units. Each group gets its own row.
- Editing, ticking off or reordering list items (PRD §4).
- A JSON endpoint or a `shopping_list` key in the JSON `show` response; no user story needs it.
- Showing the list on the plan index or the dashboard.
- Fixing the stale `'pcs'` fallback in `MealPlanResource` ingredient mapping (unrelated to this view's payload).

## Implementation Approach

The new `App\Services\ShoppingList\ShoppingListBuilder` has two layers:

1. **Pure aggregation:** `build(iterable<QuantityLine>)` takes neutral lines (ingredient id, name, unit code, factor to base, decimal quantity). It groups them by ingredient and unit group, sums exactly in the base unit, picks the display unit with the strict `>1000` rule, rounds half-up to 2 decimals, formats in Polish and sorts with the Polish collator. This is the S-04 seam: S-04 will pass scaled lines instead of recipe lines.
2. **Plan loader:** `forMealPlan(MealPlan)` eager-loads the plan's meals, their recipes (soft-deleted included) and the recipe ingredients with their units. It maps them to lines, calls `build()` and returns, alongside the list, the sorted names of recipes that contributed nothing.

The controller exposes the result as a closure Inertia prop `shoppingList`, shaped by `ShoppingListResource` and computed only for `done` plans. `Show.vue` renders a new `ShoppingList.vue` card under the grid and adds `shoppingList` to the poll. The E2E seeder gives every planner recipe a fixed ingredient set, so the browser spec can assert exact sums.

## Critical Implementation Details

- **Exact arithmetic, one rounding.** Sum the decimal strings exactly in the base unit (g/ml/szt), check the threshold on that exact sum (`> 1000`), and only then divide by 1000 where needed and round half-up to 2 decimals. Never round the inputs or the intermediate values. Examples that must hold: 1000 g → "1000 g"; 1000,01 g → "1 kg"; 1004,99 g → "1 kg"; 1005 g → "1,01 kg".
- **Poll must request the list.** `Show.vue` has to poll `only: ['mealPlan', 'shoppingList']`. With `only: ['mealPlan']` the plan turns `done` while `shoppingList` stays `null` until a manual refresh. The E2E spec catches this because the first load after "Wygeneruj" is always `pending`.

## Phase 1: Shopping list service

### Overview

The FR-6/US-011 rules as one unit-tested service, plus the loader that turns a plan into lines. No UI yet.

### Changes Required:

#### 1. Unit groups

**File**: `app/Models/Unit.php`

**Intent**: One place that says which codes can be summed together and which code is the "large" display unit of a group, so the builder doesn't hard-code unit strings.

**Contract**:
- Constant `GROUPS` mapping a code to its group: `g`, `kg` → `mass`; `ml`, `l` → `volume`; `szt` → `pieces`.
- Per group, a base code (`g`, `ml`, `szt`) and an optional large code with a threshold of 1000 (`kg` for mass, `l` for volume; none for pieces).
- Group display order for rows with the same name: `mass`, `volume`, `pieces`.
- Polish display labels: `g`, `kg`, `ml`, `l`, `szt.`.
- The factor to base stays in `units.conversion_factor_to_base` and travels on each line; the constants don't duplicate it.

#### 2. Value objects

**File**: `app/Services/ShoppingList/QuantityLine.php`, `app/Services/ShoppingList/ShoppingListItem.php`, `app/Services/ShoppingList/ShoppingList.php` (new; `declare(strict_types=1)`, readonly)

**Intent**: Typed input and output, so the seam for S-04 and the payload for S-06 are explicit.

**Contract**:
- `QuantityLine(int $ingredientId, string $name, string $unitCode, string $factorToBase, string $quantity)`: decimal strings, at most 2 decimals for `quantity`, as the DB casts deliver them.
- `ShoppingListItem(int $ingredientId, string $name, string $quantity, string $unit, string $display)`: `quantity` is the rounded value as a dot-decimal string without trailing zeros ("1.23", "500", "2.5"), `unit` is the display code (`kg`, `szt`), and `display` is the Polish text ("1,23 kg", "2,5 szt.").
- `ShoppingList(list<ShoppingListItem> $items, list<string> $recipesWithoutIngredients)`.

#### 3. Builder service

**File**: `app/Services/ShoppingList/ShoppingListBuilder.php` (new; `declare(strict_types=1)`)

**Intent**: The single implementation of the list rules, used by the plan view now and by S-04/S-06 later (the `ShoppingListService` of `api-plan.md:107`).

**Contract**:
- `build(iterable<QuantityLine> $lines): list<ShoppingListItem>`:
  - Group by `(ingredientId, Unit::GROUPS[unitCode])`. An unknown code (impossible after S-02) forms its own group keyed by the code, with no conversion, and shows that code.
  - Sum `quantity × factorToBase` exactly with bcmath.
  - If the group has a large unit and the exact base sum is `> 1000`, show it in the large unit (sum / 1000). Otherwise show it in the base unit. Round half-up to 2 decimals after this step only.
  - Format with `NumberFormatter('pl_PL', DECIMAL)`: 0–2 fraction digits, grouping off, comma separator. `display` = number + space + Polish unit label.
  - Sort with `Collator('pl_PL')` on name. Ties (same ingredient, different groups) go mass, volume, pieces.
- `forMealPlan(MealPlan $mealPlan): ShoppingList`:
  - `loadMissing('meals.recipe.recipeIngredients.ingredient', 'meals.recipe.recipeIngredients.unit')`, using the existing `withTrashed()` recipe relation.
  - Every meal's recipe ingredients become `QuantityLine`s, with the factor taken from `unit.conversion_factor_to_base`. Meals with a `null` recipe are skipped.
  - Recipes with zero ingredients go to `recipesWithoutIngredients` once each (distinct by recipe id), sorted with the same collator.
  - Reads only the given plan's meals.

#### 4. Tests

**File**: `tests/Unit/Services/ShoppingListBuilderTest.php` (new), `tests/Pest.php`

**Intent**: The roadmap calls the rounding and conversion tests mandatory. Each rule and decision gets its own named case.

**Contract**:
- Helper in `tests/Pest.php`: `attachIngredient(Recipe $recipe, string $name, string $quantity, string $unitCode): void`, built on `Ingredient::resolveIdByName` and the migrated `units` rows.
- `build()` cases (expected `display`):
  - 200 g + 300 g of one ingredient → "500 g".
  - 0,3 kg + 900 g → "1,2 kg"; 0,5 kg alone → "500 g".
  - Exactly 1000 g → "1000 g"; exactly 1000 ml → "1000 ml".
  - 1000,01 g → "1 kg"; 1001 ml → "1 l".
  - 1234 g → "1,23 kg"; 1005 g → "1,01 kg"; 1004,99 g → "1 kg".
  - 1050 ml → "1,05 l"; 0,25 l + 0,8 l → "1,05 l".
  - Pieces never convert: 1500 szt → "1500 szt."; 2,5 szt → "2,5 szt.".
  - Mixed groups: "Jajka" 2 szt + 100 g → two rows, "100 g" first, then "2 szt."; "Mleko" 200 ml + 100 g → two rows.
  - Polish order: Ziemniaki, Łosoś, Masło, Cebula, Ćwikła → Cebula, Ćwikła, Łosoś, Masło, Ziemniaki.
  - No lines → empty list.
  - `quantity` strings: "1.23", "500", "2.5".
- `forMealPlan()` cases (DB, factories):
  - Sums across the plan's meals (two recipes sharing "Mąka" 600 g each → "1,2 kg").
  - A soft-deleted recipe's ingredients still count.
  - A recipe without ingredients adds no row and appears once in `recipesWithoutIngredients`, with names sorted in Polish order.
  - Another plan of the same user doesn't leak into this plan's list.

### Success Criteria:

#### Automated Verification:

- Service tests pass: `vendor/bin/sail pest tests/Unit/Services/ShoppingListBuilderTest.php`
- Larastan passes without baseline changes: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`

**Implementation Note**: After completing this phase and all automated verification passes, pause for manual confirmation before proceeding to the next phase.

---

## Phase 2: List on the plan view

### Overview

The plan view shows the list under the grid, keeps it fresh through polling, and explains any recipes that have no ingredients.

### Changes Required:

#### 1. Resource

**File**: `app/Http/Resources/ShoppingListResource.php` (new; `declare(strict_types=1)`)

**Intent**: JSON shape in an API Resource, per the CLAUDE.md convention.

**Contract**: wraps `ShoppingList` and returns `{ items: [{ ingredient_id, name, quantity, unit, display }], recipes_without_ingredients: string[] }`.

#### 2. Controller prop

**File**: `app/Http/Controllers/MealPlanController.php`

**Intent**: Give the Inertia plan view the list only when the plan is ready, without loading ingredients for list or JSON responses.

**Contract**:
- `show()` Inertia branch adds `'shoppingList' => fn () => …`. It returns `null` unless `status === 'done'`, otherwise `(new ShoppingListResource($builder->forMealPlan($mealPlan)))->resolve()`. The builder is resolved from the container (method injection or `app()`).
- Update the comment at `:96` to say the view also needs the ingredients for the list.
- The JSON branch, `index`, `store`, `update` and `destroy` are unchanged. The existing `authorizeResource` + `MealPlanPolicy::view` keeps another user's plan at 403.

#### 3. Shopping list card

**File**: `resources/js/Components/MealPlans/ShoppingList.vue` (new)

**Intent**: Show the list the way US-011 describes, in Polish, matching the grid's card styling (border, white/dark background).

**Contract**:
- Prop `list: { items: { ingredient_id: number, name: string, quantity: string, unit: string, display: string }[], recipes_without_ingredients: string[] }`.
- `<section>` with `<h2>` "Lista zakupów" and a `<ul data-testid="shopping-list">`. Each `<li>` shows the name and `display` on one line. Key on `ingredient_id` + `unit`.
- If `items` is empty: "Przepisy w tym jadłospisie nie mają składników."
- If `recipes_without_ingredients` is not empty: a muted note "Bez składników (nie ma ich na liście): Owsianka, Jajecznica."

#### 4. Plan view

**File**: `resources/js/Pages/MealPlans/Show.vue`

**Intent**: Place the card under the grid and keep it in sync with the plan's status.

**Contract**:
- New prop `shoppingList: ShoppingListPayload | null`.
- Render `<ShoppingList>` right after `<MealPlanGrid v-else …>`, only in the `done`-with-meals branch and when `shoppingList` is not `null`.
- `usePoll(3000, { only: ['mealPlan', 'shoppingList'] }, { autoStart: false })`.

#### 5. Tests

**File**: `tests/Feature/MealPlanControllerTest.php`

**Intent**: Prove the prop contract and its failure modes at the HTTP boundary.

**Contract**:
- A `done` plan with meals whose recipes share an ingredient: `assertInertia` sees `shoppingList.items` with the summed `display` (e.g. two × 600 g "Mąka" → "1,2 kg") and the `recipes_without_ingredients` names.
- A `pending` plan (and a `processing` one) → `shoppingList` is `null`.
- A partial reload asking for `shoppingList` (Inertia testing `reloadOnly`) returns it for a `done` plan, which guards the poll contract on the server side.
- The existing "another user's plan" show test stays green (403).

### Success Criteria:

#### Automated Verification:

- Meal-plan feature tests pass: `vendor/bin/sail pest tests/Feature/MealPlanControllerTest.php`
- Full suite passes: `vendor/bin/sail composer test`
- Larastan passes without baseline changes: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`
- Client and SSR bundles build: `vendor/bin/sail npm run build`

#### Manual Verification:

- In Sail, generating a plan shows the grid and then the "Lista zakupów" card below it without a manual refresh
- Quantities read in Polish ("1,2 kg", "21 szt."), rows sort Polish-alphabetically (Ł after L), and an ingredient in two unit groups shows two adjacent rows
- A plan that includes a recipe without ingredients shows the "Bez składników" note naming it
- The card is readable in dark mode and on a narrow (mobile) screen

**Implementation Note**: After completing this phase and all automated verification passes, pause for manual confirmation before proceeding to the next phase.

---

## Phase 3: E2E

### Overview

Prove in a browser that the list arrives after the async generation and is rebuilt after regeneration, with exact sums.

### Changes Required:

#### 1. Seeder ingredients

**File**: `database/seeders/E2eMealPlanSeeder.php`

**Intent**: Give every planner recipe the same fixed ingredients, so any draw of 21 meals yields the same, known list, even on a dev DB that already has older planner recipes.

**Contract**:
- After topping up recipes, `sync` every planner user's recipes to exactly: "Mąka" 100 g, "Mleko" 50 ml, "Jajka" 1 szt. Use `Ingredient::resolveIdByName` and the unit ids by code.
- Still idempotent. Cook users are untouched.

#### 2. Playwright spec

**File**: `tests/e2e/meal-plan.spec.ts`

**Intent**: The browser-level check of US-011 and of the poll wiring.

**Contract**:
- After the first 21-cell grid: `getByTestId('shopping-list')` list items, in order, contain "Jajka" + "21 szt.", "Mąka" + "2,1 kg", "Mleko" + "1,05 l".
- After "Regeneruj" and the grid's return to 21 cells: the same three rows are visible again.

### Success Criteria:

#### Automated Verification:

- Seeder runs twice without error: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder` (twice)
- Client and SSR bundles build: `vendor/bin/sail npm run build`
- E2E suite passes in both browsers: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- Full Pest suite, Larastan and Pint pass: `vendor/bin/sail composer test && vendor/bin/sail composer phpstan && vendor/bin/sail composer pint-test`

#### Manual Verification:

- The CI `e2e-tests` job passes on the PR into `main`

**Implementation Note**: After completing this phase and all automated verification passes, pause for manual confirmation.

---

## Testing Strategy

### Unit Tests:

- `ShoppingListBuilder::build()`:
  - summing within a group;
  - base-unit normalization (0,5 kg → 500 g);
  - the strict `>1000` threshold at its boundary;
  - exact half-up rounding (1005 g → 1,01 kg);
  - pieces never convert;
  - mixed groups as separate rows in mass/volume/pieces order;
  - Polish collation;
  - Polish number formatting and the `quantity` strings.
- `ShoppingListBuilder::forMealPlan()`: sums across meals, includes soft-deleted recipes, lists recipes without ingredients, and stays within one plan.

### Integration Tests:

- `MealPlanControllerTest`: `shoppingList` prop on `done`, `null` while generating, partial reload returns it, 403 for another user.
- Playwright `meal-plan.spec.ts`: generate → grid → exact list rows → regenerate → rows again.

### Manual Testing Steps:

1. In Sail with the `queue` service up, generate a plan for a user whose recipes have ingredients; the list appears under the grid without a refresh.
2. Edit a recipe used in the plan to use "Jajka" in g as well as szt. in another recipe, then reload the plan: two "Jajka" rows, mass first.
3. Soft-delete a recipe used in the plan, reload: its ingredients are still summed.
4. Use a pre-S-02 recipe without ingredients in a plan (or remove its ingredients in the DB): the "Bez składników" note names it.

## Performance Considerations

Building the list takes 4 eager-loaded queries (meals, recipes, recipe ingredients, ingredients + units) over 21 meals and about 200 ingredient rows, then an in-memory aggregation. The closure prop runs only when requested and only for `done` plans, and the poll stops at `done`, so a plan view computes the list about once per load.

## Migration Notes

No schema changes. The seeder change rewrites the ingredients of the E2E planner users' recipes only (non-production; the seeder refuses to run in production). Existing plans get a list on their next view.

## References

- Roadmap item: `context/foundation/roadmap.md` → S-05 `shopping-list`
- PRD: `.ai/core/prd.md` FR-6, US-011 (and US-012 c.2 for the S-04 seam); `.ai/core/api-plan.md:107`
- Unit normalization: `database/migrations/2026_11_13_120000_normalize_units_and_ingredient_catalog.php`
- Prior slices: `context/archive/2026-11-13-real-weekly-meal-plan/plan.md`, `context/archive/2026-11-13-recipe-required-ingredients/plan-brief.md`
- Poll and view: `resources/js/Pages/MealPlans/Show.vue:131`, `resources/js/Components/MealPlans/MealPlanGrid.vue`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Shopping list service

#### Automated

- [x] 1.1 Service tests pass — 778c148
- [x] 1.2 Larastan passes without baseline changes — 778c148
- [x] 1.3 Pint passes — 778c148

### Phase 2: List on the plan view

#### Automated

- [x] 2.1 Meal-plan feature tests pass — b3ddb0a
- [x] 2.2 Full suite passes — b3ddb0a
- [x] 2.3 Larastan passes without baseline changes — b3ddb0a
- [x] 2.4 Pint passes — b3ddb0a
- [x] 2.5 Client and SSR bundles build — b3ddb0a

#### Manual

- [ ] 2.6 Generated plan shows the list under the grid without a manual refresh
- [ ] 2.7 Polish quantities and order; an ingredient in two unit groups shows two adjacent rows
- [ ] 2.8 Plan with an ingredient-less recipe shows the "Bez składników" note
- [ ] 2.9 Card readable in dark mode and on a narrow screen

### Phase 3: E2E

#### Automated

- [x] 3.1 Seeder runs twice without error — 86df678
- [x] 3.2 Client and SSR bundles build — 86df678
- [x] 3.3 E2E suite passes in both browsers — 86df678
- [x] 3.4 Full Pest suite, Larastan and Pint pass — 86df678

#### Manual

- [ ] 3.5 CI e2e-tests job passes on the PR into main
