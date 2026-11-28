# Recipes over MCP (add-recipe-tool) Implementation Plan

## Overview

Menu Maker gets an MCP server with a single write tool, `add-recipe-tool`. An MCP client (Claude) reads a recipe from text the user pastes or from a web page the user names, converts it to the app's model (total kcal, servings, ingredients in g/kg/ml/l/szt, steps), shows it to the user, and calls the tool. The app validates it with the same limits as the recipe form, rejects a name the user already has, and saves it for the signed-in owner. The server is served locally over stdio (Claude Code through Sail) and remotely at `/mcp` behind Passport OAuth (claude.ai, Claude Desktop, the phone).

The app does no extraction of its own: no AI call, no fetching of the URL, no queue job. Issues #40 (URL import in the UI) and #41 (text through AI in the UI) stay separate work.

## Current State Analysis

- **Saving already exists outside HTTP.** `RecipeImporter::import(User, ParsedRecipe): Recipe` (`app/Services/RecipeImport/RecipeImporter.php:30`) creates the recipe through `$user->recipes()` and syncs ingredients through `Ingredient::resolveIdByName` in one transaction. `existsFor(User, string)` (`:22`) is the case-insensitive duplicate check over the owner's non-deleted recipes. `import()` does **not** validate. It only refuses a `ParsedRecipe` whose `errors` is non-empty.
- **`ParsedRecipe` / `ParsedIngredient`** (`app/Services/RecipeImport/ParsedRecipe.php`, `ParsedIngredient.php`) are readonly DTOs. `calories` and `quantity` are numeric-strings, `calories` is **total kcal of the whole recipe**, and `unitCode` is one of `Unit::ALLOWED_CODES` (`app/Models/Unit.php:15`).
- **The form's rules are tied to Form Requests.**
  - The scalar rules sit inline in `StoreRecipeRequest::rules()` (`app/Http/Requests/StoreRecipeRequest.php:31-37`): name `max:255`, category `in:breakfast,supper,dinner`, instructions required, calories `numeric|min:50`, servings `integer|min:1`, with Polish messages at `:59-71`.
  - The ingredient rules sit in `Concerns/ValidatesRecipeIngredients` (`app/Http/Requests/Concerns/ValidatesRecipeIngredients.php:21-30`) and are keyed by `unit_id`, not by unit code. `validateUniqueIngredientNames(Validator)` (`:60`) only needs a `Validator`.
- **The Markdown importer already writes the source and the conversion table.** `MarkdownRecipeParser` appends `"\n\nŹródło: {source}"` to the instructions. Its `UNIT_ALIASES` table (`app/Services/RecipeImport/MarkdownRecipeParser.php:32-57`) is: łyżeczka 5 ml, łyżka 15 ml, szklanka 250 ml, dag 10 g, szczypta 0.5 g.
- **No machine interface exists.**
  - Sanctum is installed but not wired up: no `HasApiTokens` on `User` (`app/Models/User.php:15-18`), no `personal_access_tokens` migration, no `routes/api.php`.
  - Passport is not installed.
  - `laravel/mcp` v1.2 is in vendor only as a dev dependency of Boost. The production image runs `composer install --no-dev` (`docker/8.5/Dockerfile.prod:9`).
- **Routing and exceptions.** `bootstrap/app.php` routes `web.php`, `console.php` and `/up` only, and the `withExceptions` closure is empty.
- **Login.** Breeze's `AuthenticatedSessionController::store` returns `redirect()->intended(...)` (`app/Http/Controllers/Auth/AuthenticatedSessionController.php:36`) to an Inertia `useForm().post` (`resources/js/Pages/Auth/Login.vue:29`).
- **Deploy.** `scripts/deploy.sh:56-57` runs `config:cache` and `route:cache`. Production is `https://menu.kostecki.dev` behind Traefik and Cloudflare (`.env.prod.example:13`).
- **Reference implementation.** `household-treasurer` (sibling repo) runs the same stack: `laravel/mcp ^1.2` + `laravel/passport ^13`, `routes/ai.php`, `LocalTreasurerServer`, consent page, `LogMcpCall`, `tests/Feature/Mcp/*`, `tests/Support/PassportKeys`. Its ADR-0110 records why Sanctum was rejected and lists the gotchas this plan carries over.
- **PRD conflict.** `.ai/core/prd.md:42` (§4 "Granice produktu") says "Brak importu przepisów z URL", and `context/foundation/roadmap.md` parks "Import przepisów z URL".

## Desired End State

