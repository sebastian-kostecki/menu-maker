<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\ApproveMealPlanRequest;
use App\Http\Requests\DownloadMealPlanPdfRequest;
use App\Http\Requests\GenerateMealPlanRequest;
use App\Http\Requests\RegenerateMealPlanRequest;
use App\Http\Requests\ReturnMealPlanToDraftRequest;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use App\Services\MealPlanGenerator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MealPlanRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        RateLimiter::clear('generate-meal-plan:'.$this->user->id);
        RateLimiter::clear('regenerate-meal-plan:'.$this->user->id);
    }

    public function test_generate_meal_plan_request_validates_start_date(): void
    {
        $request = new GenerateMealPlanRequest;
        $rules = $request->rules();

        // Valid date (today)
        $validator = Validator::make(['start_date' => now()->format('Y-m-d')], $rules);
        $this->assertTrue($validator->passes());

        // Valid date (future)
        $validator = Validator::make(['start_date' => now()->addDay()->format('Y-m-d')], $rules);
        $this->assertTrue($validator->passes());

        // Invalid date (past)
        $validator = Validator::make(['start_date' => now()->subDay()->format('Y-m-d')], $rules);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('start_date', $validator->errors()->toArray());

        // Invalid format
        $validator = Validator::make(['start_date' => 'invalid-date'], $rules);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('start_date', $validator->errors()->toArray());

        // Missing start_date
        $validator = Validator::make([], $rules);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('start_date', $validator->errors()->toArray());
    }

    public function test_generate_meal_plan_request_checks_unique_start_date(): void
    {
        $startDate = now()->addDays(7)->format('Y-m-d');

        // Create existing meal plan
        MealPlan::factory()->for($this->user)->create(['start_date' => $startDate]);

        $request = new GenerateMealPlanRequest;
        $request->setUserResolver(function () {
            return $this->user;
        });

        $validator = Validator::make(['start_date' => $startDate], $request->rules());

        // Call withValidator which checks the unique constraint
        $request->withValidator($validator);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('start_date', $validator->errors()->toArray());
        $this->assertStringContainsString('Masz już jadłospis zaczynający się tego dnia', $validator->errors()->first('start_date'));
    }

    public function test_generate_meal_plan_request_does_not_rate_limit(): void
    {
        $request = new GenerateMealPlanRequest;
        $request->setUserResolver(fn () => $this->user);

        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit('generate-meal-plan:'.$this->user->id, 3600);
        }

        $validator = Validator::make(['start_date' => now()->format('Y-m-d')], $request->rules());
        $request->withValidator($validator);

        $this->assertArrayNotHasKey('rate_limit', $validator->errors()->toArray());
        $this->assertSame(5, RateLimiter::attempts('generate-meal-plan:'.$this->user->id));
    }

    public function test_regenerate_meal_plan_request_validates_regenerate_flag(): void
    {
        $request = new RegenerateMealPlanRequest;
        $rules = $request->rules();

        // Valid: regenerate is true
        $validator = Validator::make(['regenerate' => true], $rules);
        $this->assertTrue($validator->passes());

        // Invalid: regenerate is false
        $validator = Validator::make(['regenerate' => false], $rules);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('regenerate', $validator->errors()->toArray());

        // Invalid: missing regenerate
        $validator = Validator::make([], $rules);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('regenerate', $validator->errors()->toArray());
    }

    public function test_download_meal_plan_pdf_request_validates_recipes_flag(): void
    {
        $rules = (new DownloadMealPlanPdfRequest)->rules();

        foreach ([[], ['recipes' => '1'], ['recipes' => '0'], ['recipes' => true], ['recipes' => false]] as $input) {
            $this->assertTrue(Validator::make($input, $rules)->passes());
        }

        foreach (['abc', 'true'] as $invalid) {
            $validator = Validator::make(['recipes' => $invalid], $rules);
            $this->assertFalse($validator->passes());
            $this->assertArrayHasKey('recipes', $validator->errors()->toArray());
        }
    }

    public function test_download_meal_plan_pdf_request_validates_menu_flag(): void
    {
        $rules = (new DownloadMealPlanPdfRequest)->rules();

        foreach ([['menu' => '1'], ['menu' => '0'], ['menu' => true], ['menu' => '0', 'recipes' => '1']] as $input) {
            $this->assertTrue(Validator::make($input, $rules)->passes());
        }

        foreach ([[['menu' => 'abc'], 'menu'], [['menu' => '1', 'recipes' => '1'], 'recipes'], [['menu' => '1', 'recipes' => '0'], 'recipes']] as [$input, $invalidKey]) {
            $validator = Validator::make($input, $rules);
            $this->assertFalse($validator->passes());
            $this->assertArrayHasKey($invalidKey, $validator->errors()->toArray());
        }
    }

    public function test_regenerate_meal_plan_request_ignores_force_flag_and_limiter(): void
    {
        $request = new RegenerateMealPlanRequest;

        $this->assertArrayNotHasKey('force', $request->rules());

        $validator = Validator::make(['regenerate' => true, 'force' => 'invalid'], $request->rules());
        $this->assertTrue($validator->passes());
        $this->assertArrayNotHasKey('force', $validator->validated());
        $this->assertSame(0, RateLimiter::attempts('regenerate-meal-plan:'.$this->user->id));
    }

    public function test_regenerate_meal_plan_request_checks_meal_plan_status(): void
    {
        $mealPlan = MealPlan::factory()->processing()->for($this->user)->create();

        $request = new RegenerateMealPlanRequest;
        $request->setUserResolver(function () {
            return $this->user;
        });
        $request->setRouteResolver(function () use ($mealPlan) {
            return new class($mealPlan)
            {
                public function __construct(private MealPlan $mealPlan) {}

                public function parameter(string $key)
                {
                    return $this->mealPlan;
                }
            };
        });

        $validator = Validator::make(['regenerate' => true], $request->rules());
        $request->withValidator($validator);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
        $this->assertSame(RegenerateMealPlanRequest::NOT_REGENERATABLE_MESSAGE, $validator->errors()->first('status'));
    }

    public function test_regenerate_meal_plan_request_allows_draft_status(): void
    {
        seedFullWeekOfRecipes($this->user);
        $mealPlan = MealPlan::factory()->draft()->for($this->user)->create();

        $request = new RegenerateMealPlanRequest;
        $request->setUserResolver(function () {
            return $this->user;
        });
        $request->setRouteResolver(function () use ($mealPlan) {
            return new class($mealPlan)
            {
                public function __construct(private MealPlan $mealPlan) {}

                public function parameter(string $key)
                {
                    return $this->mealPlan;
                }
            };
        });

        $validator = Validator::make(['regenerate' => true], $request->rules());
        $request->withValidator($validator);

        $this->assertTrue($validator->passes());
    }

    public function test_regenerate_meal_plan_request_refuses_done_and_error_status(): void
    {
        seedFullWeekOfRecipes($this->user);

        foreach (['done', 'error', 'pending'] as $status) {
            $mealPlan = MealPlan::factory()->{$status}()->for($this->user)->create();

            $validator = $this->validatorFor(new RegenerateMealPlanRequest, $mealPlan, ['regenerate' => true]);

            $this->assertFalse($validator->passes(), $status);
            $this->assertSame(RegenerateMealPlanRequest::NOT_REGENERATABLE_MESSAGE, $validator->errors()->first('status'));
        }
    }

    public function test_regenerate_meal_plan_request_refuses_a_user_without_recipes(): void
    {
        $mealPlan = MealPlan::factory()->draft()->for($this->user)->create();

        $request = new RegenerateMealPlanRequest;
        $request->setUserResolver(function () {
            return $this->user;
        });
        $request->setRouteResolver(function () use ($mealPlan) {
            return new class($mealPlan)
            {
                public function __construct(private MealPlan $mealPlan) {}

                public function parameter(string $key)
                {
                    return $this->mealPlan;
                }
            };
        });

        $validator = Validator::make(['regenerate' => true], $request->rules());
        $request->withValidator($validator);

        $this->assertFalse($validator->passes());
        $this->assertSame([MealPlanGenerator::NO_RECIPES_MESSAGE], $validator->errors()->get('recipes'));
    }

    public function test_approve_request_accepts_a_draft_with_meals(): void
    {
        $mealPlan = mealPlanWithRecipes($this->user, 'draft', Recipe::factory()->for($this->user)->create());

        $validator = $this->validatorFor(new ApproveMealPlanRequest, $mealPlan);

        $this->assertTrue($validator->passes());
    }

    public function test_approve_request_refuses_a_non_draft_status(): void
    {
        $mealPlan = mealPlanWithRecipes($this->user, 'done', Recipe::factory()->for($this->user)->create());

        $validator = $this->validatorFor(new ApproveMealPlanRequest, $mealPlan);

        $this->assertFalse($validator->passes());
        $this->assertSame(ApproveMealPlanRequest::NOT_DRAFT_MESSAGE, $validator->errors()->first('status'));
    }

    public function test_approve_request_refuses_an_empty_draft(): void
    {
        $mealPlan = MealPlan::factory()->draft()->for($this->user)->create();

        $validator = $this->validatorFor(new ApproveMealPlanRequest, $mealPlan);

        $this->assertFalse($validator->passes());
        $this->assertSame(ApproveMealPlanRequest::EMPTY_DRAFT_MESSAGE, $validator->errors()->first('meals'));
    }

    public function test_approve_request_refuses_a_draft_whose_meals_are_all_empty(): void
    {
        $mealPlan = mealPlanWithRecipes($this->user, 'draft', Recipe::factory()->for($this->user)->create());
        $mealPlan->meals()->update(['recipe_id' => null]);

        $validator = $this->validatorFor(new ApproveMealPlanRequest, $mealPlan);

        $this->assertFalse($validator->passes());
        $this->assertSame(ApproveMealPlanRequest::EMPTY_DRAFT_MESSAGE, $validator->errors()->first('meals'));
    }

    public function test_approve_request_enforces_rate_limiting(): void
    {
        $mealPlan = mealPlanWithRecipes($this->user, 'draft', Recipe::factory()->for($this->user)->create());

        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit('generate-meal-plan:'.$this->user->id, 3600);
        }

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Zbyt wiele prób generowania');
        $this->validatorFor(new ApproveMealPlanRequest, $mealPlan);
    }

    public function test_return_to_draft_request_allows_done_and_error_only(): void
    {
        foreach (['done' => true, 'error' => true, 'draft' => false, 'pending' => false, 'processing' => false] as $status => $allowed) {
            $mealPlan = MealPlan::factory()->{$status}()->for($this->user)->create();

            $validator = $this->validatorFor(new ReturnMealPlanToDraftRequest, $mealPlan);

            $this->assertSame($allowed, $validator->passes(), $status);
            if (! $allowed) {
                $this->assertSame(ReturnMealPlanToDraftRequest::NOT_RETURNABLE_MESSAGE, $validator->errors()->first('status'));
            }
        }
    }

    /**
     * Build the request's validator for the given plan, with the user and route plan resolved.
     *
     * @param  array<string, mixed>  $data
     */
    private function validatorFor(FormRequest $request, MealPlan $mealPlan, array $data = []): \Illuminate\Validation\Validator
    {
        $request->setUserResolver(fn () => $this->user);
        $request->setRouteResolver(function () use ($mealPlan) {
            return new class($mealPlan)
            {
                public function __construct(private MealPlan $mealPlan) {}

                public function parameter(string $key)
                {
                    return $this->mealPlan;
                }
            };
        });

        $validator = Validator::make($data, $request->rules());
        $request->withValidator($validator);

        return $validator;
    }
}
