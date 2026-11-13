<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MealCategory;
use App\Models\Recipe;
use App\Models\User;
use App\Services\MealPlanGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * Re-runnable fixtures for the meal-plan Playwright spec: one planner user per browser project.
 */
class E2eMealPlanSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const EMAILS = [
        'e2e-planner-chromium@example.com',
        'e2e-planner-firefox@example.com',
    ];

    public const PASSWORD = 'Password123!';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('E2eMealPlanSeeder must not run in production.');
        }

        foreach (self::EMAILS as $email) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'E2E Planner',
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                ],
            );

            $user->mealPlans()->delete();

            foreach (MealCategory::values() as $category) {
                $missing = MealPlanGenerator::MEALS_PER_CATEGORY
                    - Recipe::where('user_id', $user->id)->where('category', $category)->count();

                if ($missing > 0) {
                    Recipe::factory()->count($missing)->create([
                        'user_id' => $user->id,
                        'category' => $category,
                    ]);
                }
            }

            RateLimiter::clear('generate-meal-plan:'.$user->id);
        }
    }
}
