# PostgreSQL Migration Implementation Plan

## Overview

Replace MySQL with PostgreSQL 18 in Sail and drop the unused Redis service. Backend tests and both CI jobs move from SQLite to PostgreSQL. Recipe search becomes case- and accent-insensitive through the `unaccent` extension, so "zurek" finds "Żurek". Leftover Docker runtimes and MySQL/Redis mentions in the docs are removed.

Stage 2 of the dependency upgrade. It requires `deps-safe-updates` to be done, because it reuses that stage's Sail ports and E2E-on-Sail setup.

## Current State Analysis

- `docker-compose.yml` defines `mysql` (`mysql/mysql-server:8.0`) and `redis`, and `laravel.test` depends on both (`docker-compose.yml:25-27`).
- The app does not use Redis: `.env.example` sets `SESSION_DRIVER`, `QUEUE_CONNECTION` and `CACHE_STORE` to `database`.
- Local `.env` and CI run on SQLite. `phpunit.xml:25-27` uses SQLite `:memory:` with `DB_FOREIGN_KEYS`. `config/database.php:19` defaults to `sqlite`.
- Local data is disposable. SQLite holds 2 test users plus seeder data (40 ingredients, 9 units). The `menu-maker_sail-mysql` volume is empty (4 KB, created on 2026-11-13 by `docker compose run`).
- Migrations have no raw SQL. `enum` becomes `varchar` with a CHECK constraint on pgsql, `unsigned*` is ignored, and `fullText('name')` becomes a GIN `to_tsvector('english', …)` index. That index stays guarded for SQLite (`create_recipes_table.php:26`, `create_ingredients_table.php:21`) and no query uses it.
- `app/Models/Recipe.php:59-65` `scopeSearch` uses `like` on `name` and `instructions`. On PostgreSQL that is case-sensitive, so `tests/Feature/RecipeControllerTest.php:157` (search "chocolate" must find "Chocolate Cake") will fail.
- `docker/pgsql/create-testing-database.sql` already exists and creates a `testing` database at first container start. Sail's stub (`vendor/laravel/sail/stubs/pgsql.stub`) defines the service: `postgres:18-alpine`, a `pg_isready` healthcheck, and a volume at `/var/lib/postgresql`.
- Host port 5432 is taken by household-treasurer.
- Unused Docker assets: `docker/8.0`–`docker/8.4`, `docker/mysql`, `docker/mariadb`.

## Desired End State

- `vendor/bin/sail up -d` starts `laravel.test` and `pgsql` only. The app runs on PostgreSQL 18 and is seeded via `migrate:fresh --seed`.
- `vendor/bin/sail composer test` runs against the PostgreSQL `testing` database. CI backend and e2e jobs use a `postgres:18-alpine` service. SQLite is gone from tests and CI.
- Recipe search matches regardless of letter case and Polish diacritics, in both `name` and `instructions`.
- `docker/` contains only `8.5/` and `pgsql/`. README, `CLAUDE.md`, `.ai/core` and `.cursor/rules` describe PostgreSQL and no Redis.

### Key Discoveries:

- `docker/pgsql/create-testing-database.sql` uses `\gexec`, so it creates `testing` only on an empty data directory. A volume created before the mount was added would not have the `testing` database.
- `unaccent` is a trusted extension (PostgreSQL ≥ 13): a role with CREATE on the database can install it without superuser. `RefreshDatabase` runs migrations on `testing`, so the extension also lands in the test database.
- `unaccent()` is STABLE, not IMMUTABLE, so it cannot back an expression index directly. Search does a sequential scan, which is fine at the current data size.
- `.github/workflows/master.yml:63` and `:132` install only `sqlite`/`pdo_sqlite`. Lines 88–98 and 153–161 build an SQLite `.env`.

## What We're NOT Doing

- No data migration. Local data is test data and gets re-seeded.
- No changes to existing migrations: enums stay as Laravel's CHECK-backed strings, and the unused `fullText` indexes stay.
- No trigram or expression indexes for search, and no full-text search.
- No escaping of `%`/`_` in search input (current behaviour kept).
- The `mysql`, `mariadb` and `redis` blocks in `config/database.php` stay (framework defaults, harmless).
- `.cursor/rules/test-plan.mdc` is not edited. It is a 21k-line generated snapshot of the repo; only the hand-written `.cursor/rules/*.mdc` files are updated.
- No production deployment. Changes target Sail and CI only.

