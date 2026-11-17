<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Repeat Dinner (Cooked Once, Eaten Twice)

- **Plan**: context/changes/meal-plan-repeat-dinner/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-17
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Automated verification (re-run 2026-11-17 in this worktree)

| Command | Result |
|---|---|
| `sail artisan migrate:fresh` → `migrate:rollback --step=1` → `migrate` | PASS |
| `sail composer test` | PASS: 532 tests, 2181 assertions |
| `sail composer phpstan` | PASS: no errors; `phpstan-baseline.neon` untouched |
| `sail composer pint-test` | PASS |
| `sail npm run build` | PASS |
| `sail npm run lint:tokens` | PASS: 74 files, 0 hits |
| `sail artisan db:seed --class=E2eMealPlanSeeder` + `sail pest --filter=E2eMealPlanSeeder` | PASS: 8 tests |
| E2E (chromium + firefox) | PASS: 36 tests |
| `npm run test:visual` | 51/52 on the first run. `shell nav hover` differed by 3 px; that baseline is not touched by this change, and the test passed on rerun (flake). |

Manual Progress items 2.5–2.10 and 3.6 are unchecked, so they are **pending**, not false ticks.

## Findings

### F1 — Re-picking a carried-over meal's own recipe silently keeps the link

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence (plan flaw)
- **Location**: resources/js/Components/MealPlans/RecipeSwapDialog.vue:52, app/Http/Controllers/MealPlanMealController.php:92
- **Detail**:
  - On a linked day D+1, the dialog hides "obecny" on the list item for its current recipe (`!meal?.from_meal_id`) and puts it on "Jak wczoraj" instead. The plain list item therefore looks like "cook this dish fresh on Tuesday".
  - Picking that item sends `recipe_id` equal to the current recipe. `changeRecipe()` returns early on the same-recipe rule, so the request succeeds, the dialog closes, and the link stays: Monday is still ×2 and Tuesday still "Z wczoraj".
  - The user can only unlink while keeping the dish by first picking another recipe and then switching back.
  - The plan's rule 1 (same recipe = no-op, keeps the link) did not separate the two intents on D+1, and no test covers the D+1 side.
- **Fix A ⭐ Recommended**: On a carried-over meal, a `recipe_id` equal to the current one drops the link (cook it fresh). The no-op stays for unfed meals and for the cooked meal.
  - Strength: Every dialog item does what it shows. Unlinking without changing the dish becomes one click, and "Jak wczoraj" vs the plain item map to two distinct outcomes.
  - Tradeoff: Narrows the plan's "same recipe = no-op" rule; needs one feature test, and the plan should be amended.
  - Confidence: MED — E2E step 4 relies on the no-op only on the cooked side (Monday), which is unchanged.
  - Blind spot: Have not checked whether double-click resubmission ever sends the same `recipe_id` on a fed meal.
- **Fix B**: Keep the backend. In the dialog, mark the current recipe's list item "obecny" and disable it while the meal is carried over.
  - Strength: Keeps the plan's rule intact; a small UI-only change.
  - Tradeoff: There is still no one-step way to unlink and keep the dish; "obecny" then shows on two items.
  - Confidence: HIGH — a local change to one template condition.
  - Blind spot: Visual baseline `meal-plan-draft-swap-dialog` would change.
- **Decision**: FIXED (Fix A) — `changeRecipe()` drops the link when a carried-over meal gets its own recipe again; test "picking the carried-over meal's own recipe from the list drops the link and keeps the dish"; plan amended (Critical Implementation Details, case 1).

### F2 — PDF prints "—" in the carried-over meal's ingredients cell

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/views/pdf/meal-plan.blade.php:82-86
- **Detail**: The plan says a carried-over meal's ingredients cell stays empty. `pdfMeal()` passes `ingredients = []`, and the non-empty branch's `@forelse … @empty — @endforelse` prints "—". The PDF tests assert only the DTO (`ingredients === []`), not the rendered cell.
- **Fix**: Add a `bool $carriedOver` to `PdfMeal` and render an empty `<td>` for it, with a rendered-HTML assertion. Alternatively, accept "—" and amend the plan.
- **Decision**: FIXED — `PdfMeal::$carriedOver` + `@elseif ($meal->carriedOver)` empty `<td>`; test "leaves a carried-over meal's ingredients cell empty".

