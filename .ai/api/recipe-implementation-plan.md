# RecipeController Implementation Plan

## Overview

Implementacja kontrolera `RecipeController` jako resource controller dla zarządzania przepisami w aplikacji Menu Maker. Kontroler obsługuje pełny CRUD cycle dla przepisów należących do zalogowanego użytkownika z relacjami N-N do składników.

**Resource Routes:**
- `GET /recipes` - lista przepisów użytkownika
- `GET /recipes/{recipe}` - szczegóły przepisu
- `POST /recipes` - tworzenie nowego przepisu
- `PUT/PATCH /recipes/{recipe}` - aktualizacja przepisu
- `DELETE /recipes/{recipe}` - usunięcie przepisu

**Model Relations:**
- `Recipe` belongsTo `User` (owner)
- `Recipe` belongsToMany `Ingredient` (via `recipe_ingredients` pivot)

## Request

### Route Parameters
- `{recipe}` - ID przepisu (route model binding)

### Request Classes
1. **StoreRecipeRequest**
   ```php
   'name' => 'required|string|max:255',
   'category' => 'required|in:breakfast,lunch,dinner',
   'instructions' => 'required|string',
   'calories' => 'required|numeric|min:0',
   'servings' => 'required|integer|min:1',
   'ingredients' => 'sometimes|array',
   'ingredients.*.ingredient_id' => 'required|exists:ingredients,id',
   'ingredients.*.quantity' => 'required|numeric|min:0.01',
   'ingredients.*.unit_id' => 'required|exists:units,id'
   ```

2. **UpdateRecipeRequest**
   - Identyczne reguły walidacji jak `StoreRecipeRequest`
   - Wszystkie pola opcjonalne podczas `PATCH`

### Query Parameters (index)
- `search` - wyszukiwanie po nazwie/instrukcjach
- `category` - filtrowanie po kategorii
- `per_page` - liczba elementów na stronę (default: 15)
- `sort` - sortowanie (name, created_at, calories)
- `direction` - kierunek sortowania (asc, desc)

## Response

### Resource Classes
1. **RecipeResource** (pojedynczy przepis)
   ```php
   'id' => $this->id,
   'name' => $this->name,
   'category' => $this->category,
   'instructions' => $this->instructions,
   'calories' => $this->calories,
   'servings' => $this->servings,
   'created_at' => $this->created_at,
   'updated_at' => $this->updated_at,
   'ingredients' => RecipeIngredientResource::collection($this->whenLoaded('recipeIngredients'))
   ```

2. **RecipeIngredientResource** (składnik w przepisie)
   ```php
   'ingredient_id' => $this->ingredient_id,
   'ingredient_name' => $this->ingredient->name,
   'quantity' => $this->quantity,
   'unit_id' => $this->unit_id,
   'unit_code' => $this->unit->code
   ```

### Response Formats
- **index**: `RecipeResource::collection()` + pagination
- **show**: `RecipeResource` z loaded relations
- **store/update**: `RecipeResource` ze statusem 201/200
- **destroy**: status 204 (No Content)

## Flow

### 1. Index Method
```php
public function index(Request $request): Response
{
    $recipes = Recipe::where('user_id', auth()->id())
        ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
        ->when($request->category, fn($q) => $q->where('category', $request->category))
        ->orderBy($request->sort ?? 'created_at', $request->direction ?? 'desc')
        ->paginate($request->per_page ?? 15);

    return Inertia::render('Recipes/Index', [
        'recipes' => RecipeResource::collection($recipes),
        'filters' => $request->only(['search', 'category']),
        'categories' => ['breakfast', 'lunch', 'dinner']
    ]);
}
```

### 2. Show Method
```php
public function show(Recipe $recipe): Response
{
    $this->authorize('view', $recipe);
    
    $recipe->load(['recipeIngredients.ingredient', 'recipeIngredients.unit']);
    
    return Inertia::render('Recipes/Show', [
        'recipe' => new RecipeResource($recipe)
    ]);
}
```

### 3. Store Method
```php
public function store(StoreRecipeRequest $request): RedirectResponse
{
    DB::transaction(function () use ($request) {
        $recipe = Recipe::create([
            ...$request->validated(),
            'user_id' => auth()->id()
        ]);
        
        if ($request->has('ingredients')) {
            $this->syncRecipeIngredients($recipe, $request->ingredients);
        }
        
        return $recipe;
    });
    
    return redirect()->route('recipes.index')
        ->with('success', 'Recipe created successfully.');
}
```

### 4. Update Method
```php
public function update(UpdateRecipeRequest $request, Recipe $recipe): RedirectResponse
{
    $this->authorize('update', $recipe);
    
    DB::transaction(function () use ($request, $recipe) {
        $recipe->update($request->validated());
        
        if ($request->has('ingredients')) {
            $this->syncRecipeIngredients($recipe, $request->ingredients);
        }
    });
    
    return redirect()->route('recipes.show', $recipe)
        ->with('success', 'Recipe updated successfully.');
}
```

