<?php

declare(strict_types=1);

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters recipes by category', function (): void {
    $user = User::factory()->create();

    Recipe::factory()->for($user)->breakfast()->count(2)->create();
    Recipe::factory()->for($user)->dinner()->count(1)->create();

    $breakfast = Recipe::query()
        ->forUser($user->id)
        ->byCategory('breakfast')
        ->get();

    expect($breakfast)->toHaveCount(2);
    $breakfast->each(function (Recipe $recipe): void {
        expect($recipe->category)->toBe('breakfast');
    });
});

it('searches recipes by name and instructions', function (): void {
    $user = User::factory()->create();

    Recipe::factory()->for($user)->create([
        'name' => 'Spicy Chili',
        'instructions' => 'Boil water',
    ]);
    Recipe::factory()->for($user)->create([
        'name' => 'Pasta',
        'instructions' => 'Add chili flakes generously',
    ]);
    Recipe::factory()->for($user)->create([
        'name' => 'Salad',
        'instructions' => 'Mix greens',
    ]);

    $results = Recipe::query()
        ->forUser($user->id)
        ->search('Chili')
        ->pluck('name')
        ->all();

    expect($results)->toContain('Spicy Chili')->toContain('Pasta')->not->toContain('Salad');
});

it('orders recipes by allowed fields and falls back to created_at desc', function (): void {
    $user = User::factory()->create();

    $older = Recipe::factory()->for($user)->create([
        'name' => 'Apple',
        'calories' => 300,
        'category' => 'breakfast',
        'created_at' => now()->subDay(),
    ]);
    $newer = Recipe::factory()->for($user)->create([
        'name' => 'Banana',
        'calories' => 200,
        'category' => 'dinner',
        'created_at' => now(),
    ]);

    $byNameAsc = Recipe::query()
        ->forUser($user->id)
        ->orderByField('name', 'asc')
        ->pluck('id')
        ->all();
    expect($byNameAsc)->toBe([$older->id, $newer->id]);

    $byCaloriesDesc = Recipe::query()
        ->forUser($user->id)
        ->orderByField('calories', 'desc')
        ->pluck('id')
        ->all();
    expect($byCaloriesDesc)->toBe([$older->id, $newer->id]);

    $fallback = Recipe::query()
        ->forUser($user->id)
        ->orderByField('nonexistent_field', 'desc')
        ->pluck('id')
        ->all();
    // Fallback should be created_at DESC → newer first
    expect($fallback)->toBe([$newer->id, $older->id]);
});
