<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MealPlan>
 */
class MealPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $dateCounter = 0;
        $startDate = now()->addDays($dateCounter++)->startOfDay();
        $endDate = $startDate->copy()->addDays(6);

        return [
            'user_id' => User::factory(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $this->faker->randomElement(['pending', 'processing', 'done', 'error']),
            'generation_meta' => null,
            'pdf_path' => null,
            'pdf_size' => null,
        ];
    }

    /**
     * Create a pending meal plan.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'generation_meta' => null,
        ]);
    }

    /**
     * Create a processing meal plan.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'generation_meta' => [
                'started_at' => now(),
                'progress' => $this->faker->numberBetween(10, 90),
            ],
        ]);
    }

    /**
     * Create a completed meal plan.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'done',
            'generation_meta' => [
                'generated_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
                'total_recipes' => 21, // 3 meals x 7 days
                'total_calories' => $this->faker->numberBetween(10000, 15000),
            ],
            'pdf_path' => 'meal-plans/'.$this->faker->uuid().'.pdf',
            'pdf_size' => $this->faker->numberBetween(500000, 2000000), // 500KB - 2MB
        ]);
    }

    /**
     * Create a failed meal plan.
     */
    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'error',
            'generation_meta' => [
                'failed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
                'error_message' => $this->faker->randomElement([
                    'Insufficient recipes available',
                    'PDF generation failed',
                    'Network timeout',
                    'Database connection lost',
                ]),
            ],
        ]);
    }

    /**
     * Create a meal plan starting today.
     */
    public function startingToday(): static
    {
        $startDate = now()->startOfDay();
        $endDate = $startDate->copy()->addDays(6);

        return $this->state(fn (array $attributes) => [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Create a meal plan starting next week.
     */
    public function startingNextWeek(): static
    {
        $startDate = now()->addWeek()->startOfWeek();
        $endDate = $startDate->copy()->addDays(6);

        return $this->state(fn (array $attributes) => [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Create a meal plan with PDF.
     */
    public function withPdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'pdf_path' => 'meal-plans/'.$this->faker->uuid().'.pdf',
            'pdf_size' => $this->faker->numberBetween(500000, 2000000),
        ]);
    }
}
