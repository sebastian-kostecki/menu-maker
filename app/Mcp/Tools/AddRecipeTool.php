<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\MealCategory;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use App\Services\RecipeImport\StructuredRecipeImporter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Saves one new recipe to the signed-in user\'s recipe book and returns it with its link. `calories` is the total kcal of the whole recipe (at least 50), not per serving. Ingredient units are only g, kg, ml, l and szt, and each ingredient appears once. `instructions` holds one step per line. A name the user already has (ignoring case and spacing) is refused with the link to that recipe. Show the user a preview and get their approval before calling.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(false)]
class AddRecipeTool extends Tool
{
    /**
     * @var array<string, mixed>|null
     */
    protected ?array $meta = ['anthropic/requiresUserInteraction' => true];

    public function __construct(private readonly StructuredRecipeImporter $importer) {}

    public function handle(Request $request): ResponseFactory
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('No user is signed in.');
        }

        Gate::forUser($user)->authorize('create', Recipe::class);

        $recipe = $this->importer->import($user, $request->all());

        return Response::structured([
            'recipe' => [
                'id' => $recipe->id,
                'name' => $recipe->name,
                'category' => $recipe->category,
                'category_label' => MealCategory::from($recipe->category)->label(),
                'calories' => (float) $recipe->calories,
                'servings' => $recipe->servings,
                'ingredient_count' => $recipe->ingredients()->count(),
                'url' => route('recipes.show', $recipe),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->max(255)->required()->description('Recipe name, in Polish.'),
            'category' => $schema->string()->enum(MealCategory::values())->required()->description('breakfast = śniadanie, supper = obiad, dinner = kolacja.'),
            'calories' => $schema->number()->min(50)->max(99999999.99)->required()->description('Total kcal of the whole recipe, not per serving.'),
            'servings' => $schema->integer()->min(1)->required()->description('Number of servings the recipe makes.'),
            'instructions' => $schema->string()->required()->description('Preparation steps, one per line.'),
            'ingredients' => $schema->array()->items($schema->object([
                'name' => $schema->string()->required()->description('Ingredient name, in Polish; each ingredient once.'),
                'quantity' => $schema->number()->min(0.01)->required()->description('Amount in the given unit.'),
                'unit' => $schema->string()->enum(Unit::ALLOWED_CODES)->required()->description('szt for countable items.'),
            ]))->min(1)->required(),
            'source_url' => $schema->string()->format('uri')->description('Address of the page the recipe comes from (http or https), optional.'),
        ];
    }
}
