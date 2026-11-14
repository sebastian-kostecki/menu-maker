# Shopping List (S-05) — Plan Brief

> Full plan: `context/changes/shopping-list/plan.md`

## What & Why

Show a shopping list next to the generated meal plan: ingredients summed, g→kg and ml→l above 1000, rounded to 2 decimal places, sorted alphabetically (FR-6, US-011). Without it the user still builds the list by hand, which is the waste the product exists to remove. S-06 (PDF) depends on it.

## Starting Point

A `done` plan has 21 meals pointing at the user's recipes. Ingredients use exactly g/kg/ml/l/szt. and one global id per name (S-02). Nothing computes a list yet, and a plan stores no quantities of its own.

## Desired End State

Below the 7×3 grid on `/meal-plans/{id}` there is a "Lista zakupów" card ("Jajka — 21 szt.", "Mąka — 2,1 kg", "Mleko — 1,05 l"), sorted in Polish order. It appears without a refresh when generation finishes and is rebuilt after "Regeneruj". Recipes without ingredients are named in a note.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Threshold | Strict `>1000`: 1000 g → "1000 g", 1000,01 g → "1 kg" | Matches the PRD wording word for word, so the tests trace to US-011. |
| Display unit | Sum in g/ml, then pick the unit by threshold only (0,5 kg alone → "500 g"; 0,3 kg + 900 g → "1,2 kg") | One rule for every row, and it covers both directions of "g↔kg". |
| Mixed units | One row per unit group (mass / volume / pieces), same-name rows adjacent, mass first | No invented conversion and no lost quantity. |
| Storage | Computed on read by `ShoppingListBuilder`; no table, no job change | No migration or sync bugs; behaves like the live grid; S-04/S-06 reuse the service. |
| Edge cases | Soft-deleted recipes count; recipes without ingredients add nothing and are named in a note | The list matches the grid, and the user knows why something is missing. |
| Placement | Card below the grid, only for `done` plans | Fits "przy jadłospisie" and the PDF order; no new shadcn components. |
| Testing | Pest unit + feature tests, plus the extended E2E `meal-plan.spec.ts` with seeded ingredients | Catches poll/prop wiring bugs Pest can't see. |
| Arithmetic | bcmath, exact sum, one half-up rounding at output; `Collator`/`NumberFormatter` `pl_PL` | Floats turn 1005 g into "1 kg" instead of "1,01 kg"; a byte sort misplaces Ł/Ś. |

## Scope

**In scope:** `Unit` groups, the `ShoppingList` value objects and builder service, `ShoppingListResource`, the `shoppingList` prop on show, the `ShoppingList.vue` card, the poll fix, Pest tests, seeder ingredients for the planner users, and the extended E2E spec.

**Out of scope:** S-04 scaling, S-06 PDF, storing the list, cross-group conversion or mixed-unit warnings, editing or ticking off items, a JSON endpoint, the list on index/dashboard.

## Architecture / Approach

`ShoppingListBuilder::build(QuantityLine[])` is pure: group by (ingredient, unit group), exact base sum, strict `>1000` → kg/l, round half-up, Polish format and sort. This is the S-04 seam. `forMealPlan()` loads meals → recipes (incl. soft-deleted) → ingredients + units, maps them to lines and collects ingredient-less recipe names. `MealPlanController::show` exposes the result as a closure prop (`null` unless `done`). `Show.vue` renders the card and polls `only: ['mealPlan', 'shoppingList']`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Shopping list service | Rules as a unit-tested service + plan loader | Rounding/threshold boundaries; float imprecision |
| 2. List on the plan view | Prop, resource, card under the grid, poll fix, feature tests | Poll omitting `shoppingList` → list missing until refresh |
| 3. E2E | Seeded planner ingredients + list assertions after generate and regenerate | Needs Sail, build, seed and the queue service |

**Prerequisites:** Sail running with the `queue` service (`vendor/bin/sail up -d`); S-02 and S-03 done (they are).
**Estimated effort:** ~1–2 sessions across 3 phases.

## Open Risks & Assumptions

- Before S-04 each meal contributes its whole recipe as written, so for a big family the list under-counts until scaling lands (by design; US-012 c.2 arrives with S-04).
- A recipe edited after generation changes an existing plan's list (accepted with "compute on read"; US-010.3 consistency for the PDF is S-06's concern).
- Relies on the S-02 guarantee of five unit codes; an unexpected code is shown as its own unconverted row instead of failing.

## Success Criteria (Summary)

- A done plan shows a correct, Polish-sorted, summed list under the grid, with the FR-6 conversion and rounding exact at the 1000 boundary.
- The list appears after generation and after regeneration without a manual refresh (proven in E2E).
- Pest, Larastan, Pint, build and both E2E browsers are green.
