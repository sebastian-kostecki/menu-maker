<?php

declare(strict_types=1);

use App\Models\MealPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters meal plans by owner using scopeOwnedBy', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    MealPlan::factory()->for($owner)->count(3)->create();
    MealPlan::factory()->for($other)->count(2)->create();

    $owned = MealPlan::query()->ownedBy($owner)->get();

    expect($owned)->toHaveCount(3);
    $owned->each(function (MealPlan $plan) use ($owner): void {
        expect($plan->user_id)->toBe($owner->id);
    });
});
