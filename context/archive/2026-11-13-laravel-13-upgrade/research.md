---
change_id: laravel-13-upgrade
topic: Impact of Laravel 12 -> 13, Tinker 3, Boost 2 on menu-maker
status: complete
date: 2026-11-13
---

# Research: Laravel 13 upgrade

## Sources
- Official guide https://laravel.com/docs/13.x/upgrade (fetched 2026-11-13).
- `composer outdated --direct` (in Sail): laravel/framework 12.72.0 -> 13.42.0, laravel/tinker 2.11.1 -> 3.1.0, laravel/boost 1.8.13 -> 2.10.4; also available but OUT OF SCOPE: pest 5.3.5, pest-plugin-laravel 5.0.1, phpunit 13.4.6, inertia-laravel 3.7.0.

## Findings
1. Required dependency bumps (guide): framework ^13.0, boost ^2.0, tinker ^3.0. The guide keeps phpunit ^12 and pest ^4, so Pest/PHPUnit 5/13 are not required (composer.json:30-31 already ^4/^12).
2. PHP: composer.json:12 requires ^8.5; Sail runs 8.5.12. Laravel 13 compatible.
3. High impact: CSRF middleware renamed `PreventRequestForgery` (old names remain as deprecated aliases) with Sec-Fetch-Site origin check. Grep of app, bootstrap, config, routes, tests, database for VerifyCsrfToken/withoutMiddleware returned no hits (checked paths only). Risk: Origin check might affect E2E/Playwright against localhost:8081 -> verify via E2E.
4. Medium: cache `serializable_classes` default false; cache store is database (.env CACHE_STORE=database); no object caching found in grep of `serializable`. Upsert `uniqueBy` validation: no `upsert` use in app/.
5. Low: cache prefix / session cookie name defaults changed; config/cache.php:106 already uses the hyphenated form, and CACHE_PREFIX is commented in .env.example:41. Session `serialization` default json only applies if config synced; do not change (keeps sessions). Other low items (JobAttempted, QueueBusy, pagination views, Str factories, Js::from) had no grep hits in app/tests.
6. Boost 2 provides `/upgrade-laravel-v13` guided prompt; Boost 2 changes guideline/skill layout (vendor/laravel/boost has .ai, skills). CLAUDE.md contains a `<laravel-boost-guidelines>` block that must be refreshed with `vendor/bin/sail artisan boost:update` (or boost:install) after upgrade; it currently lists php 8.5.12, pest v4, so partially refreshed already; verify versions after.
7. Larastan 3.x / Pint / Collision / Breeze / Sail / Pail / Sanctum compatibility with Laravel 13 is unverified; composer resolution will show. Sanctum ^4.0 expected to allow 13 (else bump to latest).

## Open points (decide in plan)
- Whether `composer update` needs larastan/sanctum/ziggy/inertia bumps: resolve at run time; record any forced bump.
- boost:update may rewrite CLAUDE.md/.mcp.json/other files; review diff, keep non-Boost content untouched.
