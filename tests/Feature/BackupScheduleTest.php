<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

test('backups are database-only, on the backups disk, and scheduled in free production slots', function () {
    expect(config('backup.backup.source.files.include'))->toBe([])
        ->and(config('backup.backup.source.databases'))->toBe([config('database.default')])
        ->and(config('backup.backup.destination.disks'))->toBe(['backups'])
        ->and(config('filesystems.disks.backups.root'))->toBe(storage_path('app/backups'));

    // withSchedule() hooks into Artisan::starting(), which the test lifecycle resets between tests.
    Artisan::all();

    $byCommand = collect($this->app->make(Schedule::class)->events())
        ->keyBy(fn (Event $event): string => match (true) {
            str_contains((string) $event->command, 'backup:run') => 'backup:run',
            str_contains((string) $event->command, 'backup:clean') => 'backup:clean',
            str_contains((string) $event->command, 'backup:monitor') => 'backup:monitor',
            default => (string) $event->command,
        });

    expect($byCommand->get('backup:run'))->toBeInstanceOf(Event::class)
        ->and($byCommand->get('backup:run')->command)->toContain('--only-db')
        ->and($byCommand->get('backup:run')->expression)->toBe('15 5 * * *')
        ->and((string) $byCommand->get('backup:run')->timezone)->toBe('Europe/Warsaw')
        ->and($byCommand->get('backup:run')->environments)->toBe(['production']);

    expect($byCommand->get('backup:clean'))->toBeInstanceOf(Event::class)
        ->and($byCommand->get('backup:clean')->expression)->toBe('30 5 * * *')
        ->and((string) $byCommand->get('backup:clean')->timezone)->toBe('Europe/Warsaw')
        ->and($byCommand->get('backup:clean')->environments)->toBe(['production']);

    expect($byCommand->get('backup:monitor'))->toBeInstanceOf(Event::class)
        ->and($byCommand->get('backup:monitor')->expression)->toBe('45 5 * * *')
        ->and((string) $byCommand->get('backup:monitor')->timezone)->toBe('Europe/Warsaw')
        ->and($byCommand->get('backup:monitor')->environments)->toBe(['production']);
});
