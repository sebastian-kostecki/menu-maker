<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: PDF Recipe Pages

- **Plan**: context/changes/pdf-recipe-pages/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-16
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | WARNING |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Automated verification (re-run during review)

The checks ran inside the `menu-maker-laravel.test-1` container, from the worktree path.

| Command | Result |
|---|---|
| `pest` exporter + palette + MealPlanController + request validation + Dashboard tests | PASS (98 tests, 588 assertions) |
| `pest` full suite | PASS (421 tests, 1622 assertions) |
| `composer phpstan` | PASS (no errors, no baseline changes) |
| `composer pint-test` | PASS (175 files) |
| `artisan route:list --name=meal-plans.pdf` | PASS (one route) |
| `npm run build` | PASS |
| `npm run lint:tokens` | PASS (72 files, 0 hits) |
| E2E `meal-plan.spec.ts`, `npm run test:visual` | NOT RE-RUN. The running Sail app serves the main checkout, not this worktree. They are ticked in Progress at 4f20c2f, and exactly the 7 expected baseline PNGs changed. |

Manual rows 1.5, 2.6, 3.6, 3.7 and 3.8 are all still `[ ]` (pending). None is ticked without evidence.

## Findings

### F1 — "Bez składników" assertion cannot fail

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Unit/Services/MealPlanPdfExporterTest.php:346
- **Detail**: `toContain('Bez składników')` is already satisfied by the shopping-list note "Bez składników (nie ma ich na liście): Stara zupa." (`meal-plan.blade.php:109`). The test would pass even if the recipe page (`meal-plan.blade.php:123`) lost the message. The plan's Testing Strategy item "A recipe without ingredients shows 'Bez składników'" is therefore not actually verified.
- **Fix**: Assert `substr_count($text, 'Bez składników')` is 2, or check that it appears after the recipe heading that follows "Lista zakupów".
- **Decision**: FIXED. The test now asserts `substr_count(..., 'Bez składników') === 2`, and a deliberate break of `meal-plan.blade.php:123` makes it fail.

### F2 — Recipe category vs meal slot is untested

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Unit/Services/MealPlanPdfExporterTest.php:298-312 (helper `addMeal()` at :25-33)
- **Detail**: The plan requires the page category to come from the recipe's own `category`, not the meal slot, because an S-20 breakfast can carry a dinner recipe. `addMeal()` always sets `meal_category = $recipe->category`, so the test cannot tell the two sources apart. The code is correct (`MealCategory::from($recipe->category)`), but nothing guards it against a regression.
- **Fix**: In "takes the recipe page fields from the recipe", create the `Meal` with `meal_category => 'breakfast'` for the supper recipe, and keep asserting `Obiad`/`supper`.
- **Decision**: FIXED. The meal is now created in the `breakfast` slot for the supper recipe, and taking the category from the slot instead makes the test fail.

### F3 — Plan-review amendments committed inside the phase-1 feat commit

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: commit aa03408
- **Detail**: `feat(pdf-recipe-pages): PDF document and template (p1)` also contains `reviews/plan-review.md`, `plan-brief.md` and the plan contract edits:
  - a one-cell `td.category` table instead of widening the selectors
  - rejecting `?recipes=true`
  - `ChefHat`/`FileDown` icons in the ⋯ menu
  - both items in the plan page's ⋯ menu
  - `dashboard-mobile-drawer.png` among the expected baselines

  The code matches the amended plan, but history shows the contract changing in the same commit as the code that satisfies it.
- **Fix**: No change needed now. In future runs, commit plan-review amendments as a separate `chore:` before phase 1.
- **Decision**: ACCEPTED. No change, as the recommendation says.

### F4 — Invalid `recipes` flag redirects to a page that shows no error

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Http/Requests/DownloadMealPlanPdfRequest.php:26-31
- **Detail**: On this GET endpoint, which streams a PDF in a new tab, `?recipes=abc` or `?recipes=true` triggers the default `failedValidation` redirect back with a `recipes` error, and no page displays that error. The app's own links always send `recipes=1`, so only a hand-edited URL hits this. The behaviour is planned and covered by `assertInvalid`.
- **Fix**: Accept as is. Optionally override `failedValidation` to `abort(422)`.
- **Decision**: ACCEPTED. Kept as is, as the recommendation says.

### F5 — Authorize-before-validate order not pinned for an invalid flag

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/MealPlanControllerTest.php (the dataset on "cannot download another user's PDF")
- **Detail**: The plan's Key Discoveries rely on `authorize()` running before `rules()`, so a non-owner gets 403, not a validation error. The 403 test's dataset covers no parameter and `recipes=1`, but not an invalid value.
- **Fix**: Add `'invalid flag' => [['recipes' => 'abc']]` to the 403 test's dataset.
- **Decision**: FIXED. The case is added to the dataset and gets 403 for both the done and the pending plan.

### F6 — `MealPlanListItem` type declared five times

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/types/meal-plan.ts:21, ActionDropdown.vue:102, MealPlanRow.vue:49, MealPlanTable.vue:84, Pages/MealPlans/Index.vue:52
- **Detail**: This duplication predates the change, and the plan chose to follow it. As a result, `pdf_with_recipes` had to be added in 6 places, and the shared `types/meal-plan.ts` is imported nowhere.
- **Fix**: As a follow-up, import `MealPlanListItem` from `@/types/meal-plan` and delete the local copies.
- **Decision**: DEFERRED. Queued in `follow-ups/review-fixes.md`.

### F7 — E2E/visual not re-verified; all manual checks pending

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: N/A (Progress rows 3.3, 3.4, 1.5, 2.6, 3.6–3.8)
- **Detail**: The review re-ran every PHP and JS automated check, but not E2E or the visual gate: the only running Sail stack mounts the main checkout. Their evidence is the 4f20c2f Progress ticks plus a baseline diff that matches the plan exactly (7 PNGs). The 5 manual checks are untouched: PDF content and order, the two filenames, the menus at all entry points with keyboard focus, the 390px header, and the PNG review.
- **Fix**: Before merging, run E2E and the visual gate against a stack serving this branch (or after merging into `develop`), then complete manual rows 1.5, 2.6 and 3.6–3.8.
- **Decision**: DEFERRED. Queued in `follow-ups/review-fixes.md`. It needs a stack serving this branch and a person for the manual rows.
