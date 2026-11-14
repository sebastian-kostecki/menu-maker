<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamilyMember>
 */
class FamilyMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * An adult by default, so the default row satisfies the weight and height requirement.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => $this->faker->firstName(),
            'birth_date' => $this->faker->dateTimeBetween('-60 years', '-25 years')->format('Y-m-d'),
            'gender' => $this->faker->randomElement(['male', 'female']),
            ...$this->normalBodyData(),
        ];
    }

    /**
     * An adult aged 25-60 with weight and height inside the normal BMI range.
     */
    public function adult(): static
    {
        return $this->state(fn (): array => [
            'birth_date' => $this->faker->dateTimeBetween('-60 years', '-25 years')->format('Y-m-d'),
            ...$this->normalBodyData(),
        ]);
    }

    /**
     * A child aged 2-15 with no body data.
     */
    public function child(): static
    {
        return $this->state(fn (): array => [
            'birth_date' => $this->faker->dateTimeBetween('-15 years', '-2 years')->format('Y-m-d'),
            'weight_kg' => null,
            'height_cm' => null,
        ]);
    }

    /**
     * Height 150-195 cm with a weight at BMI 20-24.
     *
     * @return array{weight_kg: string, height_cm: string}
     */
    private function normalBodyData(): array
    {
        $height = $this->faker->numberBetween(150, 195);
        $bmi = $this->faker->randomFloat(1, 20, 24);

        return [
            'weight_kg' => number_format($bmi * ($height / 100) ** 2, 1, '.', ''),
            'height_cm' => number_format($height, 1, '.', ''),
        ];
    }
}
