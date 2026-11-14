<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->payload = [
        'message' => 'Cannot read properties of undefined',
        'stack' => 'TypeError: at Foo.vue:10',
        'url' => '/recipes',
        'source' => 'vue',
    ];
});

afterEach(function (): void {
    Context::flush();
});

it('accepts a valid report and logs exactly one warning', function (): void {
    Log::spy();

    $this->postJson(route('client-errors.store'), $this->payload)
        ->assertNoContent();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Client error'
            && $context['message'] === 'Cannot read properties of undefined'
            && $context['url'] === '/recipes');
});

it('carries the signed-in user id in the log context', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('client-errors.store'), $this->payload)
        ->assertNoContent();

    expect(Context::get('user_id'))->toBe($user->id);
});

it('accepts a report from a guest', function (): void {
    $this->postJson(route('client-errors.store'), $this->payload)
        ->assertNoContent();

    expect(Context::has('user_id'))->toBeFalse();
});

it('rejects invalid or oversized fields', function (array $override, string $field): void {
    $this->postJson(route('client-errors.store'), array_merge($this->payload, $override))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing message' => [['message' => ''], 'message'],
    'long message' => [['message' => str_repeat('a', 501)], 'message'],
    'long stack' => [['stack' => str_repeat('a', 2001)], 'stack'],
    'missing url' => [['url' => ''], 'url'],
    'long url' => [['url' => str_repeat('a', 1001)], 'url'],
    'unknown source' => [['source' => 'other'], 'source'],
]);

it('keeps client-supplied line breaks out of the log message and context', function (): void {
    Log::spy();

    $this->postJson(route('client-errors.store'), array_merge($this->payload, [
        'message' => "oops\n[2026-01-01 00:00:00] production.CRITICAL: forged entry",
        'stack' => "Error: x\n    at Foo.vue:10\r\n    at Bar.vue:2",
    ]))->assertNoContent();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Client error'
            && ! preg_match('/[\r\n]/', $context['message'])
            && ! preg_match('/[\r\n]/', $context['stack'])
            && str_contains($context['stack'], ' | '));
});

it('logs only the path of the page, never the query string or hash', function (): void {
    Log::spy();

    $this->postJson(route('client-errors.store'), array_merge($this->payload, [
        'url' => '/reset-password/secret-token?email=jan%40example.com#frag',
    ]))->assertNoContent();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $context['url'] === '/reset-password/secret-token');
});

it('throttles the eleventh request within a minute', function (): void {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson(route('client-errors.store'), $this->payload)->assertNoContent();
    }

    $this->postJson(route('client-errors.store'), $this->payload)->assertTooManyRequests();
});

it('does not send an alert e-mail', function (): void {
    config([
        'logging.default' => 'mail_alert',
        'logging.channels.mail_alert.to' => 'ops@example.com',
        'logging.channels.mail_alert.cache_store' => 'array',
    ]);
    Log::forgetChannel('mail_alert');
    Mail::forgetMailers();

    $this->postJson(route('client-errors.store'), $this->payload)->assertNoContent();

    expect(Mail::mailer()->getSymfonyTransport()->messages()->all())->toBeEmpty();
});
