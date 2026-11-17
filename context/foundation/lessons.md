# Lessons Learned

> Append-only register of recurring rules and patterns. Re-read at start by /10x-frame, /10x-research, /10x-plan, /10x-plan-review, /10x-implement, /10x-impl-review.

## Point E2E and visual runs at the worktree's own Sail stack

- **Context**: `scripts/visual-test.sh:10` (default network `menu-maker_sail`), `tests/e2e/support/mailpit.ts:1` (default `http://localhost:8025`). Found in the `spizarnia-meal-plans-ui` impl-review (F4), where several worktree Sail stacks ran in parallel.
- **Problem**:
  - A worktree runs its own Sail project (`COMPOSE_PROJECT_NAME`, e.g. `mm-meal-plans-ui`), but the gates default to the main checkout's stack.
  - `npm run test:visual` screenshotted `develop` and reported 16 false failures.
  - `password-reset.spec.ts` read the wrong Mailpit and reported 2 false failures.
  - Worse, `--update-snapshots` run the same way silently writes another branch's rendering into this branch's baselines.
- **Rule**:
  - In a worktree whose `COMPOSE_PROJECT_NAME` is not `menu-maker`, run the visual gate with `VISUAL_DOCKER_NETWORK=<COMPOSE_PROJECT_NAME>_sail`.
  - Run E2E with `MAILPIT_URL=http://localhost:<FORWARD_MAILPIT_DASHBOARD_PORT>`. Playwright does not read `.env`, so export it.
  - Before any `--update-snapshots`, confirm the target with `docker inspect <project>-laravel.test-1`: its `/var/www/html` mount must be this worktree.
  - Keep each `FORWARD_*` key in `.env` once.
- **Applies to**: `/10x-implement`, `/10x-goal-implement`, `/10x-impl-review` and any E2E or visual-gate run from `.claude/worktrees/*`.
