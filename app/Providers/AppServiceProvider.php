<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\IngredientScaling\Drivers\AnthropicGateway;
use App\Services\IngredientScaling\Drivers\AnthropicIngredientRefiner;
use App\Services\IngredientScaling\Drivers\FakeIngredientRefiner;
use App\Services\IngredientScaling\Drivers\OpenRouterIngredientRefiner;
use App\Services\IngredientScaling\IngredientRefiner;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AnthropicGateway::class, fn (Application $app): AnthropicGateway => new AnthropicGateway(
            config('services.ingredient_refiner.anthropic.key'),
            config('services.ingredient_refiner.anthropic.model'),
            (string) config('services.ingredient_refiner.anthropic.effort'),
            (int) config('services.ingredient_refiner.timeout'),
        ));

        $this->app->bind(IngredientRefiner::class, function (Application $app): IngredientRefiner {
            $driver = config('services.ingredient_refiner.driver');

            return match ($driver) {
                'openrouter' => new OpenRouterIngredientRefiner(
                    config('services.ingredient_refiner.openrouter.key'),
                    config('services.ingredient_refiner.openrouter.model'),
                    (string) config('services.ingredient_refiner.openrouter.base_url'),
                    (int) config('services.ingredient_refiner.timeout'),
                ),
                'anthropic' => new AnthropicIngredientRefiner(
                    $app->make(AnthropicGateway::class),
                    config('services.ingredient_refiner.anthropic.key'),
                    config('services.ingredient_refiner.anthropic.model'),
                ),
                'fake' => new FakeIngredientRefiner,
                default => throw new InvalidArgumentException("Unknown AI_SCALING_DRIVER [{$driver}]."),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
