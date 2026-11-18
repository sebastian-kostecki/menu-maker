<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Spiżarnia Dashboard UI

- **Plan**: context/changes/spizarnia-dashboard-ui/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-18
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Automated verification (re-run during review)

Every check below ran against this worktree's own stack, `mm-dashboard-ui`. `/var/www/html` is mounted from this worktree, as confirmed with `docker inspect`.

| Command | Result |
|---|---|
| `vendor/bin/sail composer test` | PASS: 562 tests, 2330 assertions |
| `vendor/bin/sail composer phpstan` | PASS: no errors |
| `vendor/bin/sail composer pint-test` | PASS |
| `vendor/bin/sail npm run lint:tokens` + `bash scripts/lint-tokens.test.sh` | PASS: 78 files, 0 hits; self-test exits 0 |
| `vendor/bin/sail npm run build` | PASS: client and SSR |
| `VISUAL_DOCKER_NETWORK=mm-dashboard-ui_sail npm run test:visual` (after re-seed) | PASS: 60/60 |
| `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:8093 MAILPIT_URL=http://localhost:8094 npm run test:e2e` (after re-seed) | PASS: 36/36 |
| `git status --short` after the runs | clean, no baseline drift |

The plan drift check found MATCH on every planned item. These were verified directly:
- `focus-visible:outline-highlight-foreground` survives `cn()` (tailwind-merge drops `outline-ring`).
- The colour maps are static.
- `todayYmd` uses the local date and `weekDays` steps in UTC.
- The dashboard props stay scoped to the owner, with no N+1: `load('meals.recipe')` adds 2 queries.

One cosmetic drift was not raised as a finding. The kitchen-sink linked-meal demos run Thu→Fri and Fri→Sat instead of the plan's Mon→Tue example, because they reuse the „Jadłospis” demo data. Every required state is still shown.

## Findings

### F1 — Link-marker test encodes a reversed, invalid link

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/DashboardControllerTest.php:142
- **Detail**: The test „exposes an empty slot without a recipe and the link markers” has two problems:
  - It puts `feeds_meal_id => $source->id` on `$leftover`, which is dated day+1. In the domain, the cooked meal holds `feeds_meal_id` pointing to the carried meal (`MealPlanMealController::link()`: `$from->update(['feeds_meal_id' => $meal->id])`). So the „leftover” is really the source.
  - Dinner→dinner is not a pair in `MealLinkRules::PAIRS`.

  The test is green because it only checks serialisation, but it records a state the app can never produce, and its variable names mislead.
- **Fix**: Use a valid pair, for example supper→supper with a supper recipe. Set `feeds_meal_id` on `$source` (day 0) pointing to `$leftover` (day +1). Swap the `from_meal_id`/`feeds_meal_id` assertions to match.
- **Decision**: FIXED. The test now uses a supper→supper pair: `feeds_meal_id` on the day-0 source points to the day+1 leftover, and the assertions are swapped. DashboardControllerTest passes 10/10.

### F2 — `weekDays()` throws on a malformed date

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/mealWeek.js:4
- **Detail**: For a missing or malformed `startYmd`, `Date.UTC(NaN, …)` yields an Invalid Date, and `.toISOString()` throws a `RangeError` during render. That takes down the whole dashboard instead of only the week card. `lib/format.js` guards against exactly this case: invalid input renders as '' rather than throwing. The server always sends Y-m-d today, so the chance is low, but the helper is new and shared.
- **Fix**: Return `[]` when any of year, month or day is `NaN`, as `format.js` does.
- **Decision**: FIXED. `weekDays()` now returns `[]` when year, month or day is NaN.

### F3 — Unplanned edits outside the phase boundaries

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: context/foundation/roadmap.md:48, :139
- **Detail**:
  - The Phase 3 commit 6905d8f changed S-23 from `ready` to `in-progress` and bumped the roadmap's `updated:`. The plan says „Status fields are left to the lifecycle skills”.
  - The Phase 2 commit c10aa49 already edited `Pages/Dashboard.vue`, swapping the StatCard props, and refreshed 3 dashboard baselines. The plan put that work in Phase 3. The commit message explains why: StatCard was already live. Phase 3 overwrote those baselines, so the net effect is nil.
- **Fix**: Revert the S-23 status and `updated:` lines in the roadmap, and leave them to `/10x-archive`. The phase-split drift needs no code change.
- **Decision**: FIXED. S-23's status is back to `ready` in both the table and its section, and the roadmap's `updated:` is back to 2026-11-17. The phase-split drift is accepted, since its net effect is nil.

