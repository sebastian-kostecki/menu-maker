<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling\Drivers;

use App\Services\IngredientScaling\IngredientRefiner;
use App\Services\IngredientScaling\RefinementConfigurationException;
use App\Services\IngredientScaling\RefinementFailedException;
use App\Services\IngredientScaling\RefinementResponseParser;
use Throwable;

/**
 * Refines quantities through the Anthropic Messages API with structured output.
 */
class AnthropicIngredientRefiner implements IngredientRefiner
{
    public function __construct(
        private readonly AnthropicGateway $gateway,
        private readonly ?string $key,
        private readonly ?string $model,
    ) {}

    public function refine(array $items): array
    {
        if ($this->key === null || $this->key === '' || $this->model === null || $this->model === '') {
            throw new RefinementConfigurationException('Anthropic API key or model is not configured.');
        }

        try {
            $result = $this->gateway->complete(
                RefinementPrompt::SYSTEM,
                RefinementPrompt::userMessage($items),
                RefinementPrompt::schema(),
            );
        } catch (RefinementFailedException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RefinementFailedException('Anthropic request failed ('.$e::class.').', 0, $e);
        }

        if (in_array($result['stop_reason'], ['refusal', 'max_tokens'], true)) {
            throw new RefinementFailedException('Anthropic stopped with '.$result['stop_reason'].'.');
        }

        $decoded = json_decode($result['text'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RefinementFailedException('Anthropic response is not valid JSON.');
        }

        return RefinementResponseParser::parse($decoded, $items);
    }
}
