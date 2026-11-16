<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\UpdateMealRequest;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MealRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Meal $meal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $plan = MealPlan::factory()->draft()->for($this->user)->create();
        $this->meal = Meal::query()->create([
            'meal_plan_id' => $plan->id,
            'recipe_id' => Recipe::factory()->breakfast()->for($this->user)->create()->id,
            'meal_date' => $plan->start_date,
            'meal_category' => 'breakfast',
        ]);
    }

    /**
     * Build the request's validator for the meal with the given input.
     *
     * @param  array<string, mixed>  $input
     */
    protected function validatorFor(array $input): \Illuminate\Validation\Validator
    {
        $request = new UpdateMealRequest;
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

    public function test_refuses_a_swap_outside_a_draft(): void
    {
        $recipe = Recipe::factory()->breakfast()->for($this->user)->create();

        foreach (['pending', 'processing', 'done', 'error'] as $status) {
            $this->meal->mealPlan->update(['status' => $status]);

            $validator = $this->validatorFor(['recipe_id' => $recipe->id]);

            $this->assertTrue($validator->fails(), $status);
            $this->assertSame(UpdateMealRequest::NOT_DRAFT_MESSAGE, $validator->errors()->first('status'), $status);
        }
    }

    public function test_accepts_an_own_recipe_of_the_meal_category(): void
    {
        $recipe = Recipe::factory()->breakfast()->for($this->user)->create();

        $this->assertTrue($this->validatorFor(['recipe_id' => $recipe->id])->passes());
        $this->assertTrue($this->validatorFor(['recipe_id' => $this->meal->recipe_id])->passes());
    }

    public function test_requires_an_integer_recipe_id(): void
    {
        $this->assertArrayHasKey('recipe_id', $this->validatorFor([])->errors()->toArray());
        $this->assertArrayHasKey('recipe_id', $this->validatorFor(['recipe_id' => 'abc'])->errors()->toArray());
    }

    public function test_rejects_other_category_deleted_and_foreign_recipes(): void
    {
        $dinner = Recipe::factory()->dinner()->for($this->user)->create();
        $deleted = Recipe::factory()->breakfast()->for($this->user)->create();
        $deleted->delete();
        $foreign = Recipe::factory()->breakfast()->for(User::factory()->create())->create();

        foreach ([$dinner, $deleted, $foreign] as $recipe) {
            $validator = $this->validatorFor(['recipe_id' => $recipe->id]);

            $this->assertTrue($validator->fails());
            $this->assertSame(
                UpdateMealRequest::INVALID_RECIPE_MESSAGE,
                $validator->errors()->first('recipe_id'),
            );
        }
    }
}
