# Recipe Required Ingredients (S-02) Implementation Plan

## Overview

Make a recipe savable only with every PRD-required field (FR-003): name, category, preparation, total calories, servings **and at least one ingredient** with a name, a quantity and a unit from `g | kg | ml | l | szt.`. Ingredient names become free text with suggestions (today the user can only pick from a fixed English list and the "Add" button is dead), duplicates are rejected with a per-row error instead of silently dropped, and the recipe form and its messages become Polish. This is roadmap S-02; it unblocks the shopping list (S-05), which sums by ingredient identity and unit conversion factor.

## Current State Analysis

- Ingredients are optional: `'ingredients' => 'sometimes|array'` in `app/Http/Requests/StoreRecipeRequest.php:33` and `app/Http/Requests/UpdateRecipeRequest.php:34`; `RecipeController::store` skips the sync when the list is empty (`app/Http/Controllers/RecipeController.php:88`); the client-side `isFormValid` ignores ingredients (`resources/js/Components/recipes/RecipeForm.vue:191-199`).
- Duplicates are silently dropped: `prepareForValidation` keeps the first row per `ingredient_id` (`StoreRecipeRequest.php:73-84`, same in Update), so the `withValidator` duplicate check below it can never fire and the second row's quantity is lost.
- Units are wrong and only exist via seeders: `database/seeders/UnitSeeder.php` seeds `g, kg, ml, l, pcs, tsp, tbsp, cup`; `database/seeders/IngredientSeeder.php:17-30` seeds the same plus `szt` (and `cup` = 240). `DatabaseSeeder` runs both, so a dev DB has 9 units including both `pcs` and `szt`. No migration creates unit rows, so a fresh production DB would have none.
- Ingredients are a global dictionary (`ingredients(id, name)`, `database/migrations/2025_07_31_145625_create_ingredients_table.php`) seeded with ~40 English names. The form offers all of them in a `Select` (`resources/js/Components/recipes/IngredientRow.vue:15-60`); the "Add "X"" button emits `add-ingredient` → `IngredientManager` re-emits `add-new-ingredient` → `RecipeForm` never listens, so a user cannot use an ingredient outside the list.
- Request contract is by id: `ingredients.*.ingredient_id|quantity|unit_id`; the controller syncs a `BelongsToMany` with pivot `quantity, unit_id` (`RecipeController.php:166-176`). `recipe_ingredients` has `UNIQUE(recipe_id, ingredient_id)`.
- Read side: `RecipeResource` returns `ingredients: [{id, name, quantity, unit: {id, code}}]` (`app/Http/Resources/RecipeResource.php:32-44`), consumed by `Pages/Recipes/Form.vue:59-63` and `Components/recipes/IngredientCard.vue:6`.
- All recipe form copy and Form Request messages are English; app locale is `pl` (`lang/pl/validation.php` exists).
- No test covers ingredients. `tests/Feature/RecipeControllerTest.php` stores/updates recipes without ingredients. `tests/Unit/Models/RecipeIngredientCastTest.php:18` creates `Unit 'g'` by hand. `tests/Unit/RecipeRequestValidationTest.php` passes a payload without ingredients as valid.
- E2E: `tests/e2e/meal-plan.spec.ts` logs in a seeded planner user from `database/seeders/E2eMealPlanSeeder.php`; there is no recipe spec.
- The app is not deployed yet (F-02 is `ready`), so only local/dev data exists. PostgreSQL has `unaccent` (`2026_11_13_100000_enable_unaccent_extension.php`).

## Desired End State

On `/recipes/create` and `/recipes/{id}/edit` the user sees a Polish form. Each ingredient row has a text field with suggestions (the Polish seed catalog plus ingredients from the user's own recipes, never other users' names), a quantity and a unit select offering exactly `g, kg, ml, l, szt`. The submit button stays disabled until there is at least one complete ingredient row. Server-side, store and update reject a payload with no ingredients, an unknown unit, a quantity below 0.01 or two rows whose names normalize to the same value ("Mąka" / " mąka ") — the error sits on the duplicate row, in Polish. A saved name maps to one `ingredients` row regardless of case and surrounding/double whitespace, so "Mąka" typed by two users is one ingredient for S-05. Existing recipes without ingredients still show and still feed meal-plan generation, but cannot be saved again without adding one.

