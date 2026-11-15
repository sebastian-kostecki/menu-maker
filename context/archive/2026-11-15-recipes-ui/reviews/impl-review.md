<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Recipes UI (S-11 / DS-06)

- **Plan**: context/changes/recipes-ui/plan.md
- **Scope**: Full plan (automated rows of all phases done; all Manual rows still pending)
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-15
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Success criteria re-run (2026-11-15, this worktree's Sail stack, APP_PORT 8101)

| Check | Result |
|---|---|
| `sail npm run build` | PASS |
| `sail npm run lint:tokens` + self-test | PASS: 36 files, 0 hits |
| Greps 2.3, 2.4, 3.3, 3.4, 4.3 | PASS |
| `sail composer test` | PASS: 368 tests, 1365 assertions |
| `sail composer phpstan` / `pint-test` | PASS |
| Seeder run twice | Row ids, names, categories, servings, calories and `created_at` match across runs. `updated_at` drifts (see F4). |
| Full E2E (`npm run test:e2e`) | 21 passed, 1 failed: `[firefox] recipe-form.spec.ts:3` (submit still disabled). The re-run passed 8/8 with `--repeat-each=4 --workers=1` (see F5). |
| `npm run test:visual` ×3 | PASS: 37/37, no baseline drift |
| Manual rows 1.6–1.7, 2.7–2.10, 3.7–3.8, 4.6–4.8, 5.8–5.10 | All pending (`[ ]`), none ticked without evidence |

Verified as matching the plan: PageHeader and EmptyState contracts, format.js (module-level Intl, Polish plural rule incl. 12–14 and 22–24), server-filter empty-state predicate, `Button as-child` + Link without `@click`, one h1 per page, the E2E invariants (no new "Ilość", "Jednostka", "Składnik" or "Usuń …" names; "200 g" is one element), the deletes of RowActionDropdown and RecipeHeader, the lint paths as directories, the seeder scoped to the visual user (FKs cascade; meal plans are deleted first), and every boundary in "What We're NOT Doing".

## Findings

### F1 — Every search sends `category=all` and shows "Brak wyników"

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/recipes/RecipesToolbar.vue:104-108, resources/js/Pages/Recipes/Index.vue:36
- **Detail**: Reproduced in Chromium as the visual user. Searching "Jajecznica" navigates to `/recipes?category=all&…&search=Jajecznica&sort=function%20sort()…` and renders "Brak wyników", although "Jajecznica ze szczypiorkiem" exists. Cause:
  - The toolbar emits `{search, category: categoryValue}` with the UI value `'all'`.
  - `v-model="filterState"` on a `reactive` const replaces the object with a plain one, which also leaves `syncToStorage` watching the old object.
  - `RecipeController@index` applies `byCategory('all')` because `category` is `filled()`.

  This bug is older than this change: the toolbar diff is copy only, and `updateFilters` and the v-model are identical to 52e5dc8. The change made it worse in two ways. The new "Brak wyników" copy states that no recipe matches the filters, which is false here. And `recipes-index-no-results.png` searches a nonsense term, so the gate cannot catch the bug. The `sort=function sort()` value comes from the same path; its root cause was not verified.
- **Fix A ⭐ Recommended**: Fix it in this branch as a plan addendum. The toolbar emits `category: categoryValue === 'all' ? null : categoryValue`. Index uses `:model-value` + `@update:model-value="v => Object.assign(filterState, v)"`. Trace the stray `sort` value. Add an E2E test that searches a real name and expects its row.
  - Strength: The touched files are already migrated in this slice, and the empty state that makes the bug visible ships here.
  - Tradeoff: Breaks "no change to filtering" in What We're NOT Doing. Needs an addendum and an E2E run.
  - Confidence: MED — the cause is confirmed in code and in the browser; the `sort` part is not yet traced.
  - Blind spot: The localStorage restore path (`useLocalStorageStateSync`) is not checked with the new wiring.
- **Fix B**: Record it as a follow-up change (bug fix for recipe filtering) and merge this slice as is.
  - Strength: Keeps scope discipline strict, and the fix gets its own plan and tests.
  - Tradeoff: Search stays broken after the merge, behind a now-confident "Brak wyników" message.
  - Confidence: HIGH — the change is independent of the restyle.
  - Blind spot: How soon a follow-up slot exists on the roadmap.
- **Decision**: FIXED (Fix A). `RecipesToolbar` maps "all" → null. `Index` merges with `Object.assign` and normalises an array `filters` (the root cause of `sort=function sort()`). Added `tests/e2e/recipe-search.spec.ts` and a plan addendum. Refreshed `recipes-index-desktop.png`, where only the "Dodano" sort icon changes.

### F2 — Migration Notes not filled in; the baseline delta went past the allowed list

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/changes/recipes-ui/plan.md (Migration Notes; Progress 1.5)
- **Detail**: Phase 1 §5 asked that every modified PNG be named in Migration Notes with its reason. The section still holds the "must be listed here when it lands" placeholder; the reasons exist only in the 0597e4c commit message. The 1.5 delta allowed `section-komponenty-aplikacji.png` plus `overlay-*` dialog PNGs. `overlay-user-menu.png` (not a dialog) also changed, yet 1.5 is ticked. `overlay-dialog.png` and `overlay-alert-dialog.png` did not change.
- **Fix**: Fill in Migration Notes with the four PNGs and their reasons (new demos; page height shifts the overlay corner pixels). Record `overlay-user-menu.png` as an accepted deviation from 1.5.
- **Decision**: FIXED. Migration Notes list the four PNGs with their reasons; `overlay-user-menu.png` is recorded as an accepted deviation from 1.5.

### F3 — List rounds calories differently from Show

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/Components/recipes/RecipeRow.vue:13-16
- **Detail**: The plan says `${formatNumber(calories)} kcal`. The row passes `{ maximumFractionDigits: 0 }`, so a recipe saved with 350.5 kcal shows "351 kcal" in the list and "350,5 kcal" on Show. Every call with options also builds a new `Intl.NumberFormat`, per row and per render, against the plan's Performance note. The screenshots are unaffected, because the seeded calories are integers.
- **Fix**: Use `formatNumber(calories)` as planned.
- **Decision**: FIXED. `RecipeRow` now uses `formatNumber(calories)`.

### F4 — Seeder determinism gaps (`updated_at`, live twins)

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: database/seeders/E2eMealPlanSeeder.php:113-115, 202-214
- **Detail**: The plan and the docblock pin `created_at` and `updated_at` at 12:00 UTC. `syncPinnedCalories` runs a builder `update()` afterwards, which resets `updated_at` to now on every run (seen in the double run). No view renders it, but `RecipeResource` exposes it. Also, two live recipes with the same listed name are not deduplicated: `firstOrNew` updates one, and the other keeps its old `created_at`. Only manual use of the visual account can create such twins. Separately, and out of scope: Show loads ingredients without an `ORDER BY`, a latent risk for the zero-tolerance `recipe-show-*.png`.
- **Fix**: Wrap the update in `syncPinnedCalories` in `Recipe::withoutTimestamps(...)`, or correct the docblock.
- **Decision**: FIXED. `syncPinnedCalories` is wrapped in `Recipe::withoutTimestamps`, and two seeder runs now produce identical rows, `updated_at` included.

### F5 — Firefox recipe-form E2E flaked once in the full run

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/e2e/recipe-form.spec.ts:31
- **Detail**: In the full parallel suite, the submit button stayed `disabled` after the ingredient was filled. Four other Sail stacks were running at the time. Repeated serially, it passed 8/8 on both browsers. Nothing in the diff suggests a regression; the form now renders inside `AuthenticatedLayout`, which is heavier.
- **Fix**: Re-run the full E2E before the merge and watch CI. If it recurs, check the unit `Select` option click under load in Firefox.
- **Decision**: FIXED. It recurred, so I traced it. The cause: `fill` on "Sposób przygotowania" raced the closing category Select, whose focus return to its trigger swallowed the text. This is the same flake noted in the ai-ingredient-scaling review. The spec now waits for the listbox to be hidden. It passed 16/16 at 4 workers, and the full E2E passed 24/24. Recorded in a plan addendum.

### F6 — Breadcrumb links lack the token focus ring

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Pages/Recipes/Show.vue:9, resources/js/Pages/Recipes/Form.vue:9
- **Detail**: The "Przepisy" breadcrumb `Link`s fall back to the browser outline. The RecipeRow name link and SortableHeader use `focus-visible:ring-2 ring-ring`. S-09 and S-10 will copy this breadcrumb markup.
- **Fix**: Add `rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring` to both breadcrumb links.
- **Decision**: FIXED. Both breadcrumb links (Show, Form) now have the token focus ring.

### F7 — Visual spec: non-exact heading locators and a duplicated `logIn`

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/e2e/visual/recipes.spec.ts:18-25 (and the `openShow` h1 / "Brak wyników" locators)
- **Detail**: The plan says "Locators by role + exact name". Two heading locators lack `exact: true`; both are unique today. `logIn()` is copied from `app-shell.spec.ts` with the credentials hard-coded.
- **Fix**: Add `exact: true` to both locators. Optionally move `logIn` to `tests/e2e/visual/support/`.
- **Decision**: FIXED. Both heading locators have `exact: true`. `logIn` moved to `tests/e2e/visual/support/login.ts`, which `app-shell.spec.ts` uses too.

### F8 — format.js edge cases

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/format.js
- **Detail**: Run under node:
  - `formatNumber(' ')` returns `"0"`; whitespace is not treated as empty.
  - `formatDate(0)` returns "1 sty 1970".
  - `pluralize(2.5)` returns the "few" form, while Polish uses "many" for fractions.

  None of these is reachable with the current props. S-09 and S-10 will build on this helper.
- **Fix**: Treat `typeof value === 'string' && value.trim() === ''` as empty, and return `many` for non-integer counts.
- **Decision**: FIXED. A whitespace-only string now formats as `''`. `pluralize` coerces its input with `Number()` and returns "many" for non-integers.
