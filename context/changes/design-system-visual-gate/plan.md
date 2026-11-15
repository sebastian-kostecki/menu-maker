# Design System Visual Gate Implementation Plan

## Overview

F-04 in roadmap milestone M-2. It is the verification half of DS-01 in `context/foundation/design-system.md` (§7.2–7.4, decisions D-4 and D-6). The change adds three tools that every later view migration (S-07…S-15) depends on:

- A **kitchen sink** page, `resources/js/Pages/Dev/DesignSystem.vue`, at `/dev/design-system`. The route exists only in `local` and `testing`. The page shows every token, the five typography roles and all 21 `ui/` primitives in their §5.4 states.
- A **visual gate**: a Playwright `visual` project that screenshots the kitchen sink section by section and opens each portaled overlay. It runs in the official Playwright Docker image, so baselines are pixel-identical between WSL/Sail and GitHub Actions.
- **`npm run lint:tokens`**: a `grep -P` scan with no new dependencies. It covers a growing list of migrated paths and flags palette classes (including `white`/`black`), hex colours, arbitrary values and `dark:`. It runs as a CI step and has its own self-test.

The change also adds the kitchen-sink and `lint:tokens` bullet to the design-system rule in `CLAUDE.md`. F-03 deliberately left that bullet out.

## Current State Analysis

- F-03 has landed (`context/archive/2026-11-15-design-tokens-foundation/`):
  - OKLCH tokens are in `resources/css/app.css`.
  - `resources/js/Components/ui/` holds 21 primitives: 15 legacy plus `alert`, `alert-dialog`, `checkbox`, `skeleton`, `collapsible`, `pagination`.
  - `badge` has the variants `breakfast|supper|dinner|success|warning|info` (`resources/js/Components/ui/badge/index.js:8-25`).
  - `alert` has the variants `warning|info|success` (`resources/js/Components/ui/alert/index.js:9-20`).
  - None of the new primitives is rendered anywhere yet.
- `CLAUDE.md:25-27` carries the 3-bullet design-system rule. Nothing points at a kitchen sink or `lint:tokens`, because neither exists yet.
- `routes/web.php` has no `/dev` routes. Locally `.env` has `APP_ENV=local`; in CI `APP_ENV=testing` (`.github/workflows/master.yml`, "Prepare test environment").
- `playwright.config.ts`:
  - two projects (`chromium`, `firefox`), `fullyParallel`, `testDir: ./tests/e2e`;
  - no `toHaveScreenshot` anywhere;
  - `webServer` starts `php artisan serve` unless `E2E_SKIP_WEBSERVER` is set.
- `npm run test:e2e` = `npx playwright test`, which runs **every** project.
- Where Playwright runs:
  - Locally, from the WSL host (Docker Desktop) against Sail at `http://localhost:8081`.
  - In CI, on `ubuntu-latest` with `npx playwright install --with-deps` against `php artisan serve` on `127.0.0.1:8000`.
  - Fonts and antialiasing differ between these two hosts, so a host-made baseline would not match CI. That is the open unknown in the roadmap F-04 entry.
- Playwright is installed at 1.65.0 (`node_modules/@playwright/test/package.json`); `package.json` pins `^1.54.2`. The Sail Docker network is `menu-maker_sail`, and the app service on it is `laravel.test` (port 80).
- Figtree is loaded from `fonts.bunny.net` with `display=swap` (`resources/views/app.blade.php:8-9`). A screenshot can therefore catch the fallback font.
- `<Toaster />` is mounted only in `resources/js/Layouts/AuthenticatedLayout.vue`.
- CI:
  - `frontend-build` runs `npm ci` + build on Node 22.
  - `e2e-tests` runs `npm run test:e2e` after seeding and starting a queue worker.
  - `production-image` shellchecks `scripts/**/*.sh` and runs the shell self-test `scripts/lib/production-deploy.test.sh`, which is the pattern to follow.
  - CI only triggers on pushes to `main` and PRs into `main`.
- Shell environment: `shellcheck` is not installed on the host. `grep -P` works on WSL and on `ubuntu-latest`.

## Desired End State

