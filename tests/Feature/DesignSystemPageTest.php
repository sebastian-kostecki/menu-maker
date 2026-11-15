<?php

declare(strict_types=1);

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

it('renders the kitchen sink for a guest in the testing environment', function (): void {
    $this->get('/dev/design-system')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Dev/DesignSystem'));
});

it('does not register the kitchen sink route in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    Route::setRoutes(new RouteCollection);
    require base_path('routes/web.php');
    Route::getRoutes()->refreshNameLookups();

    expect(Route::has('dev.design-system'))->toBeFalse();

    $this->get('/dev/design-system')->assertNotFound();
});
