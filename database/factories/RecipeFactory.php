<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Recipe>
 */
class RecipeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = ['breakfast', 'supper', 'dinner'];

        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true),
            'category' => $this->faker->randomElement($categories),
            'instructions' => $this->faker->paragraphs(3, true),
            'calories' => $this->faker->numberBetween(100, 800),
            'servings' => $this->faker->numberBetween(1, 8),
        ];
    }

    /**
     * Create a breakfast recipe.
     */
    public function breakfast(): static
    {
        return $this->state(fn(array $attributes) => [
            'category' => 'breakfast',
            'calories' => $this->faker->numberBetween(200, 500),
        ]);
    }

    /**
     * Create a supper recipe.
     */
    public function supper(): static
    {
        return $this->state(fn(array $attributes) => [
            'category' => 'supper',
            'calories' => $this->faker->numberBetween(300, 600),
        ]);
    }

    /**
     * Create a dinner recipe.
     */
    public function dinner(): static
    {
        return $this->state(fn(array $attributes) => [
            'category' => 'dinner',
            'calories' => $this->faker->numberBetween(400, 800),
        ]);
    }

    /**
     * Create a low-calorie recipe.
     */
    public function lowCalorie(): static
    {
        return $this->state(fn(array $attributes) => [
            'calories' => $this->faker->numberBetween(100, 300),
        ]);
    }

    /**
     * Create a high-calorie recipe.
     */
    public function highCalorie(): static
    {
        return $this->state(fn(array $attributes) => [
            'calories' => $this->faker->numberBetween(600, 1000),
        ]);
    }
}
