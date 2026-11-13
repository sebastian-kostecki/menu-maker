# Safe Dependency Updates Implementation Plan

## Overview

Refresh Composer and npm dependencies to the newest versions allowed by the current constraints (no major-version jumps), working only inside Sail on PHP 8.5. The same change sets up E2E from the host against Sail as the baseline for later stages, drops the unused `shadcn-vue` CLI from `package.json`, and adds a Pint gate to CI.

Stage 1 of the dependency upgrade (stages 2–6: `postgres-migration`, `laravel-13-upgrade`, `pest-5-upgrade`, `inertia-3-vite-8-upgrade`, `tailwind-4-upgrade`).

## Current State Analysis

- Baseline commit `82f3786` (PHP 8.5, Pest 4, PHPUnit 12, Laravel 12.72). In the 8.5 container: 124/124 tests pass and Larastan is clean.
- `composer update --dry-run` in Sail upgrades about 35 packages within constraints. Notable: inertia-laravel 2.0.5→2.0.21, larastan 3.6→3.15, pint 1.24→1.32, sanctum 4.2→4.4, tinker 2.10→2.11, ziggy 2.5→2.6.5, mockery 1.6.15, and laravel/boost 1.1.5→1.8.13 (with laravel/mcp 0.1→0.5).
- `composer audit` flags psy/psysh (CVE-2026-25129, medium) and symfony/yaml (4 advisories, one high).
- `npm audit` reports 43 vulnerabilities (1 critical, 32 high). Most have in-range fixes. The rest come from `shadcn-vue`, whose only offered "fix" is a downgrade to 0.11.4.
- `shadcn-vue` is in `dependencies` (`package.json`), but nothing under `resources/js` imports it. It is the component generator CLI.
- Pint `--test` already fails on 7 files. CI (`.github/workflows/master.yml`) does not run Pint at all.
- The Boost guideline block in `CLAUDE.md` still says PHP 8.4.1 and Pest 3. Boost 1.1 has no `boost:update` command.
- E2E: `playwright.config.ts` starts `php artisan serve` on the host (bypassing Sail) unless `E2E_SKIP_WEBSERVER` is set. The host has only Chromium installed, but the config also runs Firefox. Host ports 80, 5173, 5432 and 8080 are taken (household-treasurer and others). The suite is `tests/e2e/login.spec.ts` (register and login).

## Desired End State

- `composer.lock` and `package-lock.json` hold the newest in-range versions. `composer.json` and `package.json` keep their constraints, except that `shadcn-vue` is removed.
- `composer audit` is clean, and `npm audit` has no findings that an in-range update could fix.
- The Boost block in `CLAUDE.md` reflects PHP 8.5 and Pest 4.
- In Sail: Pest passes, Larastan is clean, `pint --test` is clean, `npm run build` succeeds, and E2E passes in Chromium and Firefox against the Sail app on `http://localhost:8081`.
- CI runs `composer pint-test`.

### Key Discoveries:

- `docker-compose.yml:13-14`: the app and Vite ports come from `APP_PORT` (default 80) and `VITE_PORT` (default 5173). Both defaults are busy on this host, so `sail up` fails without overrides.
- `playwright.config.ts`: `baseURL` comes from `E2E_BASE_URL`, and `webServer` is skipped when `E2E_SKIP_WEBSERVER` is set.
- `.github/workflows/master.yml:100-101`: the Larastan step. Pint belongs next to it in the `backend` job.
- `CLAUDE.md:18`: the E2E command line. `CLAUDE.md:24`: the shadcn-vue location.
- `node_modules/` is bind-mounted, so `sail npm …` and host `npx playwright` share it.

## What We're NOT Doing

- No major-version upgrades (Laravel 13, Inertia 3, Vite 8, Tailwind 4, Pest 5, tinker 3, Boost 2, vueuse 15, lucide 1.0, concurrently 10). Those belong to stages 3–6.
- No changes to version constraints in `composer.json` or `package.json` beyond removing `shadcn-vue`.
- No database change. MySQL to PostgreSQL is `postgres-migration`.
- No commit of the local `.env` port overrides. `.env` is not tracked.
- No `npm audit fix --force`.
- The uncommitted Polish localisation work (`lang/`, `Login.vue`, `login.spec.ts`, `config/app.php`, `.env.example`) is not committed here. E2E runs against the working tree as it is.

## Implementation Approach

Lock-only refreshes, one commit per phase, everything executed via `vendor/bin/sail`. The E2E baseline comes first, so any later E2E failure can be attributed to the dependency change and not to the setup.

## Critical Implementation Details

- **Timing & lifecycle**: Playwright browsers are tied to the Playwright version. After phase 3 bumps `@playwright/test` to 1.65, install browsers again on the host (`npx playwright install chromium firefox`) before running E2E, or the run fails with "Executable doesn't exist".
- **Debug & observability**: `npx playwright install firefox` on the host can need system libraries that only `install-deps` (sudo) provides. If Firefox fails to launch, ask the human to run `! sudo npx playwright install-deps firefox`. Do not drop Firefox from the config.

