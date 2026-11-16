<?php

declare(strict_types=1);

use App\Jobs\GenerateMealPlanJob;
use App\Models\LogsMealPlan;
use App\Models\Meal;
use App\Models\MealIngredient;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use App\Services\IngredientScaling\MealIngredientScaler;
use App\Services\MealPlanGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/**
 * A pending plan whose 21 meals were already drawn, as after approving a draft.
 */
function approvedPlanWithDrawnMeals(User $user): MealPlan
{
    $mealPlan = MealPlan::factory()->for($user)->pending()->create([
        'start_date' => now()->startOfDay(),
        'end_date' => now()->startOfDay()->copy()->addDays(6),
    ]);
    app(MealPlanGenerator::class)->generate($mealPlan);

    return $mealPlan;
}

it('processes meal plan successfully and logs progress', function (): void {
    $user = User::factory()->create();
    seedFullWeekOfRecipes($user);
    $mealPlan = approvedPlanWithDrawnMeals($user);
    $mealIds = $mealPlan->meals()->pluck('id')->sort()->values()->all();

    // Run job synchronously (queue connection is sync in phpunit.xml)
    (new GenerateMealPlanJob($mealPlan))->handle(app(MealIngredientScaler::class));

    $mealPlan->refresh();

    expect($mealPlan->status)->toBe('done')
        ->and($mealPlan->generation_meta)
        ->toBeArray()
        ->and($mealPlan->generation_meta['version'])->toBe('scaled-v1')
        ->and($mealPlan->generation_meta['scaling'])->toHaveKeys(['status', 'unscaled_recipes', 'members_without_body'])
        ->and($mealPlan->generation_meta['scaling']['status'])->toBe('as_written')
        ->and($mealPlan->generation_meta)->not->toHaveKey('regenerate')
        ->and(Meal::query()->where('meal_plan_id', $mealPlan->id)->count())->toBe(21)
        ->and($mealPlan->meals()->pluck('id')->sort()->values()->all())->toBe($mealIds);

    $log = LogsMealPlan::query()->where('meal_plan_id', $mealPlan->id)->latest('created_at')->first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('done')
        ->and($log->started_at)->not->toBeNull()
        ->and($log->finished_at)->not->toBeNull();
});

it('writes scaled and refined meal ingredients for a family', function (): void {
    $user = User::factory()->create();
    seedFullWeekOfRecipes($user);
    seedTwoAdultFamily($user);
    Recipe::query()->where('user_id', $user->id)->each(fn (Recipe $recipe) => attachIngredient($recipe, 'Mąka', '100', 'g'));
    $mealPlan = approvedPlanWithDrawnMeals($user);

    (new GenerateMealPlanJob($mealPlan))->handle(app(MealIngredientScaler::class));

    $mealPlan->refresh();
    $rows = MealIngredient::query()->whereIn('meal_id', $mealPlan->meals()->pluck('id'))->get();

    expect($mealPlan->status)->toBe('done')
        ->and($mealPlan->generation_meta['version'])->toBe('scaled-v1')
        ->and($mealPlan->generation_meta['scaling']['status'])->toBe('refined')
        ->and($rows)->toHaveCount(21)
        ->and($rows->pluck('source')->unique()->all())->toBe([MealIngredient::SOURCE_AI]);
});

it('leaves a plan that is no longer pending untouched, e.g. a retried job after going back to draft', function (string $status): void {
    $user = User::factory()->create();
    seedFullWeekOfRecipes($user);
    $mealPlan = approvedPlanWithDrawnMeals($user);
    MealPlan::query()->whereKey($mealPlan->id)->update(['status' => $status, 'generation_meta' => null]);

    (new GenerateMealPlanJob($mealPlan))->handle(app(MealIngredientScaler::class));

    $mealPlan->refresh();

    expect($mealPlan->status)->toBe($status)
        ->and($mealPlan->generation_meta)->toBeNull()
        ->and(MealIngredient::query()->whereIn('meal_id', $mealPlan->meals()->pluck('id'))->count())->toBe(0)
        ->and(LogsMealPlan::query()->where('meal_plan_id', $mealPlan->id)->count())->toBe(0);
})->with(['draft', 'done', 'error', 'processing']);

it('marks a pending plan as error when the job fails before handle() runs', function (): void {
    config(['services.ingredient_refiner.driver' => 'nope']);

    $user = User::factory()->create();
    $mealPlan = MealPlan::factory()->for($user)->pending()->create([
        'start_date' => now()->startOfDay(),
        'end_date' => now()->startOfDay()->copy()->addDays(6),
    ]);

    expect(fn () => GenerateMealPlanJob::dispatch($mealPlan))
        ->toThrow(InvalidArgumentException::class, 'Unknown AI_SCALING_DRIVER [nope].')
        ->and($mealPlan->fresh()->status)->toBe('error')
        ->and($mealPlan->fresh()->generation_meta['error_message'])->toBe(GenerateMealPlanJob::GENERIC_ERROR_MESSAGE);
});

it('failed() releases the overlap lock of a killed attempt so the plan can be regenerated', function (): void {
    Exceptions::fake();

    $user = User::factory()->create();
    $mealPlan = MealPlan::factory()->for($user)->processing()->create([
        'start_date' => now()->startOfDay(),
        'end_date' => now()->startOfDay()->copy()->addDays(6),
    ]);
    $job = new GenerateMealPlanJob($mealPlan);
    $lockKey = $job->middleware()[0]->getLockKey($job);
    Cache::lock($lockKey, 300)->get();

    $job->failed(new TimeoutExceededException('timed out'));

    expect(Cache::lock($lockKey, 300)->get())->toBeTrue();
});

