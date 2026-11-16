<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// In production the request comes from Traefik on the Docker proxy network, with Cloudflare's
// X-Forwarded-For passed on: "<whatever the visitor sent>, <visitor>, <Docker gateway>".
beforeEach(function () {
    Route::get('/_test/forwarded', fn (Request $request) => [
        'ip' => $request->ip(),
        'secure' => $request->secure(),
        'host' => $request->getHost(),
    ]);
});

test('the client IP is the visitor Cloudflare saw, not a forged or proxy address', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
        ->withHeaders([
            'X-Forwarded-For' => '203.0.113.9, 198.51.100.7, 172.18.0.1',
            'X-Forwarded-Proto' => 'https',
        ])
        ->getJson('/_test/forwarded')
        ->assertOk()
        ->assertJson(['ip' => '198.51.100.7', 'secure' => true]);
});

test('a forwarded host is ignored, so links keep the real host', function () {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
        ->withHeaders([
            'X-Forwarded-For' => '198.51.100.7',
            'X-Forwarded-Host' => 'evil.example',
            'X-Forwarded-Port' => '8443',
        ])
        ->getJson('/_test/forwarded')
        ->assertOk();

    expect($response->json('host'))->not->toBe('evil.example');
});

test('forwarded headers from outside the Docker networks are ignored', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->withHeaders([
            'X-Forwarded-For' => '203.0.113.9',
            'X-Forwarded-Proto' => 'https',
        ])
        ->getJson('/_test/forwarded')
        ->assertOk()
        ->assertJson(['ip' => '198.51.100.20', 'secure' => false]);
});

test('requests forwarded as https by the proxy redirect to https and count as secure', function () {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('/dashboard');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://');

    $request = request();
    expect($request->secure())->toBeTrue();
});