- From Claude Code (local, through Sail) and from claude.ai, Desktop or the phone (production, through OAuth), the user can say "dodaj ten przepis: <url or text>". Claude:
  1. shows a preview (name, category, total kcal, marked as estimated when it guessed, servings, ingredients with converted units, steps);
  2. asks for approval, which the client forces through `requiresUserInteraction`;
  3. calls `add-recipe-tool`;
  4. answers with a link to the saved recipe.
- The saved recipe belongs to the signed-in user, passes every limit of the recipe form, and shows up in Przepisy like any other.
- A name the user already has (case-insensitive, whitespace-normalized) is refused with the existing recipe's link, and nothing is created.
- `/mcp` without a token answers a JSON 401 with `WWW-Authenticate` discovery. A token without `mcp:use` gets 403. More than 60 calls a minute gets 429. Every call is logged as one line without arguments.
- PRD §4 and the roadmap say that adding a recipe over MCP is allowed, while URL import in the app UI stays with #40.

Verify: `vendor/bin/sail composer test`, `composer phpstan`, `composer pint-test`, `npm run build`, `npm run lint:tokens`, plus the manual checks in Phases 1 and 3.

### Key Discoveries:

- Build a `ParsedRecipe` with `warnings: []`, `errors: []` and call `RecipeImporter::import()`. Do not add a third copy of the persistence code (`RecipeImporter.php:30-62`; the controller holds the second copy).
- `Ingredient::resolveIdByName` (`app/Models/Ingredient.php:56`) is race-safe and case-insensitive, and creates non-catalog ingredients for new names. The tool inherits that.
- Over stdio there is no middleware, so `$request->user()` is null unless the local server pins the user in `boot()` with `Auth::guard('web')->setUser()`, never `Auth::login`, which writes the session (household-treasurer `app/Mcp/Servers/LocalTreasurerServer.php`).
- `auth:api` checks no scope. Dynamic client registration grants none either, so `CheckToken::using('mcp:use')` plus `Passport::defaultScopes(['mcp:use'])` are both required (household-treasurer impl-review).
- Passport `report()`s every expired token. `dontReport(OAuthServerException::class)` keeps `mail_alert` (`config/logging.php:79-87`, error level) quiet.

## What We're NOT Doing

- No server-side extraction: no URL fetching, no JSON-LD parsing, no AI prompt, no SSRF guard, no queue job. That stays the shared core of #40 and #41.
- No read, search, update or delete tools. No MCP resources or prompts.
- No pending import or draft table, and no review page in the app. Approval happens in the chat before the call.
- No server-side unit conversion and no Polish unit words in the schema. `MarkdownRecipeParser` stays untouched.
- No storage of a "kcal estimated" flag. The estimate is only disclosed in the chat preview.
- No `allow_duplicate` override and no `request_id` idempotency table. The duplicate-name refusal is what makes a retry safe.
- No change to the web form: it keeps allowing duplicate names.
- No Sanctum wiring and no API-token UI on the profile page.
- No pruning of unused OAuth clients. Registration is limited to 10 requests a minute per IP.
- No `MCP_WRITE_ENABLED` switch. The only tool is the write tool, so the server's existence is the switch.
- No new E2E or visual-gate spec for the consent page. It is covered by feature tests and the Phase 3 manual run.

## Implementation Approach

1. **Phase 1** builds the server and the tool against the local stdio transport, so the whole flow (Claude extracts, the tool validates and saves) can be tried from Claude Code before any public endpoint exists. Validation reuses the form's limits through extracted concerns, so the two cannot drift.
2. **Phase 2** adds the public `/mcp` route with Passport OAuth, copying the proven household-treasurer setup and its fixes.
3. **Phase 3** updates the product docs and rolls out with a real claude.ai connection.

## Critical Implementation Details

- **Keep the MCP server off the web group.** `routes/ai.php` is loaded by `laravel/mcp` outside the `web` group (no session, no CSRF). Tools must never read the session. The `/oauth/*` approve and deny routes from Passport are web routes and need the session; that is expected.
- **Use one owner path everywhere.** Every query in the tool goes through the user from `$request->user()` (`forUser($user->id)` for the duplicate check, `$user->recipes()` for the create). There is no id argument that could point at another user's data.
- **Login must leave the Inertia XHR for OAuth.** When the session's `url.intended` path starts with `/oauth/authorize`, the login response must be `Inertia::location($intended)`, a full-page navigation, after forgetting `url.intended`. Otherwise the XHR follows Passport's 302 to the external `redirect_uri` and the client never receives the code.
- **Give each test its own Passport keys.** The `api` guard builds its resource server from the keys on every request, so `tests/TestCase.php` installs a throwaway keypair per test (household-treasurer `tests/Support/PassportKeys.php`). Never commit real keys. Production reads `PASSPORT_PRIVATE_KEY`/`PASSPORT_PUBLIC_KEY` from env, because a key file generated by `deploy.sh` would belong to root and FPM could not read it.

