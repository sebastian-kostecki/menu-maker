<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling;

use App\Models\FamilyMember;
use App\Models\Meal;
use App\Models\MealIngredient;
use App\Models\MealPlan;
use Collator;
use Illuminate\Support\Facades\DB;

/**
 * Writes the per-meal ingredient snapshot of a plan: each recipe ingredient scaled to the family's energy needs.
 */
class MealIngredientScaler
{
    public function __construct(
        private readonly EnergyNorms $energyNorms,
        private readonly ScalingCalculator $calculator,
    ) {}

    public function scale(MealPlan $mealPlan): ScalingOutcome
    {
        $mealPlan->load('meals.recipe.recipeIngredients');

        $members = FamilyMember::query()->where('user_id', $mealPlan->user_id)->orderBy('id')->get();
        $hasFamily = $members->isNotEmpty();

        $familyDailyKcal = '0';
        $membersWithoutBody = [];

        foreach ($members as $member) {
            $weight = $member->weight_kg === null ? null : (string) $member->weight_kg;
            $height = $member->height_cm === null ? null : (string) $member->height_cm;

            $familyDailyKcal = bcadd(
                $familyDailyKcal,
                $this->energyNorms->dailyKcal($member->gender, $member->birth_date, $mealPlan->start_date, $weight, $height),
                2,
            );

            if ($this->energyNorms->usesReferenceRow($member->birth_date, $mealPlan->start_date, $weight, $height)) {
                $membersWithoutBody[] = $member->first_name;
            }
        }

        $unscaledRecipes = [];
        $rows = [];
        $now = now();

        /** @var Meal $meal */
        foreach ($mealPlan->meals as $meal) {
            $recipe = $meal->recipe;

            if ($recipe === null) {
                continue;
            }

            $factor = $hasFamily
                ? $this->calculator->factor($familyDailyKcal, $meal->meal_category, (string) $recipe->calories)
                : null;

            if ($hasFamily && $factor === null) {
                $unscaledRecipes[$recipe->id] = $recipe->name;
            }

            foreach ($recipe->recipeIngredients->sortBy('id') as $recipeIngredient) {
                $recipeQuantity = (string) $recipeIngredient->quantity;
                $computed = $factor === null ? $recipeQuantity : $this->calculator->scale($recipeQuantity, $factor);

                $rows[] = [
                    'meal_id' => $meal->id,
                    'ingredient_id' => $recipeIngredient->ingredient_id,
                    'unit_id' => $recipeIngredient->unit_id,
                    'recipe_quantity' => $recipeQuantity,
                    'computed_quantity' => $computed,
                    'quantity' => $computed,
                    'source' => $factor === null ? MealIngredient::SOURCE_AS_WRITTEN : MealIngredient::SOURCE_COMPUTED,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::transaction(function () use ($mealPlan, $rows): void {
            MealIngredient::query()
                ->whereIn('meal_id', $mealPlan->meals->pluck('id'))
                ->delete();

            foreach (array_chunk($rows, 500) as $chunk) {
                MealIngredient::query()->insert($chunk);
            }
        });

        $names = array_values($unscaledRecipes);
        (new Collator('pl_PL'))->sort($names);

        return new ScalingOutcome(
            $hasFamily ? ScalingOutcome::STATUS_COMPUTED : ScalingOutcome::STATUS_AS_WRITTEN,
            $names,
            $membersWithoutBody,
        );
    }
}