## Implementation Approach

Infrastructure first, so the app runs on PostgreSQL in Sail. Then tests switch to PostgreSQL, which exposes the search failure, followed by the search fix with new tests. Then CI, then cleanup. One commit per phase on `develop`.

## Critical Implementation Details

- **State sequencing**: the `testing` database is created only on first start of an empty `sail-pgsql` volume. Mount `docker/pgsql/create-testing-database.sql` before the first `sail up`. If the volume was created without it, run `sail down -v` and start again. Do not create `testing` by hand.
- **Timing & lifecycle**: the `unaccent` migration must run before any query that uses `unaccent()`. Give it a timestamp newer than all existing migrations. It must be idempotent (`IF NOT EXISTS`), and `down()` must drop the extension `IF EXISTS`.

## Phase 1: Sail infrastructure on PostgreSQL

### Overview

Swap the MySQL and Redis services for PostgreSQL and point the app at it.

### Changes Required:

#### 1. Compose services

**File**: `docker-compose.yml`

**Intent**: Replace `mysql` with a `pgsql` service following Sail's pgsql stub, remove `redis`, and update `depends_on` and the volumes.

**Contract**:
- Service `pgsql`: `postgres:18-alpine`, port `${FORWARD_DB_PORT:-5432}:5432`, `pg_isready` healthcheck, volume `sail-pgsql:/var/lib/postgresql`.
- Environment as in the stub: `PGPASSWORD`, `POSTGRES_DB=${DB_DATABASE}`, `POSTGRES_USER=${DB_USERNAME}`, `POSTGRES_PASSWORD=${DB_PASSWORD:-secret}`; healthcheck uses `-d ${DB_DATABASE} -U ${DB_USERNAME}`.
- Init script mounted from `./docker/pgsql/create-testing-database.sql` (project convention, not the vendor path).
- `laravel.test.depends_on: [pgsql]`.
- Volumes: only `sail-pgsql`.

#### 2. Environment defaults

**Files**: `.env.example`, `config/database.php`

**Intent**: New checkouts default to Sail's PostgreSQL, and the Redis variables go away.

