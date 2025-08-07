<?php

use App\Http\Controllers\FamilyMemberController;
use App\Http\Controllers\MealPlanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecipeController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('family-members', FamilyMemberController::class)
        ->except(['show'])
        ->names('family-members');

    Route::resource('recipes', RecipeController::class);

    Route::resource('meal-plans', MealPlanController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy']);

    // Additional meal plan routes
    Route::get('/meal-plans/{meal_plan}/pdf', [MealPlanController::class, 'downloadPdf'])
        ->name('meal-plans.pdf');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
