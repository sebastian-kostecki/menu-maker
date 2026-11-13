# Pest 5 / PHPUnit 13 upgrade — Plan Brief

> Full plan: `context/changes/pest-5-upgrade/plan.md`
> Research: `context/changes/pest-5-upgrade/research.md`

## What & Why

Upgrade the test stack to Pest 5, pest-plugin-laravel 5 and PHPUnit 13 to stay current; they must move together.

## Starting Point

Pest 4.7.8 / PHPUnit 12.5.33, 129 passing tests on PostgreSQL via Sail (PHP 8.5).

## Desired End State

Pest 5 stack installed; all gates green.

## Key Decisions Made

| Decision | Choice | Why | Source |
| --- | --- | --- | --- |
| Install method | one composer require -W | pest 4 conflicts with phpunit >12.5.33 | Research |
| Phases | 2 (upgrade+tests; gate+guidelines) | small change | Plan |

## Scope

**In scope:** pest, plugin-laravel, phpunit and transitive plugins; Boost guideline refresh.
**Out of scope:** Inertia, Vite, Tailwind.

## Architecture / Approach

Composer bump, run suite, fix fallout, run full gate.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Bump | packages + green suite | PHPUnit 13 deprecations |
| 2. Gate | pint/build/E2E, guidelines | Boost overwriting CLAUDE.md |

**Prerequisites:** Sail up. **Estimated effort:** 1 session.

## Open Risks & Assumptions

- Runtime PHPUnit 13 behavior changes are unknown until the suite runs.

## Success Criteria (Summary)

- Suite >= 129 tests green, phpstan/pint/build/E2E green.
