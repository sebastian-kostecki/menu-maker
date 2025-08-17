<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;

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
        /** @var LengthAwarePaginator|PaginatorContract|null $paginator */
        $paginator = $this->resource instanceof \Illuminate\Support\Collection ? null : $this->resource;

        $meta = [];
        $links = [];

        if ($paginator instanceof LengthAwarePaginator) {
            $meta = [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ];

            $links = [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ];
        } elseif ($paginator instanceof PaginatorContract) {
            $from = null;
            $to = null;

            if ($this->collection->isNotEmpty()) {
                $to = $paginator->currentPage() * $paginator->perPage();
                $from = $to - $this->collection->count() + 1;
            }

            $meta = [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'from' => $from,
                'to' => $to,
            ];

            $links = [
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ];
        }

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

            'meta' => $meta,

            'links' => $links,
        ];
    }
}
