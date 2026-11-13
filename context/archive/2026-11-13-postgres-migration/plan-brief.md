# PostgreSQL Migration — Plan Brief

> Full plan: `context/changes/postgres-migration/plan.md`

## What & Why

Stage 2 of the dependency upgrade: replace MySQL with PostgreSQL 18 in Sail and drop Redis, which nothing uses. Tests and CI move from SQLite to PostgreSQL, so they exercise the engine the app actually runs on. Recipe search also gets better for Polish users: it ignores letter case and diacritics.

## Starting Point

Sail defines `mysql` and `redis`, but local development, the Pest suite and CI all run on SQLite. Local data is disposable: 2 test users plus seeders, and an empty MySQL volume. Migrations are portable, but `Recipe::scopeSearch` uses `like`, which turns case-sensitive on PostgreSQL. The existing search test proves it.

## Desired End State

`sail up -d` runs the app on PostgreSQL 18. `sail composer test` and both CI jobs (backend, E2E) run on PostgreSQL. Searching "zurek" or "ŻUREK" finds "Żurek". `docker/` holds only `8.5/` and `pgsql/`, and the docs describe PostgreSQL without Redis.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Database | PostgreSQL 18 (`postgres:18-alpine`) in Sail | User decision; matches the Sail stub and the sibling project. | Upgrade planning |
| Test database | PostgreSQL `testing` DB, not SQLite | Catches dialect differences (LIKE, CHECK enums) before production. | Upgrade planning |
| Redis | Removed | Cache, queue and session already use the `database` driver. | Upgrade planning |
| Search matching | `unaccent(col) ILIKE unaccent(?)` on name and instructions | Polish users find recipes regardless of case or diacritics. | Plan |
| CI | Postgres service in both backend and E2E jobs | CI tests what Sail runs. | Plan |
| Cleanup | Delete `docker/8.0`–`8.4`, `mysql`, `mariadb`; update docs | Repo shows only what is used. | Plan |
| Existing migrations | Untouched (CHECK-backed enums, unused fullText indexes stay) | Already portable; no data justifies a rewrite. | Plan |

## Scope

**In scope:**
- Compose and env switch
- PostgreSQL test DB
- `unaccent` migration and search scope with tests
- CI services
- Removing the Docker runtimes
- Docs in README, `CLAUDE.md`, `.ai/core` and the hand-written `.cursor/rules`

**Out of scope:**
- Data migration
- Search indexes or full-text search
- Rewriting the enum and fullText migrations
- The `mysql`/`redis` blocks in `config/database.php`
- The generated `.cursor/rules/test-plan.mdc`
- Production deployment

## Architecture / Approach

Sail runs `laravel.test` and `pgsql`. The init script creates the `testing` database next to `laravel`. Pest uses `testing` through `phpunit.xml`, and CI mirrors this with a service container. Search pushes normalisation into PostgreSQL (`unaccent` plus `ILIKE`).

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Sail infrastructure | App on PostgreSQL, no Redis, seeded, E2E green | `testing` DB missing if the volume predates the init mount |
| 2. Tests on PostgreSQL + search | Suite on PostgreSQL; accent- and case-insensitive search with tests | Other hidden SQLite-only assumptions in tests |
| 3. CI on PostgreSQL | Both jobs on a Postgres service | Verifiable only after a push to `main` or a manual dispatch |
| 4. Cleanup and docs | `docker/` trimmed; docs consistent | Missing a stale mention |

**Prerequisites:** `deps-safe-updates` done (Sail ports 8081/5174, E2E on Sail). Host port 5432 is busy, so set `FORWARD_DB_PORT=5433`.
**Estimated effort:** About 1–2 sessions, 4 commits.

## Open Risks & Assumptions

- No deployed database runs these migrations yet. Changes target Sail and CI only.
- `unaccent` maps Polish letters (ą, ć, ę, ł, ń, ó, ś, ź, ż) to ASCII. The Phase 2 tests verify this.
- Search does a sequential scan; acceptable at per-family data sizes.

## Success Criteria (Summary)

- The app, Pest suite and CI all run on PostgreSQL, with no SQLite, MySQL or Redis left in the runtime path.
- Recipe search finds "Żurek" for "zurek" and "ŻUREK".
- A fresh clone works with `cp .env.example .env`, `sail up -d` and `sail artisan migrate --seed`.
