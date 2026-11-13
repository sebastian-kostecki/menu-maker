# Laravel 13 Upgrade — Plan Brief

> Full plan: `context/changes/laravel-13-upgrade/plan.md`
> Research: `context/changes/laravel-13-upgrade/research.md`

## What & Why

Move the app from Laravel 12 to 13 with Tinker 3 and Boost 2 to stay on supported versions, then refresh the Boost guidelines in CLAUDE.md.

## Starting Point

Framework 12.72, PHP 8.5 in Sail, PostgreSQL, Pest 4 / PHPUnit 12; 129 tests, phpstan, pint, build and E2E (6) all green.

## Desired End State

Framework 13.x, tinker 3.x, boost 2.x installed; all gates green; CLAUDE.md guideline block shows Laravel 13.

## Key Decisions Made

| Decision | Choice | Why | Source |
| --- | --- | --- | --- |
| Pest/PHPUnit | Stay on 4/12 | Guide keeps them; next change | Research |
| Session serialization | Unchanged | Avoids invalidating sessions | Research |
| Extra package bumps | Only if Composer forces | Keep scope tight | Plan |

## Scope

**In scope:** framework, tinker, boost, forced transitive bumps, CLAUDE.md block.
**Out of scope:** Pest 5, Inertia 3, Vite, Tailwind.

## Architecture / Approach

Bump constraints, `composer update` in Sail, fix breakage, `boost:update`, full gate.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Dependency bump | L13/Tinker 3/Boost 2 with green tests | Package incompatibility |
| 2. Boost guidelines | Refreshed CLAUDE.md block | Overwriting hand-written text |
| 3. Final gate | Build + E2E green | CSRF origin check vs Playwright |

**Prerequisites:** Sail containers up.
**Estimated effort:** ~1 session.

## Open Risks & Assumptions

- Larastan/Sanctum may need bumps.
- boost:update may touch other generated files.

## Success Criteria (Summary)

- All gates green on Laravel 13; E2E 6/6.