## Phase 1: Local MCP server with add-recipe-tool

### Overview

`laravel/mcp` becomes a production dependency. `RecipeServer` exposes `add-recipe-tool`, and `LocalRecipeServer` serves it over stdio for the user named in `MCP_LOCAL_USER`. The tool validates with the form's limits, refuses a duplicate name with a link, and saves through `RecipeImporter`.

### Changes Required:

#### 1. Dependency and route file

**File**: `composer.json`, `composer.lock`, `routes/ai.php` (new)

**Intent**: Move `laravel/mcp` into `require` so the production image (`--no-dev`) has it, and register the local server.

**Contract**:
- `"laravel/mcp": "^1.2"` in `require`, installed through `vendor/bin/sail composer require laravel/mcp:^1.2`.
- `routes/ai.php` contains `Mcp::local('menu-maker', LocalRecipeServer::class);`.
- Phase 2 adds the web route to the same file.

#### 2. Shared recipe validation

**File**: `app/Http/Requests/Concerns/ValidatesRecipeFields.php` (new), `app/Http/Requests/StoreRecipeRequest.php`, `app/Http/Requests/Concerns/ValidatesRecipeIngredients.php`

**Intent**: Pull the scalar rules and Polish messages out of `StoreRecipeRequest`, and the name/quantity row rules out of the ingredient trait, so the MCP input is checked against exactly the form's limits. The Form Requests keep their current behaviour.

**Contract**:
- `ValidatesRecipeFields::recipeFieldRules(): array` returns the five scalar rules unchanged. `recipeFieldMessages(): array` returns their messages. `StoreRecipeRequest` uses the trait and spreads both.
- `ValidatesRecipeIngredients` gains `ingredientRowRules(): array` with the `ingredients`, `ingredients.*.name` and `ingredients.*.quantity` rules. `ingredientRules()` becomes `ingredientRowRules()` plus the `unit_id` rule.
- `UpdateRecipeRequest` is untouched.
- `tests/Unit/RecipeRequestValidationTest.php` stays green without edits.

#### 3. Structured recipe import

**File**: `app/Services/RecipeImport/StructuredRecipeImporter.php` (new), `app/Services/RecipeImport/RecipeImporter.php`

