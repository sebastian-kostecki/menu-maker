# Backend API Plan — Menu Maker

## 1. Encje, relacje, walidacja

| Encja (tabela)       | Kluczowe pola                                              | Relacje                                               | Reguły walidacji (główne)                                                           |
| -------------------- | ---------------------------------------------------------- | ----------------------------------------------------- | ----------------------------------------------------------------------------------- |
| `users`              | `name`, `email`, `password`                                | 1-N `family_members`, 1-N `recipes`, 1-N `meal_plans` | `name:string                                                                        | max:255`, `email:email                                                                                     | unique`, `password:string                   | min:8`      |
| `family_members`     | `first_name`, `birth_date`, `gender`                       | N-1 `users`                                           | `first_name:string                                                                  | max:255`, `birth_date:date                                                                                 | before:today`, `gender:in:male,female`      |
| `units`              | `code`, `conversion_factor_to_base`                        | 1-N `recipe_ingredients`                              | `code:string                                                                        | max:10                                                                                                     | unique`, `conversion_factor_to_base:numeric | min:0.0001` |
| `ingredients`        | `name`                                                     | N-N `recipes` (via `recipe_ingredients`)              | `name:string                                                                        | max:255                                                                                                    | unique`                                     |
| `recipes`            | `name`, `category`, `instructions`, `calories`, `servings` | N-1 `users`, N-N `ingredients`, 1-N `meals`           | `name:string                                                                        | max:255`, `category:in:breakfast,lunch,dinner`, `instructions:string`, `calories:numeric                   | min:0`, `servings:integer                   | min:1`      |
| `recipe_ingredients` | `quantity`, `unit_id`                                      | N-1 `recipes`, N-1 `ingredients`, N-1 `units`         | `quantity:numeric                                                                   | min:0.01`, unikalność (`recipe_id`,`ingredient_id`)                                                        |
| `meal_plans`         | `start_date`, `end_date`, `status`, `generation_meta`      | N-1 `users`, 1-N `meals`, 1-N `logs_meal_plan`        | `start_date:date`, `end_date:date                                                   | after:start_date`, sprawdzenie 7-dniowego zakresu (rule custom), `status:in:pending,processing,done,error` |
| `meals`              | `meal_date`, `meal_category`                               | N-1 `meal_plans`, N-1 `recipes`                       | `meal_date:date`, `meal_category:in:breakfast,lunch,dinner` + date w zakresie planu |
| `logs_meal_plan`     | `started_at`, `finished_at`, `status`                      | N-1 `meal_plans`                                      | `started_at:date`, `status:in:pending,processing,done,error`                        |

---

## 2. Laravel-owy plan backendu

### A. Resource Controllers

| Controller                            | Encja / tabela       | Metody                                                                             |
| ------------------------------------- | -------------------- | ---------------------------------------------------------------------------------- |
| `FamilyMemberController`              | `family_members`     | `index`, `store`, `update`, `destroy`                                              |
| `UnitController`                      | `units`              | `index`, `store`, `update`, `destroy`                                              |
| `IngredientController`                | `ingredients`        | `index`, `store`, `update`, `destroy`                                              |
| `RecipeController`                    | `recipes`            | `index`, `show`, `store`, `update`, `destroy`                                      |
| `RecipeIngredientController` (nested) | `recipe_ingredients` | `store`, `update`, `destroy`                                                       |
| `MealPlanController`                  | `meal_plans`         | `index`, `show`, `store`(generate), `update`(regenerate), `destroy`, `downloadPdf` |
| `MealController` (nested)             | `meals`              | `index`, `destroy`                                                                 |

> Uwagi:
> * Generowanie i regeneracja jadłospisu wykorzystuje `store` / `update` z dedykowanymi akcjami serwisowymi.
> * Pobieranie PDF realizuje akcja `downloadPdf` (GET).

### B. Trasy (`routes/web.php`)

```php
Route::middleware(['auth', 'verified'])->group(function () {
    // Family Members
    Route::resource('family-members', FamilyMemberController::class)->except(['create', 'edit', 'show']);

    // Units (tylko admin)
    Route::resource('units', UnitController::class)->middleware('can:manage-units')->except(['create', 'edit', 'show']);

    // Ingredients
    Route::resource('ingredients', IngredientController::class)->except(['create', 'edit', 'show']);

    // Recipes
    Route::resource('recipes', RecipeController::class);
    Route::resource('recipes.ingredients', RecipeIngredientController::class)->shallow()->only(['store', 'update', 'destroy']);

    // Meal Plans
    Route::resource('meal-plans', MealPlanController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::get('meal-plans/{meal_plan}/pdf', [MealPlanController::class, 'downloadPdf'])->name('meal-plans.pdf');

    // Meals (podgląd / usuwanie konkretnego posiłku)
    Route::resource('meal-plans.meals', MealController::class)->shallow()->only(['index', 'destroy']);
});
```

### C. FormRequest classes

