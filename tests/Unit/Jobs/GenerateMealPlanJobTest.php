<?php

declare(strict_types=1);

use App\Jobs\GenerateMealPlanJob;
use App\Models\LogsMealPlan;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\User;
use App\Services\MealPlanGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('processes meal plan successfully and logs progress', function (): void {
    $user = User::factory()->create();
    seedFullWeekOfRecipes($user);
    $mealPlan = MealPlan::factory()->for($user)->pending()->create([
        'start_date' => now()->startOfDay(),
        'end_date' => now()->startOfDay()->copy()->addDays(6),
    ]);

    // Run job synchronously (queue connection is sync in phpunit.xml)
    (new GenerateMealPlanJob($mealPlan, false))->handle(app(MealPlanGenerator::class));

    $mealPlan->refresh();

    expect($mealPlan->status)->toBe('done')
        ->and($mealPlan->generation_meta)
        ->toBeArray()
        ->and($mealPlan->generation_meta['regenerate'])->toBeFalse()
        ->and($mealPlan->generation_meta['version'])->toBe('random-v1')
        ->and(Meal::query()->where('meal_plan_id', $mealPlan->id)->count())->toBe(21);

    $log = LogsMealPlan::query()->where('meal_plan_id', $mealPlan->id)->latest('created_at')->first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('done')
        ->and($log->started_at)->not->toBeNull()
        ->and($log->finished_at)->not->toBeNull();
});

it('failed() marks meal plan as error when still processing', function (): void {
    $user = User::factory()->create();
    $mealPlan = MealPlan::factory()->for($user)->processing()->create([
        'start_date' => now()->startOfDay(),
        'end_date' => now()->startOfDay()->copy()->addDays(6),
    ]);

    $job = new GenerateMealPlanJob($mealPlan, true);
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

    (new GenerateMealPlanJob($mealPlan, false))->failed(new RuntimeException('forced failure'));

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

    expect(fn () => (new GenerateMealPlanJob($mealPlan, false))->handle(app(MealPlanGenerator::class)))
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

    (new GenerateMealPlanJob($mealPlan, false))->failed(new $exceptionClass('gave up'));

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

    (new GenerateMealPlanJob($mealPlan, false))->failed(new RuntimeException('thrown from handle'));

    Exceptions::assertNothingReported();
});

it('turns a run-time recipe shortfall into an unreported error status', function (): void {
    Exceptions::fake();

    $user = User::factory()->create();
    $mealPlan = MealPlan::factory()->for($user)->pending()->create([
        'start_date' => now()->startOfDay(),
        'end_date' => now()->startOfDay()->copy()->addDays(6),
    ]);

    (new GenerateMealPlanJob($mealPlan, true))->handle(app(MealPlanGenerator::class));

    $mealPlan->refresh();
    $log = LogsMealPlan::query()->where('meal_plan_id', $mealPlan->id)->latest('created_at')->first();

    expect($mealPlan->status)->toBe('error')
        ->and($mealPlan->generation_meta['error_message'])->toContain('Za mało przepisów w kategorii Śniadanie')
        ->and($mealPlan->generation_meta['missing'])->toBe(['breakfast' => 7, 'supper' => 7, 'dinner' => 7])
        ->and($mealPlan->generation_meta['regenerate'])->toBeTrue()
        ->and($log->status)->toBe('error')
        ->and($log->finished_at)->not->toBeNull()
        ->and(Meal::query()->where('meal_plan_id', $mealPlan->id)->count())->toBe(0);

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

    GenerateMealPlanJob::dispatch($mealPlan, false);

    expect(MealPlan::query()->count())->toBe(0)
        ->and(LogsMealPlan::query()->count())->toBe(0);

    Exceptions::assertNothingReported();
});
