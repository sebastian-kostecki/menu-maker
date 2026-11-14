<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Meal Plan PDF Export (S-06)

- **Plan**: context/changes/meal-plan-pdf-export/plan.md
- **Scope**: Full plan (Phases 1–3; all Automated rows done, Manual rows 2.8–2.12 and 3.4–3.7 still pending)
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-14
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | FAIL |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Success criteria run (2026-11-14)

| Criterion | Command | Result |
|---|---|---|
| 1.1 | `vendor/bin/sail pest tests/Unit/Services` | PASS (32 tests) |
| 2.1 | `sail artisan migrate && migrate:rollback --step=1 && migrate` | PASS |
| 2.2 | `sail pest tests/Unit/MealPlanPolicyTest.php tests/Feature/MealPlanControllerTest.php` | PASS (58 tests) |
| 2.3 | `grep -rnE "pdf_path\|pdf_size\|withPdf" app database/factories` | PASS (no matches) |
| 2.4 / 3.3 | `sail composer test` | PASS (235 tests, 856 assertions) |
| 1.2 / 2.5 / 3.3 | `sail composer phpstan` | PASS (no errors, baseline unchanged) |
| 1.3 / 2.6 / 3.3 | `sail composer pint-test` | PASS |
| 2.7 / 3.1 | `sail npm run build` | PASS |
| 3.2 | E2E seeder + `npx playwright test` (chromium and firefox) | PASS (10 tests). `--output` pointed to a temp dir because `test-results/` is root-owned from an earlier container run. |

Manual rows: none are falsely checked; all 9 are pending. Row 3.5 ("List menu item enabled only for done plans") will fail until F1 is fixed.

## Findings

### F1 — List "Pobierz PDF" menu item is always disabled

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Http/Resources/MealPlanCollection.php:88-90
- **Detail**: The plan's end state says the "⋯" item is enabled on the plan view *and the list*. Phase 2 §4 assumed that adding `links.pdf` to `MealPlanResource` "applies to the index collection", but `MealPlanCollection::toArray` builds each item by hand with `'links' => ['self' => …]` and never goes through `MealPlanResource`. On `MealPlans/Index`, `item.links.pdf` is therefore `undefined`, `ActionDropdown`'s `canDownloadPdf` (`ActionDropdown.vue:117`) is always false, and the TS types added in `Index.vue`, `MealPlanTable.vue`, `MealPlanRow.vue` and `types/meal-plan.ts` describe a field the server never sends. Tests miss it: the `links.pdf` feature test covers only `show`, and the E2E spec never opens the list menu. The plan was wrong here, and the implementation followed it.
- **Fix**: Add `'pdf' => $mealPlan->status === 'done' ? route('meal-plans.pdf', $mealPlan->id) : null` to the collection's item `links`, and add an index feature test that asserts `mealPlans.data.0.links.pdf` for a done plan and `null` for a pending one.
- **Decision**: FIXED — `MealPlanCollection` item links now include `pdf` (done → route, else null); new feature test `exposes the PDF link on the list only for done plans` (fails without the fix, verified).

### F2 — `Download` icon used but never imported in Show.vue

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Pages/MealPlans/Show.vue:19, :93
- **Detail**: The template renders `<Download class="mr-2 h-4 w-4" />`, but line 93 imports only `{ ArrowLeft, ChefHat, RefreshCw }` from `lucide-vue-next`, and there is no auto-import plugin. Vue logs "Failed to resolve component: Download" and renders an empty `<download>` element, so the header button shows no icon. The build and E2E still pass because they check only the link's name and href.
- **Fix**: Add `Download` to the lucide-vue-next import on line 93.
- **Decision**: FIXED — `Download` added to the lucide-vue-next import in Show.vue.

### F3 — Null-recipe skip and empty-day omission are untested

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Services/MealPlanPdf/MealPlanPdfExporter.php:30
- **Detail**: Phase 1 §4 says to skip meals with a `null` recipe and omit days with no meals. The code does both (`->filter(fn (Meal $meal): bool => $meal->recipe !== null)` plus grouping), but no test in `MealPlanPdfExporterTest.php` exercises either path. The plan's own test list didn't ask for one, so this is a coverage gap rather than a missing planned case.
- **Fix**: Add one `document()` case in which a meal's recipe is unresolvable (for example, its `recipe_id` points to a force-deleted recipe) and assert that the meal and its now-empty day are absent.
- **Decision**: FIXED — new exporter test `skips meals without a recipe and omits days left empty` (null recipe simulated via `setRelation`, because `meals.recipe_id` cascades on delete).

## Triage

All findings were fixed on 2026-11-14 at the user's request ("napraw samodzielnie według rekomendacji"). After the fixes: `sail composer test` 237 passed, Larastan OK, Pint OK, `npm run build` OK, E2E 10/10 passed (chromium + firefox).
