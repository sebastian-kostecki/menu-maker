<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Public Deploy with Queue Worker

- **Plan**: context/changes/public-deploy-with-queue-worker/plan.md
- **Mode**: Deep
- **Date**: 2026-11-14
- **Verdict**: REVISE (before triage) → SOUND (after fixes)
- **Findings**: 2 critical, 4 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | FAIL |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding
6/6 paths ✓, 7/9 symbols ✓ (03:30 slot ✗, supervisorctl ✗), brief↔plan ✓, Progress↔Phase ✓ (34/34 rows)

## Findings

### F1 — `supervisorctl status` will not work in the container

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: End-State Alignment
- **Location**: Phase 2 §2 (supervisord), Phase 4 checks, Desired End State, 6.2
- **Detail**:
  - The siblings' `supervisord.prod.conf` has no `[unix_http_server]`, `[supervisorctl]` or `[rpcinterface:supervisor]`.
  - `start-container` loads only `conf.d/supervisord.conf`, so no socket exists and `supervisorctl status` fails.
  - So the CI RUNNING check could never go green, and 6.2 could not be verified.
  - The siblings' infra docs (`docs/root-keeper.md:259`, `household-treasurer.md:177`) carry the same broken command.
- **Fix**: Add the three control sections to the Phase 2 §2 contract and one ProductionStackTest assertion.
- **Decision**: FIXED

### F2 — CI boots the stack without fixing permissions (runner uid 1001)

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 4 §1
- **Detail**:
  - The image files are owned by uid 1337, while the runtime sail user is uid WWWUSER (1000).
  - Only deploy.sh step 10 fixes that, and the CI job did not repeat it.
  - On the runner, `./storage` is owned by uid 1001, so `/up` (compiled view), `config:cache` and `backup:run` would fail.
  - The siblings have no CI boot job to copy from.
- **Fix**: Extract step 10 into `production_prepare_storage <compose>` in the lib, and call it from deploy.sh and from the smoke script after `up -d`.
  - Strength: CI runs the same permission code as production.
  - Tradeoff: one more lib function.
  - Confidence: HIGH — uids confirmed in RK/HT Dockerfile.prod:125-138, compose and start-container.
  - Blind spot: not run on a real runner yet.
- **Decision**: FIXED

### F3 — Backup slots 03:30 and 04:00 are taken (household-treasurer)

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Key Discoveries, Phase 1 §3/§6, Phase 5 §1, Phase 6, Desired End State, brief
- **Detail**:
  - household-treasurer runs `backup:clean` at 03:30 and `backup:monitor` at 04:00 (`bootstrap/app.php:38,45`).
  - wallet-master schedules in UTC (00:00, 01:00, 01:30, 03:00 UTC), so 05:00 Warsaw is taken in summer.
- **Fix**: Move to 05:15 run / 05:30 clean / 05:45 monitor, complete the slot list, and add a VPS slot table to docs/menu-maker.md.
- **Decision**: FIXED (adjusted: 05:15/05:30/05:45 instead of 05:00/05:15/05:30, after finding the wallet-master UTC jobs)

### F4 — Rollback "checkout the previous commit + deploy.sh" is impossible

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Migration Notes → Rollback; Phase 5 §1
- **Detail**: `production_pull_latest` calls `production_assert_branch`, which refuses a detached HEAD, and then runs `git pull --ff-only origin main`. household-treasurer documents a roll-forward instead (README.md:189-200).
- **Fix**: Roll forward: `artisan.sh down` + `migrate:rollback --force`, then `git revert` on main, then `deploy.sh`. Restore from backup is the last resort. Mirror this in docs/menu-maker.md.
- **Decision**: FIXED

### F5 — Phase 4 "Automated" criteria need a push and a PR into main

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Completeness
- **Location**: Phase 4 — Success Criteria 4.2, 4.3
- **Detail**: Pushing and opening a PR are outward-facing actions, and Phase 5 already treats them as manual (5.6). Before the push, nothing proved that the CI job works.
- **Fix A**: Move 4.2/4.3 to Manual.
  - Strength: minimal edit.
  - Tradeoff: no automated proof for Phase 4.
  - Confidence: HIGH.
  - Blind spot: none significant.
- **Fix B ⭐ Recommended**: `scripts/ci/production-smoke.sh`, called by CI and run locally as an Automated criterion; PR checks become Manual.
  - Strength: F1/F2 are caught before the push.
  - Tradeoff: one more script.
  - Confidence: MED — the local uid does not reproduce the runner's uid 1001.
  - Blind spot: the fixed container_name collides with another production-shaped stack.
- **Decision**: FIXED (Fix B)

### F6 — Compose/Dockerfile contract omits environment and ownership

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §1, Phase 2 §3
- **Detail**:
  - Without `WWWUSER` in app.environment, start-container runs `gosu ""`, which breaks `run --rm app php artisan key:generate --show`.
  - pgsql had no `POSTGRES_*` variables.
  - The runtime stage did not specify `--chown=sail:sail` or the storage/bootstrap/cache chown.
- **Fix**: Add the app environment, the pgsql environment and the image ownership to the contracts, plus a ProductionStackTest assertion.
- **Decision**: FIXED

### F7 — "Node 22 matches CI and Sail": Sail uses Node 24

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §1 (assets stage)
- **Detail**: `docker/8.5/Dockerfile:6` has `ARG NODE_VERSION=24`; only CI uses 22.
- **Fix**: Correct the rationale to "matches CI".
- **Decision**: FIXED
