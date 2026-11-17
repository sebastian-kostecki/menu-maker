<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Spiżarnia Meal Plans UI

- **Plan**: context/changes/spizarnia-meal-plans-ui/plan.md
- **Scope**: Full plan (automated rows of both phases done; all 12 manual rows still pending)
- **Reviewed phases**: 1, 2
- **Date**: 2026-11-17
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Automated verification (re-run during review)

| Criterion | Result |
|---|---|
| `sail npm run build` | PASS |
| `sail npm run lint:tokens` + `scripts/lint-tokens.test.sh` | PASS (74 files, 0 hits; self-test 3/3) |
| 1.3 grep for removed list components | PASS (no matches) |
| 2.3 grep for stripe / primary-hover / back button | PASS (no matches) |
| 2.4 `git diff --exit-code develop -- tests/e2e/meal-plan.spec.ts` | PASS |
| `sail composer test` | PASS (559 tests, 2283 assertions) |
| Full E2E after re-seed | PASS 36/36. The first run had 2 false failures in `password-reset.spec.ts`: `MAILPIT_URL` defaulted to 8025, but this worktree's Mailpit is on 8030 (see F4). |
| `npm run test:visual` | PASS 58/58 with `VISUAL_DOCKER_NETWORK=mm-meal-plans-ui_sail`. On the default network it reported 16 false failures, because it screenshotted the main checkout's stack (see F4). |

## Findings

### F1 — Kitchen-sink tiles expose live delete / return-to-draft actions

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Pages/Dev/DesignSystem.vue:915 (tiles with ids 1–5 from :295); resources/js/Components/MealPlans/ActionDropdown.vue:159, :189
- **Detail**:
  - `MealPlanTile` embeds the real `ActionDropdown`. Its „Wróć do szkicu” and „Usuń” confirms call `router.post(route('meal-plans.draft', {meal_plan: id}))` and `router.delete(route('meal-plans.destroy', …))`.
  - On `/dev/design-system` (local/testing), a logged-in developer who owns plan 1–5 can reset or delete a real plan from the demo. The policy limits this to their own data, but the data loss is still real.
  - Before this change, the kitchen sink rendered no mutating action menu. `RecipeSwapDialog` already has a `demo` prop for exactly this purpose (`RecipeSwapDialog.vue:101`, `:185`).
- **Fix**: Add a `demo` prop to `MealPlanTile` → `ActionDropdown` (as in `RecipeSwapDialog`) that skips the visits and mutations, and pass `demo` from the kitchen sink.
- **Decision**: FIXED — `demo` prop on `ActionDropdown` (confirms close without a request) and `MealPlanTile`; the kitchen sink passes `demo`.

### F2 — Sticky day column can hide the focused cell on mobile

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/MealPlans/MealPlanGrid.vue:2, :10, :30
- **Detail**:
  - The grid is a `min-w-160` table in `Card class="overflow-x-auto p-4"`, with opaque `sticky left-0 z-10 bg-card` day headers that are content-width: about 90–100 px for „poniedziałek” in extrabold.
  - At 390 px, Tab from a scrolled-right Kolacja cell to the next row's Śniadanie link. The browser scrolls the link only to the container's left edge, which leaves it and its 2px ring under the sticky `th` (WCAG 2.2 SC 2.4.11).
  - The old grid had no sticky column, so this behaviour is new.
  - Not reproduced in a browser during the review. It is inferred from the markup, and manual row 2.10/2.11 should confirm it.
- **Fix**: Give the day `th` a fixed spacing-scale width (e.g. `w-28`) and add a matching `scroll-pl-*` to the scroll container, so focus scrolling clears the sticky column.
  - Strength: Two utility classes, lint-safe (spacing scale), and it fixes both Tab and Shift+Tab.
  - Tradeoff: The fixed width may truncate or wrap a long weekday and changes the 3 mobile/desktop show baselines plus `section-jadlospis.png`.
  - Confidence: MED — scroll-padding is honoured by focus scrolling in Chromium and Firefox, but it is not verified here.
  - Blind spot: Not verified in WebKit, and the exact column width needed with the `dd.mm` line is unmeasured.
- **Decision**: FIXED — day column `w-28` (corner th, day th, skeleton td) + `scroll-pl-32` on the grid Card. Verified at 390 px in Chromium and Firefox: the focus ring went from up to 14 px under the sticky column to 14 px clear. The 5 `meal-plan-*` PNGs and `section-jadlospis.png` were refreshed and reviewed (cells shift right only).

