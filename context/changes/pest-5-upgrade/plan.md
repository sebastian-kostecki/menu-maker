# Pest 5 / PHPUnit 13 upgrade Implementation Plan

## Overview

Move `pestphp/pest` ^4 -> ^5, `pestphp/pest-plugin-laravel` ^4 -> ^5 and `phpunit/phpunit` ^12 -> ^13 in one Composer operation (they conflict otherwise), keeping the suite at >= 129 passing tests.

## Current State Analysis

Pest 4.7.8 conflicts with phpunit > 12.5.33, so the three packages cannot move separately. A Sail dry-run resolves cleanly (research.md). Tests are Pest-style; `phpunit.xml` already uses `<source>`. Pest 5 needs PHP >= 8.4 (Sail runs 8.5.12).

## Desired End State

`composer.json` requires pest ^5.0, pest-plugin-laravel ^5.0, phpunit ^13.0; lock updated (pest 5.x, phpunit 13.x, plugins 5.x); full test suite, phpstan, pint, build and E2E green; Boost guideline text current.

### Key Discoveries:

- `composer.json:29-31` hold the three constraints.
- Dry-run adds `sebastian/git-state`, `sebastian/file-filter`; removes `theseer/tokenizer`, `composer/xdebug-handler`, `composer/pcre`.
- `nunomaduro/collision` ^8.6 and `mockery` ^1.6 stay.

## What We're NOT Doing

- No Inertia, Vite, Tailwind changes.
- No test rewrites beyond what PHPUnit 13 strictly requires (intent preserved, no deletions).
- No phpstan baseline additions.

## Implementation Approach

Single `composer require --dev ... -W` through Sail, run the suite, fix breakage, then run the full gate and refresh Boost guidelines if they cite Pest 4.

## Phase 1: Bump packages and green the suite

### Overview

Upgrade via Composer and make the Pest suite pass with no skipped or risky tests.

### Changes Required:

#### 1. Composer constraints

**File**: `composer.json`, `composer.lock`

**Intent**: Raise the three constraints and let Composer update their dependency tree.

**Contract**: `pestphp/pest ^5.0`, `pestphp/pest-plugin-laravel ^5.0`, `phpunit/phpunit ^13.0`; via `vendor/bin/sail composer require --dev ... --with-all-dependencies`.

#### 2. Test/config fallout

**File**: `phpunit.xml`, `tests/**` (only if needed)

**Intent**: Resolve any PHPUnit 13 deprecations/errors, keeping test intent.

**Contract**: Suite reports >= 129 tests, 0 skipped/risky/warnings.

### Success Criteria:

#### Automated Verification:

- Packages installed at pest 5, plugin-laravel 5, phpunit 13: `vendor/bin/sail composer show pestphp/pest pestphp/pest-plugin-laravel phpunit/phpunit`
- Suite passes with >= 129 tests and 0 skipped/risky/warnings: `vendor/bin/sail composer test`
- Larastan clean: `vendor/bin/sail composer phpstan`

#### Manual Verification:

- Skim test output for new PHPUnit 13 deprecation notices

---

## Phase 2: Full gate and guideline refresh

### Overview

Run the remaining gates and update Boost guidelines if they reference Pest 4.

### Changes Required:

#### 1. Boost guidelines

**File**: `CLAUDE.md` (Boost block), `boost.json`

**Intent**: If the generated Pest section cites v4, refresh with `vendor/bin/sail artisan boost:install --guidelines --no-interaction`, preserving hand-written content.

**Contract**: Hand-written part of CLAUDE.md unchanged.

### Success Criteria:

#### Automated Verification:

- Pint clean: `vendor/bin/sail composer pint-test`
- Build ok: `vendor/bin/sail npm run build`
- E2E passes: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:8081 npm run test:e2e`
- Suite still green: `vendor/bin/sail composer test`

#### Manual Verification:

- Review the CLAUDE.md diff for lost hand-written content

---

## Testing Strategy

The existing suite is the safety net; count must stay >= 129 with no skips.

## Migration Notes

Rollback: `git revert` the upgrade commit (composer.json + lock).

## References

- Related research: `context/changes/pest-5-upgrade/research.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Bump packages and green the suite

#### Automated

- [x] 1.1 Packages installed at pest 5, plugin-laravel 5, phpunit 13 — 9905290
- [x] 1.2 Suite passes with at least 129 tests and no skipped, risky or warning results — 9905290
- [x] 1.3 Larastan clean — 9905290

#### Manual

- [ ] 1.4 Skim test output for new PHPUnit 13 deprecation notices

### Phase 2: Full gate and guideline refresh

#### Automated

- [x] 2.1 Pint clean
- [x] 2.2 Build ok
- [x] 2.3 E2E passes
- [x] 2.4 Suite still green

#### Manual

- [ ] 2.5 Review the CLAUDE.md diff for lost hand-written content
