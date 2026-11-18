<?php

declare(strict_types=1);

use App\Http\Controllers\ClientErrorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FamilyMemberController;
use App\Http\Controllers\MealPlanController;
use App\Http\Controllers\MealPlanMealController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', DashboardController::class)->middleware(['auth'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::resource('family-members', FamilyMemberController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->where(['family_member' => '[0-9]+'])
        ->names('family-members');

    Route::resource('recipes', RecipeController::class);

    Route::resource('meal-plans', MealPlanController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy'])
        ->middlewareFor(['store', 'update'], 'throttle:30,1,meal-plan-draw');

    Route::resource('meal-plans.meals', MealPlanMealController::class)
        ->only(['update'])
        ->scoped();

    Route::post('/meal-plans/{meal_plan}/meals/{meal}/move', [MealPlanMealController::class, 'move'])
        ->scopeBindings()
        ->name('meal-plans.meals.move');

    // Additional meal plan routes
    Route::post('/meal-plans/{meal_plan}/approve', [MealPlanController::class, 'approve'])
        ->name('meal-plans.approve');
    Route::post('/meal-plans/{meal_plan}/draft', [MealPlanController::class, 'backToDraft'])
        ->name('meal-plans.draft');
    Route::get('/meal-plans/{meal_plan}/pdf', [MealPlanController::class, 'downloadPdf'])
        ->name('meal-plans.pdf');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::post('/client-errors', ClientErrorController::class)
    ->middleware('throttle:10,1')
    ->name('client-errors.store');

if (app()->environment(['local', 'testing'])) {
    Route::inertia('/dev/design-system', 'Dev/DesignSystem')->name('dev.design-system');
}

require __DIR__.'/auth.php';
