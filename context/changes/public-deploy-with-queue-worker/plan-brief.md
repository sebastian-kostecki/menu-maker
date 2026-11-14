# Public Deploy with Queue Worker — Plan Brief

> Full plan: `context/changes/public-deploy-with-queue-worker/plan.md`

## What & Why

Menu Maker has to run publicly (PRD §6, roadmap F-02), and its meal plans only exist if a queue worker runs next to the web server. This change ships the production stack the shared kostecki.dev VPS expects and puts the app live at `https://menu.kostecki.dev`. It includes backups and a deploy that cannot leave half-migrated data serving users.

## Starting Point

- Only Sail's dev Docker files exist. There is no production image, no `.dockerignore`, no backup, no proxy trust, and SSR is on by default.
- CI never builds a production artefact.
- The infra repo the roadmap calls `kostecki-infra` is `kostecki-dev-infra`. It already hosts two Laravel siblings, root-keeper and household-treasurer, whose production files are the template.

## Desired End State

- One `app` container runs `artisan serve`, `queue:work --tries=1` and the scheduler. Next to it runs PostgreSQL 18, and Traefik routes `menu.kostecki.dev` to it through Cloudflare.
- Reset e-mails and error alerts arrive.
- Generation with real AI finishes in under a minute.
- An encrypted DB backup lands nightly at 05:15 (Europe/Warsaw).
- Every deploy runs as backup → maintenance → migrate → caches → reopen.
- CI boots the real image before anything reaches `main`.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Infra contract | App repo ships compose, Dockerfile.prod and deploy.sh; infra gets docs and a wrapper | That is how `kostecki-dev-infra` already runs its two Laravel apps, with no registry and no CI deploy. |
| Host | `menu.kostecki.dev`, Cloudflare Proxied, Full (strict) | Same DNS and TLS shape and the same bot protection as the siblings. |
| Production mail | Reuse the sibling SMTP account, from `noreply@kostecki.dev`; preflight requires `MAIL_HOST` and `LOG_ALERT_MAIL_TO` | Reset and alerts work from day one with no new provider. |
| Inertia SSR | Off in production (`INERTIA_SSR_ENABLED=false`), client-only `vite build`, no Node at runtime | A logged-in app gains nothing from SSR on a small shared VPS; the siblings match. |
| Backups | `spatie/laravel-backup`, DB-only, encrypted; 05:15 run / 05:30 clean / 05:45 monitor; plus one before every migrate | Recipes are irreplaceable user data, these slots are free on the VPS in both seasons (03:30 and 04:00 are taken by household-treasurer), and ops match the siblings' runbook. |
| Deploy safety | Maintenance window: down + backup in the old container → up → migrate → caches → `queue:restart` → up; on failure stay down | No request or job runs new code on an old schema, and a broken deploy is visible. |
| Worker | `queue:work --tries=1 --max-time=3600`, `stopwaitsecs=90`, `stop_grace_period: 100s` | Honours the job's 80 s timeout and lets an in-flight generation finish during a deploy. |
| Preflight | Refuses blank secrets, non-production or debug, SSR on, the `fake` driver, or a missing AI key or model | Catches the misconfigurations that would otherwise surface as silent fallbacks or 500s. |
| CI guard | `scripts/ci/production-smoke.sh` (build, boot, fix permissions, migrate, `/up`, supervisord RUNNING, real `pg_dump` backup), run locally and by a new `production-image` job | Otherwise the VPS is the only place a broken image shows up; running it locally proves it before anything is pushed. |
| Rollback | Roll forward: `migrate:rollback` if needed, `git revert` on `main`, `deploy.sh` | `deploy.sh` refuses a detached HEAD and always pulls `origin/main`, as in household-treasurer. |
| Infra repo | Own phase on `feat/menu-maker` in `kostecki-dev-infra`, merged by you | Both halves of the contract land together and the docs match the real files. |

