<?php

declare(strict_types=1);

use App\Http\Middleware\AddLogContext;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

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

        // Traefik reaches the app from the Docker proxy network and passes Cloudflare's
        // X-Forwarded-For on, so the client IP is the visitor's. Only the client IP and the
        // scheme are trusted: X-Forwarded-Host and -Port reach the app from the visitor
        // unchanged, and trusting them would let anyone change the host in generated links.
        $middleware->trustProxies(
            at: ['172.16.0.0/12'],
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );
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