### F3 — Phase 1 changed show-page baselines not listed in the plan

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/e2e/__screenshots__/visual/meal-plan-show.spec.ts/*.png (commit e31400d)
- **Detail**:
  - Phase 1, change 8 lists only the index and kitchen-sink PNGs and says „any other changed PNG is a regression”. Yet e31400d also refreshed all 5 `meal-plan-*` shots, because the new round „…” `ActionDropdown` trigger also shows on `Show.vue:35`.
  - Phase 2 refreshed them again. That is justified, and the commit message explains it, but the plan was never amended. It also breaks „each spec's baselines refreshed exactly once”.
- **Fix**: Append an addendum to plan.md noting the shared-trigger knock-on, so the archive matches what happened.
- **Decision**: FIXED — plan.md addendum.

### F4 — Documented verification commands hit the wrong stack in a parallel worktree

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: scripts/visual-test.sh:10; tests/e2e/support/mailpit.ts:1; .env (`FORWARD_MAILPIT_DASHBOARD_PORT` appears twice: 8026, then 8030)
- **Detail**:
  - `npm run test:visual` defaults to the Docker network `menu-maker_sail`, which belongs to the main checkout (develop). From this worktree it reported 16 false failures, with kitchen-sink sections hundreds of px shorter.
  - The password-reset spec defaults to Mailpit on 8025 and failed for the same reason.
  - The risk: `--update-snapshots` run the same way would overwrite this change's baselines with develop's rendering.
  - With `VISUAL_DOCKER_NETWORK=mm-meal-plans-ui_sail` and `MAILPIT_URL=http://localhost:8030`, everything is green.
  - This is not a defect in the change, but it is a trap for every parallel worktree run.
- **Fix**: Record as a lesson, or add to CLAUDE.md, that worktree runs must set `VISUAL_DOCKER_NETWORK=<project>_sail` and `MAILPIT_URL`, and drop the duplicate `.env` key.
- **Decision**: FIXED + ACCEPTED-AS-RULE: Point E2E and visual runs at the worktree's own Sail stack — lesson in `context/foundation/lessons.md`; duplicate `FORWARD_MAILPIT_PORT` / `FORWARD_MAILPIT_DASHBOARD_PORT` (1026/8026) removed from the local `.env`.

### F5 — Unknown `filter[status]` leaves no pill pressed

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/MealPlans/StatusFilterPills.vue:5, :14
- **Detail**: „Wszystkie” is pressed only when `modelValue` is empty. A hand-edited or stale URL such as `filter[status]=archived` shows zero pressed pills, which breaks the plan's „exactly one pressed” invariant. The controller does not validate the value.
- **Fix**: Press „Wszystkie” when `!statuses.some(s => s.value === modelValue)`.
- **Decision**: FIXED — „Wszystkie” is pressed for an empty or unknown status, and clicking it clears an unknown one.

### F6 — Week tiles have no heading

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/MealPlans/MealPlanTile.vue:4-9
- **Detail**: The old table gave screen readers row/column structure. The tile list replaces it with plain text, and the date link is not a heading, so heading navigation skips every week. The plan asked for `text-section-title`, but not a heading element.
- **Fix**: Wrap the date `Link` in `<h2 class="text-section-title">`, keeping the page's single `<h1>`.
- **Decision**: FIXED — date link wrapped in `<h2 class="text-section-title">` (pixel-identical; no baseline changed).

### F7 — Small unplanned kitchen-sink additions

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: resources/js/Pages/Dev/DesignSystem.vue:188, :1033
- **Detail**: Two additions the plan did not ask for:
  - `tag` was added to the generic `badgeVariants` row; the plan asked for it only on the meal swatches.
  - The shopping-list demo grid went from `md:grid-cols-2` to `md:grid-cols-3`.

  Both are harmless and covered by the refreshed baselines.
- **Fix**: Keep them, and mention both in the same plan addendum as F3.
- **Decision**: FIXED — kept, documented in the plan.md addendum.

## Triage summary

The user chose „fix everything per the recommendations”. Fixed: F1, F2, F3, F5, F6, F7 (6). Rule + fixed: F4 (1). Re-verified after the fixes: build, lint:tokens, both grep checks, visual gate 58/58 and E2E 36/36 (against `mm-meal-plans-ui_sail` and Mailpit 8030).

## Pending manual checks (plan Progress 1.7–1.11, 2.8–2.14)

None are ticked. Review the PNGs in e31400d/5117a32, especially those accepted as anti-aliasing noise: `section-formularze`, `section-komunikaty`, `section-przyciski` and `focus-visible`.