## Scope

**In scope:**
- `trustProxies`.
- Backups and their schedule.
- `.env.prod.example`, `.dockerignore` and `.gitignore`.
- `Dockerfile.prod`, `supervisord.prod.conf` and `docker-compose.prod.yml`.
- `deploy.sh`, its lib, `artisan.sh` and the shell tests.
- The consistency test and the CI boot smoke.
- The infra docs, wrapper and bootstrap.
- The first production deploy and smoke test.

**Out of scope:**
- A registry or CI-triggered deploy.
- SSR in production, nginx or Octane.
- Offsite backup and a restore drill (a follow-up).
- A new mail provider.
- Rate limits and the real client IP behind Cloudflare.
- Monitoring beyond `/up` plus mail.
- E2E against production.

## Architecture / Approach

```text
Cloudflare → Traefik (kostecki-dev-infra, network proxy)
           → menu-app [supervisord: artisan serve :80 | queue:work --tries=1 | schedule:work]
                ├─ ./storage (bind)  ├─ /storage/menu-maker-backups (bind)  └─ .env.prod → .env (ro)
           → menu-pgsql (postgres:18-alpine, volume menu-pgsql, internal network only)
```

The image is built in three stages: composer → node `vite build` → Ubuntu 24.04 with PHP 8.5 and `postgresql-client-18`. Deploys run on the VPS through `deploy.sh`, either directly or through infra's `deploy-menu-maker.sh`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. App production config + backups | Proxy trust, spatie DB-only backups and schedule, `.env.prod.example`, ignore files, tests | A backup config that silently backs up nothing; guarded by the schedule and config test |
| 2. Production image & compose | `Dockerfile.prod`, supervisord, compose with Traefik labels and limits, consistency test | Secrets or `.git` baked into the image; a PG client mismatch |
| 3. Deploy scripts | Preflight, maintenance-window deploy, `artisan.sh`, bash tests | A failed step leaves the app down; that is intended, but recovery must be documented |
| 4. CI boot smoke | `scripts/ci/production-smoke.sh`, run locally and by the `production-image` job on PRs into `main` | Docker-in-CI flakiness and the runner's uid 1001; about 4–6 minutes more per PR |
| 5. kostecki-dev-infra | `docs/menu-maker.md`, `deploy-menu-maker.sh`, bootstrap dirs, index docs (branch `feat/menu-maker`) | Commits in a second repo; the docs drift from the real files |
| 6. First production deploy | Live site plus the production smoke test, including the memory peak | SMTP or AI credentials; memory on the shared VPS |

**Prerequisites:**
- Sail running.
- Docker on the host for the image checks.
- `shellcheck` installed.
- For Phase 6: DNS access, the sibling SMTP credentials, an OpenRouter or Anthropic API key with its model, and SSH to the VPS.

**Estimated effort:** about 2–3 sessions across 5 automated phases, plus one manual deploy session.

## Open Risks & Assumptions

- **Shared SMTP.** We assume the sibling SMTP account and `kostecki.dev` sender DNS (SPF/DKIM) already work, and menu-maker shares their quota and reputation.
- **Memory.** The limits (768m app / 512m pgsql) are estimates until Phase 6 measures the peak on the shared VPS.
- **Client IP.** Behind Cloudflare, throttles key on proxy IPs, not client IPs. The siblings have the same limitation, and this change does not fix it.
- **Restore not drilled.** The steps are documented, but no drill runs in this change.
- **One worker.** Concurrent generations queue one after another, which is acceptable at MVP traffic.

## Success Criteria (Summary)

- `https://menu.kostecki.dev` is live. A new user can register, reset a password by e-mail, generate an AI-scaled plan in under a minute and download the PDF.
- Errors e-mail the operator, and an encrypted DB backup exists every night and before every deploy.
- No PR reaches `main` unless the production image builds, boots, migrates and can dump the database in CI.
