<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Public Deploy with Queue Worker

- **Plan**: context/changes/public-deploy-with-queue-worker/plan.md
- **Scope**: Full plan (automated phases)
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-14
- **Verdict**: REJECTED
- **Findings**: 1 critical, 6 warnings, 3 observations

Phase 6 is manual only and was not reviewed. Infra repo reviewed at `kostecki-dev-infra` branch `feat/menu-maker` (b086f83).

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | FAIL |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | FAIL |

## Findings

### F1 — Runtime uid change fails: `sail` stays 1337 because `ubuntu` owns uid 1000

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: docker/8.5/Dockerfile.prod:67-68
- **Detail**: `ubuntu:24.04` ships a user `ubuntu` with uid 1000. With `WWWUSER=1000`, `start-container` fails with `usermod: UID '1000' already exists` (seen in `docker logs`). `sail` stays at uid 1337, so `production_prepare_storage` hands the bind-mounted `storage/` to 1337. `run`/`exec` commands go through `gosu 1000`, which runs as the `ubuntu` user, not `sail`. The plan's contract says "At runtime `start-container` changes the uid to `WWWUSER`", and that does not happen. Sail's dev Dockerfile handles this with `RUN userdel -r ubuntu` (`docker/8.5/Dockerfile:88`). Running the local smoke test (`scripts/ci/production-smoke.sh`) left the developer checkout's `storage/` owned by 1337:1001. Sail's dev container (uid 1000) can then no longer write `storage/logs/laravel.log`, and `vendor/bin/sail composer test` now fails 7 tests with "Permission denied". The siblings' `Dockerfile.prod` files have the same gap.
- **Fix**: Add `RUN userdel -r ubuntu` before `useradd` in the runtime stage, and add a `ProductionStackTest` assertion for it. Restore local ownership with `docker run --rm -v "$PWD/storage:/s" alpine chown -R 1000:1000 /s`, then re-run the smoke test and the full suite.
- **Decision**: FIXED — `RUN userdel -r ubuntu` before `useradd` in Dockerfile.prod, plus a ProductionStackTest assertion; local storage/ chowned back to 1000:1001; smoke re-run leaves it 1000-owned

### F2 — A failed pre-deploy backup leaves an untouched app in maintenance mode

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: scripts/lib/production-deploy.sh:151-156
- **Detail**: `artisan down` sets `MAINTENANCE_STARTED=1` before `backup:run`. When the backup fails (a pg_dump error, a full disk), `set -e` exits and the trap prints "STAYS in maintenance mode". At that point nothing has changed yet: the old container, code and schema are all intact. The site stays down until someone reads the docs.
- **Fix A ⭐ Recommended**: If `backup:run` fails, run `artisan up` in the old container, reset `MAINTENANCE_STARTED=0` and exit non-zero with "backup failed, app restored, nothing deployed".
  - Strength: Keeps the plan's "no writes between backup and migration" guarantee and removes needless downtime.
  - Tradeoff: Adds a small branch to the maintenance function and its test.
  - Confidence: HIGH — the failure point is before `up -d`, so the old container is known to be healthy.
  - Blind spot: An `artisan up` that itself fails is not handled (the trap message still applies).
- **Fix B**: Keep the current behaviour and document it in `docs/menu-maker.md` recovery as "backup failed → `./scripts/artisan.sh up`".
  - Strength: No code change.
  - Tradeoff: Downtime until a human reacts.
  - Confidence: HIGH.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A) — a failed backup runs `artisan up` in the old container, resets MAINTENANCE_STARTED and exits 1; covered in production-deploy.test.sh

### F3 — The documented restore procedure cannot work as written

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: kostecki-dev-infra docs/menu-maker.md:214, 237-239
- **Detail**: There are three problems:
  - Step 1 stops `app`, then step 3 runs `deploy.sh`. `production_prepare_running_instance` refuses a stopped container unless `DEPLOY_WITHOUT_BACKUP=1`.
  - Step 2 loads a plain pg_dump (no `--clean`) into a non-empty `menu-pgsql` with `ON_ERROR_STOP=1`, which fails on the existing tables.
  - The 7z example passes the password in argv (`-p'<password>'`), which leaks it to shell history and `ps`.
  The section is marked "not drilled", but it is the operator's last resort.
- **Fix**: In the doc, drop and recreate the database before loading the dump (or `DROP SCHEMA public CASCADE; CREATE SCHEMA public; CREATE EXTENSION unaccent`). Run `DEPLOY_WITHOUT_BACKUP=1 ./scripts/deploy.sh` (or `start app` and `artisan.sh up`). Let 7z prompt for the password (`-p` with no value).
  - Strength: Makes the last-resort path executable before anyone needs it.
  - Tradeoff: Docs only, in a second repo (a new commit on `feat/menu-maker`).
  - Confidence: MED — still undrilled; the exact spatie dump filename is unverified.
  - Blind spot: The restore has never run end to end.
