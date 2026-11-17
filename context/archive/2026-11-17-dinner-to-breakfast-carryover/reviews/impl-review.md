<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Kolacja Carried Over to the Next Day's Śniadanie

- **Plan**: context/changes/dinner-to-breakfast-carryover/plan.md
- **Scope**: Full plan (all automated rows done; manual rows 2.4–2.9 and 3.6 pending)
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-17
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Success criteria run (2026-11-17, against this worktree's Sail stack)

| Check | Result |
|---|---|
| `vendor/bin/sail composer test` | PASS: 556 passed (2277 assertions) |
| `vendor/bin/sail composer phpstan` | PASS: no errors, `phpstan-baseline.neon` untouched |
| `vendor/bin/sail composer pint-test` | PASS |
| `vendor/bin/sail npm run build` | PASS |
| `vendor/bin/sail npm run lint:tokens` | PASS: 74 files, 0 hits |
| `db:seed --class=E2eMealPlanSeeder` + `pest --filter=E2eMealPlanSeeder` | PASS: 9 passed |
| `npm run test:e2e` (`E2E_BASE_URL=http://localhost:8091`) | PASS: 36 passed |
| `npm run test:visual` | 51 passed, 1 failed (`shell nav hover`; passed 2/2 on rerun, unrelated baseline, see F4) |

Manual rows 2.4–2.9 and 3.6 are unchecked and treated as pending, not as false ticks. Only the four baselines listed in plan §3.4 changed. The drift reviewer compared the old and new `meal-plan-draft-swap-dialog` PNGs: the grid behind the overlay is visible and now shows "Też na śniadanie", so that change is justified.

Cross-category readers: the scaler, `UpdateMealRequest`, `link()`/`canFeed()`, the swap options in `Show.vue`, the stripe/PDF colour, the PDF recipe pages, `ShoppingListBuilder`, `MealPlanResource` and the redraw were all checked. None of them assumes that the cooked meal and the fed meal share a category. Nothing in "What We're NOT Doing" was touched.

## Findings

### F1 — The new pair's dialog-only states are not asserted by any test

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/e2e/meal-plan.spec.ts:245-249 (code: resources/js/Components/MealPlans/RecipeSwapDialog.vue:29-40,131-139)
- **Detail**: These strings exist only in JS (`mealLinks.js`), and the project has no JS unit runner:
  - the new `feedsWarning` ("Zmiana opróżni śniadanie z …, które jest „z kolacji”.");
  - the `offerHint`;
  - the "obecny" mark on the "Z kolacji" item.

  `grep -rn "opróżni" tests/` returns nothing. The E2E only clicks `option {name: /Z kolacji/}`, and the swap-dialog visual shot opens an Obiad dialog. A `from`/`to` mix-up in `cookedPairFor`/`carriedPairFor` would hide the warning while every automated check stays green; only manual rows 2.5/2.7 would catch it.
- **Fix**: In the E2E Kolacja block:
  - before swapping Monday's Kolacja (`:246-249`), assert that the dialog contains "Zmiana opróżni śniadanie z";
  - after linking, reopen Tuesday's Śniadanie and assert that the `/Z kolacji/` option contains "obecny".
- **Decision**: FIXED: the E2E reopens Tuesday's Śniadanie and checks that the "Z kolacji" item has the text `Z kolacji: <name>`, the hint "Gotujemy raz, zostaje na śniadanie" and "obecny". It then closes the dialog with Close (`includeHidden`: the always-open Combobox hides the rest of the dialog from the accessibility tree and takes Escape). In Monday's Kolacja dialog it checks the warning `^Zmiana opróżni śniadanie z .+, które jest „z kolacji”\.$`.

### F2 — E2E Kolacja variables are named "supper", which means Obiad in this codebase

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/e2e/meal-plan.spec.ts:212-250
- **Detail**: The new block names the Kolacja (`dinner`, `td.nth(2)`, "– Kolacja") `mondaySupper`, `supperName`, `expectSupperPair`, `supperToggle` and `supperUnlinkDialog`. The S-19 block above does the opposite and calls the Obiad `mondayDinner`/`tuesdayDinner`. The spec now uses both words in both meanings, which is the "naming trap" the plan warned about. The Pest tests use `kolacja`/`sniadanie` (`planWithKolacjaAndSniadanie`).
- **Fix**: Rename the new block's variables to `mondayKolacja`, `kolacjaName`, `expectKolacjaPair`, `kolacjaToggle` and `kolacjaUnlinkDialog`, matching the Pest naming.
- **Decision**: FIXED: the Kolacja block uses `mondayKolacja`, `tuesdaySniadanie`, `kolacjaName`, `expectKolacjaPair`, `kolacjaToggle`, `sniadanieLinkDialog` and `kolacjaUnlinkDialog`. The S-19 block is unchanged.

### F3 — The PDF recipe-page label keeps a hard-coded Obiad fallback

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Services/MealPlanPdf/MealPlanPdfExporter.php:60
- **Detail**: `cookedPairFor(...)['use_suffix'] ?? 'na 2 dni'`. The plan says to use null when no pair is found, and the table marker (`:137-141`) and the grid badge both drop the label. This is the only "na 2 dni" left outside the pair definitions. It is unreachable for a valid link. On drifted data, though, the PDF would print "(na 2 dni)" on the recipe page with no marker in the table.
- **Fix**: Append ` (<suffix>)` only when `cookedPairFor()` returns a pair, otherwise print `$dayLabel` alone.
- **Decision**: FIXED: the suffix is added only when `cookedPairFor()` returns a pair; otherwise the label is `$dayLabel` alone. The `'na 2 dni'` fallback is gone.

### F4 — Visual gate flaked once on `shell nav hover`

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/e2e/visual/design-system.spec.ts:57
- **Detail**: The full run gave 51 passed, 1 failed. Run alone, `shell nav hover` passed 2/2. This change did not touch its baseline or the shell nav. It is a hover-timing flake outside this change, but it can turn CI red on the PR.
- **Fix**: None in this change. If it repeats in CI, note it as a follow-up for the hover spec (e.g. wait for the hover transition before the screenshot).
- **Decision**: ACCEPTED: no change in this PR, as recommended. One more flake was seen during the fix runs: `approve()` in `tests/e2e/meal-plan.spec.ts:77` ("Generujemy jadłospis…" not visible, in one browser, once). Both browsers passed on the next two runs. Follow-up if CI hits either flake.

## Triage summary (2026-11-17)

- Fixed: F1, F2, F3 (3)
- Accepted: F4 (1)

Verification after the fixes:
- `pest tests/Unit/Services/MealPlanPdfExporterTest.php`: 27 passed
- `composer phpstan`: no errors
- `composer pint-test`: passed
- `npm run test:e2e -- tests/e2e/meal-plan.spec.ts`: passed in Chromium and Firefox, twice in a row, after a fresh `E2eMealPlanSeeder` each time