- `GET /dev/design-system` renders `Dev/DesignSystem` without login in `local` and `testing`, and returns 404 in `production`.
- The page has 11 labelled sections (`<section aria-labelledby>` → role `region`):
  - Kolory
  - Typografia
  - Kształt i cienie
  - Przyciski
  - Odznaki
  - Komunikaty
  - Formularze
  - Karta i tabela
  - Ładowanie i rozwijanie
  - Paginacja
  - Nakładki

  Each one shows its primitives in the static §5.4 states (default, disabled, loading, invalid, empty) using token utilities only.
- `npm run test:visual` takes 19 screenshots in `mcr.microsoft.com/playwright:v<installed>-noble`: 11 sections, one focus-visible state and 7 opened overlays. It compares them against the baselines committed under `tests/e2e/__screenshots__/`, and it is green on both the local Sail setup and CI. A one-token change in `app.css` turns it red.
- `npm run lint:tokens` exits 0 on the paths in `scripts/lint-tokens.paths` (initially `resources/js/Pages/Dev`). It exits 1 with `file:line` findings when a literal appears, and 2 on a broken list. CI runs it together with its self-test.
- `npm run test:e2e` still runs only chromium and firefox. Running the `visual` project outside the Docker wrapper skips it with a pointer to `npm run test:visual`.
- `CLAUDE.md`, `design-system.md` §7 and the roadmap F-04 unknown describe the tools as built.

### Key Discoveries:

- The badge and alert variants exist with Polish-ready names (`resources/js/Components/ui/badge/index.js:8-25`, `resources/js/Components/ui/alert/index.js:9-20`). The button has 6 variants and 5 sizes (`resources/js/Components/ui/button/index.js:9-29`).
- Existing E2E specs use only role and text selectors and Polish labels (`tests/e2e/recipe-form.spec.ts`). The visual spec should locate the same way (`getByRole('region', { name: 'Przyciski' })`).
- Shell self-test pattern with `pass`/`failed` helpers and a temp dir: `scripts/lib/production-deploy.test.sh:1-20`. CI already shellchecks every `scripts/**/*.sh`.
- Existing closure routes (`/`, `/dashboard` in `routes/web.php`) survive `route:cache` in CI's "Validate production caches" step, so `Route::inertia` is safe there.
- `public/hot` exists locally while `npm run dev` runs. With it present, `@vite` points the page at `localhost:5174`, which a container on the Sail network cannot reach.

## What We're NOT Doing

