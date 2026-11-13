# Safe Dependency Updates — Plan Brief

> Full plan: `context/changes/deps-safe-updates/plan.md`

## What & Why

This is stage 1 of the dependency upgrade: refresh Composer and npm to the newest versions the current constraints allow, with no major-version jumps. It clears the known Composer advisories and the fixable npm vulnerabilities (43 reported, 1 critical). It also gives stages 2–6 a clean, tested starting point with E2E running against Sail.

## Starting Point

The baseline is commit `82f3786`: PHP 8.5 in Sail, Pest 4 and Laravel 12.72, with 124/124 tests passing. Lockfiles are months old. `shadcn-vue`, a generator CLI, sits in `dependencies`. Pint already fails on 7 files and is not run in CI. E2E only ran via host `php artisan serve`, and the default Sail ports are busy on this host.

## Desired End State

Lockfiles are current within range. `composer audit` is clean, and `npm audit` has nothing left that an in-range update could fix. The Boost block in `CLAUDE.md` matches PHP 8.5 and Pest 4. Style is clean and enforced in CI. E2E passes in Chromium and Firefox against the Sail app on port 8081, and `CLAUDE.md` documents how to run it.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Refresh scope | Full `composer update` / `npm update`, transitive included | Most vulnerabilities live in transitive packages. |
| Version constraints | Unchanged; only lockfiles move | Keeps the stage within range; constraint bumps happen in the major-upgrade stages. |
| shadcn-vue | Remove from `package.json`, use `npx shadcn-vue@2 add` | Not imported by the app; its vulnerabilities have no in-range fix. |
| Pint | Formatting-only commit plus `composer pint-test` in CI | Stops style drift before later stages touch many files. |
| E2E | Host Playwright against Sail (`APP_PORT=8081`, `VITE_PORT=5174`, `E2E_SKIP_WEBSERVER=1`) | A real browser check on PHP 8.5, reused by stages 2, 5 and 6. |
| Rollout | One commit per phase on `develop` | Each step can be reverted on its own. |

## Scope

**In scope:**
- Composer and npm lockfile refresh within constraints
- Removing `shadcn-vue`
- Regenerating the Boost guideline block
- Pint fix and CI step
- E2E setup on Sail and its documentation in `CLAUDE.md`

**Out of scope:**
- Major upgrades: Laravel 13, Inertia 3, Vite 8, Tailwind 4, Pest 5, Boost 2
- The PostgreSQL switch
- Constraint bumps
- `npm audit fix --force`
- Committing the local `.env` or the uncommitted Polish localisation

## Architecture / Approach

Everything runs through `vendor/bin/sail` on PHP 8.5. Playwright runs on the host against the containerised app (`node_modules` is bind-mounted and shared). Each phase ends with Pest, Larastan, build and E2E before its commit.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. E2E baseline on Sail | Green E2E on current code; documented run command | Firefox may need host system libraries (sudo) |
| 2. Composer refresh | Current composer.lock; clean audit; fresh Boost block | Boost 1.8 may lack `boost:update` (fallback: re-run install) |
| 3. npm refresh | Current package-lock; shadcn-vue gone | Minor UI regressions from reka-ui or vue-sonner updates |
| 4. Pint fix and CI gate | Clean style; CI enforces it | Formatting commit touches unrelated files |

**Prerequisites:** Docker running; household-treasurer may stay up (ports avoided).
**Estimated effort:** About 1 session, 4 small commits.

## Open Risks & Assumptions

- The in-range updates are assumed non-breaking. The Pest suite and a single E2E spec (register and login) are the only automated nets, so the manual UI check in Phase 3 matters.
- Some npm advisories may remain if they have no in-range fix. These are recorded and carried to stages 5 and 6.

## Success Criteria (Summary)

- `sail composer test`, `phpstan`, `pint-test`, `npm run build` and E2E (Chromium and Firefox) are all green on `develop`.
- `composer audit` is clean, and `npm audit` has nothing left that an in-range update could fix.
- The app works in the browser on http://localhost:8081 with no visible regressions.
