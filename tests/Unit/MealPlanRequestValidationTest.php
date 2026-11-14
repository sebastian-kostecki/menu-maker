<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\GenerateMealPlanRequest;
use App\Http\Requests\RegenerateMealPlanRequest;
use App\Models\MealPlan;
use App\Models\User;
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

    public function test_generate_meal_plan_request_validates_regenerate_flag(): void
    {
        $request = new GenerateMealPlanRequest;
        $rules = $request->rules();

        // Valid boolean values
        $validator = Validator::make([
            'start_date' => now()->format('Y-m-d'),
            'regenerate' => true,
        ], $rules);
        $this->assertTrue($validator->passes());

        $validator = Validator::make([
            'start_date' => now()->format('Y-m-d'),
            'regenerate' => false,
        ], $rules);
        $this->assertTrue($validator->passes());

        // String representations
        $validator = Validator::make([
            'start_date' => now()->format('Y-m-d'),
            'regenerate' => '1',
        ], $rules);
        $this->assertTrue($validator->passes());

        $validator = Validator::make([
            'start_date' => now()->format('Y-m-d'),
            'regenerate' => '0',
        ], $rules);
        $this->assertTrue($validator->passes());

        // Invalid value
        $validator = Validator::make([
            'start_date' => now()->format('Y-m-d'),
            'regenerate' => 'invalid',
        ], $rules);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('regenerate', $validator->errors()->toArray());
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

    public function test_generate_meal_plan_request_enforces_rate_limiting(): void
    {
        $request = new GenerateMealPlanRequest;
        $request->setUserResolver(function () {
            return $this->user;
        });

        // Hit rate limit 5 times
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit('generate-meal-plan:'.$this->user->id, 3600);
        }

        $validator = Validator::make(['start_date' => now()->format('Y-m-d')], $request->rules());

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Zbyt wiele prób generowania');
        $request->withValidator($validator);
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
        $this->assertStringContainsString('Nie można regenerować jadłospisu w trakcie generowania', $validator->errors()->first('status'));
    }

    public function test_regenerate_meal_plan_request_allows_done_status(): void
    {
        seedFullWeekOfRecipes($this->user);
        $mealPlan = MealPlan::factory()->done()->for($this->user)->create();

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

    public function test_regenerate_meal_plan_request_allows_error_status(): void
    {
        seedFullWeekOfRecipes($this->user);
        $mealPlan = MealPlan::factory()->error()->for($this->user)->create();

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

    public function test_regenerate_meal_plan_request_reports_shortfall_on_recipes(): void
    {
        $mealPlan = MealPlan::factory()->done()->for($this->user)->create();

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
        $this->assertCount(3, $validator->errors()->get('recipes'));
    }
}