### F3 — Readers trust `feeds_meal_id` without checking the shared recipe

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Services/MealLinks/MealLinkRules.php (`fedMealIds`), used by MealIngredientScaler, ShoppingListBuilder and MealPlanPdfExporter
- **Detail**:
  - The scaler, the shopping list and the PDF treat a meal as carried over only because another meal points to it.
  - Every current writer keeps both meals on the same `recipe_id`: link, change on either side, redraw, back to draft, the seeder, and recipe hard-delete via `nullOnDelete`.
  - If a future writer (for example S-20) broke that pairing, the fed meal's own recipe would silently get no ingredients and no shopping-list entry.
- **Fix**: In `fedMealIds()`, count a meal as fed only when its `recipe_id` equals the feeding meal's.
- **Decision**: FIXED — `fedMealIds()` counts a fed meal only when it shares the cooked meal's recipe; test "does not treat a fed meal with another recipe as carried over".

### F4 — Family-less cooked meal is doubled under an "as written" notice

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Services/IngredientScaling/MealIngredientScaler.php (as_written branch), app/Services/IngredientScaling/ScalingNotices.php:33
- **Detail**: The plan intends this and a test pins it: without a family, the cooked meal's rows are `source = as_written` with doubled quantities. "Ilości są podane jak w przepisach" is then not literally true for that meal. The "Na 2 dni" badge explains it on screen.
- **Fix**: Adjust the notice copy (e.g. "…jak w przepisach (obiady na 2 dni — podwójnie)") only when the plan has a link.
- **Decision**: FIXED — `as_written` notice adds ", a dla obiadów na 2 dni — podwójnie" when the loaded meals hold a link (no query, so the index stays N+1-free); ScalingNotices test added.

### F5 — `MealLinkRules` created with `new`; unused relations; no JS test for the mirror

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Services/IngredientScaling/MealIngredientScaler.php, app/Services/ShoppingList/ShoppingListBuilder.php, app/Services/MealPlanPdf/MealPlanPdfExporter.php; app/Models/Meal.php:51-57; resources/js/lib/mealLinks.js
- **Detail**:
  - The three services instantiate `MealLinkRules` inline. The scaler receives all its other collaborators through the constructor, and the controller injects it.
  - `Meal::feedsMeal()` and `fedBy()` were planned but are used nowhere.
  - `linkSourceFor()` mirrors `canFeed()`, but only E2E and visual tests cover it.
- **Fix**: Inject `MealLinkRules` through the constructors. Keep the relations for S-20, or drop them.
- **Decision**: FIXED — `MealLinkRules` injected (with a `new` default, like `QuantityFormatter`) into MealIngredientScaler, ShoppingListBuilder and MealPlanPdfExporter. `feedsMeal()`/`fedBy()` kept for S-20; no JS test runner in the repo, so `linkSourceFor()` stays covered by E2E/visual.

### F6 — Change marked implemented while manual checks are still pending

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/meal-plan-repeat-dinner/plan.md (Progress 2.5–2.10, 3.6)
- **Detail**:
  - Seven manual checks are unchecked, including the review of the 4 changed PNGs. The automated drift check confirmed that exactly the 4 listed baselines changed, but nobody has looked at the pixels.
  - The roadmap still reads S-19 `in-progress`.
- **Fix**: Run the manual checklist (incl. PNG review) before merging. Set roadmap S-19 to `done` at archive.
- **Decision**: DEFERRED TO HUMAN — manual checks 2.5–2.10 and 3.6 (PNG review) need a person; roadmap S-19 goes to `done` at /10x-archive.

## Triage summary (2026-11-17)

- Fixed: F1 (Fix A), F2, F3, F4, F5 (5)
- Deferred to human: F6 (1)
- Verification after fixes: Pest 536 passed (2191 assertions), Larastan OK, Pint OK, E2E 36 passed (chromium + firefox).
