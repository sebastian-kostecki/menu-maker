<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Recipe::class, 'recipe');
    }

    /**
     * Display a listing of the resource with filtering and search.
     */
    public function index(Request $request): Response
    {
        $query = Recipe::forUser(Auth::id());

        // Apply search filter
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Apply category filter
        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        // Apply sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderByField($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');

        $recipes = $query->paginate($request->get('per_page', 15));

        return Inertia::render('Recipes/Index', [
            'recipes' => RecipeResource::collection($recipes),
            'filters' => $request->only(['search', 'category', 'sort', 'direction']),
            'categories' => [
                ['value' => 'breakfast', 'label' => 'Breakfast'],
                ['value' => 'supper', 'label' => 'Supper'],
                ['value' => 'dinner', 'label' => 'Dinner'],
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Recipes/Form', [
            'recipe' => null,
            'categories' => [
                ['value' => 'breakfast', 'label' => 'Breakfast'],
                ['value' => 'lunch', 'label' => 'Lunch'],
                ['value' => 'dinner', 'label' => 'Dinner'],
            ],
            'ingredients' => Ingredient::orderBy('name')->get(['id', 'name']),
            'units' => Unit::orderBy('code')->get(['id', 'code']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRecipeRequest $request): RedirectResponse
    {
        $recipe = DB::transaction(function () use ($request) {
            $validatedData = $request->validated();

            // Remove ingredients from recipe data
            $ingredients = $validatedData['ingredients'] ?? [];
            unset($validatedData['ingredients']);

            // Create recipe
            $recipe = Recipe::create([
                ...$validatedData,
                'user_id' => Auth::id(),
            ]);

            // Sync ingredients if provided
            if (!empty($ingredients)) {
                $this->syncRecipeIngredients($recipe, $ingredients);
            }

            return $recipe;
        });

        return redirect()->route('recipes.index')
            ->with('success', 'Recipe created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Recipe $recipe): Response
    {
        $recipe->load(['recipeIngredients.ingredient', 'recipeIngredients.unit']);

        return Inertia::render('Recipes/Show', [
            'recipe' => new RecipeResource($recipe),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Recipe $recipe): Response
    {
        $recipe->load(['recipeIngredients.ingredient', 'recipeIngredients.unit']);

        return Inertia::render('Recipes/Form', [
            'recipe' => new RecipeResource($recipe),
            'categories' => [
                ['value' => 'breakfast', 'label' => 'Breakfast'],
                ['value' => 'lunch', 'label' => 'Lunch'],
                ['value' => 'dinner', 'label' => 'Dinner'],
            ],
            'ingredients' => Ingredient::orderBy('name')->get(['id', 'name']),
            'units' => Unit::orderBy('code')->get(['id', 'code']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRecipeRequest $request, Recipe $recipe): RedirectResponse
    {
        DB::transaction(function () use ($request, $recipe) {
            $validatedData = $request->validated();

            // Remove ingredients from recipe data
            $ingredients = $validatedData['ingredients'] ?? null;
            unset($validatedData['ingredients']);

            // Update recipe
            $recipe->update($validatedData);

            // Sync ingredients if provided
            if (isset($ingredients)) {
                $this->syncRecipeIngredients($recipe, $ingredients);
            }
        });

        return redirect()->route('recipes.show', $recipe)
            ->with('success', 'Recipe updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Recipe $recipe): RedirectResponse
    {
        $recipe->delete();

        return redirect()->route('recipes.index')
            ->with('success', 'Recipe deleted successfully.');
    }

    /**
     * Sync recipe ingredients with the provided data.
     *
     * @param Recipe $recipe
     * @param array $ingredients
     * @return void
     */
    private function syncRecipeIngredients(Recipe $recipe, array $ingredients): void
    {
        $pivotData = collect($ingredients)->mapWithKeys(function ($ingredient) {
            return [$ingredient['ingredient_id'] => [
                'quantity' => $ingredient['quantity'],
                'unit_id' => $ingredient['unit_id'],
            ]];
        });

        $recipe->ingredients()->sync($pivotData);
    }
}