**Contract**:
- `.env.example`: `DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, `DB_PORT=5432`, `DB_DATABASE=laravel`, `DB_USERNAME=sail`, `DB_PASSWORD=password`, with the `REDIS_*` lines removed.
- `config/database.php:19`: fallback `env('DB_CONNECTION', 'pgsql')`.
- Note: `.env.example` also carries the uncommitted `APP_LOCALE=pl` change. Stage only the DB/Redis hunks.

#### 3. Local environment (not committed)

**File**: `.env`

**Intent**: Use PostgreSQL locally on a free host port.

**Contract**: Same DB values as `.env.example`, plus `FORWARD_DB_PORT=5433`. Remove the empty `menu-maker_sail-mysql` and `menu-maker_sail-redis` volumes after `sail down`.

### Success Criteria:

#### Automated Verification:

- Only app and pgsql run: `vendor/bin/sail ps` lists `laravel.test` and `pgsql`, and no `mysql` or `redis`
- Both databases exist: `vendor/bin/sail psql -c '\l'` lists `laravel` and `testing`
- Schema and seed apply: `vendor/bin/sail artisan migrate:fresh --seed`
- App uses pgsql: `vendor/bin/sail artisan db:show` reports PostgreSQL 18
- E2E passes in Chromium and Firefox against http://localhost:8081

#### Manual Verification:

- Register, log in, create and edit a recipe and a family member in the browser

**Implementation Note**: After automated verification passes, pause for manual confirmation before Phase 2.

---

## Phase 2: Tests on PostgreSQL and accent-insensitive search

### Overview

Run the Pest suite on PostgreSQL, then make recipe search ignore case and diacritics.

### Changes Required:

#### 1. Test database

**File**: `phpunit.xml`

**Intent**: Point tests at the PostgreSQL `testing` database. Host and credentials come from `.env` in Sail and from CI.

**Contract**: `DB_CONNECTION=pgsql`, `DB_DATABASE=testing`; remove `DB_FOREIGN_KEYS` (SQLite-only). Run once before the search fix and confirm that `recipes index can be filtered by search` fails. That failure proves the dialect gap.

#### 2. unaccent extension

**File**: `database/migrations/<new timestamp>_enable_unaccent_extension.php`

**Intent**: Install the extension the search query relies on.

**Contract**: `up()` runs `CREATE EXTENSION IF NOT EXISTS unaccent`; `down()` runs `DROP EXTENSION IF EXISTS unaccent`.

#### 3. Search scope

**File**: `app/Models/Recipe.php` (`scopeSearch`, lines 59–65)

**Intent**: Match `name` or `instructions` regardless of case and Polish diacritics.

**Contract**: Same signature `scopeSearch($query, string $search)`, still OR-ing both columns inside one grouped `where`. Each condition is `unaccent(<column>) ILIKE unaccent(?)` with the bound value `"%{$search}%"`.

#### 4. Search tests

**File**: `tests/Feature/RecipeControllerTest.php`

**Intent**: Lock in the new matching rules next to the existing search test.

**Contract**:
- "zurek" finds a recipe named "Żurek".
- "ŻUREK" finds "żurek".
- "chocolate" still finds "Chocolate Cake" (existing test).
- A term that appears only in `instructions` finds the recipe.
- Another user's matching recipe is never returned.

### Success Criteria:

#### Automated Verification:

- Suite runs on pgsql and passes: `vendor/bin/sail composer test`
- New search tests pass: `vendor/bin/sail pest --filter=search`
- Migration is reversible: `vendor/bin/sail artisan migrate:rollback --step=1 && vendor/bin/sail artisan migrate`
- Larastan clean: `vendor/bin/sail composer phpstan`
- Style clean: `vendor/bin/sail composer pint-test`

#### Manual Verification:

- Searching "zurek" on the recipes page finds a recipe named "Żurek"

**Implementation Note**: After automated verification passes, pause for manual confirmation before Phase 3.

---

## Phase 3: CI on PostgreSQL

### Overview

Give both CI jobs a PostgreSQL service and remove SQLite from the pipeline.

### Changes Required:

#### 1. Workflow

**File**: `.github/workflows/master.yml`

**Intent**: Backend tests and E2E run against the same database engine as Sail.

**Contract**:
- `backend` and `e2e-tests` each get `services.postgres`: `postgres:18-alpine`, with env `POSTGRES_DB=testing`, `POSTGRES_USER`, `POSTGRES_PASSWORD`, port `5432:5432` and a `pg_isready` health option.
- `setup-php` extensions use `pgsql, pdo_pgsql` instead of `sqlite, pdo_sqlite` (lines 63 and 132).
- The generated `.env` (lines 88–91 and 153–156) uses `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_DATABASE=testing` and the service credentials.
- The SQLite `touch` lines (94 and 160) are removed.

### Success Criteria:

#### Automated Verification:

- No SQLite left in the workflow: `grep -ci sqlite .github/workflows/master.yml` returns 0
- Workflow is valid YAML: `vendor/bin/sail php -r 'yaml_parse_file(".github/workflows/master.yml") or exit(1);'` (or `npx yaml-lint`)

#### Manual Verification:

- After the next push to `main` (or a `workflow_dispatch` run), the backend and E2E jobs are green on PostgreSQL

**Implementation Note**: After automated verification passes, pause for manual confirmation before Phase 4.

---

## Phase 4: Cleanup and documentation

### Overview

Remove Docker assets nobody uses and make every doc say PostgreSQL.

### Changes Required:

#### 1. Docker assets

**Files**: `docker/8.0/`, `docker/8.1/`, `docker/8.2/`, `docker/8.3/`, `docker/8.4/`, `docker/mysql/`, `docker/mariadb/`

**Intent**: Only the PHP 8.5 runtime and the pgsql init script are used.

**Contract**: The directories are deleted; `docker/` keeps `8.5/` and `pgsql/`.

#### 2. Project docs

**Files**: `README.md` (tech stack lines 40–41, setup), `CLAUDE.md` (line 3 stack, line 29 testing), `.ai/core/tech-stack.md`, `.ai/core/test-plan.md` (lines 16, 92), `.ai/core/api-plan.md` (lines 123, 126), `.ai/core/db-plan.md` (note 3, line 180)

**Intent**: Describe PostgreSQL 18 in Sail with tests on the `testing` database, no Redis, and accent-insensitive search.

**Contract**:
- MySQL becomes PostgreSQL.
- Redis mentions are removed or replaced with the `database` driver.
- `db-plan.md` note 3 uses PostgreSQL syntax (`start_date + INTERVAL '6 days'`) and states that the constraint is not yet implemented in migrations.

#### 3. Cursor rules

**Files**: `.cursor/rules/tech-stack.mdc`, `.cursor/rules/testing.mdc`, `.cursor/rules/database.mdc`, `.cursor/rules/security-performance.mdc`

**Intent**: Keep the legacy Cursor rules from contradicting the stack.

**Contract**: MySQL becomes PostgreSQL. The "Redis Integration" section and the Redis bullets are removed.

### Success Criteria:

#### Automated Verification:

- Only used runtimes left: `ls docker` prints `8.5` and `pgsql`
- No stale stack mentions: `grep -rniE "mysql|redis" README.md CLAUDE.md .ai/core .cursor/rules --exclude=test-plan.mdc` returns nothing about the current stack
- Sail still builds: `vendor/bin/sail build laravel.test`
- Tests pass: `vendor/bin/sail composer test`

#### Manual Verification:

- README setup steps work from a clean clone (`cp .env.example .env`, `sail up -d`, `sail artisan migrate --seed`)

---

## Testing Strategy

### Unit Tests:

- None new. Existing Unit tests run on PostgreSQL unchanged.

### Integration Tests:

- Feature tests for search (Phase 2): case and diacritics, the instructions column, and owner scoping.
- Every existing Feature test now runs on PostgreSQL, which covers the CHECK-backed enums and foreign keys.
- Playwright register/login in Chromium and Firefox against Sail on PostgreSQL.

### Manual Testing Steps:

1. `vendor/bin/sail up -d`, open http://localhost:8081, register and log in.
2. Create recipes "Żurek" and "Chocolate Cake"; search "zurek", "ŻUREK" and "chocolate".
3. Add a family member; open meal plans.

## Performance Considerations

`unaccent(col) ILIKE` cannot use an index, so search is a sequential scan per user. That is acceptable for per-family recipe counts. If search gets slow, the future fix is an IMMUTABLE wrapper function with a `pg_trgm` GIN index (out of scope).

## Migration Notes

No data to migrate. Rollback per phase is `git revert <sha>` followed by `sail down -v && sail up -d && sail artisan migrate --seed`. A `.env` reverted to SQLite still works with the old commits.

## References

- Change identity: `context/changes/postgres-migration/change.md`
- Prerequisite: `context/changes/deps-safe-updates/plan.md` (Sail ports, E2E on Sail)
- Sail pgsql stub: `vendor/laravel/sail/stubs/pgsql.stub`
- Testing DB init: `docker/pgsql/create-testing-database.sql`
- Search scope: `app/Models/Recipe.php:59-65`
- Search test: `tests/Feature/RecipeControllerTest.php:157`
- CI: `.github/workflows/master.yml:63,88-98,132,153-161`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Sail infrastructure on PostgreSQL

#### Automated

- [x] 1.1 Only app and pgsql run
- [x] 1.2 Both databases exist
- [x] 1.3 Schema and seed apply
- [x] 1.4 App uses pgsql
- [x] 1.5 E2E passes in Chromium and Firefox

#### Manual

- [ ] 1.6 Register, log in, create recipe and family member in the browser

### Phase 2: Tests on PostgreSQL and accent-insensitive search

#### Automated

- [ ] 2.1 Suite runs on pgsql and passes
- [ ] 2.2 New search tests pass
- [ ] 2.3 Migration is reversible
- [ ] 2.4 Larastan clean
- [ ] 2.5 Style clean

#### Manual

- [ ] 2.6 Searching "zurek" finds "Żurek" in the UI

### Phase 3: CI on PostgreSQL

#### Automated

- [ ] 3.1 No SQLite left in the workflow
- [ ] 3.2 Workflow is valid YAML

#### Manual

- [ ] 3.3 CI backend and E2E jobs green on PostgreSQL

### Phase 4: Cleanup and documentation

#### Automated

- [ ] 4.1 Only used runtimes left in docker/
- [ ] 4.2 No stale stack mentions
- [ ] 4.3 Sail still builds
- [ ] 4.4 Tests pass

#### Manual

- [ ] 4.5 README setup works from a clean clone
