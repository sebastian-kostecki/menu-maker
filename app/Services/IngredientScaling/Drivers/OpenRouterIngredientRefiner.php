<?php

declare(strict_types=1);

namespace App\Services\IngredientScaling\Drivers;

use App\Services\IngredientScaling\IngredientRefiner;
use App\Services\IngredientScaling\RefinementConfigurationException;
use App\Services\IngredientScaling\RefinementFailedException;
use App\Services\IngredientScaling\RefinementResponseParser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Refines quantities through OpenRouter's chat completions endpoint with strict JSON-schema output.
 */
class OpenRouterIngredientRefiner implements IngredientRefiner
{
    public function __construct(
        private readonly ?string $key,
        private readonly ?string $model,
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    public function refine(array $items): array
    {
        if ($this->key === null || $this->key === '' || $this->model === null || $this->model === '') {
            throw new RefinementConfigurationException('OpenRouter API key or model is not configured.');
        }

        try {
            $response = Http::withToken($this->key)
                ->acceptJson()
                ->timeout($this->timeout)
                ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => RefinementPrompt::SYSTEM],
                        ['role' => 'user', 'content' => RefinementPrompt::userMessage($items)],
                    ],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'ingredient_refinement',
                            'strict' => true,
                            'schema' => RefinementPrompt::schema(),
                        ],
                    ],
                ])
                ->throw();
        } catch (ConnectionException $e) {
            throw new RefinementFailedException('OpenRouter request timed out or could not connect.', 0, $e);
        } catch (RequestException $e) {
            throw new RefinementFailedException('OpenRouter responded with HTTP '.$e->response->status().'.', 0, $e);
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content)) {
            throw new RefinementFailedException('OpenRouter response has no message content.');
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RefinementFailedException('OpenRouter response content is not valid JSON.');
        }

        return RefinementResponseParser::parse($decoded, $items);
    }
}