Verify with: Pest (Form Request, controller, migration/model tests), Larastan, Pint, `npm run build`, and the new Playwright spec `tests/e2e/recipe-form.spec.ts`.

### Key Discoveries:

- Removing the dedupe in `prepareForValidation` is required for the duplicate-error decision to work at all — today the after-hook sees an already deduplicated array.
- `RecipeIngredientCastTest.php:18` will hit `units.code` UNIQUE once the migration seeds units; it must reuse the seeded `g`.
- Recipes use `SoftDeletes`; "ingredients from the user's own recipes" should include only non-deleted recipes (default scope does this).
- Forms submit with `form.put` (full payload), so making `ingredients` required on update does not break the UI; there is no API/PATCH client (`routes/web.php:32` is a plain `Route::resource`).

## What We're NOT Doing

- No per-user ingredient table and no ingredient CRUD/endpoint; suggestions are an Inertia prop.
- No merging of duplicate rows or unit conversion on save (conversion is S-05).
- No exclusion of ingredient-less recipes from meal-plan generation and no "missing ingredients" badge.
- No translation of `Recipes/Index`, `Recipes/Show`, toolbar or delete dialog (only the form, its components and messages).
- No cleanup of the English seed ingredients already in dev DBs (they lose `is_catalog` visibility, see Phase 1).
- No change to the `RecipeResource` read shape beyond what the form needs.

## Implementation Approach

Data first, so validation can rely on exactly five unit rows and a normalized-name unique index. Then switch the write contract from `ingredient_id` to `name` (resolved server-side with an insert-or-get on the normalized name), with Polish messages and per-row duplicate errors. Then rebuild the ingredient row as a text input with suggestions and Polish copy. Finally prove the whole path in a browser.

## Critical Implementation Details

- **Name normalization is one function used everywhere.** Trim, collapse internal whitespace to a single space, compare with `lower()` (PostgreSQL `lower` handles Polish diacritics). The stored `name` keeps the first creator's casing (trimmed/collapsed). The unique index must be on the same expression the resolver queries, e.g. `CREATE UNIQUE INDEX ingredients_name_normalized_unique ON ingredients (lower(name))` with names already stored trimmed/collapsed; resolve via `insertOrIgnore` then select by `lower(name)` so two concurrent saves of the same new name cannot create two rows or throw.
- **Unit migration order:** ensure `szt` exists before re-pointing `pcs` rows; convert `tsp/tbsp/cup` rows to `ml` (quantity × 5 / 15 / 250) before deleting those units, because `recipe_ingredients.unit_id` cascades on delete.

## Phase 1: Data — units and ingredient catalog

### Overview

One migration makes the database the single source of truth for the five PRD units and a Polish ingredient catalog, and adds the normalized-name uniqueness that S-05 relies on.

### Changes Required:

#### 1. Migration

**File**: `database/migrations/2026_11_13_120000_normalize_units_and_ingredient_catalog.php` (new, `declare(strict_types=1)`)

**Intent**: Seed/upsert exactly `g`(1), `kg`(1000), `ml`(1), `l`(1000), `szt`(1); re-point `recipe_ingredients` from `pcs` to `szt`; convert `tsp`→`ml` ×5, `tbsp`→`ml` ×15, `cup`→`ml` ×250 (multiply quantity); then delete every unit not in the five. Add `ingredients.is_catalog` (boolean, default false), normalize existing names (trim/collapse), add the unique `lower(name)` index, and insert the Polish catalog (e.g. Mąka, Cukier, Sól, Pieprz czarny, Oliwa z oliwek, Masło, Jajka, Mleko, Pierś z kurczaka, Wołowina, Wieprzowina, Łosoś, Pomidory, Cebula, Czosnek, Ziemniaki, Marchew, Brokuł, Papryka, Szpinak, Ryż, Makaron, Chleb, Ser żółty, Jogurt naturalny, Cytryna, Bazylia, Oregano, Tymianek, Papryka słodka, Kmin rzymski, Imbir, Miód, Sos sojowy, Ocet, Bulion drobiowy, Bulion warzywny, Mleko kokosowe, Śmietana 30%) with `is_catalog = true` (insert-or-update on the normalized name).

**Contract**: `units.code ∈ {g,kg,ml,l,szt}` after `up()`; `ingredients.is_catalog boolean not null default false`; unique index on `lower(name)`. If a pre-existing dev DB has two ingredient names that collide after normalization, merge them (re-point `recipe_ingredients`, dropping the later row when a recipe already has the kept one) before creating the index. `down()` drops the index and column only; unit/data conversion is documented as irreversible.