## Phase 1: E2E baseline on Sail

### Overview

Run the app in Sail on non-conflicting ports and get the existing E2E suite green from the host before any dependency changes.

### Changes Required:

#### 1. Local environment (not committed)

**File**: `.env`

**Intent**: Move the Sail app and Vite ports off the busy defaults so `sail up -d` starts next to household-treasurer.

**Contract**: `APP_PORT=8081`, `VITE_PORT=5174`, `APP_URL=http://localhost:8081`. The SQLite database file `database/database.sqlite` exists and is migrated via `sail artisan migrate`.

#### 2. Playwright browsers on the host

**Intent**: Install Firefox for the configured `firefox` project; Chromium is already present.

**Contract**: `npx playwright install firefox` (host). Fallback for missing system libraries is in Critical Implementation Details.

#### 3. Document the E2E run

**File**: `CLAUDE.md`

**Intent**: Replace the E2E command line (`CLAUDE.md:18`) with the Sail-based invocation, so agents do not run `php artisan serve` on host PHP.

**Contract**: The documented command is `vendor/bin/sail npm run build` first, then `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e` from the host. Add a note that `.env` must set `APP_PORT` and `VITE_PORT` when the defaults are busy.

### Success Criteria:

#### Automated Verification:

- Containers are up: `vendor/bin/sail ps` shows `laravel.test` running
- App answers: `curl -fsS -o /dev/null http://localhost:8081/login`
- Assets build: `vendor/bin/sail npm run build`
- E2E passes in Chromium and Firefox: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:8081 npm run test:e2e`

#### Manual Verification:

- Login page at http://localhost:8081/login renders with styles

**Implementation Note**: After automated verification passes, pause for manual confirmation before Phase 2.

---

## Phase 2: Composer refresh

### Overview

Update all Composer packages within constraints and refresh the Boost guideline block.

### Changes Required:

#### 1. Lockfile

**File**: `composer.lock`

**Intent**: Pull the newest in-range versions, including transitive packages, which also resolves the psysh and symfony/yaml advisories.

**Contract**: `vendor/bin/sail composer update`; `composer.json` unchanged.

#### 2. Boost guidelines

**File**: `CLAUDE.md` (the `<laravel-boost-guidelines>` block only)

**Intent**: Regenerate the vendor block so it reports PHP 8.5 and Pest 4. The project section above it stays untouched.

**Contract**: `vendor/bin/sail artisan boost:update` if Boost 1.8 provides it, otherwise `vendor/bin/sail artisan boost:install` again with Claude Code only. Afterwards remove any regenerated `.cursor/mcp.json` or `.cursor/rules/laravel-boost.mdc`, and confirm `.mcp.json` still runs `vendor/bin/sail artisan boost:mcp`.

### Success Criteria:

#### Automated Verification:

- Tests pass: `vendor/bin/sail composer test`
- Larastan clean: `vendor/bin/sail composer phpstan`
- No Composer advisories: `vendor/bin/sail composer audit`
- Boost block reports PHP 8.5 and Pest v4: `grep -E "php - 8\.5|pestphp/pest \(PEST\) - v4" CLAUDE.md`
- Boost MCP server lists its tools through Sail (initialize + `tools/list` over stdin to `vendor/bin/sail artisan boost:mcp`)
- E2E passes in Chromium and Firefox

#### Manual Verification:

- `CLAUDE.md` diff touches only the Boost block

**Implementation Note**: After automated verification passes, pause for manual confirmation before Phase 3.

---

## Phase 3: npm refresh

### Overview

Remove the `shadcn-vue` CLI from the dependency tree and update all npm packages within their ranges.

### Changes Required:

#### 1. Remove shadcn-vue

**File**: `package.json`, `package-lock.json`

**Intent**: `shadcn-vue` is a generator CLI that the app never imports. Keeping it installed carries vulnerabilities that cannot be fixed in range.

**Contract**: `vendor/bin/sail npm uninstall shadcn-vue`. `components.json` stays, because the CLI reads it.

#### 2. Lockfile

**File**: `package-lock.json`

**Intent**: Newest in-range versions for direct and transitive packages (vue 3.5.45, vite 7.3, @inertiajs/vue3 2.3, playwright 1.65, axios, reka-ui, tailwindcss 3.4.19 …).

**Contract**: `vendor/bin/sail npm update`; `package.json` ranges unchanged.

#### 3. Document the component generator

**File**: `CLAUDE.md` (project section, line 24)

**Intent**: Tell agents how to add shadcn-vue components now that the CLI is not installed.

**Contract**: The documented command is `vendor/bin/sail npx shadcn-vue@2 add <component>`, run against `@components.json`.

### Success Criteria:

#### Automated Verification:

- `shadcn-vue` is not installed: `npm ls shadcn-vue` reports it as empty
- Build succeeds: `vendor/bin/sail npm run build`
- No in-range-fixable npm vulnerabilities: `vendor/bin/sail npm audit` shows no fixes that do not require `--force`
- Playwright browsers refreshed: `npx playwright install chromium firefox`
- E2E passes in Chromium and Firefox
- Tests pass: `vendor/bin/sail composer test`

#### Manual Verification:

- Recipes, family members and meal plans pages render correctly at http://localhost:8081 (dropdowns, dialogs, toasts)

**Implementation Note**: After automated verification passes, pause for manual confirmation before Phase 4.

---

## Phase 4: Pint fix and CI gate

### Overview

Apply Pint 1.32 to the codebase in a formatting-only commit and enforce it in CI.

### Changes Required:

#### 1. Formatting

**Files**: those reported by `pint --test` (at least the 7 pre-existing: `app/Http/Controllers/MealPlanController.php`, `app/Http/Requests/GenerateMealPlanRequest.php`, `app/Http/Resources/MealPlanCollection.php`, `app/Http/Resources/MealPlanResource.php`, `tests/CreatesApplication.php`, `tests/Feature/FamilyMemberTest.php`, `tests/Feature/MealPlanControllerTest.php`)

**Intent**: Bring the code in line with `pint.json` under the updated Pint, as a pure formatting commit.

**Contract**: `vendor/bin/sail composer pint`; no behavioural edits in this commit.

#### 2. CI step

**File**: `.github/workflows/master.yml`

**Intent**: Fail the pipeline on style drift.

**Contract**: New step "Code style (Pint)" running `composer pint-test` in the `backend` job, next to the Larastan step (line 100).

### Success Criteria:

#### Automated Verification:

- Style clean: `vendor/bin/sail composer pint-test`
- Tests pass: `vendor/bin/sail composer test`
- Larastan clean: `vendor/bin/sail composer phpstan`
- Workflow contains the Pint step: `grep -n "composer pint-test" .github/workflows/master.yml`

#### Manual Verification:

- Formatting commit diff contains whitespace, ordering or syntax-style changes only

---

## Testing Strategy

### Unit Tests:

- No new tests. The existing Pest suite (Unit and Feature, 124 tests) is the regression net for backend updates.

### Integration Tests:

- Playwright `tests/e2e/login.spec.ts` (register and login) in Chromium and Firefox against Sail, run in every phase from Phase 1 on.

### Manual Testing Steps:

1. Open http://localhost:8081, register, log in.
2. Create, edit and delete a recipe; add a family member; open meal plans.
3. Check the dropdown menus, dialogs and sonner toasts (reka-ui and vue-sonner updates).

## Performance Considerations

None. Lockfile refresh only.

## Migration Notes

Rollback is per phase via `git revert <sha>` followed by `sail composer install` or `sail npm ci`. The local `.env` port overrides stay in place for later stages.

## References

- Change identity: `context/changes/deps-safe-updates/change.md`
- Baseline commit: `82f3786` (PHP 8.5)
- Sail compose ports: `docker-compose.yml:13-14`
- Playwright config: `playwright.config.ts`
- CI backend job: `.github/workflows/master.yml:100-101`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: E2E baseline on Sail

#### Automated

- [ ] 1.1 Containers are up
- [ ] 1.2 App answers on port 8081
- [ ] 1.3 Assets build
- [ ] 1.4 E2E passes in Chromium and Firefox

#### Manual

- [ ] 1.5 Login page renders with styles

### Phase 2: Composer refresh

#### Automated

- [ ] 2.1 Tests pass
- [ ] 2.2 Larastan clean
- [ ] 2.3 No Composer advisories
- [ ] 2.4 Boost block reports PHP 8.5 and Pest v4
- [ ] 2.5 Boost MCP server lists its tools through Sail
- [ ] 2.6 E2E passes in Chromium and Firefox

#### Manual

- [ ] 2.7 CLAUDE.md diff touches only the Boost block

### Phase 3: npm refresh

#### Automated

- [ ] 3.1 shadcn-vue is not installed
- [ ] 3.2 Build succeeds
- [ ] 3.3 No in-range-fixable npm vulnerabilities
- [ ] 3.4 Playwright browsers refreshed
- [ ] 3.5 E2E passes in Chromium and Firefox
- [ ] 3.6 Tests pass

#### Manual

- [ ] 3.7 Recipes, family members and meal plans pages render correctly

### Phase 4: Pint fix and CI gate

#### Automated

- [ ] 4.1 Style clean
- [ ] 4.2 Tests pass
- [ ] 4.3 Larastan clean
- [ ] 4.4 Workflow contains the Pint step

#### Manual

- [ ] 4.5 Formatting commit diff contains style changes only