| Klasa                           | Używana w                           | Reguły walidacji (skrót)                                   |
| ------------------------------- | ----------------------------------- | ---------------------------------------------------------- |
| `StoreFamilyMemberRequest`      | `FamilyMemberController@store`      | `first_name`, `birth_date`, `gender`                       |
| `UpdateFamilyMemberRequest`     | `FamilyMemberController@update`     | jak wyżej                                                  |
| `StoreUnitRequest`              | `UnitController@store`              | `code`, `conversion_factor_to_base`                        |
| `StoreIngredientRequest`        | `IngredientController@store`        | `name`                                                     |
| `StoreRecipeRequest`            | `RecipeController@store`            | `name`, `category`, `instructions`, `calories`, `servings` |
| `UpdateRecipeRequest`           | `RecipeController@update`           | jw.                                                        |
| `StoreRecipeIngredientRequest`  | `RecipeIngredientController@store`  | `ingredient_id`, `quantity`, `unit_id`                     |
| `UpdateRecipeIngredientRequest` | `RecipeIngredientController@update` | jw.                                                        |
| `GenerateMealPlanRequest`       | `MealPlanController@store`          | `start_date:date`, opcjonalnie `regenerate:boolean`        |

### D. Policies / Gates

| Policy               | Akcje                                     | Reguła                                  |
| -------------------- | ----------------------------------------- | --------------------------------------- |
| `RecipePolicy`       | `view`, `create`, `update`, `delete`      | Użytkownik = owner przepisu             |
| `MealPlanPolicy`     | `view`, `update`, `delete`, `downloadPdf` | Owner + status!=processing dla `delete` |
| `FamilyMemberPolicy` | `viewAny`, `create`, `update`, `delete`   | Owner użytkownik                        |
| `IngredientPolicy`   | `manage`                                  | Tylko admin                             |
| `UnitPolicy`         | `manage`                                  | Tylko admin                             |

### E. Inertia Pages (Vue components)

| Komponent                 | Renderowany przez                   |
| ------------------------- | ----------------------------------- |
| `FamilyMembers/Index.vue` | `FamilyMemberController@index`      |
| `Recipes/Index.vue`       | `RecipeController@index`            |
| `Recipes/Show.vue`        | `RecipeController@show`             |
| `Recipes/Form.vue`        | `RecipeController@store` & `update` |
| `Ingredients/Index.vue`   | `IngredientController@index`        |
| `MealPlans/Index.vue`     | `MealPlanController@index`          |
| `MealPlans/Show.vue`      | `MealPlanController@show`           |
| `MealPlans/Generate.vue`  | `MealPlanController@store`          |
| `Units/Index.vue`         | `UnitController@index`              |

### F. Dodatkowa logika biznesowa

| Typ      | Nazwa                                               | Cel                                                       |
| -------- | --------------------------------------------------- | --------------------------------------------------------- |
| Service  | `MealPlanGeneratorService`                          | Losowy wybór przepisów, brak powtórzeń, 7 dni × 3 posiłki |
| Service  | `IngredientScalingService`                          | Skalowanie ilości wg kalorii, porcji i profilu rodziny    |
| Service  | `ShoppingListService`                               | Sumowanie i konwersja jednostek (g→kg, ml→l)              |
| Service  | `PdfExportService`                                  | Generacja pojedynczego PDF z jadłospisem i listą zakupów  |
| Job      | `GenerateMealPlanJob`                               | Kolejkowanie procesu generowania jadłospisu               |
| Job      | `GeneratePdfJob`                                    | Kolejkowanie renderu PDF                                  |
| Event    | `MealPlanGenerationStarted` / `Finished` / `Failed` | Monitoring postępu                                        |
| Listener | `UpdateMealPlanStatusListener`                      | Aktualizacja kolumny `status` + `generation_meta`         |

---

## 3. Bezpieczeństwo i wydajność

1. **Uwierzytelnianie**: Laravel Sanctum (SPA tokens) + session cookies; trasy `auth` middleware.
2. **Autoryzacja**: Policies + Gates; dodatkowo middleware `can:*` przy trasach admina.
3. **CSRF**: Domyślna ochrona Laravel + Inertia.
4. **Rate limiting / throttling**: `RateLimiter::for('api', 60 req / 1 min)` oraz per-route limit dla generacji PDF (np. 5/h).
5. **Caching**:
   * Redis jako `cache` i `queue` driver.
   * Cache listy jednostek i składników (`rememberForever`).
   * Cache wyników pełnotekstowego wyszukiwania przepisów (tag `recipes`).
6. **Kolejki**: Redis + `database` fallback; Jobs dla ciężkich zadań (AI, PDF).
7. **XSS / bezpieczeństwo danych**: Escapowanie w Vue (domyślne), `v-html` unikać; sanitizacja danych wejściowych.
8. **SQL Injection**: Eloquent ORM, parametryzowane zapytania.
9. **Backup i czyszczenie danych**: `prune` command usuwający stare pliki PDF.
10. **Monitoring**: Events + Laravel Telescope w środowisku dev; Sentry produkcyjnie.
