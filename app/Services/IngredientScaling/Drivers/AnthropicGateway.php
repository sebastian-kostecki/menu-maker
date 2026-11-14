<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling\Drivers;

use Anthropic\Client;
use Anthropic\Messages\JSONOutputFormat;
use Anthropic\Messages\OutputConfig;
use Anthropic\Messages\TextBlock;
use Anthropic\RequestOptions;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Thin wrapper over the Anthropic SDK so the driver can be tested without network.
 */
class AnthropicGateway
{
    private const MAX_TOKENS = 8192;

    public function __construct(
        private readonly ?string $key,
        private readonly ?string $model,
        private readonly string $effort,
        private readonly int $timeout,
    ) {}

    /**
     * @param  array<string, mixed>  $schema
     * @return array{text: string, stop_reason: ?string}
     */
    public function complete(string $system, string $userMessage, array $schema): array
    {
        $message = $this->makeClient()->messages->create(
            maxTokens: self::MAX_TOKENS,
            messages: [['role' => 'user', 'content' => $userMessage]],
            model: (string) $this->model,
            outputConfig: OutputConfig::with(effort: $this->effort, format: JSONOutputFormat::with(schema: $schema)),
            system: $system,
        );

        $text = '';

        foreach ($message->content as $block) {
            if ($block instanceof TextBlock) {
                $text .= $block->text;
            }
        }

        return ['text' => $text, 'stop_reason' => $message->stopReason];
    }

    /**
     * The SDK does not enforce a request timeout itself, so the Guzzle transport does; the SDK's own retries are off.
     */
    public function makeClient(): Client
    {
        return new Client(
            apiKey: (string) $this->key,
            requestOptions: RequestOptions::with(
                timeout: (float) $this->timeout,
                maxRetries: 0,
                transporter: new GuzzleClient([
                    'timeout' => $this->timeout,
                    'connect_timeout' => $this->timeout,
                ]),
            ),
        );
    }
}
