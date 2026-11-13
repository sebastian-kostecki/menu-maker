# Inertia 3, laravel-vite-plugin 3 and Vite 8 Upgrade Implementation Plan

## Overview

Move the server adapter (inertiajs/inertia-laravel 2 -> 3), the client adapter (@inertiajs/vue3 2 -> 3), laravel-vite-plugin 2 -> 3 and Vite 7 -> 8 together, keeping behaviour identical. Tailwind is a separate change and stays untouched.

## Current State Analysis

See `research.md`. Inertia usage is small and conventional: standard `createInertiaApp`/`createServer` entries with Ziggy, `useForm`/`router`/`Link`/`Head`/`usePage`, `Inertia::render` only (no lazy/defer), `assertInertia` in 3 feature test files. No `config/inertia.php`. Only a known v3 breakpoint in code: `<title inertia>` in `resources/views/app.blade.php:8`.

## Desired End State

All four packages upgraded; `composer test` (129 tests), phpstan, pint, client+SSR build and E2E (6/6) green; SSR demonstrably renders HTML on the login page; Boost guidelines refreshed for Inertia 3.

### Key Discoveries:

- `@vitejs/plugin-vue` 6.0.x already lists vite ^8 as peer, so it only needs a lockfile bump (research.md).
- `laravel-vite-plugin` 3 requires vite ^8 and still exports `./inertia-helpers` (`resolvePageComponent`), so `app.js`/`ssr.js` need no import change.
- `@inertiajs/vue3/server` `createServer` export is the documented v3 SSR entry when not using `@inertiajs/vite`.
- Ziggy 2.x has no v3; independent of Inertia.

## What We're NOT Doing

- Not upgrading Tailwind, or any other package than the four (plus plugin-vue patch bump if needed).
- Not adopting the optional `@inertiajs/vite` plugin or new v3 features (useHttp, optimistic updates, layout props).
- Not adding SSR to Sail/CI infrastructure (out of scope; SSR is verified manually in phase 3).
- No phpstan baseline additions.

## Implementation Approach

Server first (phase 1, verifiable by Pest), then the JS toolchain (phase 2, verifiable by build), then end-to-end verification and docs (phase 3). Each phase ends green and is committed.

## Phase 1: Server adapter inertia-laravel 3

### Overview

Upgrade the Composer package and adapt Blade/config.

### Changes Required:

#### 1. Composer constraint

**File**: `composer.json`, `composer.lock`

**Intent**: Require `inertiajs/inertia-laravel` ^3.0.

**Contract**: `vendor/bin/sail composer require inertiajs/inertia-laravel:^3.0 -W` resolves with Laravel 13 and Boost 2.

#### 2. Root Blade template

**File**: `resources/views/app.blade.php`

**Intent**: Rename `<title inertia>` to `<title data-inertia>` per the v3 guide.

**Contract**: head attribute `data-inertia`; `@inertia`, `@inertiaHead` kept.

#### 3. Config

**File**: `config/inertia.php`

**Intent**: Publish the restructured config (`vendor:publish --provider="Inertia\ServiceProvider"`) and keep defaults, so `pages`/`testing` keys exist explicitly; then `view:clear`.

**Contract**: `pages.paths` = `resource_path('js/Pages')`.

#### 4. Server-side code audit

**Intent**: Fix any failures in `HandleInertiaRequests` or tests that v3 surfaces (e.g. `ensure_pages_exist`, page object changes).

### Success Criteria:

#### Automated Verification:

- Pest suite passes (129 tests): `vendor/bin/sail composer test`
- Larastan clean: `vendor/bin/sail composer phpstan`
- Pint clean: `vendor/bin/sail composer pint-test`

#### Manual Verification:

- None for this phase (UI is verified in phase 3).

---

## Phase 2: JS toolchain (Inertia vue3 3, laravel-vite-plugin 3, Vite 8)

### Overview

Bump npm packages and make client and SSR builds work.

### Changes Required:

#### 1. npm packages

**File**: `package.json`, `package-lock.json`

**Intent**: `@inertiajs/vue3` ^3, `laravel-vite-plugin` ^3, `vite` ^8, `@vitejs/plugin-vue` to latest 6.x.

**Contract**: `vendor/bin/sail npm install` (Sail Node must satisfy ^20.19 || >=22.12; verify).

#### 2. Entry points / config adaptation

**File**: `resources/js/app.js`, `resources/js/ssr.js`, `vite.config.js`, `resources/js/bootstrap.js`

**Intent**: Only change what the build or runtime requires (e.g. Vite 8 config incompatibilities, axios still declared as own devDependency). Keep Ziggy setup.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- `npm ls @inertiajs/vue3 vite laravel-vite-plugin` shows v3 / v8 / v3 without invalid peers
- Pest suite still passes: `vendor/bin/sail composer test`

#### Manual Verification:

- None for this phase.

---

## Phase 3: End-to-end verification, SSR check and Boost guidelines

### Overview

Prove forms, Ziggy routes and SSR work in the browser and refresh agent guidelines.

### Changes Required:

#### 1. E2E and SSR

**Intent**: Run Playwright against Sail; run `vendor/bin/sail artisan inertia:start-ssr` in the background and curl the login page to confirm server-rendered markup (fall back to client rendering must NOT be the reason it passes: check response contains rendered form HTML inside `<div id="app">`, not only the JSON script). Stop the SSR server afterwards.

**Contract**: SSR on `http://127.0.0.1:13714`.

#### 2. Boost guidelines

**File**: `CLAUDE.md`, `boost.json`, related generated guideline files

**Intent**: `vendor/bin/sail artisan boost:install --guidelines --no-interaction`; keep hand-written CLAUDE.md content and Hard Rules intact (restore by diff if overwritten).

### Success Criteria:

#### Automated Verification:

- Full gate: `vendor/bin/sail composer test`, `composer phpstan`, `composer pint-test`, `vendor/bin/sail npm run build`
- E2E passes (6/6): `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:8081 npm run test:e2e`
- SSR login page contains server-rendered HTML: `curl -s http://localhost:8081/login` with SSR server running

#### Manual Verification:

- Click through login, recipe create/edit form, family member CRUD and delete confirm in a browser for visual regressions (progress bar, toasts, form `processing` states)

---

## Testing Strategy

Existing Pest feature tests (assertInertia) cover server props; Playwright covers browser flows. No new tests are required unless an adaptation reveals a gap.

## Migration Notes

Rollback: revert the phase commits and `composer install` / `npm ci`.

## References

- Related research: `context/changes/inertia-3-vite-8-upgrade/research.md`
- Upgrade guide: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Server adapter inertia-laravel 3

#### Automated

- [x] 1.1 Pest suite passes (129 tests) — 995a8da
- [x] 1.2 Larastan clean — 995a8da
- [x] 1.3 Pint clean — 995a8da

### Phase 2: JS toolchain (Inertia vue3 3, laravel-vite-plugin 3, Vite 8)

#### Automated

- [x] 2.1 Client and SSR bundles build
- [x] 2.2 npm ls shows v3 / v8 / v3 without invalid peers
- [x] 2.3 Pest suite still passes

### Phase 3: End-to-end verification, SSR check and Boost guidelines

#### Automated

- [ ] 3.1 Full gate (test, phpstan, pint, build)
- [ ] 3.2 E2E passes 6/6
- [ ] 3.3 SSR login page contains server-rendered HTML

#### Manual

- [ ] 3.4 Click through login, recipe form, family member CRUD for visual regressions
