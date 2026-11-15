<!-- PLAN-REVIEW-REPORT -->
# Plan Review: App Shell Restyle Implementation Plan

- **Plan**: context/changes/app-shell-restyle/plan.md
- **Mode**: Deep
- **Date**: 2026-11-15
- **Verdict**: REVISE (SOUND after fixes)
- **Findings**: 1 critical, 3 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding
Grounding: 11/11 paths ✓, 5/5 symbols ✓, brief↔plan ✓ (7 lint paths, 8 baselines, 3 phases)

## Findings

### F1 — Verification can't run against this worktree's code

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Prerequisites (plan-brief) / every Success Criteria block
- **Detail**: The worktree `.claude/worktrees/app-shell-restyle` has no `vendor/`, `node_modules/` or `.env`, so `vendor/bin/sail npm run build` fails at once. `scripts/visual-test.sh:18` stops at its `node_modules` check. Sail serves the checkout it mounted (`.:/var/www/html`), so with Sail running from the main checkout, E2E and the visual gate would test main's code, and `--update-snapshots` would write baselines of the old shell. A worktree stack's network is `app-shell-restyle_sail` (no `COMPOSE_PROJECT_NAME`).
- **Fix A ⭐ Recommended**: Add an "Environment" prerequisite: a Sail stack run from the checkout being implemented (own `.env` ports, `vendor/` via the Sail Composer container, `VISUAL_DOCKER_NETWORK=app-shell-restyle_sail`).
  - Strength: Keeps worktree isolation; fits an unattended `/10x-goal-implement` run.
  - Tradeoff: A second stack (DB volume, ports) and a one-time install.
  - Confidence: HIGH — network name, mount and script checks read from the code.
  - Blind spot: Whether two Sail stacks fit in the host's memory and ports.
- **Fix B**: Implement from the main checkout on branch `app-shell-restyle`.
  - Strength: The stack already works there.
  - Tradeoff: Takes over the user's working copy; no parallel jobs.
  - Confidence: HIGH
  - Blind spot: The uncommitted plan files must be moved over.
- **Decision**: FIXED (Fix A) — new `## Environment` section in plan.md; brief Prerequisites updated.

### F2 — Inertia Link replaces click handlers given to it

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 1 §2, §3, §4
- **Detail**: `@inertiajs/vue3` 3.11 renders `h(as, { ...attrs, ...elProps, ...regularEvents })` (`dist/index.js:1963-1977`), so its own `onClick` overwrites a passed-in click handler. reka's `handleSelect` never runs on the `as-child` menu items (the menu doesn't close; keyboard still works via `currentTarget.click()`). An `@click="emit('navigate')"` on nav Links would most likely never fire. The E2E "dialog hidden" check passes only because the layout remounts.
- **Fix**: Emit `navigate` from `<Link @start>` (a declared prop). Note in the UserMenu contract that its items rely on the remount, not `@select`.
  - Strength: The Sheet closes right on click.
  - Tradeoff: None worth noting.
  - Confidence: MED — read from the dist source, not run in a browser.
  - Blind spot: Whether `onStart` fires for a link to the current URL.
- **Decision**: FIXED

### F3 — Collapse toggle position and collapsed brand row unspecified

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §2 vs Phase 3 §2
- **Detail**: The Intent orders "nav list, collapse toggle". The focus-visible test assumes the toggle comes right before the nav. Today it sits in the brand row (`Sidebar.vue:102-127`). The collapsed `w-16` rail layout of the brand icon plus toggle is not specified.
- **Fix**: DOM order brand row (brand link + toggle) → nav → footer. When collapsed, the brand row is a centred column.
- **Decision**: FIXED

### F4 — Two verification checks can't pass as written

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 criteria 3.4 and 3.7
- **Detail**: `git status --porcelain` collapses the new untracked `app-shell.spec.ts/` folder into one entry (5, not 8). 3.7 expects a "token overlay", but the plan keeps `bg-black/80`.
- **Fix**: Use `git status --porcelain -uall …` (8 `??`, 0 ` M`). Reword 3.7 to "the dimmed Sheet overlay".
- **Decision**: FIXED

### F5 — E2E role locators should be exact and scoped

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §7, Phase 3 §2
- **Detail**: Substring name matching can hit links in page bodies and break strict mode.
- **Fix**: Scope to `getByRole('navigation', { name: 'Główna nawigacja' })` with `exact: true`.
- **Decision**: FIXED

### F6 — The user-menu aria-label hides its visible text

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §3
- **Detail**: `aria-label="Menu użytkownika"` replaces the visible name and e-mail (WCAG 2.5.3, label in name).
- **Fix**: Replace it with a leading `<span class="sr-only">Menu użytkownika</span>`. The locators still match.
- **Decision**: FIXED

### F7 — Project-level `updateSnapshots` is ignored by Playwright

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3 §5; pre-existing in `playwright.config.ts:30`
- **Detail**: Playwright 1.65 reads `updateSnapshots` only from the CLI or the top-level config (`lib/common/index.js:552`). The effective mode is `default`: a missing baseline is written and the test fails.
- **Fix**: Out of scope. Note it in Migration Notes as a known trap and follow-up.
- **Decision**: FIXED (documented in Migration Notes)