#### 2. Seeders

**File**: `database/seeders/UnitSeeder.php`, `database/seeders/IngredientSeeder.php`, `database/seeders/DatabaseSeeder.php`

**Intent**: Remove the now-redundant unit and English ingredient seeding so seeders cannot reintroduce `pcs/tsp/tbsp/cup` or English catalog names.

**Contract**: Delete `UnitSeeder` and `IngredientSeeder`; `DatabaseSeeder` no longer calls them.

#### 3. Models

**File**: `app/Models/Ingredient.php`, `app/Models/Unit.php`

**Intent**: Add `declare(strict_types=1)`; `is_catalog` fillable + boolean cast; a scope that returns suggestions for a user; a static resolver that maps a raw name to an ingredient id using the normalization rule.

**Contract**: `Ingredient::scopeSuggestedFor(Builder $query, int $userId)` → catalog rows OR rows linked via `recipe_ingredients` to the user's non-deleted recipes, ordered by name. `Ingredient::normalizeName(string): string` and `Ingredient::resolveIdByName(string): int` (insert-or-ignore + select on `lower(name)`). `Unit::ALLOWED_CODES = ['g','kg','ml','l','szt']`.

#### 4. Tests

**File**: `tests/Unit/Models/IngredientTest.php` (new), `tests/Unit/Models/RecipeIngredientCastTest.php`

**Intent**: Prove the migrated state and the model contract; fix the cast test to use the seeded `g`.

**Contract**: After migrations exactly the five unit codes exist with the listed factors; catalog rows have `is_catalog = true`; `resolveIdByName(' mąka ')` and `resolveIdByName('MĄKA')` return the catalog `Mąka` id without inserting; a new name inserts once; `suggestedFor` returns catalog + own-recipe ingredients and excludes another user's custom ingredient and ingredients of the user's soft-deleted recipes.

### Success Criteria:

#### Automated Verification:

