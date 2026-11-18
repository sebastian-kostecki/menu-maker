<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\MoveMealRequest;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MoveMealRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected MealPlan $plan;

    protected Meal $meal;

    protected Meal $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->plan = MealPlan::factory()->draft()->for($this->user)->create();
        $this->meal = $this->mealIn($this->plan, 'breakfast');
        $this->other = $this->mealIn($this->plan, 'supper');
    }

    protected function mealIn(MealPlan $plan, string $category): Meal
    {
        return Meal::query()->create([
            'meal_plan_id' => $plan->id,
            'recipe_id' => Recipe::factory()->for($this->user)->create(['category' => $category])->id,
            'meal_date' => $plan->start_date,
            'meal_category' => $category,
        ]);
    }

    /**
     * Build the request's validator for the meal with the given input.
     *
     * @param  array<string, mixed>  $input
     */
    protected function validatorFor(array $input): \Illuminate\Validation\Validator
    {
        $request = new MoveMealRequest;
        $request->setRouteResolver(fn () => new class($this->meal)
        {
            public function __construct(private Meal $meal) {}

            public function parameter(string $name): Meal
            {
                return $this->meal;
            }
        });

        return Validator::make($input, $request->rules(), $request->messages())->after($request->after());
    }

    public function test_accepts_another_meal_of_the_same_plan(): void
    {
        $this->assertFalse($this->validatorFor(['target_meal_id' => $this->other->id])->fails());
    }

    public function test_requires_an_integer_target_meal_id(): void
    {
        foreach ([[], ['target_meal_id' => 'abc']] as $input) {
            $validator = $this->validatorFor($input);

            $this->assertTrue($validator->fails());
            $this->assertSame(MoveMealRequest::TARGET_INVALID_MESSAGE, $validator->errors()->first('target_meal_id'));
        }
    }

    public function test_rejects_a_target_of_another_plan_or_a_missing_one(): void
    {
        $foreignPlan = MealPlan::factory()->draft()->for($this->user)->create();
        $foreign = $this->mealIn($foreignPlan, 'breakfast');

        foreach ([$foreign->id, 999999] as $id) {
            $validator = $this->validatorFor(['target_meal_id' => $id]);

            $this->assertTrue($validator->fails());
            $this->assertSame(MoveMealRequest::TARGET_INVALID_MESSAGE, $validator->errors()->first('target_meal_id'));
        }
    }

    public function test_refuses_a_move_outside_a_draft(): void
    {
        $this->plan->update(['status' => 'done']);
        $this->meal->refresh();

        $this->assertArrayHasKey('status', $this->validatorFor(['target_meal_id' => $this->other->id])->errors()->toArray());
    }
}
