<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MealPlanCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = MealPlanResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($mealPlan) {
                return [
                    'id' => $mealPlan->id,
                    'start_date' => $mealPlan->start_date->format('Y-m-d'),
                    'end_date' => $mealPlan->end_date->format('Y-m-d'),
                    'status' => $mealPlan->status,
                    'created_at' => $mealPlan->created_at,
                    'updated_at' => $mealPlan->updated_at,

                    // Counts for performance
                    'meals_count' => $mealPlan->meals_count ?? 0,
                    'logs_count' => $mealPlan->logs_count ?? 0,

                    // Links
                    'links' => [
                        'self' => route('meal-plans.show', $mealPlan->id),
                    ],
                ];
            }),

            'meta' => [
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'per_page' => $this->perPage(),
                'total' => $this->total(),
                'from' => $this->firstItem(),
                'to' => $this->lastItem(),
            ],

            'links' => [
                'first' => $this->url(1),
                'last' => $this->url($this->lastPage()),
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl(),
            ],
        ];
    }
}