- Migrations apply on a fresh DB: `vendor/bin/sail artisan migrate:fresh --no-interaction`
- Model tests pass: `vendor/bin/sail pest tests/Unit/Models`
- Larastan passes: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`

#### Manual Verification:

- On an existing dev DB with the old 9 units, `vendor/bin/sail artisan migrate` leaves only g/kg/ml/l/szt and recipes that used tsp/tbsp/cup/pcs now show ml/szt with converted quantities

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Backend — required ingredients saved by name

### Overview

Switch the write contract to names, require at least one ingredient on store and update, reject duplicates per row, translate messages, and give the form the scoped suggestion list.

### Changes Required:

#### 1. Form Requests

**File**: `app/Http/Requests/StoreRecipeRequest.php`, `app/Http/Requests/UpdateRecipeRequest.php`

**Intent**: Require ≥1 ingredient on both; validate each row by name; restrict units to the five codes; replace the silent dedupe with a per-row error on every row after the first whose normalized name repeats; Polish messages for every rule. Share the ingredient rules/messages/duplicate check between both requests (trait or base class) instead of duplicating them again.

**Contract**: `ingredients: required|array|min:1`; `ingredients.*.name: required|string|max:255` (after trim); `ingredients.*.quantity: required|numeric|min:0.01|max:99999999.99`; `ingredients.*.unit_id: required|integer|exists:units,id` where the unit code is in `Unit::ALLOWED_CODES`. Duplicate error key `ingredients.{i}.name`, message „Ten składnik już jest na liście.”. Empty list message on `ingredients`: „Dodaj co najmniej jeden składnik.”. Remove `prepareForValidation` dedupe. Update keeps `sometimes|required` on scalar fields but `ingredients` is `required`.

#### 2. Controller

**File**: `app/Http/Controllers/RecipeController.php`

**Intent**: Resolve each row's name to an ingredient id via `Ingredient::resolveIdByName` inside the existing transaction, then sync as today; always sync on store and update. `create`/`edit` pass `ingredients` = `Ingredient::suggestedFor($userId)` (`id`, `name`) and `units` limited to the five codes in display order g, kg, ml, l, szt. Flash messages in Polish.

**Contract**: Request payload `ingredients: [{name: string, quantity: number, unit_id: int}]`; Inertia props `ingredients: [{id, name}]`, `units: [{id, code}]` unchanged in shape.

#### 3. Tests

**File**: `tests/Unit/RecipeRequestValidationTest.php`, `tests/Feature/RecipeControllerTest.php`

**Intent**: Cover the new rules and the name resolution; update existing store/update tests to send ingredients.

**Contract**: Validation: missing/empty `ingredients` fails; unknown unit fails; quantity 0 fails; "Mąka" + " mąka " fails with error on `ingredients.1.name`; a valid payload passes; messages are Polish. Controller: store with a catalog name links the catalog id (no new row); store with a new name creates one ingredient; update without ingredients is rejected (an existing ingredient-less recipe stays untouched); update replaces the ingredient set; `create`/`edit` props include the catalog and the user's own custom ingredient but not another user's.

### Success Criteria:

#### Automated Verification:

- Recipe tests pass: `vendor/bin/sail pest tests/Feature/RecipeControllerTest.php tests/Unit/RecipeRequestValidationTest.php`
- Full suite passes: `vendor/bin/sail composer test`
- Larastan passes: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Frontend — Polish recipe form with free-text ingredients

### Overview

Replace the ingredient `Select` with a text input plus suggestions, drop the dead "Add" path, require a complete ingredient before submit, and translate the form.

### Changes Required:

#### 1. Ingredient row and manager

**File**: `resources/js/Components/recipes/IngredientRow.vue`, `resources/js/Components/recipes/IngredientManager.vue`

**Intent**: The name field is a text input bound to `name` with suggestions from `availableIngredients` (a native `<datalist>` or a shadcn-vue combobox already in `Components/ui/` — no new dependency); remove the `add-ingredient`/`add-new-ingredient` events. Errors read `ingredients.{i}.name`. New rows start as `{name: '', quantity: '', unit_id: null}`. Polish labels: „Składnik”, „Ilość”, „Jednostka”, „Dodaj składnik”, „Usuń składnik”, empty state „Nie dodano jeszcze składników.”. The unit select shows `szt.` for code `szt`.

**Contract**: Row model `{name: string, quantity: string|number, unit_id: number|null}`; each input keeps an accessible label (used by the E2E spec).

#### 2. Form and page

**File**: `resources/js/Components/recipes/RecipeForm.vue`, `resources/js/Pages/Recipes/Form.vue`

**Intent**: `isFormValid` additionally requires ≥1 ingredient row with non-blank name, quantity > 0 and a unit. Edit mode maps existing ingredients to `{name, quantity, unit_id}`. Translate all form copy (title „Nowy przepis” / „Edytuj przepis”, „Nazwa przepisu”, „Kategoria”, „Sposób przygotowania”, „Kaloryczność (kcal)”, „Liczba porcji”, „Składniki”, „Zapisz przepis” / „Zapisz zmiany”, „Anuluj”, „* Pola wymagane”). Show the general `ingredients` error under the list.

**Contract**: Submitted payload matches Phase 2.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Full suite still passes: `vendor/bin/sail composer test`

#### Manual Verification:

- Creating a recipe with a new ingredient name, then editing it, shows the name, quantity and unit preserved
- Typing „mą” suggests „Mąka”; another user's custom ingredient is not suggested
- Submit stays disabled with no ingredient row or an incomplete one; a duplicate name shows the Polish error on that row
- An old recipe without ingredients opens in edit and cannot be saved until an ingredient is added

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: E2E — create a recipe with an ingredient

### Overview

One browser test proves the form can actually create a recipe with an ingredient end to end (the class of bug Pest cannot see, like today's dead "Add" button).

### Changes Required:

#### 1. Seeder

**File**: `database/seeders/E2eMealPlanSeeder.php`

**Intent**: Also create one recipe-author user per browser project (`e2e-cook-chromium@example.com`, `e2e-cook-firefox@example.com`, same password) and force-delete their recipes on every run, so the spec starts clean and never touches the planner users' 21 recipes.

**Contract**: Seeder stays idempotent and refuses to run in production.

#### 2. Spec

**File**: `tests/e2e/recipe-form.spec.ts` (new)

**Intent**: Log in as the cook user, open „Nowy przepis”, fill all fields, add one ingredient with a new name (e.g. „Kasza jaglana E2E”), quantity 200, unit g, submit, open the created recipe and assert the ingredient name, „200” and „g” are visible.

**Contract**: Follows `tests/e2e/meal-plan.spec.ts` login and locator style (labels/roles, Polish copy).

### Success Criteria:

#### Automated Verification:

- E2E passes against Sail: `vendor/bin/sail npm run build && vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- Full Pest suite passes: `vendor/bin/sail composer test`

