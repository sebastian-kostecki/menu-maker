<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Spiżarnia Family and Profile UI

- **Plan**: context/changes/spizarnia-family-profile-ui/plan.md
- **Scope**: Full plan (the automated rows of both phases are done; all 9 manual rows are still pending)
- **Reviewed phases**: 1, 2
- **Date**: 2026-11-18
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Automated verification (re-run during review)

All of these ran against this worktree's own Sail stack (`mm-family-profile-ui`). Its `/var/www/html` mount was confirmed to be this worktree.

| Criterion | Result |
|---|---|
| `sail npm run build` | PASS |
| `sail npm run lint:tokens` + `scripts/lint-tokens.test.sh` | PASS (72 files, 0 hits; self-test 3/3) |
| 1.3 grep for `FamilyMemberTable` / `FamilyMemberPagination` / `pluralizePl` | PASS (no matches) |
| `sail composer test` (includes `E2eMealPlanSeederTest`) | PASS (560 tests, 2286 assertions) |
| `sail composer phpstan` / `pint-test` | PASS / PASS |
| Functional E2E after re-seeding (`APP_PORT=8091`, `MAILPIT_URL=:8096`) | PASS (36 passed; the visual project is skipped outside Docker) |
| Visual gate (`VISUAL_DOCKER_NETWORK=mm-family-profile-ui_sail`, no `--update-snapshots`) | PASS (62/62, the planned total) |

**Plan adherence.** Every Changes Required item in both phases matches the plan. That includes the class contracts (`min-h-57.5`, `p-5.5`, `size-14`, `max-w-180`, `shadow-sticker shadow-destructive`), the accessible names, the colour cycle, `:today` in the kitchen sink, `tile` being kept out of the `buttonSizes` loop, and the shot totals of 61 and then 62 in `design-system.md` §7.

**Manual checks.** Rows 1.9–1.13 and 2.6–2.9 are all unchecked. They are pending before merge, not a finding.

## Findings

### F1 — Family tiles lose list semantics

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Pages/FamilyMembers/Index.vue:85
- **Detail**:
  - The person tiles sit in a plain `<div class="grid">` of `role="article"` cards.
  - The table they replace had row and column semantics, and the sibling tile view wraps its tiles in a list (`Pages/MealPlans/Index.vue:25` uses `<ul aria-label="Jadłospisy"><li>`).
  - So a screen reader no longer announces a list or how many people are in it.
  - The plan specified a `<div>` grid, so this is a gap in the plan that the code faithfully implements.
- **Fix**: Render the grid as `<ul aria-label="Członkowie rodziny">` with one `<li>` per tile, and put the dashed tile in a final `<li>` or just after the list in the same grid. Preflight resets list styling, so the pixels should not change. Confirm that with the visual gate.
- **Decision**: FIXED — `<ul aria-label="Członkowie rodziny">` with `<li class="grid">` per tile, and the dashed tile in the last `<li>`. The visual gate is 62/62 with no baseline refresh.

### F2 — Two unplanned changes not recorded in the plan

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: tests/e2e/__screenshots__/visual/design-system.spec.ts/overlay-confirm-dialog-field.png; tests/e2e/meal-plan.spec.ts:74
- **Detail**:
  - **The overlay baseline.** Phase 1 change 9 says "any other changed PNG is a regression". Yet commit 1d5a21c refreshed `overlay-confirm-dialog-field.png`, from blob 393dd5e to 86e835b.
    - The difference is 2 pixels on the dialog's left and right edges, at most 12 RGB levels each. That is anti-aliasing noise, probably because the new tile grid shifts the content under the dialog.
    - It is recorded neither in the plan nor in the commit message.
  - **The flake fix.** Commit bde0a98 fixes a flake in `meal-plan.spec.ts`.
    - The spec now waits for the POST `/approve` response and then for `data-testid="shopping-list"`, instead of the short-lived „Generujemy jadłospis…” text.
    - It is a justified, separate `fix(e2e)` commit, and it touches no feature code.
    - It is not in the plan either.
- **Fix**: Add a short note under the plan's Progress (or in `change.md` Notes) recording both, with the overlay PNG confirmed as noise.
- **Decision**: FIXED — both recorded in `change.md` Notes.

### F3 — Family-visual seeding does not enforce "no recipes or plans"

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: database/seeders/E2eMealPlanSeeder.php:156
- **Detail**:
  - `syncFamilyVisualUser()` only deletes and recreates family members.
  - The class docblock (:31-32) says the user has "no recipes or plans", and `E2eMealPlanSeederTest.php:150-151` asserts that. The assertion holds only because the test database is fresh.
  - On a dev database where someone logged in as this user and created data, that data survives re-seeding.
  - The dashboard-empty user next to it does enforce this, with `recipes()->withTrashed()->forceDelete()` and `mealPlans()->delete()`.
  - There is no visual impact today, because the family page shows neither recipes nor plans.
- **Fix**: Add the same two deletes to `syncFamilyVisualUser()`, so the seeder guarantees what its docblock and test claim.
- **Decision**: FIXED — `syncFamilyVisualUser()` force-deletes recipes (trashed ones included) and deletes meal plans. New Pest test: „clears recipes and plans the family-visual user gained since the last seed”.

### F4 — `parseYmd` rolls over impossible dates

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/format.js:18
- **Detail**:
  - **Impossible dates.** `BARE_YMD` accepts `2023-02-31` or `2020-13-45`. `new Date(y, m-1, d)` then rolls them over to a valid date, so `ageInYears` returns a number where its docblock promises `null`. `formatDate` now prints the rolled-over date, where before it returned `''` for `2020-13-45`.
  - **Two different input rules.** `ageInYears` checks only the 10-character prefix, while `formatDate` requires a bare string.
  - **Impact today.** None of this is reachable now: `FamilyMember` casts `birth_date` to `date:Y-m-d`, and server validation rejects impossible dates.
- **Fix**: In `parseYmd`, return an Invalid Date when the month or day does not round-trip, so both callers fall into their existing invalid-date paths.
- **Decision**: FIXED — `parseYmd` returns an Invalid Date when the month or day does not round-trip. All callers already handle that.

### F5 — Family visual spec does not assert the seeded tiles

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/e2e/visual/family-members.spec.ts:16
- **Detail**:
  - The desktop and mobile shots wait only for the h1 before the screenshot.
  - `visual/meal-plans-index.spec.ts:26` first asserts the seeded tile count, so a stale or missing seed fails with a clear message.
  - Here the same situation surfaces as an unexplained pixel diff.
- **Fix**: Add `await expect(page.getByRole('article')).toHaveCount(3)` before the desktop and mobile screenshots.
- **Decision**: FIXED — `toHaveCount(3)` on articles before the desktop and mobile shots.
