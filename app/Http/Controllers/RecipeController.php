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
use Illuminate\Database\Eloquent\Builder;
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

        // Faceted counts: respect the search but ignore the selected category.
        $categoryCounts = $this->categoryCounts($query);

        // Apply category filter; an unknown value filters nothing.
        $rawCategory = $request->input('category');
        $category = is_string($rawCategory) ? MealCategory::tryFrom($rawCategory) : null;
        if ($category !== null) {
            $query->byCategory($category->value);
        }

        // Apply sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderByField($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');

        $perPage = min(max((int) $request->get('per_page', 12), 5), 100);
        $recipes = $query->paginate($perPage);

        return Inertia::render('Recipes/Index', [
            'recipes' => RecipeResource::collection($recipes),
            'categoryCounts' => $categoryCounts,
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

        return redirect()->to($this->recipesIndexReturnUrl())
            ->with('success', 'Przepis został usunięty.');
    }

    /**
     * The list URL to return to after a delete: the filtered list the request came from
     * (without `page`, which may no longer exist), otherwise the plain index.
     */
    private function recipesIndexReturnUrl(): string
    {
        $indexUrl = route('recipes.index');
        $previous = url()->previous();

        if (! str_starts_with($previous, $indexUrl.'?')) {
            return $indexUrl;
        }

        parse_str((string) parse_url($previous, PHP_URL_QUERY), $query);
        unset($query['page']);

        return route('recipes.index', $query);
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
     * Recipe counts per category for the given (owner-scoped, optionally searched) query, plus their sum.
     *
     * @param  Builder<Recipe>  $query
     * @return array{all: int, breakfast: int, supper: int, dinner: int}
     */
    private function categoryCounts(Builder $query): array
    {
        /** @var array<string, int> $grouped */
        $grouped = (clone $query)
            ->toBase()
            ->select('category', DB::raw('count(*) as aggregate'))
            ->groupBy('category')
            ->pluck('aggregate', 'category')
            ->map(fn ($count): int => (int) $count)
            ->all();

        $breakfast = $grouped[MealCategory::Breakfast->value] ?? 0;
        $supper = $grouped[MealCategory::Supper->value] ?? 0;
        $dinner = $grouped[MealCategory::Dinner->value] ?? 0;

        return [
            'all' => $breakfast + $supper + $dinner,
            'breakfast' => $breakfast,
            'supper' => $supper,
            'dinner' => $dinner,
        ];
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
