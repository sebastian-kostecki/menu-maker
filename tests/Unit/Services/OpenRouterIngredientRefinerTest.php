<?php

declare(strict_types=1);

use App\Services\IngredientScaling\Drivers\OpenRouterIngredientRefiner;
use App\Services\IngredientScaling\RefinementConfigurationException;
use App\Services\IngredientScaling\RefinementFailedException;
use App\Services\IngredientScaling\RefinementItem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

function openRouterItems(): array
{
    return [new RefinementItem(1, 'Mąka', 'g', '203.40'), new RefinementItem(2, 'Jajka', 'szt', '2.37')];
}

function openRouterRefiner(?string $key = 'sk-test', ?string $model = 'vendor/model'): OpenRouterIngredientRefiner
{
    return new OpenRouterIngredientRefiner($key, $model, 'https://openrouter.ai/api/v1', 20);
}

function openRouterReply(string $content): array
{
    return ['choices' => [['message' => ['content' => $content]]]];
}

it('sends the model, the schema and only ingredient data, and parses the answer', function (): void {
    Http::fake(['openrouter.ai/*' => Http::response(openRouterReply(json_encode(['items' => [
        ['key' => 'i1-g-203.40', 'quantity' => 200],
        ['key' => 'i2-szt-2.37', 'quantity' => 2],
    ]])))]);

    $result = openRouterRefiner()->refine(openRouterItems());

    expect($result)->toBe(['i1-g-203.40' => '200.0000', 'i2-szt-2.37' => '2.0000']);
    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer sk-test')
            && $body['model'] === 'vendor/model'
            && $body['response_format']['type'] === 'json_schema'
            && $body['response_format']['json_schema']['strict'] === true
            && $body['response_format']['json_schema']['schema']['required'] === ['items']
            && str_contains($body['messages'][1]['content'], 'Mąka')
            && ! str_contains(json_encode($body), 'first_name');
    });
});

it('fails on a missing key or model without any request', function (?string $key, ?string $model): void {
    expect(fn () => openRouterRefiner($key, $model)->refine(openRouterItems()))
        ->toThrow(RefinementConfigurationException::class);
    Http::assertNothingSent();
})->with([
    'no key' => [null, 'vendor/model'],
    'empty key' => ['', 'vendor/model'],
    'no model' => ['sk-test', null],
]);

it('fails on malformed JSON content', function (): void {
    Http::fake(['openrouter.ai/*' => Http::response(openRouterReply('not json'))]);

    expect(fn () => openRouterRefiner()->refine(openRouterItems()))->toThrow(RefinementFailedException::class);
});

it('fails on an unknown, missing, duplicate or non-numeric answer', function (array $items): void {
    Http::fake(['openrouter.ai/*' => Http::response(openRouterReply(json_encode(['items' => $items])))]);

    expect(fn () => openRouterRefiner()->refine(openRouterItems()))->toThrow(RefinementFailedException::class);
})->with([
    'unknown key' => [[['key' => 'i1-g-203.40', 'quantity' => 200], ['key' => 'zzz', 'quantity' => 2]]],
    'missing key' => [[['key' => 'i1-g-203.40', 'quantity' => 200]]],
    'duplicate key' => [[['key' => 'i1-g-203.40', 'quantity' => 200], ['key' => 'i1-g-203.40', 'quantity' => 201]]],
    'non-numeric' => [[['key' => 'i1-g-203.40', 'quantity' => 'dużo'], ['key' => 'i2-szt-2.37', 'quantity' => 2]]],
]);

it('fails on a 500 response', function (): void {
    Http::fake(['openrouter.ai/*' => Http::response('oops', 500)]);

    expect(fn () => openRouterRefiner()->refine(openRouterItems()))->toThrow(RefinementFailedException::class, 'HTTP 500');
});

it('fails on a timeout', function (): void {
    Http::fake(['openrouter.ai/*' => fn () => throw new ConnectionException('timed out')]);

    expect(fn () => openRouterRefiner()->refine(openRouterItems()))->toThrow(RefinementFailedException::class);
});
