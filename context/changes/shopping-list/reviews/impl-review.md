<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Shopping List (S-05)

- **Plan**: context/changes/shopping-list/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-14
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Automated verification (re-run 2026-11-14)

| Command | Result |
|---|---|
| `vendor/bin/sail composer test` | PASS: 216 tests, 795 assertions |
| `vendor/bin/sail composer phpstan` | PASS: no errors, baseline unchanged |
| `vendor/bin/sail composer pint-test` | PASS |
| `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder` (twice) | PASS |
| `vendor/bin/sail npm run build` | PASS |
| `npm run test:e2e` (Chromium + Firefox) | PASS: 10/10. It needed `--output` pointed at another directory, because the local `test-results/` is owned by root. |

Unplanned files (all justified):
- `Recipe.php`: a Larastan generic on `recipeIngredients()`.
- `RecipeIngredient.php`: `strict_types` and `@property` docs.
- `IngredientTest.php`: the helper was renamed to avoid a global function collision.

## Findings

### F1 — bcmath/intl not declared as requirements

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: composer.json (require); .github/workflows/master.yml:80, :169
- **Detail**: `ShoppingListBuilder` now needs `ext-bcmath` and `ext-intl` (`bcmul`, `NumberFormatter`, `Collator`). Neither is in composer.json `require`, and neither is in the CI `setup-php` `extensions:` lists. Sail ships both, and CI passes only because the runner image happens to include them. A different environment would fail at runtime instead of at `composer install`.
- **Fix**: Add `"ext-bcmath": "*"` and `"ext-intl": "*"` to composer.json `require`, and `bcmath, intl` to both CI extension lists.
- **Decision**: FIXED — ext-bcmath/ext-intl added to composer.json require (lock content-hash refreshed) and to both CI setup-php extension lists

### F2 — Duplicated global test helpers

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Feature/MealPlanControllerTest.php:426, tests/Unit/Services/ShoppingListBuilderTest.php:95, :15
- **Detail**: `mealPlanWithRecipes()` and `planWithRecipes()` do the same thing, and each is a global function in its own file. `const SHOPPING_FACTORS` is a global constant. Pest loads every file into one process, and this change already had to rename `attachIngredient` in IngredientTest for that reason. The next helper with the same name will cause a fatal redeclare.
- **Fix**: Move one `mealPlanWithRecipes(User, string $status, Recipe ...)` into tests/Pest.php, delete the copy, and make `SHOPPING_FACTORS` local to `displaysFor()`.
- **Decision**: FIXED — single mealPlanWithRecipes(User, string $status, Recipe ...) moved to tests/Pest.php, both copies removed; SHOPPING_FACTORS made local

### F3 — Unknown unit code still multiplied by factor

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Services/ShoppingList/ShoppingListBuilder.php:28-30
- **Detail**: The plan says an unknown code forms its own group "with no conversion", but line 30 multiplies by `factorToBase` for every code. With a factor other than 1, the list would show a converted number next to the original unit label. `Unit::ALLOWED_CODES` makes this unreachable today, and no test covers it.
- **Fix**: Use `$line->quantity` unmultiplied when the code is not in `Unit::GROUPS`.
- **Decision**: FIXED — unknown codes no longer multiplied by factorToBase; regression test added

### F4 — NumberFormatter failure displays an empty number

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Services/ShoppingList/ShoppingListBuilder.php:138
- **Detail**: `(string) $formatter->format((float) $quantity)` turns a `false` result into "", which would render as " kg" with no error. The float cast is harmless, because the value is already rounded to 2 decimals.
- **Fix**: Fall back to `str_replace('.', ',', $quantity)` when `format()` returns false.
- **Decision**: FIXED — falls back to comma-decimal string when NumberFormatter::format() returns false

### F5 — tests/Pest.php touched without strict_types

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Pest.php:1
- **Detail**: CLAUDE.md asks for `declare(strict_types=1);` to be added when touching a PHP file. Pest.php gained `attachIngredient` but still has no declaration.
- **Fix**: Add `declare(strict_types=1);` after `<?php`.
- **Decision**: FIXED — declare(strict_types=1) added to tests/Pest.php

### F6 — Manual checks pending

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/shopping-list/plan.md:343-359
- **Detail**: Manual checks 2.6–2.9 (live list after generation, Polish formatting and order, the "Bez składników" note, dark mode and mobile) and 3.5 (CI e2e job on the PR into main) are unchecked, so they are pending, not falsely ticked. The browser spec covers 2.6 and part of 2.7 automatically.
- **Fix**: Run the manual checks in Sail and open the PR into main before archiving.
- **Decision**: PENDING — manual checks 2.6–2.9 and 3.5 left to the user