### 5. Destroy Method
```php
public function destroy(Recipe $recipe): RedirectResponse
{
    $this->authorize('delete', $recipe);
    
    $recipe->delete(); // Soft delete recommended
    
    return redirect()->route('recipes.index')
        ->with('success', 'Recipe deleted successfully.');
}
```

### Helper Methods
```php
private function syncRecipeIngredients(Recipe $recipe, array $ingredients): void
{
    $pivotData = collect($ingredients)->mapWithKeys(function ($ingredient) {
        return [$ingredient['ingredient_id'] => [
            'quantity' => $ingredient['quantity'],
            'unit_id' => $ingredient['unit_id']
        ]];
    });
    
    $recipe->ingredients()->sync($pivotData);
}
```

## Security

### Authorization (RecipePolicy)
```php
class RecipePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // User can view their own recipes
    }
    
    public function view(User $user, Recipe $recipe): bool
    {
        return $user->id === $recipe->user_id;
    }
    
    public function create(User $user): bool
    {
        return true; // Any authenticated user can create
    }
    
    public function update(User $user, Recipe $recipe): bool
    {
        return $user->id === $recipe->user_id;
    }
    
    public function delete(User $user, Recipe $recipe): bool
    {
        return $user->id === $recipe->user_id;
    }
}
```

### Middleware Stack
- `auth` - wymagane uwierzytelnienie
- `verified` - weryfikacja email
- Route model binding z automatic policy checks

### Data Protection
- **Mass Assignment**: fillable fields w modelu Recipe
- **XSS Protection**: automatyczne escapowanie w Vue templates
- **CSRF Protection**: automatyczna ochrona Inertia.js
- **SQL Injection**: Eloquent ORM queries
- **File Uploads**: brak w tym kontrolerze (przyszłe zdjęcia przepisów)

## Errors

### Validation Errors (422)
- Nieprawidłowe dane w FormRequest
- Automatyczne przekierowanie z błędami walidacji
- Frontend wyświetla błędy pod polami formularza

### Authorization Errors (403)
- Próba dostępu do cudzego przepisu
- Automatyczne przekierowanie na 403 page

### Not Found (404)
- Nieistniejący recipe ID
- Route model binding automatycznie rzuca ModelNotFoundException

### Server Errors (500)
- Database transaction failures
- Unexpected exceptions
- Logowanie do Laravel logs + Sentry (production)

### Business Logic Errors
- Próba przypisania nieistniejącego składnika
- Nieprawidłowe jednostki miary
- Duplikaty składników w przepisie (validation level)

## Steps

### 1. Model Setup
- [x] Recipe model z relationships
- [ ] Recipe migration with proper indexes
- [ ] Model factories for testing
- [ ] Soft deletes configuration

### 2. Request Classes
- [ ] Create `StoreRecipeRequest`
- [ ] Create `UpdateRecipeRequest`
- [ ] Implement custom validation rules
- [ ] Add authorization methods

### 3. Policy Implementation
- [ ] Create `RecipePolicy`
- [ ] Register policy in `AuthServiceProvider`
- [ ] Test authorization scenarios

### 4. Resource Classes
- [ ] Create `RecipeResource`
- [ ] Create `RecipeIngredientResource`
- [ ] Optimize eager loading
- [ ] Add conditional fields

### 5. Controller Implementation
- [ ] Implement `index` method with filtering/search
- [ ] Implement `show` method with relationships
- [ ] Implement `store` method with transaction
- [ ] Implement `update` method with ingredient sync
- [ ] Implement `destroy` method with policy check
- [ ] Add helper methods for ingredient management

### 6. Route Registration
- [ ] Register resource routes
- [ ] Add route model binding
- [ ] Configure middleware stack
- [ ] Set up route caching

### 7. Frontend Integration
- [ ] Create Vue components (Index, Show, Form)
- [ ] Implement Inertia.js pages
- [ ] Add form validation on frontend
- [ ] Implement search and filtering UI
- [ ] Add loading states and error handling

### 8. Testing
- [ ] Feature tests for all CRUD operations
- [ ] Policy authorization tests
- [ ] Form request validation tests
- [ ] Integration tests with ingredients
- [ ] Performance tests for large datasets

### 9. Performance Optimization
- [ ] Database indexes on commonly queried fields
- [ ] Eager loading optimization
- [ ] Query caching for static data
- [ ] Pagination optimization
- [ ] API response caching (Redis)

### 10. Documentation
- [ ] API documentation
- [ ] Frontend component documentation
- [ ] Database relationships documentation
- [ ] Deployment considerations