- **Decision**: FIXED — infra feat/menu-maker e9153a7: restore drops and recreates the DB, comes back with DEPLOY_WITHOUT_BACKUP=1, and 7z prompts for the password

### F4 — Empty `ANTHROPIC_MODEL`/`ANTHROPIC_EFFORT` override config defaults

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: .env.prod.example:56-57
- **Detail**: `KEY=` yields `''`, not the `env()` default (`config/services.php:50-51`). An operator who copies the template and switches to `AI_SCALING_DRIVER=anthropic` sends an empty model and effort, while the preflight checks only `ANTHROPIC_API_KEY`.
- **Fix**: Comment out the two lines in `.env.prod.example` (`# ANTHROPIC_MODEL=claude-opus-5-5`, `# ANTHROPIC_EFFORT=low`).
- **Decision**: FIXED — ANTHROPIC_MODEL/EFFORT commented out in .env.prod.example

### F5 — `.dockerignore` lets live storage data, including unencrypted dumps, into image layers

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: .dockerignore:48-56
- **Detail**: `deploy.sh` builds on the VPS, where `./storage` holds live data. `storage/app/backup-temp` (spatie's unencrypted SQL dump, which can be left behind after a failed run), `storage/app/private`, `storage/app/public` and `storage/framework/down` are not excluded, so `COPY . .` bakes them into the layers. The runtime bind mount hides them but does not remove them from the image.
- **Fix**: Add `storage/app/backup-temp`, `storage/app/private`, `storage/app/public` and `storage/framework/down` to `.dockerignore`.
- **Decision**: FIXED — backup-temp, private, public and framework/down added to .dockerignore

### F6 — `scripts/artisan.sh` runs Artisan as root

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: scripts/artisan.sh:26
- **Detail**: `exec app php artisan` has no `-u sail`. The recovery docs tell the operator to run `artisan.sh up`, `down` and `migrate:rollback`, which can create root-owned daily logs and caches that the `sail` web process cannot write. `deploy.sh` itself uses `-u sail` everywhere (root-keeper commit `38a3426`). The siblings' `artisan.sh` has the same gap.
- **Fix**: Use `$COMPOSE exec -u sail "${exec_flags[@]}" app php artisan "$@"`.
- **Decision**: FIXED — artisan.sh uses `exec -u sail`

### F7 — First deploy serves traffic before maintenance mode; `down` runs before storage is prepared

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: scripts/deploy.sh (steps 8-10), scripts/lib/production-deploy.sh:161-184
- **Detail**: With no old container, `up -d` starts serving against an empty schema, and only then does `production_ensure_maintenance_mode` run `artisan down` as `sail`. That happens before `production_prepare_storage` fixes ownership (compare F1). If `down` fails, `MAINTENANCE_STARTED` is still 0, so the trap stays silent.
- **Fix**: In `deploy.sh`, call `production_prepare_storage "$COMPOSE"` before `production_ensure_maintenance_mode`.
- **Decision**: FIXED — deploy.sh runs production_prepare_storage before production_ensure_maintenance_mode

### F8 — Scheduled backup and monitor silently skip while the app is in maintenance mode

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: bootstrap/app.php:28-47
- **Detail**: The scheduler skips events in maintenance mode by default. After a failed deploy the app stays down on purpose, so the 05:15 backup and the 05:45 monitor (the alert) both stop.
- **Fix**: Add `->evenInMaintenanceMode()` to `backup:run` and `backup:monitor`, and assert it in `BackupScheduleTest`.
- **Decision**: FIXED — evenInMaintenanceMode() on backup:run and backup:monitor, asserted in BackupScheduleTest

### F9 — `trustProxies(at: '*')` on a shared `proxy` network

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: bootstrap/app.php:26
- **Detail**: Any container on the external `proxy` network can spoof `X-Forwarded-*` headers. That affects throttle and IP logging, and the `Host` used in reset links, since there is no `TrustHosts`. It matches the siblings and the plan, and the plan's "NOT Doing" list already accepts the IP limitation.
- **Fix**: Record it as an accepted risk in `docs/menu-maker.md`, or add `$middleware->trustHosts(at: ['menu.kostecki.dev'])`.
- **Decision**: ACCEPTED — documented as an accepted risk in the infra docs/menu-maker.md (trustHosts would reject the in-container localhost health checks)

### F10 — `production-image` CI job has no timeout and never exercises `deploy.sh`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: .github/workflows/master.yml:233-258
- **Detail**: The job does a full apt, composer and npm image build without `timeout-minutes`. The deploy ordering (down → backup → up → migrate → up) and the failure trap are covered only through unit-level helpers, not end to end. The plan scoped it that way.
- **Fix**: Add `timeout-minutes: 30` to the job.
- **Decision**: FIXED — timeout-minutes: 30 on production-image
