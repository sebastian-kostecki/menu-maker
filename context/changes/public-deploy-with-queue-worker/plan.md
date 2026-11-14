# Public Deploy with Queue Worker Implementation Plan

## Overview

Menu Maker goes live at `https://menu.kostecki.dev` on the shared kostecki.dev VPS (roadmap F-02, "Mikrus"). The VPS runs behind Traefik from the `kostecki-dev-infra` repo, which the roadmap calls `kostecki-infra`.

This repo ships a production Docker stack in the same shape as its siblings root-keeper and household-treasurer:

- an image;
- a compose file whose app container runs the web server, the queue worker and the scheduler;
- an encrypted daily DB backup;
- a deploy script that migrates inside a maintenance window;
- a CI job that builds and boots the image before anything reaches the server.

The infra repo gets the matching docs and deploy wrapper. The plan ends with the first real deploy and a production smoke test.

## Current State Analysis

- **No production artefact exists.**
  - The only Docker files are Sail's: `docker-compose.yml`, plus `docker/8.5/Dockerfile` with xdebug, pcov, swoole and Playwright.
  - There is no `.dockerignore`, and `.gitignore` does not list `.env.prod`.
  - CI (`.github/workflows/master.yml`) runs build, Larastan, Pest and E2E, but never builds a production image and has no deploy step.
- **The infra contract is already settled.** `kostecki-dev-infra` holds no app code.
  - Each app repo provides `docker-compose.prod.yml`, `docker/8.5/Dockerfile.prod`, `docker/8.5/supervisord.prod.conf`, `scripts/deploy.sh`, `scripts/artisan.sh` and `.env.prod.example`.
  - Traefik discovers the `app` container through labels on the external `proxy` network (`kostecki-dev-infra/docs/ADDING-AN-APP.md`).
  - The infra repo adds only `docs/<app>.md`, `scripts/deploy-<app>.sh`, the VPS directories in `scripts/bootstrap-vps.sh`, and rows in README, ARCHITECTURE and SETUP.
  - There is no registry and no CI-triggered deploy: the operator runs the deploy script on the VPS. This answers the roadmap's open F-02 unknown.
- **The queue needs a specific worker.** The only job, `app/Jobs/GenerateMealPlanJob.php:38`, has `$timeout = 80` against `retry_after` 90 (`config/queue.php:42`).
  - It has no `$tries`.
  - Its `WithoutOverlapping(...)->dontRelease()` middleware assumes `--tries=1` (`GenerateMealPlanJob.php:56-72`).
  - Production must run `queue:work --tries=1`, which honours the job's `$timeout` (`context/archive/2026-11-14-ai-ingredient-scaling/plan.md:639`).
  - There is no scheduled task today (`routes/console.php`).
- **Error reporting is logs plus mail.** `LOG_STACK=daily,mail_alert` and `LOG_ALERT_MAIL_TO` are production-only settings left for F-02 (`context/archive/2026-11-13-error-tracking-baseline/plan-brief.md`).
- **Mail is required.** Password reset needs real SMTP: with an unreachable mail host, `POST /forgot-password` returns 500 (S-01 follow-up F3). That provider choice was also deferred to F-02.
- **AI configuration.**
  - The production driver is `openrouter` or `anthropic` (`config/services.php:38-53`). `fake` is test-only.
  - A missing key or model throws a non-retryable `RefinementConfigurationException`. Plans then finish with code-computed amounts and an hourly error mail.
- **PDF rendering.** dompdf is pure PHP with a bundled DejaVu font. It renders on request with no file on disk, and caches fonts in `storage/fonts`, a directory that does not exist yet.
- **Inertia SSR is on by default.**
  - `config/inertia.php:24` defaults to enabled.
  - `npm run build` also builds the SSR bundle (`package.json:6`).
  - The client build needs no PHP, since Ziggy comes from npm `ziggy-js`. Tailwind does `@source` `vendor/.../Pagination` views (`resources/css/app.css:6`), so the asset stage needs `vendor/`.
- **HTTPS behind the proxy.** There is no `trustProxies` (`bootstrap/app.php:18-25`), so behind Traefik and Cloudflare, redirects and URLs would be `http://`. Both siblings call `$middleware->trustProxies(at: '*')`.
- **`unaccent` extension.** The migration `2026_11_13_100000_enable_unaccent_extension.php` runs `CREATE EXTENSION`. The official `postgres` image's `POSTGRES_USER` is a superuser, so it works.
- **No backups exist.** Both siblings use `spatie/laravel-backup` ^10.3 on Laravel 13.

### Key Discoveries:

- **Template files:**
  - root-keeper: `docker-compose.prod.yml`, `docker/8.5/Dockerfile.prod`, `docker/8.5/supervisord.prod.conf`, `scripts/deploy.sh`, `scripts/lib/production-deploy.sh`, `scripts/artisan.sh` and `.env.prod.example`.
  - household-treasurer: `.dockerignore`, the maintenance-window functions in `scripts/lib/production-deploy.sh:30-115`, and `tests/Unit/ProductionPostgresClientVersionTest.php`.
- **Do not copy root-keeper's missing `.dockerignore`.** Its `COPY . .` bakes `.git`, `context/` and the VPS `.env.prod` into the image.
- **`postgresql-client-18` must come from the pgdg apt repo.** Ubuntu 24.04 ships client 16, which cannot dump a PG18 server (household-treasurer commit `ae317e8`).
- **Run `migrate` and the caches as `-u sail`.** As root they leave root-owned files in the bind-mounted `storage` (root-keeper commit `38a3426`).
- **`artisan down` survives `up -d`.** It writes the maintenance file into the bind-mounted `storage`, and `queue:work` does not process jobs while the app is down. This is the household-treasurer pattern.
- **Backup slots already taken on the VPS** (Europe/Warsaw):
  - 03:00 (treasurer `backup:run`, Vaultwarden dump), 03:15 (Backrest), 03:30 and 04:00 (treasurer `backup:clean` and `backup:monitor`, `household-treasurer/bootstrap/app.php:38,45`).
  - 03:45, 04:15 and 04:45 (root-keeper), and 04:30 (landing refresh).
  - wallet-master runs in UTC (`wallet-master/routes/console.php:11-15`): 00:00, 01:00, 01:30 and 03:00 UTC, which is 01:00–04:00 Warsaw in winter and 02:00–05:00 in summer.
  - The infra docs list only part of this. 05:15, 05:30 and 05:45 are free in both seasons.
