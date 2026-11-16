# Empty Meal Slot — Plan Brief

> Full plan: `context/changes/meal-plan-empty-slot/plan.md`
> Roadmap: `context/foundation/roadmap.md` — S-18 (M-3), MS-03

## What & Why

Families don't cook every meal: sometimes nobody eats breakfast at home. In the draft the user should be able to mark a meal "Nie robimy" and get no ingredients for it. The same change drops the 7-recipes-per-category requirement, which blocks users who have only a few recipes (for example the 6 importable kolacje left open by `recipe-markdown-import`).

## Starting Point

S-17 delivered the draft: generation draws 21 meals, the user swaps recipes per meal and approves, and then the AI scaling runs. `meals.recipe_id` is NOT NULL, the draw refuses any category with fewer than 7 recipes, and the Dashboard warns "Nie można jeszcze wygenerować tygodnia". The scaler and the shopping list already skip a meal without a recipe. The PDF drops it, and the grid cannot fill it.

## Desired End State

- Every draft has 21 meals. A short category fills random days, and the rest read "Bez posiłku".
- A notice above the draft grid names the short categories ("Kolacja (6 z 7)") and links "Dodaj przepis".
- In the swap dialog, "Nie robimy (pusty posiłek)" empties a meal. An empty cell has "Wybierz" to fill it back.
- After approval, an empty meal shows "Bez posiłku" on screen and in the PDF, with nothing on the shopping list.
- Only a user with no recipe at all is refused.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| 7-per-category rule | Dropped; gaps stay empty in the draft | Users with few recipes can still plan a week | Roadmap |
| When generation refuses | Only with zero recipes, via the "Dodaj przepis" link in the dialog | Any recipe gives a usable draft | Roadmap |
| Which days stay empty | Random days per category (day offsets shuffled) | Matches the random draw; a redraw moves the gaps | Plan |
| Storage of an empty meal | A `meals` row with `recipe_id = NULL`, FK `nullOnDelete` | Every cell stays addressable for edits and for S-19 links | Plan |
| How to empty and refill | "Nie robimy" item in the swap dialog; "Wybierz" button on an empty cell | One control per cell, one endpoint (`PATCH … recipe_id: null`) | Plan |
| Empty-meal label | Muted "Bez posiłku" on screen and in the PDF, same for user-emptied and shortfall gaps | Neutral wording fits both causes without a new column | Plan |
| Shortfall messaging | Draft notice only; the Dashboard warning is removed | The message shows where the gaps are, and the dashboard no longer claims generation is impossible | Plan |
| All-empty draft | Approval refused ("Szkic nie ma żadnego posiłku z przepisem…") | No AI call or rate-limit slot for a plan with nothing to scale | Plan |
| Notice data | Server prop `shortCategories` (draft only) | Pest can pin it | Plan |
| List meal count | Counts only meals with a recipe | The list shows what is actually planned | Plan |

## Scope

**In scope:**
- the nullable migration
- the gap-aware draw and the zero-recipe refusal
- the generate, redraw, approve and swap validators
- `shortCategories`
- removing the Dashboard warning
- the grid, swap dialog and notice UI
- PDF rows and recipe pages
- kitchen sink demos
- the E2E empty/refill flow
- a pinned empty meal in the seeded draft and deliberate baselines

**Out of scope:**
- distinguishing user-emptied from shortfall gaps
- the draw repeating recipes
- a dashboard hint
- a separate "Opróżnij" button
- editing outside the draft
- redistributing kcal
- scaler, refiner and shopping-list builder changes
- linked meals (S-19/S-20)

## Architecture / Approach

An empty meal is a NULL `recipe_id` on a regular row. The draw always writes 21 rows per plan and gives drawn recipes the first entries of a shuffled day list per category. The S-17 swap endpoint accepts `null` (`present|nullable`, written as is, not via `integer()`). Downstream, only the PDF exporter needs a change; the resource, the scaler and the shopping list already handle the case.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Empty meals in data and draw (backend) | Migration, gap-aware draw, zero-recipe refusal, validators, `shortCategories`, Dashboard prop removal, Pest | Many tests assume 7 per category and must be rewritten; `integer()` would turn `null` into 0 |
| 2. Empty meals in UI and PDF | "Bez posiłku"/"Wybierz" cells, the "Nie robimy" item, the notice, Dashboard cleanup, PDF rows, kitchen sink | One control per cell; the PDF must keep every day |
| 3. E2E and visual gate | Empty → approve (20 meals) → refill → approve (21 meals); a pinned empty meal; deliberate baselines | Baselines changing beyond the listed six |

**Prerequisites:** S-17 is merged (it is, on `develop`). A worktree Sail stack is set up as in the plan's Environment section, with `AI_SCALING_DRIVER=fake`.
**Estimated effort:** ~2–3 agent sessions across 3 phases.

## Open Risks & Assumptions

- The E2E totals assume the seeder's identical pinned ingredients, so one empty meal gives 40 szt. / 4 kg / 2 l. Confirm the whole-number formatting in Phase 3.
- A migration rollback deletes empty meals. This is acceptable because the grid and the PDF tolerate missing rows.
- After Phase 1 the UI still renders an empty cell as "—" without "Wybierz", until Phase 2. Merge only after Phase 3.

## Success Criteria (Summary)

- A user with fewer than 7 recipes in a category gets a draft with "Bez posiłku" gaps on random days and a notice naming the short categories.
- A user can empty and refill any draft meal. After approval the empty meal has no ingredients on screen, on the shopping list or in the PDF.
- Pest, Larastan, Pint, the build, `lint:tokens`, E2E and the visual gate are green, and only the expected baselines changed.
