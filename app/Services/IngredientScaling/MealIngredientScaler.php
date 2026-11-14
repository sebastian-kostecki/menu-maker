<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling;

use App\Models\FamilyMember;
use App\Models\Meal;
use App\Models\MealIngredient;
use App\Models\MealPlan;
use Collator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

/**
 * Writes the per-meal ingredient snapshot of a plan: each recipe ingredient scaled to the family's energy needs.
 */
class MealIngredientScaler
{
    private const MAX_ATTEMPTS = 3;

    private const PAUSE_MILLISECONDS = 500;

    public function __construct(
        private readonly EnergyNorms $energyNorms,
        private readonly ScalingCalculator $calculator,
        private readonly IngredientRefiner $refiner,
        private readonly RefinementGuard $guard,
    ) {}

    public function scale(MealPlan $mealPlan): ScalingOutcome
    {
        $mealPlan->load(['meals.recipe.recipeIngredients.ingredient', 'meals.recipe.recipeIngredients.unit']);

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
        $items = [];
        $rowsByKey = [];
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
                // Keyed by name: recipe names are not unique, and the notice should name each one once.
                $unscaledRecipes[$recipe->name] = $recipe->name;
            }

            foreach ($recipe->recipeIngredients->sortBy('id') as $recipeIngredient) {
                $recipeQuantity = (string) $recipeIngredient->quantity;
                $computed = $factor === null ? $recipeQuantity : $this->calculator->scale($recipeQuantity, $factor);

                $index = count($rows);
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

                if ($factor !== null) {
                    $item = new RefinementItem(
                        $recipeIngredient->ingredient_id,
                        (string) $recipeIngredient->ingredient?->name,
                        (string) $recipeIngredient->unit?->code,
                        $computed,
                    );
                    $items[$item->key] ??= $item;
                    $rowsByKey[$item->key][] = $index;
                }
            }
        }

        $status = $hasFamily ? ScalingOutcome::STATUS_COMPUTED : ScalingOutcome::STATUS_AS_WRITTEN;

        if ($items === []) {
            if ($hasFamily) {
                $status = ScalingOutcome::STATUS_REFINED;
            }
        } else {
            $proposals = $this->refineWithRetries(array_values($items));

            if ($proposals !== null) {
                $status = ScalingOutcome::STATUS_REFINED;

                foreach ($items as $key => $item) {
                    $accepted = $this->guard->accept($item, $proposals[$key]);

                    if ($accepted === null) {
                        continue;
                    }

                    foreach ($rowsByKey[$key] as $index) {
                        $rows[$index]['quantity'] = $accepted;
                        $rows[$index]['source'] = MealIngredient::SOURCE_AI;
                    }
                }
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

        return new ScalingOutcome($status, $names, $membersWithoutBody);
    }

    /**
     * Asks the refiner up to MAX_ATTEMPTS times, outside any transaction. On final failure reports once and returns null.
     *
     * @param  list<RefinementItem>  $items
     * @return array<string, string>|null
     */
    private function refineWithRetries(array $items): ?array
    {
        $last = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return $this->refiner->refine($items);
            } catch (RefinementFailedException $e) {
                $last = $e;

                if (! $e->isRetryable()) {
                    break;
                }

                if ($attempt < self::MAX_ATTEMPTS) {
                    Sleep::for(self::PAUSE_MILLISECONDS)->milliseconds();
                }
            }
        }

        report(new AiRefinementFailedException($last));

        return null;
    }
}
