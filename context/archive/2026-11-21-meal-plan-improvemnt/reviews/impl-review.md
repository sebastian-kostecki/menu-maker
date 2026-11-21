<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Menu-only PDF ("Sam jadłospis")

- **Plan**: context/changes/meal-plan-improvemnt/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-11-21
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

Automated checks run during this review: `sail pest --filter=menu` (20 passed), `sail composer test` (612 passed), `sail composer phpstan` (no errors, baseline unchanged), `sail composer pint-test` (passed), `sail npm run lint:tokens` (74 files, 0 hits), `sail npm run build` (OK), `npm run test:visual` after `E2eMealPlanSeeder` (64 passed, main checkout stack `menu-maker`).

## Findings

### F1 — Menu absence test does not assert the scaling notice is missing

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Unit/Services/MealPlanPdfExporterTest.php:551
- **Detail**: Plan §7 requires the rendered menu to "not contain … a scaling notice". The test sets `scaling.status = as_written` (which makes `ScalingNotices` produce "Dodaj członków rodziny, aby przeliczyć składniki…"), but never asserts that text is absent, so the setup is dead and the plan bullet is unverified. "Też na śniadanie" is also not covered (the fixture has no such link).
- **Fix**: Add `->not->toContain('Dodaj członków rodziny')` to the expectation chain.
- **Decision**: FIXED — asserted absence of "Dodaj członków rodziny" in the menu text.

### F2 — One-page render test uses 238-char spaced names, not 255 / unbroken

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Unit/Services/MealPlanPdfExporterTest.php:535
- **Detail**: Plan says "21 names of 255 characters → 1 page". The test uses `str_repeat('Ąęśćżółń Wxyz ', 17)` (238 chars, with spaces), so the `word-wrap: break-word` path for an unbreakable name is never rendered by any test (the 255×`a` test only checks the DTO string). A reviewer probe rendered 255×`W` and 255×`a` names after `Str::limit` and got 1 page, so the guarantee holds today — it is just not pinned. The DTO test also only has meals on 2 days, so "7 day labels" is not asserted as a count.
- **Fix**: Turn the one-page test into a dataset: a 255-char spaced name and `str_repeat('W', 255)`.
- **Decision**: FIXED — one-page test is now a dataset (255-char Polish words; 255×`W` unbreakable) and asserts 7 day labels; deliberate break (limit 255) makes both rows fail.

### F3 — `menu` flag rules untested in the Form Request unit test; `menu=1&recipes=0` is rejected

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Http/Requests/DownloadMealPlanPdfRequest.php:29, tests/Unit/MealPlanRequestValidationTest.php:125
- **Detail**: CLAUDE.md puts Form Request tests in `tests/Unit/`; `test_download_meal_plan_pdf_request_validates_recipes_flag` was not extended for `menu`. The allowed case `menu=0&recipes=1` is untested anywhere. `prohibited_if_accepted:menu` rejects any present `recipes`, including `recipes=0`, so `menu=1&recipes=0` is a 422 even though it is unambiguous. The UI never sends it, so this is strictness, not a user-visible bug.
- **Fix A ⭐ Recommended**: Keep the rule; extend the unit test with valid (`menu=1`, `menu=0&recipes=1`, `[]`) and invalid (`menu=abc`, `menu=1&recipes=1`, `menu=1&recipes=0`) inputs.
  - Strength: Pins current behaviour in the place CLAUDE.md expects; matches the plan's "no ambiguous variant" intent.
  - Tradeoff: Documents a slightly over-strict edge as intended.
  - Confidence: HIGH — existing test at :125 already loops over valid/invalid inputs; just add rows.
  - Blind spot: None significant.
- **Fix B**: Relax to reject only `recipes` accepted together with `menu` (e.g. a closure or `after()` check), then add the same tests.
  - Strength: Only truly contradictory requests fail.
  - Tradeoff: Custom rule instead of a built-in one; more code for a case nobody sends.
  - Confidence: MED — needs care with "1"/"true"/"on" accepted values.
  - Blind spot: Error message wording for the custom rule.
- **Decision**: FIXED via Fix A — `test_download_meal_plan_pdf_request_validates_menu_flag` pins menu=1/0/true and menu=0&recipes=1 as valid, menu=abc, menu=1&recipes=1 and menu=1&recipes=0 as invalid.

