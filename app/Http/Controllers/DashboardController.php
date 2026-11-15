<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\MealPlanResource;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Services\MealPlanGenerator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Render the dashboard with owner-scoped counts, the newest ready plan and the recipe shortfall.
     *
     * Props:
     * - stats: array{recipes: int, mealPlans: int}
     * - latestMealPlan: array<string, mixed>|null
     * - recipeShortfall: list<string>
     */
    public function __invoke(Request $request, MealPlanGenerator $generator): Response
    {
        $user = $request->user();

        $latestMealPlan = MealPlan::ownedBy($user)
            ->where('status', 'done')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        return Inertia::render('Dashboard', [
            'stats' => [
                'recipes' => Recipe::forUser($user->id)->count(),
                'mealPlans' => MealPlan::ownedBy($user)->count(),
            ],
            'latestMealPlan' => $latestMealPlan === null
                ? null
                : (new MealPlanResource($latestMealPlan))->resolve(),
            'recipeShortfall' => $generator->shortfallMessages(
                $generator->missingRecipesPerCategory($user->id)
            ),
        ]);
    }
}