- No screenshots of real views (Recipes, MealPlans…). Each migration slice adds its own view screenshot and its paths to `scripts/lint-tokens.paths`.
- No `Components/Common/` or domain components (`CategoryBadge`, `StatusTag`) in the kitchen sink. S-07 adds them along with their states.
- No restyling or regenerating of `ui/` primitives (D-9 follow-up), even where a legacy primitive lacks an `aria-invalid` style. The kitchen sink shows the primitives as they are today.
- No hover screenshots. Hover is checked manually only.
- No dark-mode section or dark screenshots (D-1).
- No self-hosted Figtree. The font stays on bunny.net, and the spec asserts that it loaded.
- No pixel tolerance (`maxDiffPixels`/`maxDiffPixelRatio` stay at 0, with Playwright's default per-pixel `threshold`).
- No firefox screenshots: the visual project is chromium only.
- No change to CI triggers (still pushes to `main` and PRs into `main`).
- No new npm or Composer dependencies.

## Implementation Approach

The work goes in dependency order:

1. The kitchen sink exists first, so it can be the first entry on the lint list and the subject of the screenshots.
2. Then `lint:tokens`, which also proves the kitchen sink itself is literal-free.
3. Then the visual gate.
4. Then the docs that point agents at all three.

Determinism comes from four things:

- One renderer: the Docker image, tagged from the installed Playwright version.
- One font: Figtree, loaded explicitly and asserted.
- Per-section locator screenshots, so a height change in one section doesn't shift the others.
- Overlays screenshotted as their own element (dialog, menu, listbox, toast), never the whole viewport.

## Critical Implementation Details

**Docker wrapper (`scripts/visual-test.sh`).**
- The wrapper reads the Playwright version from `node_modules/@playwright/test/package.json` and runs `mcr.microsoft.com/playwright:v<version>-noble`, with the repo mounted at `/work`.
- It runs `npx playwright test --project=visual "$@"` with these settings:
  - `--user "$(id -u):$(id -g)"` and `-e HOME=/tmp`, so baselines and `test-results/` aren't root-owned;
  - `--ipc=host`;
  - `-e E2E_SKIP_WEBSERVER=1 -e PLAYWRIGHT_VISUAL_DOCKER=1 -e CI`.
- Network and base URL are configurable:
  - `VISUAL_DOCKER_NETWORK` defaults to `menu-maker_sail`; CI uses `host`.
  - `VISUAL_BASE_URL` defaults to `http://laravel.test`; CI uses `http://127.0.0.1:8000`. The wrapper passes it in as `E2E_BASE_URL`.
- It must fail fast, with a clear message, when:
  - `public/hot` exists (tell the user to stop `npm run dev` and run `vendor/bin/sail npm run build`);
  - `node_modules` is missing;
  - the network doesn't exist (tell the user to run `vendor/bin/sail up -d`).

**Font check.** `document.fonts.check()` returns `true` when no matching face exists, so it proves nothing. Before any screenshot, the spec must do this:
1. `await document.fonts.ready`.
2. For each weight 400, 500 and 600, call `document.fonts.load('<w> 16px Figtree')`.
3. Assert that each call resolved to at least one face whose `family` is Figtree and whose `status` is `'loaded'`.

If any weight fails, the test fails with the message "Figtree did not load (fonts.bunny.net unreachable?)". It must not take a screenshot with the fallback font.

**`lint:tokens` patterns** (`grep -nP`, applied to `*.vue`, `*.js` and `*.ts` under each listed path, skipping anything under `/Components/ui/`):

```text
palette:   (?<![\w-])(?:bg|text|border(?:-[xytrblse])?|ring(?:-offset)?|outline|divide|from|via|to|fill|stroke|shadow|inset-shadow|inset-ring|accent|caret|decoration|placeholder)-(?:(?:slate|gray|zinc|neutral|stone|mauve|olive|mist|taupe|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}|white|black)(?![\w-])
hex:       (?<![\w&])#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})(?![\w-])
arbitrary: (?<=[\w)])-\[[^\]\s]+\](?!:)
dark:      (?<![\w-])dark:
```

The `(?!:)` lookahead keeps arbitrary *variants* legal (`data-[state=open]:`, `aria-[invalid=true]:`), while values such as `w-[120px]` are flagged. Variant prefixes (`hover:bg-gray-100`) and opacity suffixes (`bg-white/80`) still match the palette pattern. A slot named only with hex letters (for example `#add`) is a known false positive; rename the slot if it ever happens.

**Snapshot settings.** These go on the `visual` project:
- `snapshotPathTemplate: '{testDir}/__screenshots__/{testFilePath}/{arg}{ext}'`, with no platform or project suffix, since there is a single renderer;
- `updateSnapshots: process.env.CI ? 'none' : 'missing'`, so CI never writes baselines.

`toHaveScreenshot` keeps its defaults (`animations: 'disabled'`, `caret: 'hide'`), which freeze `animate-spin`, `animate-pulse` and tw-animate enter transitions. `focus-visible` requires a keyboard interaction: focus a known element, then `page.keyboard.press('Tab')`. `.focus()` alone may not trigger `:focus-visible`.

## Phase 1: Kitchen sink route and page

### Overview

Register `/dev/design-system` in `local`/`testing` only. Build the kitchen sink page from tokens and the 21 primitives in their static states, and prove the environment gate with Pest.

### Changes Required:

#### 1. Env-gated route

**File**: `routes/web.php`

**Intent**: Expose the kitchen sink to developers, agents and CI without shipping it to production (D-4). It needs no login, because it shows no user data.

**Contract**: Inside `if (app()->environment(['local', 'testing']))`, add `Route::inertia('/dev/design-system', 'Dev/DesignSystem')->name('dev.design-system')`. There is no controller and no middleware beyond the `web` group.

#### 2. Kitchen sink page

**File**: `resources/js/Pages/Dev/DesignSystem.vue` (new)

**Intent**: One page that shows the whole visual contract: tokens, typography and every primitive in its §5.4 states. It gives screenshots a stable subject and gives agents a catalogue to copy from. It must obey the CLAUDE.md rule itself: token utilities only, with no palette classes, hex colours, arbitrary values or `dark:`.

**Contract**:
- Standalone page with no `AuthenticatedLayout`, so S-08 never shifts its baselines.
- `<Head title="Design system" />`, a root `min-h-screen bg-background text-foreground`, content in `max-w-6xl mx-auto`, one `<h1>`.
- Its own `<Toaster />` from `@/Components/ui/sonner`.
- Static demo data only: no props and no network images, so `Avatar` uses its fallback initials.
- Polish labels throughout.
- Exactly these 11 sections, in this order. Each is a `<section aria-labelledby>` with an `<h2>`, so it is locatable as `getByRole('region', { name })`.
  1. **Kolory**: one swatch per token pair, each showing the token name in its own foreground colour:
     - base: `background`/`foreground`, `card`, `muted`/`muted-foreground`, `secondary`, `primary`/`primary-foreground`, `accent`/`accent-foreground`, `destructive`/`destructive-foreground`, `border`, `input`, `ring`;
     - states: `success`, `warning`, `info`;
     - meals: `meal-breakfast`, `meal-supper`, `meal-dinner`.
  2. **Typografia**: the five §4.5 roles, plus a right-aligned `tabular-nums` column with Polish-formatted amounts (for example `1,25 kg`).
  3. **Kształt i cienie**: `rounded-sm|md|lg|full`, a card with a border and no shadow, `shadow-sm` and `shadow-lg`.
  4. **Przyciski**:
     - all 6 variants, in default and disabled states;
     - all 5 sizes (the `icon` size with `aria-label`);
     - a loading button (disabled, with a `Loader2 animate-spin` icon and "Zapisywanie…").
  5. **Odznaki**: all 10 badge variants with Polish labels (Śniadanie, Obiad, Kolacja, Gotowy, …).
  6. **Komunikaty**: `Alert` in `default`, `destructive`, `warning`, `info` and `success`, each with an icon, a title and a description.
  7. **Formularze**:
     - `Label`+`Input` four ways: default, placeholder, disabled, and invalid (`aria-invalid="true"`, with an error text in `text-destructive` linked by `aria-describedby`);
     - `Textarea`, default and disabled;
     - `Checkbox` unchecked, checked and disabled;
     - a closed `Select` with a placeholder and 3 options;
     - a closed `Combobox` with 3 options.
  8. **Karta i tabela**: a `Card` with header, description, content and footer; a `Table` with 3 rows and right-aligned `tabular-nums` numbers; a table showing its empty state ("Brak przepisów").
  9. **Ładowanie i rozwijanie**: `Skeleton` rows, one closed and one open (`defaultOpen`) `Collapsible`, `Separator`, and an `Avatar` showing its fallback.
  10. **Paginacja**: `Pagination` on page 2 of 5.
  11. **Nakładki**: trigger buttons with stable Polish names:
      - "Otwórz dialog" (`Dialog`);
      - "Otwórz potwierdzenie" (`AlertDialog`);
      - "Otwórz panel" (`Sheet`);
      - "Otwórz menu" (`DropdownMenu`);
      - "Pokaż powiadomienie" (toast with fixed text and `duration: Infinity`).

      The section notes that the Select and Combobox from Formularze are its other two overlays.

#### 3. Route test

**File**: `tests/Feature/DesignSystemPageTest.php` (new, `declare(strict_types=1);`)

**Intent**: Prove the D-4 gate: the page renders for a guest in `testing`, and the route does not exist in `production`.

**Contract**: The test has two cases:
1. A guest `GET /dev/design-system` passes `assertOk()` and `assertInertia(fn ($page) => $page->component('Dev/DesignSystem'))`.
2. With the environment switched to `production`, the route is absent and `GET /dev/design-system` returns 404. To set this up:
   1. Switch with `app()->detectEnvironment(fn () => 'production')`.
   2. Reset the route collection.
   3. Re-require `routes/web.php`.
   4. Refresh the name lookups.
   5. Assert both `Route::has('dev.design-system') === false` and the 404.

### Success Criteria:

#### Automated Verification:

- The route test passes: `vendor/bin/sail pest --filter=DesignSystemPageTest`
- The full Pest suite and code style stay green: `vendor/bin/sail composer test && vendor/bin/sail composer pint-test`
- The client and SSR bundles build: `vendor/bin/sail npm run build`
- The route is registered locally: `vendor/bin/sail artisan route:list --path=dev` lists `dev.design-system`
- Route caching still works: `vendor/bin/sail artisan route:cache && vendor/bin/sail artisan route:clear`

#### Manual Verification:

- `http://localhost:${APP_PORT}/dev/design-system` shows all 11 sections in the warm palette with Figtree, with no console errors.
- Every overlay opens and closes (Esc and close button), and Tab shows the green focus ring on buttons, inputs and the checkbox.
- Hover states change colour on buttons, ghost/outline buttons, dropdown items and pagination links.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: `lint:tokens`

### Overview

Add a dependency-free scan of migrated files for colour literals, driven by a path list that each slice extends. Wire it into CI and cover it with a self-test.

### Changes Required:

#### 1. Scanner

**File**: `scripts/lint-tokens.sh` (new, `#!/usr/bin/env bash`, `set -euo pipefail`)

**Intent**: Enforce rule 1 of design-system §3 ("Widok nie zna palety Tailwinda") mechanically on every path a slice has migrated (D-6).

**Contract**:
- Usage: `scripts/lint-tokens.sh [paths-file]`. The default paths file is `scripts/lint-tokens.paths`, and paths resolve relative to the current working directory.
- Scans `*.vue`, `*.js` and `*.ts` files under each listed path (a file or a directory, recursively). Anything whose path contains `/Components/ui/` is always skipped.
- Applies the four patterns from Critical Implementation Details and prints one line per hit: `<file>:<line>: <palette|hex|arbitrary|dark>: <matched text>`. The run ends with a count.
- Exit codes:
  - 0: no hits;
  - 1: one or more hits;
  - 2: configuration error (the paths file is missing, a listed path doesn't exist, or the list has no entries after comments and blank lines).
- Must pass shellcheck.

#### 2. Path list

**File**: `scripts/lint-tokens.paths` (new)

**Intent**: The single list each migration slice appends to. By S-15 it covers all of `resources/js`.

**Contract**:
- One path per line; `#` comments and blank lines are ignored.
- A header comment explains that each slice adds the paths it migrated in its own change, and that `Components/ui/` is always excluded.
- Initial entry: `resources/js/Pages/Dev`.

#### 3. Self-test

**File**: `scripts/lint-tokens.test.sh` (new)

**Intent**: Pin the regex behaviour so later edits can't silently loosen or over-tighten it. Follows `scripts/lib/production-deploy.test.sh`.

**Contract**:
- Builds fixtures and a paths file in a `mktemp -d` dir and runs the scanner from there. Prints `PASS:` or `FAIL:` per case and exits non-zero on any failure.
- Each of these must be flagged, with the right kind:
  - palette: `bg-gray-100`, `hover:text-red-600`, `bg-white/80`, `border-b-slate-200`, `text-black`;
  - hex: `#fff`, `#FF2D20`, `#11223344`;
  - arbitrary: `w-[120px]`, `grid-cols-[1fr_2fr]`;
  - dark: `dark:bg-card`.
- Each of these must not be flagged: `bg-primary/80`, `text-muted-foreground`, `bg-meal-breakfast`, `data-[state=open]:bg-accent`, `[&_svg]:size-4`, `<template #default>`, `&#123;`.
- A listed directory containing `Components/ui/x.vue` with `bg-gray-100` produces no hit.
- A missing listed path and an empty list both exit 2.

#### 4. npm script and CI

**File**: `package.json`, `.github/workflows/master.yml`

**Intent**: Make the scan a one-word command and a required CI step that runs on every PR into `main`.

**Contract**:
- `package.json` scripts: `"lint:tokens": "bash scripts/lint-tokens.sh"`.
- In the `frontend-build` job, after "Install dependencies" and before "Build for production", add two steps:
  - "Design tokens lint (self-test)" → `bash scripts/lint-tokens.test.sh`;
  - "Design tokens lint" → `npm run lint:tokens`.

### Success Criteria:

#### Automated Verification:

- The self-test passes: `bash scripts/lint-tokens.test.sh`
- The kitchen sink is literal-free: `vendor/bin/sail npm run lint:tokens` exits 0
- A deliberate break is caught: temporarily adding `class="bg-gray-100"` to `resources/js/Pages/Dev/DesignSystem.vue` makes `npm run lint:tokens` exit 1 and print that `file:line`. Reverting returns exit 0.
- Both scripts pass shellcheck: `docker run --rm -v "$PWD:/mnt" -w /mnt koalaman/shellcheck:stable scripts/lint-tokens.sh scripts/lint-tokens.test.sh`
- CI runs both steps: `grep -nE "lint-tokens.test.sh|npm run lint:tokens" .github/workflows/master.yml` shows two lines inside `frontend-build`

#### Manual Verification:

- The finding format (`file:line: kind: match`) and the exit-2 messages read clearly enough for an agent to fix the cause without opening the script.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Visual gate

### Overview

Add a chromium-only Playwright `visual` project that runs in the pinned Docker image, a spec with 19 screenshots, the committed baselines and the CI steps. Keep the regular E2E run unchanged in behaviour.

### Changes Required:

#### 1. Playwright projects

**File**: `playwright.config.ts`

**Intent**: Separate the visual check from the behaviour specs, so each runs in the right environment.

**Contract**:
- The `chromium` and `firefox` projects get `testIgnore: /visual\//`.
- A new project, `visual`:
  - `testMatch: /visual\/.*\.spec\.ts$/`, `use: { ...devices['Desktop Chrome'] }`;
  - the `snapshotPathTemplate` and `updateSnapshots` settings from Critical Implementation Details.
- Everything else stays as it is.

#### 2. npm scripts

**File**: `package.json`

**Intent**: Keep `npm run test:e2e` running exactly the behaviour specs, and give the visual gate its own entry point.

**Contract**:
- `"test:e2e": "npx playwright test --project=chromium --project=firefox"`, with the same change to `test:e2e:headed`. `test:e2e:ui` stays unfiltered.
- New script: `"test:visual": "bash scripts/visual-test.sh"`. Extra arguments pass through, for example `npm run test:visual -- --update-snapshots`.

#### 3. Docker wrapper

**File**: `scripts/visual-test.sh` (new, shellcheck-clean)

**Intent**: One command that renders identically on WSL/Sail and in CI.

**Contract**: As described under Critical Implementation Details: version-tagged image, user mapping, network and base URL variables, and the three fail-fast checks.

#### 4. Visual spec

**File**: `tests/e2e/visual/design-system.spec.ts` (new)

**Intent**: Screenshot the kitchen sink so that any token or primitive change shows up as a reviewable diff before it reaches a view.

**Contract**:
- Guard at the top: `test.skip(!process.env.PLAYWRIGHT_VISUAL_DOCKER, 'Run via npm run test:visual (Docker-pinned renderer)')`.
- A shared `openKitchenSink(page)` helper that:
  - goes to `/dev/design-system`;
  - waits for the `<h1>`;
  - runs the Figtree check from Critical Implementation Details.
- Screenshots, all strict. Every screenshot is a locator screenshot, never a full page.
  - 11 section screenshots, one per region, named `section-<slug>.png` (for example `section-przyciski.png`).
  - `focus-visible.png`: focus the first button in "Przyciski" via keyboard, then screenshot that section.
  - 7 overlay screenshots, one per test from a fresh page, each of the opened element:
    - `overlay-dialog.png` (role `dialog`);
    - `overlay-alert-dialog.png` (role `alertdialog`);
    - `overlay-sheet.png` (role `dialog`);
    - `overlay-dropdown-menu.png` (role `menu`);
    - `overlay-select.png` (role `listbox`);
    - `overlay-combobox.png` (role `listbox`);
    - `overlay-toast.png` (the sonner toast `li`).

#### 5. Baselines

**File**: `tests/e2e/__screenshots__/visual/design-system.spec.ts/*.png` (new, 19 files)

**Intent**: The approved reference, generated only through the wrapper.

**Contract**:
- Create them with `npm run test:visual -- --update-snapshots`, review each PNG, then commit.
- They must never be regenerated on the host or in CI.

#### 6. CI

**File**: `.github/workflows/master.yml` (`e2e-tests` job)

**Intent**: Run the gate in CI with the same image, after the behaviour specs.

**Contract**: After "Run E2E tests", add three steps:
1. "Start app server": `php artisan serve --host=127.0.0.1 --port=8000 > storage/logs/serve.log 2>&1 &`, then a bounded `curl` wait loop that fails after about 30 s.
2. "Run visual gate": `npm run test:visual` with env `VISUAL_DOCKER_NETWORK=host` and `VISUAL_BASE_URL=http://127.0.0.1:8000`.
3. An `if: failure()` upload of `test-results/`, as artifact `visual-diffs`, alongside the existing log upload.

### Success Criteria:

#### Automated Verification:

- The gate is green against committed baselines, twice in a row (stability): `vendor/bin/sail npm run build && npm run test:visual && npm run test:visual`
- Exactly 19 baselines are committed: `ls tests/e2e/__screenshots__/visual/design-system.spec.ts/*.png | wc -l` prints `19`
- A deliberate break is caught:
  - temporarily changing `--primary` in `resources/css/app.css`, then rebuilding, makes `npm run test:visual` fail on `section-kolory.png` and `section-przyciski.png`;
  - reverting and rebuilding returns it to green.
- The behaviour E2E suite runs only chromium and firefox and stays green: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- The visual project skips outside Docker: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npx playwright test --project=visual` reports all tests skipped
- The wrapper refuses a dev-server state: with `public/hot` present, `npm run test:visual` exits non-zero with the build hint
- The wrapper passes shellcheck: `docker run --rm -v "$PWD:/mnt" -w /mnt koalaman/shellcheck:stable scripts/visual-test.sh`

#### Manual Verification:

- Each of the 19 baseline PNGs shows Figtree (not a fallback), the intended state, and an overlay that isn't clipped. The focus ring is visible in `focus-visible.png`.
- The first PR into `main` after this change shows "Run visual gate" green in GitHub Actions. If it is red, the `visual-diffs` artifact shows why.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: Docs and rule

### Overview

Point agents at the three tools and record the resolved unknown.

### Changes Required:

#### 1. CLAUDE.md

**File**: `CLAUDE.md`

**Intent**: Complete the design-system rule F-03 left short (§7.1, fourth bullet), and document the new commands.

**Contract**:
- "Structure & Conventions" gets one new bullet, after the literal-ban bullet:
  - a new primitive variant or shared pattern gets a demo, with its states, in the kitchen sink `resources/js/Pages/Dev/DesignSystem.vue` (`/dev/design-system`, `local`/`testing` only);
  - a slice that migrates a view appends its paths to `scripts/lint-tokens.paths` and keeps `npm run lint:tokens` green;
  - baselines are updated only deliberately, with `npm run test:visual -- --update-snapshots`, and each changed PNG is reviewed in the diff.
- "Commands" gets two new entries:
  - `vendor/bin/sail npm run lint:tokens`;
  - the visual gate: from the host, after `vendor/bin/sail npm run build` with `npm run dev` stopped, `npm run test:visual`. It runs in the pinned Playwright Docker image on the `menu-maker_sail` network; `VISUAL_DOCKER_NETWORK` and `VISUAL_BASE_URL` override the defaults.
- The CI sentence under "Git & CI" mentions `lint:tokens` and the visual gate.

#### 2. design-system.md §7

**File**: `context/foundation/design-system.md`

**Intent**: Make §7.2–7.4 describe the built tools instead of the plan, and bump `updated:`.

**Contract**:
- §7.2: the 11 sections, no layout, no login.
- §7.3: the Docker-pinned `visual` project, 19 screenshots (sections, focus-visible, 7 overlays), chromium only, zero tolerance, Figtree asserted, `--update-snapshots` only through the wrapper.
- §7.4: `scripts/lint-tokens.sh` + `scripts/lint-tokens.paths`, the four literal kinds including `white`/`black`, exit codes 0/1/2, the self-test, the `frontend-build` CI step.
- No other sections change.

#### 3. Roadmap F-04 unknown

**File**: `context/foundation/roadmap.md`

**Intent**: Close the open unknown in the F-04 entry with the decision made here.

**Contract**:
- Rewrite the F-04 **Unknowns** bullet as resolved: baselines are generated and compared only in `mcr.microsoft.com/playwright:v<installed>-noble`, locally and in CI (decided 2026-11-15 in this plan).
- Bump `updated:` in the frontmatter.
- Leave `Status` to the 10x skills.

### Success Criteria:

#### Automated Verification:

- CLAUDE.md names all three tools: `grep -cE "Dev/DesignSystem.vue|lint-tokens.paths|test:visual" CLAUDE.md` prints at least `3`
- design-system §7 describes the built tools: `grep -nE "lint-tokens.paths|test:visual|white" context/foundation/design-system.md` matches lines inside §7
- The roadmap unknown is resolved: `grep -n "mcr.microsoft.com/playwright" context/foundation/roadmap.md` matches the F-04 entry

#### Manual Verification:

- The new CLAUDE.md bullet reads as a rule an agent can follow in S-07 without opening this plan, and it doesn't repeat the existing three bullets.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- Pest `DesignSystemPageTest` covers the environment gate: renders in `testing`, 404 and no named route in `production`.
- `scripts/lint-tokens.test.sh` covers every literal kind, the legal look-alikes (opacity on tokens, arbitrary variants, slots, entities), the `ui/` exclusion and the exit-2 configuration errors.

### Integration Tests:

- `npm run test:visual`: 19 strict screenshots of the kitchen sink in the pinned renderer, with the Figtree load asserted first.
- `npm run test:e2e` stays the behaviour suite (chromium and firefox) and is unaffected by the new project.

### Manual Testing Steps:

1. Run `vendor/bin/sail up -d && vendor/bin/sail npm run build`, then open `/dev/design-system` and walk all 11 sections, including hover and keyboard focus.
2. Run `npm run test:visual -- --update-snapshots` once, then open every PNG in `tests/e2e/__screenshots__/` and check the font, state and overlay framing.
3. Change one token, rebuild, run `npm run test:visual`, and read the diff in `test-results/`. Revert.
4. On the first PR into `main`, confirm both lint steps and "Run visual gate" pass.

## Performance Considerations

- The Playwright image is about 2 GB. The first local run pulls it. CI pulls it on every `e2e-tests` run, which adds roughly 30–60 s; that's acceptable for a job that already installs browsers.
- 19 locator screenshots on one static page should take well under a minute.

## Migration Notes

There is no data migration. For later slices:

- Each migrated view adds its paths to `scripts/lint-tokens.paths`.
- If it adds a screenshot, it does so in its own spec under `tests/e2e/visual/`.
- S-15 replaces the list entries with `resources/js`.
- Upgrading `@playwright/test` changes the image tag automatically and may require a deliberate baseline refresh in the same commit.

## References

- Roadmap item: `context/foundation/roadmap.md` (F-04, M-2)
- Design system: `context/foundation/design-system.md` §3, §4.5, §5.1, §5.4, §7.2–7.4, D-4, D-6, D-9
- Previous change: `context/archive/2026-11-15-design-tokens-foundation/plan.md`
- Shell self-test pattern: `scripts/lib/production-deploy.test.sh:1-20`
- Existing E2E style: `tests/e2e/recipe-form.spec.ts`, `playwright.config.ts`
- CI: `.github/workflows/master.yml` (`frontend-build`, `e2e-tests`, `production-image` shellcheck)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Kitchen sink route and page

#### Automated

- [x] 1.1 The route test passes — 89d5019
- [x] 1.2 The full Pest suite and code style stay green — 89d5019
- [x] 1.3 The client and SSR bundles build — 89d5019
- [x] 1.4 The route is registered locally — 89d5019
- [x] 1.5 Route caching still works — 89d5019

#### Manual

- [ ] 1.6 All 11 sections render in the warm palette with Figtree, no console errors
- [ ] 1.7 Every overlay opens and closes; Tab shows the focus ring
- [ ] 1.8 Hover states change colour

### Phase 2: `lint:tokens`

#### Automated

- [x] 2.1 The self-test passes — acd41b8
- [x] 2.2 The kitchen sink is literal-free — acd41b8
- [x] 2.3 A deliberate break is caught — acd41b8
- [x] 2.4 Both scripts pass shellcheck — acd41b8
- [x] 2.5 CI runs both steps — acd41b8

#### Manual

- [ ] 2.6 Findings and exit-2 messages are clear enough to act on

### Phase 3: Visual gate

#### Automated

- [x] 3.1 The gate is green against committed baselines, twice in a row — dc0f900
- [x] 3.2 Exactly 19 baselines are committed — dc0f900
- [x] 3.3 A deliberate break is caught — dc0f900
- [x] 3.4 The behaviour E2E suite runs only chromium and firefox and stays green — dc0f900
- [x] 3.5 The visual project skips outside Docker — dc0f900
- [x] 3.6 The wrapper refuses a dev-server state — dc0f900
- [x] 3.7 The wrapper passes shellcheck — dc0f900

#### Manual

- [ ] 3.8 Each baseline PNG shows Figtree, the intended state and an unclipped overlay
- [ ] 3.9 The first PR into main shows the visual gate green in GitHub Actions

### Phase 4: Docs and rule

#### Automated

- [x] 4.1 CLAUDE.md names all three tools
- [x] 4.2 design-system §7 describes the built tools
- [x] 4.3 The roadmap unknown is resolved

#### Manual

- [ ] 4.4 The new CLAUDE.md bullet reads as an actionable, non-duplicated rule
