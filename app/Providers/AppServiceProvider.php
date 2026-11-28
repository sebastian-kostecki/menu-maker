<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\IngredientScaling\Drivers\AnthropicGateway;
use App\Services\IngredientScaling\Drivers\AnthropicIngredientRefiner;
use App\Services\IngredientScaling\Drivers\FakeIngredientRefiner;
use App\Services\IngredientScaling\Drivers\OpenRouterIngredientRefiner;
use App\Services\IngredientScaling\IngredientRefiner;
use Carbon\CarbonInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use InvalidArgumentException;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\Scope;
use Symfony\Component\HttpFoundation\Response;

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

        // No client uses the device grant and it has no views. Set here: Passport registers its routes on boot.
        Passport::$deviceCodeGrantEnabled = false;
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->configurePassport();

        RateLimiter::for('mcp', fn (Request $request): Limit => Limit::perMinute(60)->by(
            (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ));
    }

    /**
     * Passport is the OAuth server of the remote MCP endpoint: short-lived tokens, Inertia consent page.
     * `mcp:use` is the default scope, so a token request without `scope` still gets a token `/mcp` accepts.
     */
    private function configurePassport(): void
    {
        Passport::tokensCan(['mcp:use' => 'Dodawanie przepisów do Twojej bazy']);
        Passport::defaultScopes(['mcp:use']);
        Passport::tokensExpireIn(CarbonInterval::hour());
        Passport::refreshTokensExpireIn(CarbonInterval::days(30));

        Passport::authorizationView(
            /**
             * @param  array{client: Client, scopes: array<int, Scope>, request: Request, authToken: string}  $parameters
             */
            fn (array $parameters): Response => Inertia::render('Auth/OAuthAuthorize', [
                'client_name' => $parameters['client']->name,
                'client_id' => (string) $parameters['client']->getKey(),
                'scopes' => array_values(array_map(
                    fn (Scope $scope): string => $scope->description,
                    $parameters['scopes'],
                )),
                'redirect_host' => $this->redirectHost($parameters['request'], $parameters['client']),
                'auth_token' => $parameters['authToken'],
                'state' => $parameters['request']->input('state'),
                'csrf_token' => csrf_token(),
            ])->toResponse($parameters['request']),
        );
    }

    /**
     * Where the answer of the consent page goes (host, with the port when given). The client chooses its own
     * name, so this is what tells the user who really gets access.
     */
    private function redirectHost(Request $request, Client $client): string
    {
        $uri = (string) ($request->input('redirect_uri') ?: ($client->redirect_uris[0] ?? ''));
        $port = parse_url($uri, PHP_URL_PORT);

        return parse_url($uri, PHP_URL_HOST).($port ? ":{$port}" : '');
    }
}