- **The infra repo's default branch is `master`.** Its shell tests are plain bash: `scripts/lib/*.test.sh` with `pass`/`failed`/`assert_eq` helpers.

## Desired End State

- `https://menu.kostecki.dev/up` returns 200 through Cloudflare (Proxied, Full strict) and Traefik (Let's Encrypt).
- A user can register, log in and receive a password-reset e-mail whose link is `https://`.
- A user can generate a 7×3 plan that a real AI refines in under a minute, and can download the PDF.
- An unhandled error mails `LOG_ALERT_MAIL_TO`.
- Inside the single `app` container, supervisord shows `php`, `queue` and `scheduler` as RUNNING.
- Every night at 05:15 (Europe/Warsaw) an encrypted DB-only backup lands in `/storage/menu-maker-backups`.
- Every deploy takes a DB backup and migrates while the app shows 503, and comes back only when everything succeeded.
- CI proves on every PR into `main` that the production image builds, boots, migrates, serves `/up` and can dump the database.

Verify with each phase's success criteria and the Phase 6 production smoke test.

## What We're NOT Doing

- No container registry, image push or CI-triggered deploy. Deploys stay manual on the VPS through `deploy.sh`, as for the siblings.
- No Inertia SSR in production: no Node in the runtime image, no `inertia:start-ssr` process.
- No nginx, FrankenPHP or Octane. `php artisan serve` with `PHP_CLI_SERVER_WORKERS=4` stays, as for the siblings.
- No offsite backup and no restore drill in this change. The restore steps are documented and the drill is a follow-up.
- No new mail provider. We reuse the SMTP account the sibling apps use.
- No rate limiting on `/register` or `/forgot-password`, and no real client IP behind Cloudflare. Throttles see Cloudflare or Traefik IPs, the same limitation the siblings have.
- No E2E run against production, and no metrics or uptime monitoring beyond `/up` and mail alerts.
- No update of the stale `.ai/core/tech-stack.md`.
- No change to Sail, the dev compose or the existing CI jobs beyond adding one new job.

## Implementation Approach

Copy the proven sibling shape and change only what menu-maker needs:

1. A DB-only backup, because the app stores no files.
2. A worker with `--tries=1` that honours the 80 s job timeout.
3. A client-only asset build.
4. A preflight that refuses an incomplete `.env.prod`: mail, alert recipient, backup password, a real AI driver with its key, SSR off.
5. Caches built before the app leaves maintenance mode.
6. Graceful worker shutdown, so a deploy does not kill a generation job mid-flight.

App-side changes (Phase 1) land before the image (Phase 2), so the image boots with a backup package and a scheduler to run. The deploy script (Phase 3) depends on both. CI (Phase 4) exercises Phases 1–3 together. The infra repo (Phase 5) documents what exists. The first deploy (Phase 6) is manual.

## Critical Implementation Details

- **Timing & lifecycle.** `docker stop` gives a container 10 s by default, while a generation job may run 80 s. Give the queue program `stopwaitsecs=90` and the `app` service `stop_grace_period: 100s`, so `up -d` lets `queue:work` finish the current job after SIGTERM. Otherwise a deploy leaves a plan stuck in `processing` until the 300 s overlap lock expires.
- **State sequencing.** Order in `deploy.sh`:
  1. `artisan down` in the old container, before its DB backup, so nothing is written between backup and migration.
  2. Build the caches after `migrate`.
  3. Run `queue:restart` so the worker reboots with the cached config.
  4. Run `artisan up` last.
  - A failure at any step leaves the app down on purpose (EXIT trap).
- **First-deploy key.** `.env.prod` is mounted read-only. Generate `APP_KEY` with `docker compose ... run --rm --no-deps app php artisan key:generate --show` and paste it, rather than `key:generate` writing into the file.

## Phase 1: App Production Config and Backups

### Overview

Make the app correct behind a TLS-terminating proxy, add encrypted DB-only backups with a production-only schedule, and describe the production environment in `.env.prod.example`.

### Changes Required:

#### 1. Trust the reverse proxy

**File**: `bootstrap/app.php`

**Intent**: Behind Cloudflare and Traefik, requests arrive over plain HTTP with `X-Forwarded-*` headers. Trust them so redirects, signed reset links and `SESSION_SECURE_COOKIE` see HTTPS.

**Contract**: `$middleware->trustProxies(at: '*')` inside the existing `withMiddleware` closure, as in the siblings' `bootstrap/app.php:19`.

#### 2. Backup package and configuration

**File**: `composer.json`, `composer.lock`, `config/backup.php` (published), `config/filesystems.php`

**Intent**: Add `spatie/laravel-backup` ^10.3, approved in planning. Configure a DB-only backup: the app stores no user files, and recipes, family and plans live only in PostgreSQL.

**Contract**:
- **Disk:** a new `backups` local disk at `storage_path('app/backups')`, which the compose file bind-mounts to the host.
- **`backup.backup.name`:** `env('BACKUP_NAME', 'menu-maker')`, pinned so a renamed `APP_NAME` does not move the archive folder.
- **Sources:** `source.files.include` is `[]` and `source.databases` is `[config default connection]`.
- **Destination:** the `backups` disk only.
- **Encryption:** `password` from `BACKUP_ARCHIVE_PASSWORD`, and `encryption` is `'default'` when a password is set.
- **Notifications:** by mail to `BACKUP_NOTIFICATION_MAIL`, failure events only. The success-type notifications get an empty channel list, so the inbox sees only problems.
- **Cleanup and monitoring:** the root-keeper cleanup strategy, and monitoring with a maximum age of 1 day.

#### 3. Backup schedule

**File**: `bootstrap/app.php`

**Intent**: Run the backup nightly in a VPS slot no other app uses, only in production.

**Contract**: a `withSchedule` closure with three entries, each `->timezone('Europe/Warsaw')->withoutOverlapping()->onOneServer()->environments(['production'])`:
- `backup:run --only-db` at `05:15`
- `backup:clean` at `05:30`
- `backup:monitor` at `05:45`

These slots avoid every job listed under Key Discoveries, including wallet-master's UTC jobs in summer time.

#### 4. Font cache directory

**File**: `storage/fonts/.gitignore`

**Intent**: dompdf writes its font cache to `storage/fonts`. The production `storage` is the bind-mounted checkout, so the directory must exist in git.

**Contract**: the standard Laravel storage `.gitignore` (`*` plus `!.gitignore`).

#### 5. Production environment template and ignore files

**File**: `.env.prod.example`, `.gitignore`, `.dockerignore`, `.env.example`

**Intent**: One documented template the operator copies to `.env.prod`. Keep secrets out of git and out of the image.

**Contract**:
- **`.env.prod.example` holds every key production reads, empty where secret:**
  - App: `APP_NAME="Menu Maker"`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://menu.kostecki.dev`, `APP_LOCALE=pl`, `APP_MAINTENANCE_DRIVER=file`, `PHP_CLI_SERVER_WORKERS=4`.
  - Logging: `LOG_CHANNEL=stack`, `LOG_STACK=daily,mail_alert`, `LOG_LEVEL=info`, `LOG_DAILY_DAYS=14`, `LOG_ALERT_MAIL_TO=`.
  - Database: `DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, `DB_DATABASE=menu_maker`, `DB_USERNAME=menu`, `DB_PASSWORD=`.
  - Session: `SESSION_DRIVER=database`, `SESSION_LIFETIME=1440`, `SESSION_SECURE_COOKIE=true`, `SESSION_DOMAIN=menu.kostecki.dev`.
  - Cache and queue: `CACHE_STORE=database`, a pinned `CACHE_PREFIX`, `QUEUE_CONNECTION=database`.
  - Mail: SMTP on port 587, `MAIL_FROM_ADDRESS="noreply@kostecki.dev"`, `MAIL_HOST=`, `MAIL_USERNAME=`, `MAIL_PASSWORD=`.
  - SSR: `INERTIA_SSR_ENABLED=false`.
  - AI: `AI_SCALING_DRIVER=openrouter`, `OPENROUTER_API_KEY=`, `OPENROUTER_MODEL=`, `ANTHROPIC_API_KEY=`, `ANTHROPIC_MODEL`, `ANTHROPIC_EFFORT`.
  - Backup: `BACKUP_NAME=menu-maker`, `BACKUP_ARCHIVE_PASSWORD=`, `BACKUP_NOTIFICATION_MAIL=`, `BACKUP_HOST_DIR=/storage/menu-maker-backups`.
  - Build and user: `VITE_APP_NAME="${APP_NAME}"`, `WWWUSER=1000`, `WWWGROUP=1000`.
  - Comments name the keys `deploy.sh` requires, and say to keep `BACKUP_ARCHIVE_PASSWORD` off the VPS too.
- **`.gitignore`:** add `.env.prod`.
- **`.dockerignore`:**
  - Start from household-treasurer's file.
  - Also exclude `docker-compose*.yml`, `.ai`, `.claude`, `.cursor`, `docs`, `CLAUDE*.md`, `.mcp.json`, `.10x-cli.json`, `playwright.config.ts`, `test-results` and `storage/fonts/*`.
  - Keep `!.env.example` and `!.env.prod.example`.
- **`.env.example`:** add commented `BACKUP_*` keys so dev and test know them. Tests must not need a backup password.

#### 6. Tests

**File**: `tests/Feature/BackupScheduleTest.php`, `tests/Feature/TrustedProxyTest.php`

**Intent**: Lock the schedule slots and backup shape against drift, and prove HTTPS detection behind the proxy.

**Contract**:
- **Backup and schedule:**
  - Backup config is DB-only on the `backups` disk.
  - The three schedule events have the cron expressions `15 5 * * *`, `30 5 * * *` and `45 5 * * *`, timezone `Europe/Warsaw`, environments `['production']`, and the run command contains `--only-db`.
  - Call `Artisan::all()` first, as root-keeper's `tests/Feature/BackupScheduleTest.php` does.
- **Proxy:** a request carrying `X-Forwarded-Proto: https` produces an `https://` redirect `Location` and reports `secure()`. Use, for example, a guest hitting an auth-protected route.

### Success Criteria:

#### Automated Verification:

- Backup and proxy tests pass: `vendor/bin/sail pest --filter='BackupSchedule|TrustedProxy'`
- Full suite passes: `vendor/bin/sail composer test`
- Larastan passes without new baseline entries: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`
- `.env.prod` is git-ignored: `git check-ignore -q .env.prod`
- Config still caches: `vendor/bin/sail artisan config:cache && vendor/bin/sail artisan config:clear`

---

## Phase 2: Production Image and Compose

### Overview

Build a production image without dev tooling, and a compose stack whose `app` container serves HTTP, runs the queue worker and runs the scheduler under supervisord next to a PostgreSQL 18 container. Traefik picks it up on `menu.kostecki.dev`.

### Changes Required:

#### 1. Production Dockerfile

**File**: `docker/8.5/Dockerfile.prod`

**Intent**: A three-stage build in the root-keeper shape, minus the parts menu-maker does not use (Wayfinder, PHP in the asset stage, SSR bundle).

**Contract**:
- **`vendor` stage:** mirrors root-keeper's composer stage (`composer:2`, `--no-dev`, optimized autoloader, cleared `bootstrap/cache/*.php`).
- **`assets` stage:**
  - `node:22` slim. Node 22 matches CI (`master.yml`); Sail's dev image uses Node 24.
  - Copies `vendor/` from the first stage for Tailwind's `@source`.
  - Runs `npm ci` and then **`npx vite build` only**, not `npm run build`, so no SSR bundle is built.
  - Takes the `VITE_APP_NAME` build arg.
- **`runtime` stage:**
  - Base: `ubuntu:24.04` with the ondrej PHP 8.5 and pgdg repos.
  - PHP extensions: cli, pgsql, mbstring, xml, curl, zip, bcmath, intl, gd, readline.
  - Packages: `postgresql-client-$POSTGRES_VERSION` (`ARG POSTGRES_VERSION=18`), supervisor, gosu, curl.
  - `setcap` so the non-root `sail` user can bind port 80.
  - Reuses Sail's `start-container` and `php.ini`, as the siblings do.
  - Creates `sail` with build-time uid 1337 and group `$WWWGROUP`, as Sail does. At runtime `start-container` changes the uid to `WWWUSER`.
  - Copies the app from `vendor` and `public/build` from `assets` with `--chown=sail:sail`, then `mkdir -p` and `chown -R sail:sail` for `storage` and `bootstrap/cache`, as in root-keeper `Dockerfile.prod:134-138`.
  - Image files stay owned by uid 1337, which is not the runtime uid. `production_prepare_storage` (Phase 3) fixes that after every `up -d`.
  - No Node, `EXPOSE 80`, and `SUPERVISOR_PHP_COMMAND` running `artisan serve` on `0.0.0.0:80`.

#### 2. Supervisord programs

**File**: `docker/8.5/supervisord.prod.conf`

**Intent**: Three long-running processes in one container. Logs go to `docker logs` with Docker-side rotation instead of growing in `storage/logs`, which suits the small disk.

**Contract**:
- **Control socket**, so `supervisorctl status` works:
  - `[unix_http_server]` with `file=/var/run/supervisor.sock` and `chmod=0700`;
  - `[supervisorctl]` with `serverurl=unix:///var/run/supervisor.sock`;
  - `[rpcinterface:supervisor]` with `supervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface`.
  - The siblings' `supervisord.prod.conf` lack these sections, so their documented `supervisorctl status` fails. Do not copy that gap.
- **Programs** `php`, `queue` and `scheduler`, all as `%(ENV_SUPERVISOR_PHP_USER)s`, stdout and stderr to `/dev/stdout` and `/dev/stderr` with `*_maxbytes=0`.
- `queue` runs `php /var/www/html/artisan queue:work --tries=1 --sleep=1 --max-time=3600`, with `autorestart=true` and `stopwaitsecs=90`. It has no `--timeout`, so the job's 80 s applies.
- `scheduler` runs `php /var/www/html/artisan schedule:work`.

#### 3. Production compose

**File**: `docker-compose.prod.yml`

**Intent**: The artefact `kostecki-dev-infra` expects: an `app` service on the external `proxy` network with Traefik labels, and a private PostgreSQL.

**Contract**:
- **`app` service:**
  - Build `docker/8.5/Dockerfile.prod` with args `WWWGROUP`, `VITE_APP_NAME` and `POSTGRES_VERSION: 18`. Image `menu-maker-app:prod`, `container_name: menu-app`, `restart: unless-stopped`.
  - Environment: `WWWUSER: ${WWWUSER:-1000}` and `SUPERVISOR_PHP_USER: sail`. Without `WWWUSER`, `start-container` runs `gosu "" ...` for every `run`/`exec` command, including the first-deploy `key:generate --show`.
  - Limits: `mem_limit: 768m`, `cpus: 1.0`, `pids_limit: 256`, `stop_grace_period: 100s`.
  - Logging: `json-file` with `max-size: 10m`, `max-file: 3`.
  - Volumes: `./storage:/var/www/html/storage`, `${BACKUP_HOST_DIR:-/storage/menu-maker-backups}:/var/www/html/storage/app/backups` and `./.env.prod:/var/www/html/.env:ro`.
  - Networks: `menu-maker-internal` and `proxy`. `depends_on` pgsql healthy.
  - Traefik labels on router and service `menu`: ``Host(`menu.kostecki.dev`)``, `websecure`, `letsencrypt`, port 80, `traefik.docker.network=proxy`.
- **`pgsql` service:**
  - `postgres:18-alpine`, `container_name: menu-pgsql`.
  - Environment: `POSTGRES_DB: ${DB_DATABASE}`, `POSTGRES_USER: ${DB_USERNAME}` and `POSTGRES_PASSWORD: ${DB_PASSWORD}`. `POSTGRES_USER` is the superuser that runs `CREATE EXTENSION unaccent`.
  - Limits: `mem_limit: 512m`, `cpus: 0.5`, `pids_limit: 128`.
  - Named volume `menu-pgsql` at `/var/lib/postgresql`.
  - The `pg_isready` healthcheck, and network `menu-maker-internal` only.
- **Networks:** `menu-maker-internal` is a bridge and `proxy` is `external: true`.

#### 4. Consistency test

**File**: `tests/Unit/ProductionStackTest.php`

**Intent**: Cheap guards against silent drift between the compose file, the Dockerfile, supervisord, `.env.prod.example` and the job. Extends household-treasurer's PG-client test.

**Contract**: assert that
- the compose `postgres:N` image equals `POSTGRES_VERSION: N` and `ARG POSTGRES_VERSION=N`, and the Dockerfile uses `noble-pgdg` and `postgresql-client-$POSTGRES_VERSION`;
- the supervisord queue command uses `queue:work` with `--tries=1` and no `--timeout` below `GenerateMealPlanJob`'s timeout;
- the job timeout is below `config('queue.connections.database.retry_after')`;
- `stopwaitsecs` is at least the job timeout;
- supervisord has the `[unix_http_server]`, `[supervisorctl]` and `[rpcinterface:supervisor]` sections;
- the compose `app` service sets `WWWUSER` and `SUPERVISOR_PHP_USER`, and `pgsql` takes `POSTGRES_PASSWORD` from `${DB_PASSWORD}`;
- the Dockerfile runs `vite build` without `--ssr`;
- `.env.prod.example` has `INERTIA_SSR_ENABLED=false`, `APP_DEBUG=false` and `QUEUE_CONNECTION=database`, and its `APP_URL` host equals the Traefik `Host(...)` in the compose file.

### Success Criteria:

#### Automated Verification:

- Consistency test passes: `vendor/bin/sail pest --filter=ProductionStack`
- Compose file is valid: `docker compose --env-file .env.prod.example -f docker-compose.prod.yml config -q`
- Image builds from a clean context: `docker build -f docker/8.5/Dockerfile.prod -t menu-maker-app:prod .`
- Image has no SSR bundle, no Node and no env secrets: `docker run --rm --entrypoint sh menu-maker-app:prod -c 'test ! -e bootstrap/ssr && ! command -v node && test ! -e .env.prod && test ! -d .git && test -f public/build/manifest.json'`
- Image ships the matching dump client: `docker run --rm --entrypoint pg_dump menu-maker-app:prod --version | grep -q ' 18\.'`
- Full suite passes: `vendor/bin/sail composer test`

---

## Phase 3: Deploy Scripts

### Overview

`scripts/deploy.sh` turns `git pull` on the VPS into a safe release. It refuses an incomplete `.env.prod`, backs up the database inside a maintenance window, migrates, warms the caches and only then reopens the app. `scripts/artisan.sh` runs Artisan in production.

### Changes Required:

#### 1. Deploy library

**File**: `scripts/lib/production-deploy.sh`

**Intent**: The reusable functions, kept separate so they can be tested without Docker.

**Contract**:
- **Generic helpers from the siblings:** `PRODUCTION_BRANCH` (default `main`), `production_assert_branch`, `production_pull_latest`, `_production_env_value` (reads the last `KEY=` line without sourcing and strips quotes), `_production_is_blank` and `_production_is_truthy`.
- **`production_assert_env_ready <file>`** exits non-zero with `ERROR: <KEY> ...` on stderr when:
  - any of these is blank: `APP_KEY`, `DB_PASSWORD`, `MAIL_HOST`, `MAIL_FROM_ADDRESS`, `LOG_ALERT_MAIL_TO`, `BACKUP_ARCHIVE_PASSWORD`, `BACKUP_NOTIFICATION_MAIL`;
  - `APP_ENV` is not `production`;
  - `APP_DEBUG` is truthy;
  - `INERTIA_SSR_ENABLED` is not falsy;
  - `AI_SCALING_DRIVER` is neither `openrouter` nor `anthropic`;
  - the driver is `openrouter` and `OPENROUTER_API_KEY` or `OPENROUTER_MODEL` is blank;
  - the driver is `anthropic` and `ANTHROPIC_API_KEY` is blank.
- **Maintenance-window functions**, adapted from household-treasurer `scripts/lib/production-deploy.sh:30-115` without its user snapshots and control numbers:
  - **`production_prepare_running_instance`:** when the app container is running, run `artisan down` and then `backup:run --only-db --disable-notifications` in it, both as `sail`, and set `MAINTENANCE_STARTED=1`.
    - When no app container exists (first deploy), skip.
    - When the container exists but is stopped, exit with an error unless `DEPLOY_WITHOUT_BACKUP=1`.
  - **`production_ensure_maintenance_mode`:** after `up -d`, if maintenance was not started yet (first deploy), run `artisan down` in the new container.
  - **`production_leave_maintenance_mode`:** run `artisan up`.
- **`production_prepare_storage <compose command>`:** as root in the running `app` container, `mkdir -p storage/{fonts,logs,app/backups,framework/{cache,sessions,views}} bootstrap/cache`, then `chown -R sail:sail storage bootstrap/cache`.
  - It fixes the gap between the image's uid 1337 and the runtime `WWWUSER`. It also fixes the host owner of the bind-mounted `storage` and backup dir, for example uid 1001 on a GitHub runner.
  - `deploy.sh` and the Phase 4 smoke script both call it, so CI runs the same permission code as production.
  - **`production_report_failure`** (EXIT trap): on non-zero exit with maintenance started, say the app stays in maintenance mode and point to `docs/menu-maker.md`'s recovery section in the infra repo.

#### 2. Deploy script

**File**: `scripts/deploy.sh`

**Intent**: One command for every release.

**Contract**: `COMPOSE="docker compose --env-file .env.prod -f docker-compose.prod.yml"`, then in this order:
1. Check that `.env.prod` exists.
2. `production_assert_env_ready`.
3. `production_pull_latest`.
4. `$COMPOSE build app`.
5. Resolve `BACKUP_HOST_DIR` (shell, then `.env.prod`, then the default `/storage/menu-maker-backups`), export it, then `sudo mkdir` and `chown` it.
6. Set `MAINTENANCE_STARTED=0`, then `trap production_report_failure EXIT`.
7. `production_prepare_running_instance`.
8. `$COMPOSE up -d`.
9. `production_ensure_maintenance_mode`.
10. `production_prepare_storage "$COMPOSE"`.
11. `migrate --force` as `sail`.
12. `package:discover`, `config:cache`, `route:cache` and `view:cache`, all as `sail`.
13. `queue:restart`.
14. `production_leave_maintenance_mode`.
15. `curl -fsS http://localhost/up` inside the app container.
16. Print `Done. Check https://menu.kostecki.dev/up`.

#### 3. Artisan wrapper

**File**: `scripts/artisan.sh`

**Intent**: Run Artisan in the production container. Copy the siblings' file, which is generic.

**Contract**: `scripts/artisan.sh <artisan args>`. It runs `exec -T` unless stdin and stdout are a TTY, and refuses when `.env.prod` is missing.

#### 4. Shell tests

**File**: `scripts/lib/production-deploy.test.sh`

**Intent**: Prove that the preflight refuses each incomplete `.env.prod` and accepts a complete one, without Docker.

**Contract**: a plain bash test in the infra repo's `*.test.sh` style (`pass`/`failed`/`assert_eq`, temp dir, exit 1 on any failure).
- Build env files from a complete fixture with one rule broken each: each required key blank, `APP_ENV=local`, `APP_DEBUG=true`, `INERTIA_SSR_ENABLED=true`, `AI_SCALING_DRIVER=fake`, openrouter without a model, anthropic without a key.
- Run `production_assert_env_ready` in a subshell and assert a non-zero exit and that the offending key appears on stderr.
- The complete fixture, and both a quoted and a duplicated-last-wins key, must pass.
- Also assert that `production_assert_branch` refuses a branch other than `main`, using a throwaway git repo.

### Success Criteria:

#### Automated Verification:

- Preflight tests pass: `bash scripts/lib/production-deploy.test.sh`
- Scripts are lint-clean: `shellcheck scripts/deploy.sh scripts/artisan.sh scripts/lib/production-deploy.sh scripts/lib/production-deploy.test.sh`
- Scripts are executable: `test -x scripts/deploy.sh && test -x scripts/artisan.sh`
- The example env file fails preflight because it has blank secrets: `bash -c 'source scripts/lib/production-deploy.sh && production_assert_env_ready .env.prod.example'; test $? -ne 0`

---

## Phase 4: CI Boot Smoke

### Overview

A boot-smoke script builds the production image and boots the real compose stack. It runs locally during implementation and in a new CI job before any PR reaches `main`. Then a broken image, a missing extension, a permissions gap or a crashing supervisord program fails locally or in CI instead of on the VPS.

### Changes Required:

#### 1. Boot-smoke script

**File**: `scripts/ci/production-smoke.sh`

**Intent**: One script that CI and the implementer both run, so the stack is proven locally before anything is pushed.

**Contract**:
- **Guard:** refuse to run when `.env.prod` already exists. That protects a real env file and stops it from running on the VPS. Sources `scripts/lib/production-deploy.sh`.
- **Setup:**
  - `docker network create proxy` if it is missing;
  - write a throwaway `.env.prod` from `.env.prod.example` with:
    - a generated `APP_KEY` (`base64:` plus 32 random bytes from `openssl rand -base64 32`), a DB password and `BACKUP_ARCHIVE_PASSWORD`;
    - `BACKUP_HOST_DIR` in a temp dir (`${RUNNER_TEMP:-$(mktemp -d)}/backups`);
    - `MAIL_MAILER=log`, and dummy `MAIL_HOST`/`LOG_ALERT_MAIL_TO`/`BACKUP_NOTIFICATION_MAIL`;
    - `AI_SCALING_DRIVER=openrouter` with a dummy key and model;
  - assert that `production_assert_env_ready .env.prod` passes;
  - `$COMPOSE up -d --build`, then `production_prepare_storage "$COMPOSE"`.
- **Checks**, each as `sail` inside `app` where Artisan is used:
  - `migrate --force` succeeds;
  - `curl -fsS http://localhost/up` returns 200;
  - after 15 s, `supervisorctl status` shows `php`, `queue` and `scheduler` all `RUNNING`, so none is crash-looping;
  - `config:cache` and `route:cache` succeed;
  - `backup:run --only-db --disable-notifications` exits 0 and a `.zip` appears under `$BACKUP_HOST_DIR`.
- **Teardown (EXIT trap):** on failure print `$COMPOSE logs`, then always `$COMPOSE down -v` and remove the throwaway `.env.prod`. Exit non-zero on any failed check.
- The fixed `container_name`s (`menu-app`, `menu-pgsql`) mean the script cannot run next to another production-shaped stack on the same Docker host. Sail's containers have other names and do not collide.

#### 2. Production image job

**File**: `.github/workflows/master.yml`

**Intent**: Add one job, `production-image`, independent of the existing jobs, with the same `if` as `e2e-tests`: pushes to `main`/`master` and PRs into `main`. Leave the existing jobs unchanged.

**Contract**:
- checkout;
- `shellcheck` on `scripts/**/*.sh`, and `bash scripts/lib/production-deploy.test.sh`;
- `bash scripts/ci/production-smoke.sh`, with the docker logs printed by its trap;
- on failure, also upload `storage/logs/*.log`.

### Success Criteria:

#### Automated Verification:

- Workflow file is valid YAML and a validator accepts it: `npx --yes @action-validator/cli .github/workflows/master.yml`
- Smoke script is lint-clean and executable: `shellcheck scripts/ci/production-smoke.sh && test -x scripts/ci/production-smoke.sh`
- Local boot smoke passes: `bash scripts/ci/production-smoke.sh`

#### Manual Verification:

- Push `develop`, open the PR into `main`, and confirm that the `production-image` job is green (`gh pr checks`). Pushing and opening the PR publish to GitHub, so they are manual.
- Existing jobs (`frontend-build`, `backend`, `e2e-tests`) are still green on the same PR.
- Break the image on purpose in a scratch commit, for example remove `postgresql-client-$POSTGRES_VERSION`, and confirm `production-image` goes red at the backup step. Then revert.

---

## Phase 5: kostecki-dev-infra Integration

### Overview

Register menu-maker in the infra repo so the VPS bootstrap, the docs and the deploy wrapper match what this repo ships. This phase commits in a **second repository**, `~/my-projects/kostecki-dev-infra`, on a new branch `feat/menu-maker` cut from `master`. Commit there locally. Pushing the branch, opening the PR and merging it are manual steps (5.6), because they publish to GitHub. In menu-maker, only the Progress rows change.

### Changes Required:

#### 1. App guide

**File**: `kostecki-dev-infra/docs/menu-maker.md`

**Intent**: The operator's single page for menu-maker, modelled on `docs/root-keeper.md`.

**Contract**:
- **Sections:** DNS (`menu.kostecki.dev`, Cloudflare Proxied, Full strict) and the `curl -svI .../up` check; VPS directory; Traefik labels; production stack table (files, services, networks, volume `menu-pgsql`).
- **`.env.prod`:**
  - the required keys the preflight enforces;
  - `APP_KEY` from `run --rm --no-deps app php artisan key:generate --show`, because the file is mounted read-only;
  - reuse of the sibling SMTP account;
  - choosing `AI_SCALING_DRIVER` and its key or model.
- **Backups:** DB-only, encrypted, in `/storage/menu-maker-backups/menu-maker/`, at 05:15, 05:30 and 05:45 Europe/Warsaw, password kept off the VPS. Include a VPS slot table that lists every app's jobs, including household-treasurer's 03:30 and 04:00 and wallet-master's UTC jobs, so the next app does not collide.
- **Restore:** DB-only steps adapted from `docs/root-keeper.md` (7z in a throwaway container, a throwaway `postgres:18-alpine`, matching `POSTGRES_USER`), marked "not drilled yet".
- **Deploy:** first and update flows, the maintenance-window behaviour, and recovery when a deploy fails and the app stays down (read the error, fix, re-run `deploy.sh`, or `./scripts/artisan.sh up` after a manual fix).
- **Rollback:** roll forward, as in household-treasurer `README.md:189-200`, because `deploy.sh` refuses a detached HEAD and always fast-forwards to `origin/main`. The procedure is in Migration Notes → Rollback.
- **Verify:** `/up` and `supervisorctl status`.

#### 2. Deploy wrapper and VPS bootstrap

**File**: `kostecki-dev-infra/scripts/deploy-menu-maker.sh`, `kostecki-dev-infra/scripts/bootstrap-vps.sh`, `kostecki-dev-infra/.env.example`

**Intent**: Same shape as `deploy-root-keeper.sh`. New servers get the directories automatically.

**Contract**:
- `deploy-menu-maker.sh` changes to `${VPS_MENU_MAKER_DIR:-/srv/apps/menu-maker}`, runs `./scripts/deploy.sh` and prints the URL. It is executable and uses LF line endings (`.gitattributes`).
- `bootstrap-vps.sh` adds `/srv/apps/menu-maker` and `/storage/menu-maker-backups` to `mkdir` and `chown`, plus a next-steps line.
- `.env.example` adds `VPS_MENU_MAKER_DIR=/srv/apps/menu-maker`.

#### 3. Index docs

**File**: `kostecki-dev-infra/README.md`, `docs/ARCHITECTURE.md`, `docs/SETUP.md`, `docs/ADDING-AN-APP.md`

**Intent**: menu-maker appears everywhere the other Laravel apps do.

**Contract**:
- `README.md`: repo table row and docs table row.
- `docs/ARCHITECTURE.md`: the `menu.kostecki.dev` branch in the diagram, a layer table row, a repositories tree entry and the `/srv/apps` and `/storage` trees.
- `docs/SETUP.md`: a `### menu-maker` checklist, the repo-structure script list and the infra `.env` block.
- `docs/ADDING-AN-APP.md`: the Laravel examples line.

### Success Criteria:

#### Automated Verification:

- Scripts parse and are lint-clean: `cd ~/my-projects/kostecki-dev-infra && bash -n scripts/deploy-menu-maker.sh scripts/bootstrap-vps.sh && shellcheck scripts/deploy-menu-maker.sh scripts/bootstrap-vps.sh`
- Existing infra shell tests still pass: `cd ~/my-projects/kostecki-dev-infra && bash scripts/lib/landing-lib.test.sh && bash scripts/lib/backup-lib.test.sh`
- Every relative link in `docs/menu-maker.md` resolves to an existing file in the infra repo: a scripted grep of its `](./…)` and `](…md)` targets.
- Commit exists on `feat/menu-maker` in the infra repo: `git -C ~/my-projects/kostecki-dev-infra log master..feat/menu-maker --oneline` is non-empty.

#### Manual Verification:

- Read `docs/menu-maker.md` end to end and confirm that a person who never saw this plan could do the first deploy with it.
- Push `feat/menu-maker`, then open and merge the infra PR `feat/menu-maker` → `master`.

---

## Phase 6: First Production Deploy

### Overview

Put the stack on the VPS and prove the key paths on the real server, including the memory the roadmap said must be measured there. Everything here is manual.

**Prerequisites:**
- Phases 1–4 merged `develop` → `main` with CI green.
- The Phase 5 infra PR merged and pulled to `/srv/infra`.
- The SMTP credentials and the AI key are available.

### Changes Required:

None in code. The operator follows `kostecki-dev-infra/docs/menu-maker.md`:
1. Create DNS `menu` → VPS IP (Proxied).
2. Clone the repo to `/srv/apps/menu-maker` on `main`.
3. Create `.env.prod` from the template, fill the secrets, and generate `APP_KEY` with `--show`.
4. Run `/srv/infra/scripts/deploy-menu-maker.sh`.

### Success Criteria:

#### Manual Verification:

- `curl -svI https://menu.kostecki.dev/up` returns `HTTP/2 200` with `server: cloudflare` and a `cf-ray` header.
- `supervisorctl status` in `menu-app` shows `php`, `queue` and `scheduler` RUNNING.
- **Account flow:**
  - Register a test account on the Polish screens and log in.
  - Use "forgot password": the e-mail arrives through the sibling SMTP, its link starts with `https://menu.kostecki.dev` and resets the password.
- **Generation and PDF:**
  - With at least 7 recipes per category and a family profile, generate a plan. It finishes in under 1 minute with AI-refined amounts and no fallback notice, using the real driver.
  - Regenerate once.
  - The PDF downloads and shows Polish characters correctly.
- **Error alert:** `./scripts/artisan.sh tinker --execute 'Log::error("menu-maker production alert smoke");'` delivers exactly one alert e-mail to `LOG_ALERT_MAIL_TO`.
- **Backup:** `./scripts/artisan.sh backup:run --only-db` creates an encrypted `.zip` in `/storage/menu-maker-backups/menu-maker/`, and it opens with the off-VPS copy of `BACKUP_ARCHIVE_PASSWORD` through 7z.
- **Memory:** during a generation and a PDF download, `docker stats --no-stream menu-app menu-pgsql` stays below the 768m and 512m limits. Record the peak in `docs/menu-maker.md`.
- **Redeploy:**
  - Run `deploy-menu-maker.sh` a second time with no changes. The site shows 503 briefly, comes back, and the test account, recipes and plan are intact.
  - A pre-deploy backup appears.
- **Scheduled backup:** the next morning a scheduled backup from 05:15 exists, and no failure e-mail was sent.

---

## Testing Strategy

### Unit Tests:

- `tests/Unit/ProductionStackTest.php`: consistency between the compose file, the Dockerfile, supervisord, `.env.prod.example` and the job/queue timeouts.

### Feature Tests:

- `tests/Feature/BackupScheduleTest.php`: DB-only backup config, schedule slots, timezone, production-only.
- `tests/Feature/TrustedProxyTest.php`: `X-Forwarded-Proto: https` gives https redirects and a secure request.

### Shell Tests:

- `scripts/lib/production-deploy.test.sh`: every preflight rule refuses, a complete env passes, the branch guard works.

### Integration Tests:

- `scripts/ci/production-smoke.sh`, run locally and by the CI `production-image` job: real image, real compose, `production_prepare_storage`, migrations, `/up`, supervisord programs, a real `pg_dump` 18 backup.

### Manual Testing Steps:

1. The Phase 6 production smoke list, top to bottom, on the real VPS.
2. The deliberate image break in Phase 4, plus the PR checks there.

## Performance Considerations

- The VPS is shared by four apps.
  - Limits are app 768m / 1 CPU and pgsql 512m / 0.5 CPU, smaller than root-keeper's 1g, because menu-maker stores no files and dompdf stays well under 128 MB per render.
  - Phase 6 measures the real peak. If it is close to the limit, raise `mem_limit` in a follow-up rather than lowering `PHP_CLI_SERVER_WORKERS`.
- Four `artisan serve` workers, plus one queue worker, means one generation at a time. A second user's generation waits in the queue: the poll shows `pending` and the result arrives later. That is acceptable at MVP traffic.
- Docker log rotation (10m × 3) and `LOG_DAILY_DAYS=14` keep the disk bounded.

## Migration Notes

- The first deploy runs all migrations on an empty database, including `CREATE EXTENSION unaccent` as the superuser `POSTGRES_USER` and the units catalog seeded inside a migration. Do not run `db:seed` in production: `DatabaseSeeder` only creates a test user.
- Later deploys:
  - take a DB-only backup in the old container under maintenance mode before migrating;
  - stop the worker gracefully, letting an in-flight job finish within 90 s;
  - reopen only after migrations, caches and `queue:restart` all succeeded.
- **Rollback (roll forward):** `deploy.sh` cannot deploy an older commit. `production_pull_latest` refuses a detached HEAD and fast-forwards `main` to `origin/main`. Follow household-treasurer `README.md:189-200`:
  1. If the release added migrations, then on the VPS run `./scripts/artisan.sh down` and `./scripts/artisan.sh migrate:rollback --force` while the new code is still deployed.
  2. Locally, `git revert` the release commits on `main` and push.
  3. On the VPS, run `scripts/deploy.sh`, which pulls the revert and reopens the app.
  - Restoring the pre-deploy backup (`docs/menu-maker.md` → Restore) is the last resort, when `migrate:rollback` cannot undo a data change.

## References

- Roadmap F-02: `context/foundation/roadmap.md:94-106`
- Infra contract: `~/my-projects/kostecki-dev-infra/docs/ADDING-AN-APP.md`, `docs/root-keeper.md`, `scripts/deploy-root-keeper.sh`, `docker-compose.yml` (Traefik, `proxy`, `letsencrypt`, `websecure`)
- Production stack template: `~/my-projects/root-keeper/{docker-compose.prod.yml,docker/8.5/Dockerfile.prod,docker/8.5/supervisord.prod.conf,scripts/deploy.sh,scripts/lib/production-deploy.sh,scripts/artisan.sh,.env.prod.example,config/backup.php,tests/Feature/BackupScheduleTest.php}`
- Maintenance window, `.dockerignore` and PG client test: `~/my-projects/household-treasurer/{scripts/lib/production-deploy.sh:30-115,.dockerignore,tests/Unit/ProductionPostgresClientVersionTest.php}`
- Worker timeout constraint: `context/archive/2026-11-14-ai-ingredient-scaling/plan.md:639`, `app/Jobs/GenerateMealPlanJob.php:38,56-72`, `config/queue.php:42`
- Production log and mail settings: `context/archive/2026-11-13-error-tracking-baseline/plan-brief.md`, `config/logging.php:71-87`
- Production SMTP deferred to F-02: `context/archive/2026-11-14-polish-auth-with-24h-session/` (follow-up F3)
- SSR default and build: `config/inertia.php:24`, `package.json:6`, `vite.config.js`, `resources/css/app.css:6`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: App Production Config and Backups

#### Automated

- [x] 1.1 Backup and proxy tests pass — cf6afb8
- [x] 1.2 Full suite passes — cf6afb8
- [x] 1.3 Larastan passes without new baseline entries — cf6afb8
- [x] 1.4 Pint passes — cf6afb8
- [x] 1.5 `.env.prod` is git-ignored — cf6afb8
- [x] 1.6 Config still caches — cf6afb8

### Phase 2: Production Image and Compose

#### Automated

- [x] 2.1 Consistency test passes — 180f341
- [x] 2.2 Compose file is valid — 180f341
- [x] 2.3 Image builds from a clean context — 180f341
- [x] 2.4 Image has no SSR bundle, no Node and no env secrets — 180f341
- [x] 2.5 Image ships the matching dump client — 180f341
- [x] 2.6 Full suite passes — 180f341

### Phase 3: Deploy Scripts

#### Automated

- [x] 3.1 Preflight tests pass — 90ec829
- [x] 3.2 Scripts are lint-clean — 90ec829
- [x] 3.3 Scripts are executable — 90ec829
- [x] 3.4 The example env file fails preflight because it has blank secrets — 90ec829

### Phase 4: CI Boot Smoke

#### Automated

- [x] 4.1 Workflow file is valid YAML and accepted by a validator — 6a4159f
- [x] 4.2 Smoke script is lint-clean and executable — 6a4159f
- [x] 4.3 Local boot smoke passes — 6a4159f

#### Manual

- [ ] 4.4 The `production-image` job is green on the PR from `develop` into `main`
- [ ] 4.5 Existing jobs are still green on the same PR
- [ ] 4.6 Deliberate image break turns `production-image` red, then revert

### Phase 5: kostecki-dev-infra Integration

#### Automated

- [x] 5.1 Scripts parse and are lint-clean
- [x] 5.2 Existing infra shell tests still pass
- [x] 5.3 Every relative link in `docs/menu-maker.md` resolves
- [x] 5.4 Commit exists on `feat/menu-maker` in the infra repo

#### Manual

- [ ] 5.5 `docs/menu-maker.md` is enough for a first deploy by someone new
- [ ] 5.6 Push, open and merge the infra PR `feat/menu-maker` → `master`

### Phase 6: First Production Deploy

#### Manual

- [ ] 6.1 `/up` returns 200 through Cloudflare
- [ ] 6.2 supervisord shows `php`, `queue` and `scheduler` RUNNING
- [ ] 6.3 Register, log in and receive a working https reset e-mail
- [ ] 6.4 Generate with real AI under 1 minute, regenerate, download a correct Polish PDF
- [ ] 6.5 Forced error delivers exactly one alert e-mail
- [ ] 6.6 Manual backup creates an encrypted zip that opens with the off-VPS password
- [ ] 6.7 Memory peak stays below the limits and is recorded
- [ ] 6.8 Second deploy shows a brief 503, keeps the data and takes a pre-deploy backup
- [ ] 6.9 Next-morning scheduled backup exists with no failure e-mail
