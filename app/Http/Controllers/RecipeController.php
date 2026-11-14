<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MealCategory;
use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            'categories' => $this->categoryOptions(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Recipes/Form', [
            'recipe' => null,
            'categories' => $this->categoryOptions(),
            'ingredients' => Ingredient::suggestedFor((int) Auth::id())->get(['ingredients.id', 'ingredients.name']),
            'units' => $this->unitOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRecipeRequest $request): RedirectResponse
    {
        $recipe = DB::transaction(function () use ($request) {
            $validatedData = $request->validated();

            $ingredients = $validatedData['ingredients'];
            unset($validatedData['ingredients']);

            $recipe = $request->user()->recipes()->create($validatedData);

            $this->syncRecipeIngredients($recipe, $ingredients);

            return $recipe;
        });

        return redirect()->route('recipes.index')
            ->with('success', 'Przepis został dodany.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Recipe $recipe): Response
    {
        $recipe->load(['recipeIngredients.ingredient', 'recipeIngredients.unit']);

        return Inertia::render('Recipes/Show', [
            'recipe' => (new RecipeResource($recipe))->resolve(),
            'canEdit' => $request->user()->can('update', $recipe),
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
            'categories' => $this->categoryOptions(),
            'ingredients' => Ingredient::suggestedFor((int) Auth::id())->get(['ingredients.id', 'ingredients.name']),
            'units' => $this->unitOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRecipeRequest $request, Recipe $recipe): RedirectResponse
    {
        DB::transaction(function () use ($request, $recipe) {
            $validatedData = $request->validated();

            $ingredients = $validatedData['ingredients'];
            unset($validatedData['ingredients']);

            $recipe->update($validatedData);

            $this->syncRecipeIngredients($recipe, $ingredients);
        });

        return redirect()->route('recipes.show', $recipe)
            ->with('success', 'Przepis został zaktualizowany.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Recipe $recipe): RedirectResponse
    {
        $recipe->delete();

        return redirect()->route('recipes.index')
            ->with('success', 'Przepis został usunięty.');
    }

    /**
     * Sync recipe ingredients, resolving each row's name to an ingredient id.
     *
     * @param  list<array{name: string, quantity: numeric-string|int|float, unit_id: int|string}>  $ingredients
     */
    private function syncRecipeIngredients(Recipe $recipe, array $ingredients): void
    {
        $pivotData = collect($ingredients)->mapWithKeys(function (array $ingredient): array {
            return [Ingredient::resolveIdByName($ingredient['name']) => [
                'quantity' => $ingredient['quantity'],
                'unit_id' => $ingredient['unit_id'],
            ]];
        });

        $recipe->ingredients()->sync($pivotData);
    }

    /**
     * The five allowed units in display order.
     *
     * @return Collection<int, Unit>
     */
    private function unitOptions(): Collection
    {
        return Unit::whereIn('code', Unit::ALLOWED_CODES)
            ->get(['id', 'code'])
            ->sortBy(fn (Unit $unit): int => (int) array_search($unit->code, Unit::ALLOWED_CODES, true))
            ->values();
    }

    /**
     * Category options with Polish labels, in display order.
     *
     * @return list<array{value: string, label: string}>
     */
    private function categoryOptions(): array
    {
        return array_map(
            fn (MealCategory $category): array => [
                'value' => $category->value,
                'label' => $category->label(),
            ],
            MealCategory::cases(),
        );
    }
}