### F4 — All manual verification rows are pending

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/spizarnia-dashboard-ui/plan.md:425-449
- **Detail**: Manual rows 2.6–2.9 and 3.8–3.13 (10 in total) are all `[ ]`, so none of them was falsely ticked. A green unattended run is not a finished change. Two checks have no automated equivalent:
  - the focus ring on the PDF trigger on `highlight`;
  - the hover inversion of `inverse-outline`.

  The real PDF downloads from the hero and the no-ready-plan hero on the live page are only partly covered by the kitchen-sink shots.
- **Fix**: Walk the manual checklist at http://localhost:8093 before merging, and tick the rows.
- **Decision**: PENDING (human). Before merging, someone has to walk the manual checklist (rows 2.6–2.9 and 3.8–3.13) in a browser.

### F5 — Category fill-class map now duplicated in four places

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Components/Dashboard/WeekOverview.vue:11, resources/js/Components/Dashboard/StatCard.vue:6
- **Detail**: The map from `breakfast/supper/dinner` to `bg-meal-* text-meal-*-foreground` now lives in four places:
  - `MealPlanGrid.vue:167`
  - `GuestLayout.vue:12`
  - `WeekOverview.vue:11`
  - `StatCard.vue:6`

  `lib/mealCategories.js` already holds `MEAL_CATEGORIES` and the labels.
- **Fix A ⭐ Recommended**: Export `MEAL_CATEGORY_FILL_CLASSES` from `lib/mealCategories.js` and use it in the two new dashboard components only.
  - Strength: The new code reuses one source. The class strings stay literal, so Tailwind still finds them. Plan constraints are respected: no `MealPlanGrid` change.
  - Tradeoff: Two older copies remain until a follow-up.
  - Confidence: HIGH — a static object export keeps the Critical Implementation Details rule intact.
  - Blind spot: `StatCard`'s `tone` is semantically a colour, not a meal, so sharing the meal map couples them.
- **Fix B**: Leave as is, and add a `## Parked` roadmap bullet to consolidate all four after S-23…S-26 merge.
  - Strength: No churn while the parallel slices are in flight.
  - Tradeoff: The duplication grows if S-24/S-26 add more copies.
  - Confidence: MED — depends on the parallel slices.
  - Blind spot: Haven't checked whether S-24/S-26 add their own copies.
- **Decision**: FIXED via Fix A. `MEAL_CATEGORY_FILL_CLASSES` now lives in `lib/mealCategories.js`, and WeekOverview and StatCard both use it. StatCard's validator now checks against `MEAL_CATEGORIES`. MealPlanGrid and GuestLayout are untouched.

### F6 — Kitchen-sink caption names the wrong meal

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Pages/Dev/DesignSystem.vue:1074
- **Detail**: The caption reads „wartość 1 (kolacja, …)”, but the tile uses `tone="supper"`, which is „Obiad” (`lib/mealCategories.js`). The caption is in `section-pulpit.png`, so fixing it means refreshing that one baseline.
- **Fix**: Change „kolacja” to „obiad”, then run `--update-snapshots` for `section-pulpit.png` only, on the `mm-dashboard-ui_sail` network.
- **Decision**: FIXED. The caption now reads „obiad”. Only `section-pulpit.png` was refreshed: the mount was verified first and the new PNG reviewed. The full visual gate passes 60/60.

### F7 — Stray whitespace edit in an unrelated test

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/e2e/visual/design-system.spec.ts:136
- **Detail**: The diff turns `test('shell nav hover', async` into `test('shell nav hover',async`. This is an accidental edit to a test the plan does not touch.
- **Fix**: Restore the space.
- **Decision**: FIXED. The space is restored.

### F8 — Hero heading is a bare date

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/Dashboard/LatestPlanHero.vue:27
- **Detail**: In a screen reader's heading list, the hero `<h2>` reads only „15–21 czerwca 2026”, because the „Ostatni jadłospis” context is in a `<p>` before it. A minor point: `StatusTag value="done"` is hard-coded rather than taken from `mealPlan.status`, though the controller guarantees `done`.
- **Fix**: Prefix the h2 with `<span class="sr-only">Ostatni jadłospis: </span>`. This makes no visual change, so no baseline moves.
- **Decision**: FIXED. The hero h2 now starts with an sr-only „Ostatni jadłospis: ” prefix, with no pixel change. The hard-coded `StatusTag value="done"` is left as is.
