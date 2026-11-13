# Laravel 13 Upgrade Implementation Plan

## Overview

Upgrade laravel/framework 12 -> 13, laravel/tinker 2 -> 3 and laravel/boost 1 -> 2 (plus any ecosystem package Composer forces), then refresh the Boost guidelines block in CLAUDE.md.

## Current State Analysis

Framework 12.72.0, tinker 2.11.1, boost 1.8.13, Pest ^4 / PHPUnit ^12, PHP 8.5.12 in Sail. Baseline: 129 tests, phpstan, pint, build, E2E 6/6 all green. The official 13.x upgrade guide lists only dependency bumps as required; Pest 4 and PHPUnit 12 stay as is (see research.md).

### Key Discoveries:

- composer.json:12 PHP ^8.5, already compatible.
- No use of VerifyCsrfToken, upsert, JobAttempted, QueueBusy, Js::from in app/bootstrap/config/routes/tests/database (research.md).
- config/cache.php:106 already uses the hyphenated prefix; CACHE_STORE=database.
- Highest risk: new CSRF origin check (Sec-Fetch-Site) against Playwright E2E; Larastan/Sanctum/Ziggy/Inertia compatibility with L13.

## What We're NOT Doing

- No Pest 5 / PHPUnit 13 (next change), no Inertia 3 / Vite / Tailwind upgrades, unless Composer strictly forces it (then record why).
- No change to session `serialization` or cache config beyond what is required.
- No phpstan-baseline additions.

## Implementation Approach

Bump constraints in composer.json, run `composer update` for the target packages with dependencies inside Sail, fix whatever breaks, run all gates, commit. Then run `boost:update` to regenerate guidelines and review the CLAUDE.md diff. Finish with the full gate including E2E.

## Phase 1: Dependency bump to Laravel 13

### Overview

Move composer constraints and lock to Laravel 13 / Tinker 3 / Boost 2 and keep the app green.

### Changes Required:

#### 1. composer.json and composer.lock

**File**: `composer.json`

**Intent**: Raise `laravel/framework` to ^13.0, `laravel/tinker` to ^3.0, `laravel/boost` to ^2.0; bump other packages (sanctum, larastan, etc.) only if Composer cannot resolve.

**Contract**: `vendor/bin/sail composer update laravel/framework laravel/tinker laravel/boost --with-all-dependencies` resolves; any additional forced bump is recorded in change.md Notes.

#### 2. Application code and config

**Intent**: Fix breakages surfaced by tests/phpstan; apply guide items only where they affect the app (e.g. CSRF middleware naming if referenced).

**Contract**: No new phpstan baseline entries; Pint clean.

### Success Criteria:

#### Automated Verification:

- Composer resolves and lock shows framework 13.x, tinker 3.x, boost 2.x: `vendor/bin/sail composer show laravel/framework laravel/tinker laravel/boost`
- Pest suite passes: `vendor/bin/sail composer test`
- Larastan passes: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`

#### Manual Verification:

- Review composer.lock diff for unexpected major bumps (Pest/PHPUnit/Inertia must be unchanged)

---

## Phase 2: Refresh Boost guidelines

### Overview

Regenerate the Boost guidelines block so CLAUDE.md reflects the new versions.

### Changes Required:

#### 1. CLAUDE.md and Boost-generated files

**File**: `CLAUDE.md`

**Intent**: Check `vendor/bin/sail artisan list boost`, then run `boost:update` (or `boost:install --no-interaction` if that is what Boost 2 provides) to refresh the `<laravel-boost-guidelines>` block; keep all hand-written sections untouched.

**Contract**: Block shows laravel/framework v13, php 8.5.x, pest v4; the content outside the block is byte-identical; `.mcp.json` still starts the boost MCP server.

### Success Criteria:

#### Automated Verification:

- Block lists laravel/framework v13: `grep -n "laravel/framework (LARAVEL) - v13" CLAUDE.md`
- Hand-written part unchanged: `git diff CLAUDE.md` shows changes only inside the boost block
- Suite still green: `vendor/bin/sail composer test`

#### Manual Verification:

- Skim generated guideline/skill files for anything that contradicts project rules in CLAUDE.md

---

## Phase 3: Final gate

### Overview

Prove build and browser flows work on Laravel 13.

### Changes Required:

#### 1. Fixes only if the gate fails

**Intent**: Adjust app/E2E setup if the CSRF origin check or anything else breaks the browser flows.

**Contract**: Gate commands below all exit 0.

### Success Criteria:

#### Automated Verification:

- Client and SSR build: `vendor/bin/sail npm run build`
- E2E 6/6: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:8081 npm run test:e2e`
- Full suite, phpstan and pint pass: `vendor/bin/sail composer test && vendor/bin/sail composer phpstan && vendor/bin/sail composer pint-test`

#### Manual Verification:

- Log in, plan a week and open the app in a browser on http://localhost:8081 once to confirm no CSRF/419 errors

## Testing Strategy

Existing suite (129 tests) plus E2E; no new tests expected unless a code fix is required, in which case add a regression test.

## Migration Notes

Do not change session `serialization` (keeps sessions valid). Rollback = revert the commits and `composer install`.

## References

- Related research: `context/changes/laravel-13-upgrade/research.md`
- Upgrade guide: https://laravel.com/docs/13.x/upgrade

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Dependency bump to Laravel 13

#### Automated

- [x] 1.1 Composer resolves with framework 13.x, tinker 3.x, boost 2.x
- [x] 1.2 Pest suite passes
- [x] 1.3 Larastan passes
- [x] 1.4 Pint passes

#### Manual

- [ ] 1.5 Review composer.lock diff for unexpected major bumps

### Phase 2: Refresh Boost guidelines

#### Automated

- [ ] 2.1 Guidelines block lists laravel/framework v13
- [ ] 2.2 CLAUDE.md diff confined to the boost block
- [ ] 2.3 Pest suite still passes

#### Manual

- [ ] 2.4 Skim generated guideline/skill files for contradictions with CLAUDE.md

### Phase 3: Final gate

#### Automated

- [ ] 3.1 Client and SSR build passes
- [ ] 3.2 E2E passes 6/6
- [ ] 3.3 Full suite, phpstan and pint pass

#### Manual

- [ ] 3.4 Browser smoke check on localhost:8081 shows no CSRF/419 errors