**Intent**: Turn already-structured input (the tool's arguments) into a stored recipe. It validates with the shared rules, normalizes the name, refuses a duplicate, maps the result to a `ParsedRecipe`, and calls `RecipeImporter::import()`. It sits apart from the tool, so it is testable without MCP and reusable by #40 and #41 later.

**Contract**:
- `final class StructuredRecipeImporter`, constructor-injected `RecipeImporter`.
- `import(User $user, array $input): Recipe`. It throws `ValidationException` on any failure:
  - Rules:
    - `recipeFieldRules()`;
    - `ingredientRowRules()`;
    - `ingredients.*.unit` as `required|in:` + `Unit::ALLOWED_CODES`;
    - `source_url` as `nullable|string|max:2048|url:http,https`;
    - `calories` additionally gets `max:99999999.99` (the `decimal(10,2)` column limit), in this importer only, not in the form. Without it a JSON float like `1e12` overflows the column and `1e20` reaches bcmath as `"1.0E+20"`, so the tool would throw instead of answering a validation error.
  - Messages: the shared Polish messages, plus one for `ingredients.*.unit` that names the five codes.
  - After-hook: `validateUniqueIngredientNames`.
  - The name is normalized with `Ingredient::normalizeName()` before the duplicate check and before saving.
  - Duplicate: `RecipeImporter` gains `findFor(User $user, string $name): ?Recipe`. It queries `forUser($user->id)` and compares `lower(regexp_replace(btrim(name), '\s+', ' ', 'g')) = lower(?)` with the normalized name, so a stored name with stray spaces also matches. `existsFor()` becomes `findFor(...) !== null`. If it finds a recipe, throw `ValidationException::withMessages(['name' => "Masz już przepis „{name}”: {url}"])`, where `{url}` is `route('recipes.show', $existing)`.
  - Instructions: when `source_url` is given, append `"\n\nŹródło: {source_url}"`, as `MarkdownRecipeParser` does.
  - Numbers: `calories` and each `quantity` are rounded half-up to 2 decimals as numeric-strings (bcmath, as the parser does) before building `ParsedIngredient`/`ParsedRecipe`.
- The class carries `declare(strict_types=1)`.

#### 4. MCP servers

**File**: `app/Mcp/Servers/RecipeServer.php` (new), `app/Mcp/Servers/LocalRecipeServer.php` (new), `config/services.php`, `.env.example`

**Intent**: One server class lists the tool and carries the instructions Claude needs to extract correctly. The local subclass pins the user for stdio, in the `local` environment only.

**Contract**:
- `RecipeServer` sets `#[Name('Menu Maker')]`, `#[Version('1.0.0')]`, and `$tools = [AddRecipeTool::class]`. Its `#[Instructions(...)]` is one English paragraph saying:
  - answer in Polish;
  - read the recipe from the text or the page yourself;
  - categories: śniadanie = `breakfast`, obiad = `supper`, kolacja = `dinner`;
  - `calories` is the **total for the whole recipe** (multiply per-serving by servings); when the source has none, estimate it from the ingredients and say in the preview that it is an estimate;
  - units only g/kg/ml/l/szt, with the conversion table łyżeczka 5 ml, łyżka 15 ml, szklanka 250 ml, dag 10 g, szczypta 0.5 g, and countable items in szt;
  - merge an ingredient that appears twice;
  - pass the page address as `source_url`;
  - always show the full preview and get approval before calling `add-recipe-tool`;
  - on a duplicate-name error, offer a different name or the existing link.
- `LocalRecipeServer extends RecipeServer`. Its `boot()` throws `RuntimeException` unless `app()->isLocal()`, resolves `config('services.mcp.local_user')` (an e-mail or an id) to a `User`, and calls `Auth::guard('web')->setUser($user)`.
- `config/services.php` gets `'mcp' => ['local_user' => env('MCP_LOCAL_USER')]`.
- `.env.example` gets a commented `MCP_LOCAL_USER=`.

#### 5. The tool

**File**: `app/Mcp/Tools/AddRecipeTool.php` (new)

**Intent**: A thin tool. It resolves the user, authorizes `create` on `Recipe` through the existing policy, delegates to `StructuredRecipeImporter`, and answers with a structured summary and link. The schema tells the client the exact shape and units.

**Contract**:
- Name `add-recipe-tool`, with `#[Description(...)]` in English summarizing the input rules.
- Annotations: `#[IsReadOnly(false)]`, `#[IsDestructive(false)]`, `#[IsIdempotent(false)]`.
- `protected ?array $meta = ['anthropic/requiresUserInteraction' => true]`.
- `schema()`:
  - `name`: string, required, max 255.
  - `category`: string enum `breakfast|supper|dinner`, required.
  - `calories`: number, `->min(50)->max(99999999.99)`, required, described as total kcal.
  - `servings`: integer, minimum 1, required.
  - `instructions`: string, required, one step per line.
  - `ingredients`: array, min 1, required. Its items are an object of `name` (string), `quantity` (number, `->min(0.01)`, as the form) and `unit` (enum of the five codes).
  - `source_url`: string, `->format('uri')`, optional.
  - Builder calls (`vendor/laravel/framework/src/Illuminate/JsonSchema`, verified): `$schema->array()->items($schema->object([...]))`, `->enum([...])`, inclusive `->min()`/`->max()` on numbers (there is no `exclusiveMinimum` method), `->format('uri')` on strings.
- `handle(Request $request): ResponseFactory`:
  - `$request->user()` must be a `User`, otherwise throw `AuthenticationException`.
  - Call `Gate::forUser($user)->authorize('create', Recipe::class)`.
  - Then `StructuredRecipeImporter::import($user, $request->all())`.
  - Return `Response::structured(['recipe' => [id, name, category, category_label, calories, servings, ingredient_count, url]])`. `category_label` comes from `MealCategory`, and `url` is the absolute `route('recipes.show', $recipe)`.
  - A `ValidationException` surfaces as a tool error (laravel/mcp's default).

#### 6. Tests

**File**: `tests/Feature/Mcp/AddRecipeToolTest.php` (new), `tests/Feature/Mcp/RecipeServerTest.php` (new), `tests/Unit/Services/StructuredRecipeImporterTest.php` (new)

**Intent**: Cover the behaviour the user decided on and the failure modes, through `RecipeServer::actingAs($user)->tool(AddRecipeTool::class, [...])`.

**Contract**:
- **Tool test:**
  - A valid call creates one recipe owned by the acting user, with its ingredients and unit codes mapped. A catalog ingredient is linked, not duplicated. `Źródło:` is appended when `source_url` is given. The structured `recipe.url` points at the new recipe.
  - A name equal to one of the user's recipes, differing only in case or whitespace, is refused with the existing link (`assertHasErrors`), and the recipe count stays the same. This includes a stored name with a double space, created directly through the factory.
  - The same name owned by another user, or soft-deleted by this user, does not block.
  - Refused inputs: calories 49; calories `1e12`; category `lunch`; unit `łyżka`; two ingredients with the same name; an empty ingredient list; `source_url` with `ftp://` or `javascript:`.
  - The annotations and `_meta.anthropic/requiresUserInteraction` match the contract.
  - The response never contains another user's recipe data (`assertDontSee`).
- **Server test:**
  - Only `add-recipe-tool` is registered.
  - `LocalRecipeServer` refuses to boot outside `local`, refuses an unknown or empty `MCP_LOCAL_USER`, and resolves by e-mail and by id.
- **Importer unit test:** rounding of calories and quantities, name normalization, and the `ValidationException` key for the duplicate (`name`).

### Success Criteria:

#### Automated Verification:

- `laravel/mcp` is listed under `require` in `composer.json`: `vendor/bin/sail composer show --direct laravel/mcp`
- New MCP tests pass: `vendor/bin/sail pest tests/Feature/Mcp tests/Unit/Services/StructuredRecipeImporterTest.php`
- Recipe form validation is unchanged: `vendor/bin/sail pest tests/Unit/RecipeRequestValidationTest.php tests/Feature/RecipeControllerTest.php`
- Full suite passes: `vendor/bin/sail composer test`
- Larastan passes with no baseline additions: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`
- The local server starts and lists the tool: `vendor/bin/sail artisan mcp:inspector menu-maker` or a `tools/list` over `mcp:start menu-maker` with `MCP_LOCAL_USER` set

#### Manual Verification:

- In Claude Code, connected with `claude mcp add menu-maker -- vendor/bin/sail artisan mcp:start menu-maker` (with `MCP_LOCAL_USER` set), adding a recipe from a pasted Polish text shows a preview, asks for approval and saves. The link opens the recipe in the local app with the right category, total kcal, servings and units.
- Adding from a recipe page URL that gives kcal per serving saves the total (per serving × servings). A page without kcal gets an estimate that the preview calls an estimate.
- Repeating the same recipe is refused with the link to the first one.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Remote /mcp behind Passport OAuth

### Overview

Install Passport as the OAuth authorization server for `/mcp`. It uses the `mcp:use` scope, dynamic client registration through `Mcp::oauthRoutes()`, an Inertia consent page in Polish, and the login redirect fix. It also adds JSON 401s, rate limits and one log line per call.

### Changes Required:

#### 1. Passport install

**File**: `composer.json`, `composer.lock`, `database/migrations/*_create_oauth_*_table.php` (published), `config/auth.php`, `app/Models/User.php`, `config/passport.php` (only if published), `.env.example`

**Intent**: Add Passport 13 with the keys taken from env, and make `User` an OAuth user, without touching Sanctum.

**Contract**:
- `vendor/bin/sail composer require laravel/passport:^13`, then publish Passport's migrations. They create the tables `oauth_auth_codes`, `oauth_access_tokens`, `oauth_refresh_tokens`, `oauth_clients` and `oauth_device_codes`.
- `config/auth.php` gets the guard `'api' => ['driver' => 'passport', 'provider' => 'users']`.
- `User` implements `Laravel\Passport\Contracts\OAuthenticatable` and uses `Laravel\Passport\HasApiTokens`, never Sanctum's trait. Its file gets `declare(strict_types=1)`.
- `.env.example` documents `PASSPORT_PRIVATE_KEY=` and `PASSPORT_PUBLIC_KEY=`: PEM text with `\n`, printed by `php artisan passport:keys` on a throwaway checkout.

#### 2. Passport, limiter and exception configuration

**File**: `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `config/mcp.php` (published), `config/logging.php`

**Intent**: Use short-lived tokens with the scope always granted, allow redirects only to Claude and loopback, rate-limit calls, keep expected OAuth failures out of `mail_alert`, and have `/mcp` always answer JSON.

**Contract**:
- `AppServiceProvider::boot()`:
  - `Passport::defaultScopes(['mcp:use'])`, so a token request without `scope` still gets it (as household-treasurer). `Mcp::oauthRoutes()` already declares the scope (`ensureMcpScope()` → `tokensCan`), and dynamic registration assigns it to the client. Call `Passport::tokensCan(['mcp:use' => 'Dodawanie przepisów do Twojej bazy'])` yourself only to give it the Polish description shown on the consent page.
  - `tokensExpireIn(1 hour)` and `refreshTokensExpireIn(30 days)`.
  - `Passport::authorizationView(fn (array $parameters) => Inertia::render('Auth/OAuthAuthorize', [...props])->toResponse(request()))`.
  - `RateLimiter::for('mcp', 60 per minute by user id, else IP)`.
- `config/mcp.php`: `redirect_domains` is exactly `https://claude.ai`, `https://claude.com` and `http://localhost`. Everything else is published as-is.
- `bootstrap/app.php`:
  - `withRouting` keeps its files. `routes/ai.php` is auto-loaded by laravel/mcp; confirm with `route:list`.
  - `withExceptions` gets `$exceptions->dontReport(OAuthServerException::class)` and `$exceptions->shouldRenderJsonWhen(fn (Request $r) => $r->is('mcp') || $r->expectsJson())`.
- `config/logging.php` gets a `mcp` channel: daily, `storage/logs/mcp.log`, level info, 14 files.

#### 3. The web endpoint and call logging

**File**: `routes/ai.php`, `app/Http/Middleware/LogMcpCall.php` (new)

**Intent**: Serve `RecipeServer` (never the local one) at `/mcp` behind the token and scope, and log every call, including refused ones.

**Contract**:
- Routes:
  - `Route::middleware('throttle:10,1')->group(fn () => Mcp::oauthRoutes());`
  - `Mcp::web('/mcp', RecipeServer::class)->middleware([LogMcpCall::class, 'auth:api', CheckToken::using('mcp:use'), 'throttle:mcp']);`
  - `LogMcpCall` comes first.
- `LogMcpCall` writes one `Log::channel('mcp')->info('mcp call', [...])` line per request. The fields are `user_id`, the JSON-RPC `method`, the `tool` name for `tools/call`, `status`, `duration_ms`, `tool_error` (`result.isError`) and `error_code` (`error.code`). It never logs arguments, recipe text or response content.

#### 4. Consent page and login redirect

**File**: `resources/js/Pages/Auth/OAuthAuthorize.vue` (new), `app/Http/Controllers/Auth/AuthenticatedSessionController.php`

**Intent**: Show a Polish consent screen in the existing guest look, and make a login that started at `/oauth/authorize` return there with a full-page navigation.

**Contract**:
- Page:
  - Uses `GuestLayout`, a `<h1>` through `Common/PageHeader` or the layout's title slot (design-system §5.2), token utilities only (`lint:tokens`), and `Button` from `Components/ui/button`.
  - Text: "Aplikacja {client_name} prosi o dostęp do Twojego konta Menu Maker", with the scope "Dodawanie przepisów do Twojej bazy" listed, "Zezwól" and "Odmów".
  - Two **native** `<form method="post">` elements submit through Ziggy: approve to `passport.authorizations.approve` (POST `/oauth/authorize`), deny to `passport.authorizations.deny` with `_method=DELETE`. Both carry hidden fields `_token`, `state`, `client_id` and `auth_token`.
  - The forms are native because approve and deny answer with a 302 to an external `redirect_uri`, which an Inertia visit cannot follow.
- Props are built in the `authorizationView` closure, which receives one array with `client`, `user`, `scopes`, `request` and `authToken` (Passport `AuthorizationController.php:96-101`): `client_name` and `client_id` from `client`, `auth_token` from `authToken`, `state` from `request`, `scopes` (descriptions) from `scopes`, and `csrf_token`.
- `AuthenticatedSessionController::store`: if the path of `session('url.intended')` starts with `/oauth/authorize`, forget it and return `Inertia::location($intended)`. Otherwise keep `redirect()->intended(...)`. The return type widens to `RedirectResponse|SymfonyResponse`.

#### 5. Test support and tests

**File**: `tests/Support/PassportKeys.php` (new), `tests/TestCase.php`, `tests/Feature/Mcp/HttpEndpointTest.php` (new), `tests/Feature/Mcp/OAuthTest.php` (new), `tests/Feature/Auth/AuthenticationTest.php`

**Intent**: Give tests a throwaway keypair, and cover the endpoint's guards, discovery, registration, consent props and the login redirect.

**Contract**:
- `PassportKeys::install()` generates or caches an RSA keypair in a temp location and sets the Passport key config. `TestCase::setUp()` calls it, following household-treasurer.
- HTTP endpoint test:
  - A guest `postJson('/mcp', tools/list)` gets 401 with a `WWW-Authenticate` header naming the resource metadata URL.
  - `Passport::actingAs($user, ['mcp:use'])` gets 200 and lists `add-recipe-tool`. `Passport::actingAs($user, [])` gets 403.
  - The 61st call in a minute gets 429.
  - A `tools/call` that saves a recipe writes one `mcp` log line whose context has no `name`, `ingredients` or `instructions` values (`Log::spy()` / `Log::shouldReceive` on the channel).
- OAuth test:
  - Protected-resource discovery (root and `/mcp`) and authorization-server discovery list `mcp:use`, PKCE `S256` and the registration endpoint.
  - Dynamic registration accepts `https://claude.ai/api/mcp/auth_callback` and loopback on any port, and refuses `https://evil.example/callback` with `invalid_redirect_uri`, creating no client.
  - The 11th discovery request in a minute gets 429.
  - `GET /oauth/authorize` for a signed-in user renders `Auth/OAuthAuthorize` with the props.
- Authentication test: an Inertia login (`X-Inertia` header) with `url.intended=/oauth/authorize?...` returns 409 with `X-Inertia-Location` equal to that URL. A login with no or another intended URL still redirects to the dashboard.

### Success Criteria:

#### Automated Verification:

- Passport migrations apply on the testing database: `vendor/bin/sail artisan migrate --env=testing`
- MCP and OAuth tests pass: `vendor/bin/sail pest tests/Feature/Mcp tests/Feature/Auth`
- Full suite passes: `vendor/bin/sail composer test`
- Larastan passes with no baseline additions: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`
- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token lint passes over the new page: `vendor/bin/sail npm run lint:tokens`
- Route and config caching still work: `vendor/bin/sail artisan config:cache && vendor/bin/sail artisan route:cache && vendor/bin/sail artisan route:list --path=mcp`, then `vendor/bin/sail artisan optimize:clear`
- Existing E2E suite still passes (login flow untouched for normal intended URLs): `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`

#### Manual Verification:

- Locally, `npx @modelcontextprotocol/inspector` against `http://localhost:${APP_PORT}/mcp`:
  - completes the OAuth flow (login page, then the Polish consent page, then back to the inspector);
  - lists `add-recipe-tool`;
  - saves a recipe.
- "Odmów" on the consent page returns the client an `access_denied` error, and no token is issued.
- `storage/logs/mcp.log` shows one line per call, with no recipe content.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Docs and rollout

### Overview

Bring the product docs in line with the new input channel, document how to connect, prepare production env, and verify the real claude.ai connector against production.

### Changes Required:

#### 1. Product docs

**File**: `.ai/core/prd.md`, `context/foundation/roadmap.md`

**Intent**: Remove the contradiction between PRD §4 and the shipped feature, and keep #40/#41 scoped to the in-app UI.

**Contract**:
- PRD §4: replace "Brak importu przepisów z URL." with a line saying that the app itself does not import from a URL in its UI (#40), while an MCP client may add a recipe it extracted from text or a page through `add-recipe-tool`.
- Add a short user story or a note in the recipes section that recipes can be added over MCP (local Claude Code, claude.ai connector) after approval in the chat.
- Roadmap `## Parked` → "Import przepisów z URL": note that MCP addition shipped in change `mcp`, and that #40 still covers the in-app UI.
- Roadmap M-7 row: mention `mcp` as a delivered input channel. Do not touch any slice statuses.

#### 2. Connect guide and production env

**File**: `README.md`, `.env.prod.example`

**Intent**: Write down the two ways to connect, and the env needed in production.

**Contract**:
- A README section "Serwer MCP" covering:
  - local: set `MCP_LOCAL_USER`, then `claude mcp add menu-maker --scope local -- vendor/bin/sail artisan mcp:start menu-maker`;
  - remote: on claude.ai, Settings → Connectors → Add custom connector → `https://menu.kostecki.dev/mcp`, then sign in and approve;
  - the tool's rules in one paragraph: total kcal, units, duplicate refusal.
- `.env.prod.example` gets `PASSPORT_PRIVATE_KEY=` and `PASSPORT_PUBLIC_KEY=`, with a comment on generating them once (`php artisan passport:keys` on a throwaway checkout, paste as one line with `\n`) and that `config:cache` must run after a change, which `deploy.sh` already does.

### Success Criteria:

#### Automated Verification:

- Full suite still passes: `vendor/bin/sail composer test`
- PRD no longer contains the old sentence: `! grep -n "Brak importu przepisów z URL\." .ai/core/prd.md`

#### Manual Verification:

- Production `.env.prod` has the Passport keys. After `scripts/deploy.sh`, `curl -s https://menu.kostecki.dev/.well-known/oauth-protected-resource/mcp` returns the `/mcp` resource with `mcp:use`.
- On claude.ai (web or phone), the custom connector `https://menu.kostecki.dev/mcp` signs in through the Menu Maker login and consent page. A recipe added from a real Polish recipe site shows a preview, asks for approval, saves, and the returned link opens it in production.
- The production `mail_alert` mailbox gets no mail from expired or invalid tokens during the run.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful.

---

## Testing Strategy

### Unit Tests:

- `StructuredRecipeImporter`: rounding, name normalization, duplicate key, `Źródło:` suffix.
- Existing `RecipeRequestValidationTest` as the regression guard for the extracted concerns.

### Integration Tests:

- `tests/Feature/Mcp/AddRecipeToolTest.php`: happy path, owner scoping, duplicate refusal, every refused input, annotations and meta.
- `tests/Feature/Mcp/RecipeServerTest.php`: tool list and the local server's boot guards.
- `tests/Feature/Mcp/HttpEndpointTest.php` and `OAuthTest.php`: 401/403/429, discovery, dynamic registration, consent props, logging without content.
- `tests/Feature/Auth/AuthenticationTest.php`: the Inertia location on an OAuth intended URL.

### Manual Testing Steps:

1. Claude Code (local stdio): add from pasted text, add from a URL with per-serving kcal, add from a URL without kcal, then repeat one of them.
2. MCP Inspector against local `/mcp`: OAuth approve and deny, `tools/list`, one save.
3. claude.ai connector against production: one save from a real recipe site, link opens.

## Performance Considerations

One insert transaction per call, the same as the web form. The only new per-request cost is Passport's token verification on `/mcp`. Calls are limited to 60 a minute per user.

## Migration Notes

- Passport adds five `oauth_*` tables. They are additive, and `deploy.sh` migrates them in maintenance mode.
- Rollback: drop the `Mcp::web` route (or revert the change) and roll back the five migrations. No recipe data depends on them.
- The keys must be set in `.env.prod` before the first deploy that includes Phase 2, otherwise `/mcp` answers 500 on token verification.

## References

- Change identity: `context/changes/mcp/change.md`
- Persistence to reuse: `app/Services/RecipeImport/RecipeImporter.php:22-62`
- Form rules to share: `app/Http/Requests/StoreRecipeRequest.php:31-71`, `app/Http/Requests/Concerns/ValidatesRecipeIngredients.php:21-85`
- Unit conversion table and source suffix: `app/Services/RecipeImport/MarkdownRecipeParser.php:32-57`
- Login redirect: `app/Http/Controllers/Auth/AuthenticatedSessionController.php:30-37`
- Reference implementation: `../household-treasurer/routes/ai.php`, `app/Mcp/Servers/LocalTreasurerServer.php`, `app/Mcp/Tools/AddCashOperationTool.php`, `app/Http/Middleware/LogMcpCall.php`, `app/Http/Responses/Concerns/LeavesInertiaForOAuthAuthorization.php`, `resources/js/pages/auth/OAuthAuthorize.vue`, `tests/Feature/Mcp/*`, `tests/Support/PassportKeys.php`, `context/foundation/adr/0110-mcp-server-with-oauth-through-passport.md`
- Related issues: [#40](https://github.com/sebastian-kostecki/menu-maker/issues/40), [#41](https://github.com/sebastian-kostecki/menu-maker/issues/41)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Local MCP server with add-recipe-tool

#### Automated

- [x] 1.1 `laravel/mcp` is listed under `require` in `composer.json` — 8ad83b8
- [x] 1.2 New MCP tests pass — 8ad83b8
- [x] 1.3 Recipe form validation is unchanged — 8ad83b8
- [x] 1.4 Full suite passes — 8ad83b8
- [x] 1.5 Larastan passes with no baseline additions — 8ad83b8
- [x] 1.6 Pint passes — 8ad83b8
- [x] 1.7 The local server starts and lists the tool — 8ad83b8

#### Manual

- [ ] 1.8 Claude Code adds a recipe from pasted text with preview and approval
- [ ] 1.9 URL with per-serving kcal saves the total; page without kcal gets a disclosed estimate
- [ ] 1.10 Repeating the same recipe is refused with the link

### Phase 2: Remote /mcp behind Passport OAuth

#### Automated

- [x] 2.1 Passport migrations apply on the testing database — 74ab069
- [x] 2.2 MCP and OAuth tests pass — 74ab069
- [x] 2.3 Full suite passes — 74ab069
- [x] 2.4 Larastan passes with no baseline additions — 74ab069
- [x] 2.5 Pint passes — 74ab069
- [x] 2.6 Client and SSR bundles build — 74ab069
- [x] 2.7 Token lint passes over the new page — 74ab069
- [x] 2.8 Route and config caching still work — 74ab069
- [x] 2.9 Existing E2E suite still passes — 74ab069

#### Manual

- [ ] 2.10 MCP Inspector completes OAuth against local /mcp and saves a recipe
- [ ] 2.11 Deny on the consent page returns access_denied
- [ ] 2.12 mcp.log shows one line per call without recipe content

### Phase 3: Docs and rollout

#### Automated

- [x] 3.1 Full suite still passes
- [x] 3.2 PRD no longer contains the old sentence

#### Manual

- [ ] 3.3 Production discovery answers after deploy with Passport keys set
- [ ] 3.4 claude.ai connector adds a recipe in production end to end
- [ ] 3.5 No mail_alert mail from expired or invalid tokens
