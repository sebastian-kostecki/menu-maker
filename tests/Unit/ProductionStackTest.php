<?php

declare(strict_types=1);

use App\Jobs\GenerateMealPlanJob;

function productionFile(string $path): string
{
    return (string) file_get_contents(base_path($path));
}

function productionQueueCommand(): string
{
    expect(productionFile('docker/8.5/supervisord.prod.conf'))
        ->toMatch('/\[program:queue\]\s*\ncommand=(.+)/');

    preg_match('/\[program:queue\]\s*\ncommand=(.+)/', productionFile('docker/8.5/supervisord.prod.conf'), $matches);

    return $matches[1];
}

test('production image installs a pg_dump matching the postgres server major version', function (): void {
    $compose = productionFile('docker-compose.prod.yml');
    $dockerfile = productionFile('docker/8.5/Dockerfile.prod');

    expect(preg_match('/image:\s+[\'"]?postgres:(\d+)/', $compose, $matches))->toBe(1);

    $majorVersion = $matches[1];

    expect($dockerfile)
        ->toContain('noble-pgdg')
        ->toContain('postgresql-client-$POSTGRES_VERSION')
        ->toContain('ARG POSTGRES_VERSION='.$majorVersion)
        ->and($compose)->toContain('POSTGRES_VERSION: '.$majorVersion);
});

test('queue worker runs once per job and never times out before the job does', function (): void {
    $command = productionQueueCommand();
    $jobTimeout = (new ReflectionClass(GenerateMealPlanJob::class))->getProperty('timeout')->getDefaultValue();

    expect($command)->toContain('queue:work')->toContain('--tries=1');

    if (preg_match('/--timeout=(\d+)/', $command, $matches) === 1) {
        expect((int) $matches[1])->toBeGreaterThanOrEqual($jobTimeout);
    }

    expect($jobTimeout)->toBeLessThan((int) config('queue.connections.database.retry_after'));

    expect(preg_match('/\[program:queue\].*?stopwaitsecs=(\d+)/s', productionFile('docker/8.5/supervisord.prod.conf'), $stop))->toBe(1)
        ->and((int) $stop[1])->toBeGreaterThanOrEqual($jobTimeout);
});

test('supervisord exposes a control socket for supervisorctl', function (): void {
    expect(productionFile('docker/8.5/supervisord.prod.conf'))
        ->toContain('[unix_http_server]')
        ->toContain('[supervisorctl]')
        ->toContain('[rpcinterface:supervisor]');
});

test('compose app and pgsql services are wired for the runtime user and database password', function (): void {
    $compose = productionFile('docker-compose.prod.yml');

    expect($compose)
        ->toContain('WWWUSER: ${WWWUSER:-1000}')
        ->toContain('SUPERVISOR_PHP_USER: sail')
        ->toContain('POSTGRES_PASSWORD: ${DB_PASSWORD}');
});

test('production image frees uid 1000 so start-container can move sail to WWWUSER', function (): void {
    $dockerfile = productionFile('docker/8.5/Dockerfile.prod');

    $userdel = strpos($dockerfile, 'userdel -r ubuntu');
    $useradd = strpos($dockerfile, 'useradd');

    expect($userdel)->not->toBeFalse()
        ->and($useradd)->not->toBeFalse()
        ->and($userdel)->toBeLessThan($useradd);
});

test('production image builds the client bundle only', function (): void {
    $dockerfile = productionFile('docker/8.5/Dockerfile.prod');

    expect(preg_match('/^RUN npx vite build\s*$/m', $dockerfile))->toBe(1)
        ->and($dockerfile)->not->toContain('--ssr');
});

test('example production env matches the compose stack', function (): void {
    $env = productionFile('.env.prod.example');
    $compose = productionFile('docker-compose.prod.yml');

    expect($env)
        ->toContain("INERTIA_SSR_ENABLED=false\n")
        ->toContain("APP_DEBUG=false\n")
        ->toContain("QUEUE_CONNECTION=database\n");

    preg_match('/^APP_URL=(\S+)/m', $env, $url);
    preg_match('/Host\(`([^`]+)`\)/', $compose, $host);

    expect(parse_url($url[1], PHP_URL_HOST))->toBe($host[1]);
});
