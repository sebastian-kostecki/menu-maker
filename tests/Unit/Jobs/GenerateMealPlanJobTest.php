<?php

declare(strict_types=1);

use App\Jobs\GenerateMealPlanJob;
use App\Models\LogsMealPlan;
use App\Models\MealPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('processes meal plan successfully and logs progress', function (): void {
    $user = User::factory()->create();
    $mealPlan = MealPlan::factory()->for($user)->pending()->create([
        'start_date' => now()->startOfDay(),
        'end_date' => now()->startOfDay()->copy()->addDays(6),
    ]);

    // Run job synchronously (queue connection is sync in phpunit.xml)
    (new GenerateMealPlanJob($mealPlan, false))->handle();

    $mealPlan->refresh();

    expect($mealPlan->status)->toBe('done')
        ->and($mealPlan->generation_meta)
        ->toBeArray()
        ->and($mealPlan->generation_meta['regenerate'])->toBeFalse();

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
        ->and(($mealPlan->generation_meta['error_message'] ?? null))->toBe('forced failure');
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
