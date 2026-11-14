<?php

declare(strict_types=1);

use Anthropic\Core\Exceptions\APIStatusException;
use App\Services\IngredientScaling\Drivers\AnthropicGateway;
use App\Services\IngredientScaling\Drivers\AnthropicIngredientRefiner;
use App\Services\IngredientScaling\RefinementConfigurationException;
use App\Services\IngredientScaling\RefinementFailedException;
use App\Services\IngredientScaling\RefinementItem;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

function anthropicItems(): array
{
    return [new RefinementItem(1, 'Mąka', 'g', '203.40')];
}

function anthropicRefinerWith(?array $reply, ?Throwable $throws = null, ?string $key = 'sk-ant'): AnthropicIngredientRefiner
{
    $gateway = Mockery::mock(AnthropicGateway::class);
    $expectation = $gateway->shouldReceive('complete');
    $throws === null ? $expectation->andReturn($reply) : $expectation->andThrow($throws);

    return new AnthropicIngredientRefiner($gateway, $key, 'claude-opus-5-5');
}

it('parses a structured answer from the gateway', function (): void {
    $text = json_encode(['items' => [['key' => 'i1-g-203.40', 'quantity' => 200]]]);

    $result = anthropicRefinerWith(['text' => $text, 'stop_reason' => 'end_turn'])->refine(anthropicItems());

    expect($result)->toBe(['i1-g-203.40' => '200.0000']);
});

it('fails on refusal and max_tokens stop reasons', function (string $reason): void {
    expect(fn () => anthropicRefinerWith(['text' => '{}', 'stop_reason' => $reason])->refine(anthropicItems()))
        ->toThrow(RefinementFailedException::class, $reason);
})->with(['refusal', 'max_tokens']);

it('fails on non-JSON text and on a gateway error', function (): void {
    expect(fn () => anthropicRefinerWith(['text' => 'nope', 'stop_reason' => 'end_turn'])->refine(anthropicItems()))
        ->toThrow(RefinementFailedException::class);
    expect(fn () => anthropicRefinerWith(null, new RuntimeException('network'))->refine(anthropicItems()))
        ->toThrow(RefinementFailedException::class);
});

it('maps an SDK status error to a configuration error only when it is a permanent 4xx', function (int $status, bool $retryable): void {
    $error = APIStatusException::from(new Request('POST', 'https://api.anthropic.com/v1/messages'), new Response($status));

    try {
        anthropicRefinerWith(null, $error)->refine(anthropicItems());
        $this->fail('Expected a RefinementFailedException.');
    } catch (RefinementFailedException $e) {
        expect($e->isRetryable())->toBe($retryable)->and($e->getMessage())->toContain("HTTP {$status}");
    }
})->with([
    'unauthorized' => [401, false],
    'bad request' => [400, false],
    'rate limited' => [429, true],
    'overloaded' => [529, true],
]);

it('treats a missing key as a non-retryable configuration error', function (): void {
    $refiner = anthropicRefinerWith(null, null, '');

    expect(fn () => $refiner->refine(anthropicItems()))->toThrow(RefinementConfigurationException::class);
});

it('builds an SDK client with no SDK retries and a 20 s transport timeout', function (): void {
    $client = (new AnthropicGateway('sk-ant', 'claude-opus-5-5', 'low', 20))->makeClient();

    $options = $client->mergedOptions(null);
    expect($options->maxRetries)->toBe(0)
        ->and($options->transporter)->toBeInstanceOf(GuzzleClient::class)
        ->and($options->transporter->getConfig('timeout'))->toBe(20)
        ->and($options->transporter->getConfig('connect_timeout'))->toBe(20);
});