it('failed() marks meal plan as error when still processing', function (): void {
    $user = User::factory()->create();
    $mealPlan = MealPlan::factory()->for($user)->processing()->create([
        'start_date' => now()->startOfDay(),
        'end_date' => now()->startOfDay()->copy()->addDays(6),
    ]);

    $job = new GenerateMealPlanJob($mealPlan);
    $job->failed(new RuntimeException('forced failure'));

    $mealPlan->refresh();
    expect($mealPlan->status)->toBe('error')
        ->and($mealPlan->generation_meta)
        ->toBeArray()
        ->and(($mealPlan->generation_meta['error_message'] ?? null))->toBe(GenerateMealPlanJob::GENERIC_ERROR_MESSAGE);
});

it('failed() adds meal_plan_id to the log context and writes no error entry itself', function (): void {
    Log::spy();

    $user = User::factory()->create();
    $mealPlan = MealPlan::factory()->for($user)->processing()->create([
        'start_date' => now()->startOfDay(),
        'end_date' => now()->startOfDay()->copy()->addDays(6),
    ]);

    (new GenerateMealPlanJob($mealPlan))->failed(new RuntimeException('forced failure'));

    expect(Context::get('meal_plan_id'))->toBe($mealPlan->id)
        ->and($mealPlan->fresh()->status)->toBe('error');

    Log::shouldNotHaveReceived('error');
});

it('handle() adds meal_plan_id to the log context, rethrows and writes no error entry itself', function (): void {
    Log::spy();

    $user = User::factory()->create();
    $mealPlan = MealPlan::factory()->for($user)->pending()->create([
        'start_date' => now()->startOfDay(),
        'end_date' => now()->startOfDay()->copy()->addDays(6),
    ]);

    $calls = 0;
    MealPlan::updating(function () use (&$calls): void {
        if (++$calls === 1) {
            throw new RuntimeException('forced failure inside handle');
        }
    });

    expect(fn () => (new GenerateMealPlanJob($mealPlan))->handle(app(MealIngredientScaler::class)))
        ->toThrow(RuntimeException::class, 'forced failure inside handle')
        ->and(Context::get('meal_plan_id'))->toBe($mealPlan->id)
        ->and($mealPlan->fresh()->status)->toBe('error')
        ->and($mealPlan->fresh()->generation_meta['error_message'])->toBe(GenerateMealPlanJob::GENERIC_ERROR_MESSAGE);

    Log::shouldNotHaveReceived('error');
});

it('failed() reports timeouts and exceeded attempts, which the worker does not report itself', function (string $exceptionClass): void {
    Exceptions::fake();

    $user = User::factory()->create();
    $mealPlan = MealPlan::factory()->for($user)->processing()->create([
        'start_date' => now()->startOfDay(),
        'end_date' => now()->startOfDay()->copy()->addDays(6),
    ]);

    (new GenerateMealPlanJob($mealPlan))->failed(new $exceptionClass('gave up'));

    Exceptions::assertReported($exceptionClass);
    expect(Context::get('meal_plan_id'))->toBe($mealPlan->id);
})->with([
    'max attempts' => [MaxAttemptsExceededException::class],
    'timeout' => [TimeoutExceededException::class],
]);

it('failed() does not re-report an exception the worker already reported', function (): void {
    Exceptions::fake();

    $user = User::factory()->create();
    $mealPlan = MealPlan::factory()->for($user)->processing()->create([
        'start_date' => now()->startOfDay(),
        'end_date' => now()->startOfDay()->copy()->addDays(6),
    ]);

    (new GenerateMealPlanJob($mealPlan))->failed(new RuntimeException('thrown from handle'));

    Exceptions::assertNothingReported();
});

it('drops the job silently when the meal plan was deleted before processing', function (): void {
    Exceptions::fake();

    $user = User::factory()->create();
    $mealPlan = MealPlan::factory()->for($user)->pending()->create([
        'start_date' => now()->startOfDay(),
        'end_date' => now()->startOfDay()->copy()->addDays(6),
    ]);
    $mealPlan->delete();

    GenerateMealPlanJob::dispatch($mealPlan);

    expect(MealPlan::query()->count())->toBe(0)
        ->and(LogsMealPlan::query()->count())->toBe(0);

    Exceptions::assertNothingReported();
});

it('a plan with an empty meal writes ingredients only for filled meals and ends done', function (): void {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user)->create();
    attachIngredient($recipe, 'Mąka', '100', 'g');
    $mealPlan = mealPlanWithRecipes($user, 'pending', $recipe);
    $emptyMeal = Meal::query()->create([
        'meal_plan_id' => $mealPlan->id,
        'recipe_id' => null,
        'meal_date' => $mealPlan->start_date->copy()->addDays(2),
        'meal_category' => 'supper',
    ]);

    (new GenerateMealPlanJob($mealPlan))->handle(app(MealIngredientScaler::class));

    expect($mealPlan->fresh()->status)->toBe('done')
        ->and(MealIngredient::query()->where('meal_id', $emptyMeal->id)->count())->toBe(0)
        ->and(MealIngredient::query()->whereIn('meal_id', $mealPlan->meals()->pluck('id'))->count())->toBe(1);
});
