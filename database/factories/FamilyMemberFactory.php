<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FamilyMember>
 */
class FamilyMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'first_name' => $this->faker->firstName(),
            'birth_date' => $this->faker->dateTimeBetween('-80 years', '-1 year')->format('Y-m-d'),
            'gender' => $this->faker->randomElement(['male', 'female']),
        ];
    }
}
