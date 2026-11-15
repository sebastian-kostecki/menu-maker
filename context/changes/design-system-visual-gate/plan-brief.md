# Design System Visual Gate — Plan Brief

> Full plan: `context/changes/design-system-visual-gate/plan.md`

## What & Why

F-04 in roadmap M-2 gives the design system a way to verify itself before any view is migrated. It adds:

- a kitchen-sink page at `/dev/design-system`;
- a Playwright screenshot gate on that page;
- `npm run lint:tokens`, which catches colour literals in migrated files.

Without these tools, slices S-07…S-15 could drift back to palette classes, and token changes would land without anyone seeing their effect.

## Starting Point

- F-03 put OKLCH tokens in `app.css` and gave `ui/` 21 primitives, including the badge and alert variants. Six of those primitives aren't rendered anywhere yet.
- Playwright has two behaviour projects, chromium and firefox, and no screenshots. Locally it runs on WSL against Sail; in CI it runs on `ubuntu-latest`, so pixels differ between the two.
- `CLAUDE.md` has a 3-bullet design-system rule with no tools behind it.

## Desired End State

- An agent or developer can open `/dev/design-system` (only in `local`/`testing`) and see every token, the typography roles and all primitives in their states.
- `npm run test:visual` compares 19 screenshots against committed baselines and gives the same result locally and in CI.
- `npm run lint:tokens` fails on any palette class, hex colour, arbitrary value or `dark:` in listed paths.
- Both run in CI, and `CLAUDE.md` tells agents how to extend them.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Route scope | `Route::inertia` only in `local`/`testing`, no login, 404 in production | D-4; the page shows no user data and CI needs it | Design system (D-4) |
| Baseline environment | Official Playwright Docker image, tag read from the installed version, used locally (Sail network) and in CI (`--network host`) | Same pixels everywhere, so agents in later slices can update baselines locally | Plan |
| State coverage | Static states on the page; the spec drives focus-visible and opens 7 overlays one at a time; hover is manual only | Real behaviour instead of forced copies, and the dialogs S-07 builds on are covered | Plan |
| Literal definition | All Tailwind palette scales plus `white`/`black`, hex colours, arbitrary values (not arbitrary variants), `dark:` | Matches the CLAUDE.md rule and keeps the §4.8 token-only dark mode possible | Plan |
| Font determinism | Keep bunny.net; the spec loads Figtree 400/500/600 and fails loudly if any is missing | No app change, and a font outage is an explicit error rather than a pixel diff | Plan |
| Screenshot shape | Per-section and per-overlay locator screenshots, chromium only, zero tolerance | Localised diffs, one renderer, and no tolerance hiding small regressions | Plan |
| Lint tooling | Bash + `grep -P`, path list in `scripts/lint-tokens.paths`, self-test, exit codes 0/1/2 | D-6 (no new dependencies); same pattern as `production-deploy.test.sh` | Design system (D-6) + Plan |
| Kitchen sink layout | Standalone, no `AuthenticatedLayout` | S-08's shell restyle must not shift these baselines | Plan |

## Scope

**In scope:**

- `routes/web.php` gate, `Pages/Dev/DesignSystem.vue` (11 sections), and a Pest test.
- `scripts/lint-tokens.sh`, its `.paths` file and self-test, the npm script and a CI step.
- `visual` Playwright project, `scripts/visual-test.sh`, the spec, 19 baselines, the `test:e2e` project filter and CI steps.
- `CLAUDE.md` bullet and commands, `design-system.md` §7, and the roadmap F-04 unknown.

**Out of scope:**

- Screenshots of real views. Each slice adds its own.
- `Components/Common/` and domain components (S-07).
- Restyling `ui/` (D-9).
- Hover screenshots, dark mode, self-hosted fonts, firefox screenshots, pixel tolerance, and new dependencies.

## Architecture / Approach

The kitchen sink is a static Inertia page built from tokens and `ui/` primitives, with one labelled region per section. A wrapper script runs the `visual` Playwright project inside `mcr.microsoft.com/playwright:v<installed>-noble`:

- locally, attached to `menu-maker_sail` at `http://laravel.test`;
- in CI, on the host network against `php artisan serve`.

The spec asserts Figtree, then takes locator screenshots. `lint:tokens` is independent of all this: a grep over a growing path list, run in the `frontend-build` job.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Kitchen sink route and page | `/dev/design-system` with 11 sections, env-gated, with a Pest test | The production-404 test has to reload the routes under a different environment |
| 2. `lint:tokens` | Scanner, path list, self-test, CI step | Regex false positives or false negatives; pinned by the self-test |
| 3. Visual gate | Docker-pinned project, 19 baselines, CI steps | Flaky renders (fonts, portals, network), mitigated by one image, the font check and locator screenshots |
| 4. Docs and rule | `CLAUDE.md`, design-system §7, roadmap unknown | None significant |

**Prerequisites:**
- Sail running (`vendor/bin/sail up -d`).
- Docker on the host able to pull `mcr.microsoft.com/playwright` and `koalaman/shellcheck`.
- `npm run dev` stopped (no `public/hot`) before visual runs.
- `.env` as described for E2E in `CLAUDE.md`.

**Estimated effort:** about 1–2 sessions across 4 phases. Phase 1 (the page) and Phase 3 (the stable gate) are the largest.

## Open Risks & Assumptions

- Assumption: a container on `menu-maker_sail` can render the app at `http://laravel.test`, with built assets resolved from the request host. If it can't, `VISUAL_BASE_URL` and the network can be overridden.
- CI depends on `fonts.bunny.net` being reachable. An outage shows up as an explicit font error, not a diff.
- A Playwright upgrade changes the renderer and needs a deliberate baseline refresh in the same commit.
- The visual gate in CI is only proven on the first PR into `main` (manual check 3.9).

## Success Criteria (Summary)

- A one-token change in `app.css` turns `npm run test:visual` red, both locally and in CI. With no change it stays green across repeated runs.
- A palette class in any listed file fails `npm run lint:tokens` with its `file:line`, while token utilities and arbitrary variants pass.
- An agent starting S-07 learns from `CLAUDE.md` where to add demos, how to extend the lint list and how to update baselines.
