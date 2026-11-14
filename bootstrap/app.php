<?php

declare(strict_types=1);

use App\Http\Middleware\AddLogContext;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            AddLogContext::class,
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('backup:run --only-db')
            ->dailyAt('05:15')
            ->timezone('Europe/Warsaw')
            ->withoutOverlapping()
            ->onOneServer()
            ->evenInMaintenanceMode()
            ->environments(['production']);

        $schedule->command('backup:clean')
            ->dailyAt('05:30')
            ->timezone('Europe/Warsaw')
            ->withoutOverlapping()
            ->onOneServer()
            ->environments(['production']);

        $schedule->command('backup:monitor')
            ->dailyAt('05:45')
            ->timezone('Europe/Warsaw')
            ->withoutOverlapping()
            ->onOneServer()
            ->evenInMaintenanceMode()
            ->environments(['production']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
