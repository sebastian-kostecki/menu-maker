<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\MealPlan
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
            'pdf_path' => $this->pdf_path,
            'pdf_size' => $this->pdf_size,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'meals' => $this->whenLoaded('meals', function () {
                /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Meal> $meals */
                $meals = $this->meals;

                return $meals->sortBy('meal_date')->map(function (\App\Models\Meal $meal) {
                    return [
                        'id' => $meal->id,
                        'meal_date' => $meal->meal_date->format('Y-m-d'),
                        'meal_category' => $meal->meal_category,
                        'recipe' => $this->when($meal->relationLoaded('recipe'), function () use ($meal) {
                            return [
                                'id' => $meal->recipe->id,
                                'name' => $meal->recipe->name,
                                'category' => $meal->recipe->category,
                                'calories' => $meal->recipe->calories,
                                'servings' => $meal->recipe->servings,
                                'ingredients' => $this->when($meal->recipe->relationLoaded('recipeIngredients'), function () use ($meal) {
                                    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\RecipeIngredient> $ingredients */
                                    $ingredients = $meal->recipe->recipeIngredients;
                                    return $ingredients->map(function (\App\Models\RecipeIngredient $recipeIngredient) {
                                        return [
                                            'id' => $recipeIngredient->ingredient->id ?? null,
                                            'name' => $recipeIngredient->ingredient->name ?? 'Unknown ingredient',
                                            'quantity' => $recipeIngredient->quantity ?? 0,
                                            'unit' => [
                                                'id' => $recipeIngredient->unit->id ?? null,
                                                'code' => $recipeIngredient->unit->code ?? 'pcs',
                                            ],
                                        ];
                                    });
                                }, []),
                            ];
                        }),
                    ];
                });
            }, []),

            'logs' => $this->whenLoaded('logs', function () {
                /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\LogsMealPlan> $logs */
                $logs = $this->logs;
                return $logs
                    ->sortByDesc('created_at')
                    ->map(function (\App\Models\LogsMealPlan $log): array {
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
            ],
        ];
    }
}