### F4 — Manual checks 1.5, 2.5 and 2.6 still pending

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/meal-plan-improvemnt/plan.md (Progress)
- **Detail**: All Automated rows are `[x]` and pass on re-run. Manual 1.5 (open the PDF by hand), 2.5 (the option works from all four menus) and 2.6 (baseline diff) are `[ ]`. For 2.6, the reviewer compared the PNGs: 192×76 → 192×108, the top 64 rows are pixel-identical, and the only addition is the "Sam jadłospis" row. It is the only PNG changed.
- **Fix**: Run the plan's manual testing steps 1–4 and tick 1.5/2.5; 2.6 can be ticked from the evidence above after a glance.
- **Decision**: PENDING (human) — manual checks 1.5, 2.5, 2.6 left for the user; queued in follow-ups/review-fixes.md.

### F5 — "Bez posiłku" is not muted in the menu grid

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/views/pdf/meal-plan-menu.blade.php:50
- **Detail**: The full template renders "Bez posiłku" in `<span class="meta">` (muted, `meal-plan.blade.php:77`); the menu prints it as a plain cell, so an empty slot looks like a recipe named "Bez posiłku".
- **Fix**: Wrap it in a muted span, add `--muted-foreground` to the palette mirror comment, and raise the menu's minimum token count in the palette test from 9 to 10.
- **Decision**: FIXED — `PdfMenuRow::EMPTY_SLOT` constant; the template prints it in a muted `.meta` span; `--muted-foreground` added to the mirror; palette minimum 9 → 10.

### F6 — `MENU_NAME_LIMIT` docblock says "ellipsis", output is ASCII "..."

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Services/MealPlanPdf/MealPlanPdfExporter.php:20
- **Detail**: `Str::limit()` appends `'...'` (the test asserts this at :525). The plan said "default ellipsis", so behaviour matches the plan; only the wording or typography is off.
- **Fix**: Pass `'…'` as `Str::limit`'s third argument and update the test (or reword the comment).
- **Decision**: FIXED — `Str::limit(..., '…')`; docblock and test updated.

### F7 — Third hand-copied `links` shape extended instead of reusing the shared type

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Components/MealPlans/ActionDropdown.vue:108-122, resources/js/Pages/MealPlans/Index.vue:~94, resources/js/Pages/MealPlans/Show.vue:~228
- **Detail**: Pre-existing debt: three local copies of the `links` shape got `pdf_menu` added by hand next to `MealPlanListItem` in `types/meal-plan.ts:19`, which `MealPlanTile.vue` already imports. The plan asked for exactly this, so it is not drift; it just makes the next link a four-file edit again.
- **Fix**: Import `MealPlanListItem` in ActionDropdown/Index; export a `MealPlanLinks` type for Show.vue (follow-up, out of this change's scope).
- **Decision**: DEFERRED — pre-existing type duplication; queued in follow-ups/review-fixes.md.

### F8 — E2E does not check the "Sam jadłospis" href

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: tests/e2e/meal-plan.spec.ts:123-124
- **Detail**: The spec asserts the hrefs of "Z przepisami" and "Bez przepisów" only. The plan explicitly chose "No E2E spec change", relying on manual check 2.5, so this is a conscious gap, not drift.
- **Fix**: Add one `toHaveAttribute('href', /menu=1/)` line for "Sam jadłospis" (needs a plan addendum lifting the E2E exclusion).
- **Decision**: FIXED — tests/e2e/meal-plan.spec.ts also fetches the "Sam jadłospis" href and checks the `-sam-jadlospis` filename (lifts the plan's "no E2E change" exclusion at the user's request); spec passes in chromium and firefox.

### F9 — Duplicate meals for one date and category are dropped silently in the menu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Services/MealPlanPdf/MealPlanPdfExporter.php:120
- **Detail**: `$meals->first(...)` keeps one meal per (date, category). The `meals` table has an index, not a unique constraint, on `(meal_plan_id, meal_date, meal_category)`, so a duplicate would print twice in the full PDF but once in the menu. The generator likely makes this unreachable (not verified).
- **Fix**: None needed now; revisit only if a unique constraint is ever added or duplicates appear.
- **Decision**: ACCEPTED — no change; noted in follow-ups/review-fixes.md.