**Implementation Note**: After completing this phase and all automated verification passes, the change is ready for review.

---

## Testing Strategy

### Unit Tests:

- Migrated state: five units with factors; catalog flagged.
- `Ingredient::normalizeName` / `resolveIdByName` (case, whitespace, Polish diacritics, insert once).
- `suggestedFor` scoping (catalog + own, not others', not soft-deleted).
- Form Request rules: required list, unit set, quantity bound, per-row duplicate error, Polish messages.

### Integration Tests:

- Controller store/update by name, ingredient-less update rejected, suggestion props scoped.
- Playwright: create recipe with ingredient → visible on detail page.

### Manual Testing Steps:

1. Migrate an existing dev DB and confirm unit conversion.
2. Create a recipe with a new and a catalog ingredient; edit it.
3. Try a duplicate name, an empty list, a zero quantity.
4. Log in as another user and confirm the custom ingredient is not suggested.

## Performance Considerations

The suggestion query runs on form load only: a union of ~40 catalog rows and the user's own ingredients; the existing `recipe_ingredients` indexes cover the join. No caching needed.

## Migration Notes

Not deployed yet, so only dev data is affected. The migration converts legacy unit rows rather than deleting them; `down()` cannot restore removed units. English seed ingredients already in a dev DB remain as non-catalog rows (shown only to users whose recipes use them).

## References

- Roadmap item: `context/foundation/roadmap.md` (S-02)
- PRD: `.ai/core/prd.md` FR-003, FR-008, US-005, US-006
- DB plan: `.ai/core/db-plan.md` §1.3, §1.4, §1.6
- Similar implementation (E2E seeder + spec): `database/seeders/E2eMealPlanSeeder.php`, `tests/e2e/meal-plan.spec.ts`
- Previous change for conventions: `context/archive/2026-11-13-real-weekly-meal-plan/plan.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Data — units and ingredient catalog

#### Automated

- [x] 1.1 Migrations apply on a fresh DB: `vendor/bin/sail artisan migrate:fresh --no-interaction` — fba933a
- [x] 1.2 Model tests pass: `vendor/bin/sail pest tests/Unit/Models` — fba933a
- [x] 1.3 Larastan passes: `vendor/bin/sail composer phpstan` — fba933a
- [x] 1.4 Pint passes: `vendor/bin/sail composer pint-test` — fba933a

#### Manual

- [x] 1.5 On an existing dev DB with the old 9 units, `vendor/bin/sail artisan migrate` leaves only g/kg/ml/l/szt and recipes that used tsp/tbsp/cup/pcs now show ml/szt with converted quantities — fba933a

### Phase 2: Backend — required ingredients saved by name

#### Automated

- [x] 2.1 Recipe tests pass: `vendor/bin/sail pest tests/Feature/RecipeControllerTest.php tests/Unit/RecipeRequestValidationTest.php` — 87e2652
- [x] 2.2 Full suite passes: `vendor/bin/sail composer test` — 87e2652
- [x] 2.3 Larastan passes: `vendor/bin/sail composer phpstan` — 87e2652
- [x] 2.4 Pint passes: `vendor/bin/sail composer pint-test` — 87e2652

### Phase 3: Frontend — Polish recipe form with free-text ingredients

#### Automated

- [x] 3.1 Client and SSR bundles build: `vendor/bin/sail npm run build`
- [x] 3.2 Full suite still passes: `vendor/bin/sail composer test`

#### Manual

- [x] 3.3 Creating a recipe with a new ingredient name, then editing it, shows the name, quantity and unit preserved
- [x] 3.4 Typing „mą” suggests „Mąka”; another user's custom ingredient is not suggested
- [x] 3.5 Submit stays disabled with no ingredient row or an incomplete one; a duplicate name shows the Polish error on that row
- [x] 3.6 An old recipe without ingredients opens in edit and cannot be saved until an ingredient is added

### Phase 4: E2E — create a recipe with an ingredient

#### Automated

- [ ] 4.1 E2E passes against Sail: `vendor/bin/sail npm run build && vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- [ ] 4.2 Full Pest suite passes: `vendor/bin/sail composer test`
