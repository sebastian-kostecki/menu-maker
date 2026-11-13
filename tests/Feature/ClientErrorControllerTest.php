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
        'url' => 'https://app.test/recipes',
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
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Cannot read properties of undefined')
            && $context['url'] === 'https://app.test/recipes');
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
    'long message' => [['message' => str_repeat('a', 1001)], 'message'],
    'long stack' => [['stack' => str_repeat('a', 5001)], 'stack'],
    'missing url' => [['url' => ''], 'url'],
    'long url' => [['url' => str_repeat('a', 2001)], 'url'],
    'unknown source' => [['source' => 'other'], 'source'],
]);

it('throttles the eleventh request within a minute', function (): void {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson(route('client-errors.store'), $this->payload)->assertNoContent();
    }

    $this->postJson(route('client-errors.store'), $this->payload)->assertTooManyRequests();
});

it('does not send an alert e-mail', function (): void {
    $dedupStore = tempnam(sys_get_temp_dir(), 'client-error-dedup-');
    unlink($dedupStore);

    config([
        'logging.default' => 'mail_alert',
        'logging.channels.mail_alert.to' => 'ops@example.com',
        'logging.channels.mail_alert.dedup_store' => $dedupStore,
    ]);
    Log::forgetChannel('mail_alert');
    Mail::forgetMailers();

    $this->postJson(route('client-errors.store'), $this->payload)->assertNoContent();

    Log::channel('mail_alert')->getLogger()->close();

    expect(Mail::mailer()->getSymfonyTransport()->messages()->all())->toBeEmpty();

    @unlink($dedupStore);
});
