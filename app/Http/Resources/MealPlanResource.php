<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\MealCategory;
use App\Models\LogsMealPlan;
use App\Models\Meal;
use App\Models\MealIngredient;
use App\Models\MealPlan;
use App\Services\IngredientScaling\ScalingNotices;
use App\Services\ShoppingList\QuantityFormatter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MealPlan
 */
class MealPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),
            'status' => $this->status,
            'generation_meta' => $this->generation_meta,
            'scaling' => ScalingNotices::for($this->resource),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'meals' => $this->whenLoaded('meals', function () {
                /** @var Collection<int, Meal> $meals */
                $meals = $this->meals;

                return $meals->sortBy([
                    fn (Meal $a, Meal $b): int => $a->meal_date <=> $b->meal_date,
                    fn (Meal $a, Meal $b): int => MealCategory::displayIndex($a->meal_category)
                        <=> MealCategory::displayIndex($b->meal_category),
                ])->values()->map(function (Meal $meal) {
                    return [
                        'id' => $meal->id,
                        'meal_date' => $meal->meal_date->format('Y-m-d'),
                        'meal_category' => $meal->meal_category,
                        'recipe' => $this->when($meal->relationLoaded('recipe') && $meal->recipe !== null, function () use ($meal) {
                            return [
                                'deleted' => $meal->recipe->trashed(),
                                'id' => $meal->recipe->id,
                                'name' => $meal->recipe->name,
                                'category' => $meal->recipe->category,
                                'calories' => $meal->recipe->calories,
                                'servings' => $meal->recipe->servings,
                            ];
                        }),
                        'ingredients' => $this->when($meal->relationLoaded('ingredients'), function () use ($meal) {
                            return $meal->ingredients
                                ->filter(fn (MealIngredient $mealIngredient): bool => bccomp((string) $mealIngredient->quantity, '0', 2) !== 0)
                                ->map(fn (MealIngredient $mealIngredient): array => [
                                    'ingredient_id' => $mealIngredient->ingredient_id,
                                    'name' => $mealIngredient->ingredient->name ?? '',
                                    'quantity' => (string) $mealIngredient->quantity,
                                    'unit' => ['code' => $mealIngredient->unit->code ?? ''],
                                    'display' => (new QuantityFormatter)->format((string) $mealIngredient->quantity, $mealIngredient->unit->code ?? ''),
                                    'source' => $mealIngredient->source,
                                ])
                                ->values()
                                ->all();
                        }),
                    ];
                });
            }, []),

            'logs' => $this->whenLoaded('logs', function () {
                /** @var Collection<int, LogsMealPlan> $logs */
                $logs = $this->logs;

                return $logs
                    ->sortByDesc('created_at')
                    ->map(function (LogsMealPlan $log): array {
                        return [
                            'id' => $log->id,
                            'started_at' => $log->started_at,
                            'finished_at' => $log->finished_at,
                            'status' => $log->status,
                            'created_at' => $log->created_at,
                        ];
                    })
                    ->values()
                    ->all();
            }, []),

            // Helper attributes
            'meals_count' => $this->whenCounted('meals'),
            'logs_count' => $this->whenCounted('logs'),

            // Links
            'links' => [
                'self' => route('meal-plans.show', $this->id),
                'pdf' => $this->status === 'done' ? route('meal-plans.pdf', $this->id) : null,
            ],
        ];
    }
}
