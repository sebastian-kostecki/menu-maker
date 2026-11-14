<?php

declare(strict_types=1);

test('requests forwarded as https by the proxy redirect to https and count as secure', function () {
    $response = $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'menu.kostecki.dev',
        'X-Forwarded-Port' => '443',
    ])->get('/dashboard');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://');

    $request = request();
    expect($request->secure())->toBeTrue();
});
