# Repeat Dinner (Cooked Once, Eaten Twice) — Plan Brief

> Full plan: `context/changes/meal-plan-repeat-dinner/plan.md`

## What & Why

Families often cook one pot of Obiad for two days. In the draft, the user can mark that day D's Obiad is also day D+1's (MS-04). Day D is scaled for both meals. Day D+1 shows the same dish "Z wczoraj" with no ingredients of its own, so the shopping list buys the dish once, in double quantity. This adds the link mechanism that S-20 reuses for Kolacja → next-day Śniadanie.

## Starting Point

S-17 and S-18 are done:
- A draft has 21 meals and one per-meal endpoint (`PATCH meal-plans/{plan}/meals/{meal}`) swaps, empties (`recipe_id` NULL) or fills a meal.
- Approval scales each meal on its own (`MealIngredientScaler`), and the shopping list sums ingredient rows across meals.
- There is no way to relate two meals.
- In the code, Obiad is `supper` (Kolacja is `dinner`).

## Desired End State

- **Linking.** Tuesday's Obiad dialog offers "Jak wczoraj: <Monday's dish>". Picking it shows "Na 2 dni" on Monday and "Z wczoraj" on Tuesday.
- **After approval.** Monday's Składniki are doubled, Tuesday has none, and the PDF prints both markers.
- **Unlinking.** Swapping or emptying Monday's Obiad empties Tuesday's. Swapping Tuesday's only unlinks it.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Quantities | Day D scaled for both meals, day D+1 has no ingredients, shopping list once × 2 | Cooking once must buy once. | Roadmap (2026-11-16) |
| Mechanism | The cooked meal points to the meal that eats it (`meals.feeds_meal_id`), shared with S-20 | One mechanism for both "cooked once" slices. | Roadmap |
| Unlinking | Swap or empty of day D removes the link and empties day D+1 | Day D+1 has no ingredients without day D. | Roadmap |
| Where the action lives | Fixed "Jak wczoraj" item in day D+1's swap dialog | Keeps one action per cell and reuses the S-18 marker-item pattern. | Plan |
| Chains | Pairs only; refused both ways | Matches "dwa dni z rzędu", factor is always ×2, unlinking never cascades. | Plan |
| No family profile | Double as written (and for unscaled recipes) | "Cooked for two meals" means the same with or without a family. | Plan |
| Markers | "Na 2 dni" on day D, "Z wczoraj" on day D+1 (muted Badge; PDF text) | Both cells explain themselves, in text, not colour. | Plan |
| Carried-over `recipe_id` | Keeps the cooked meal's recipe | List count, approve check, link and PDF name stay correct for free. | Plan |
| ×2 vs `MAX_FACTOR` cap | Double after the single-meal cap | The cap keeps meaning "implausible recipe kcal". | Plan |

## Scope

**In scope:**
- the `feeds_meal_id` migration and the `MealLinkRules` pair rules (`supper` → `supper`, +1 day);
- link and unlink through the existing swap endpoint (`from_meal_id`);
- the scaler ×2, the shopping-list skip and the resource fields;
- the grid badges, the dialog item and its warning, the redraw copy and the kitchen sink;
- PDF markers and recipe pages;
- E2E, the seeder and the visual baselines.

**Out of scope:**
- chains;
- other categories (S-20 adds Kolacja → Śniadanie);
- links across plans;
- a "Na 2 dni" button on day D;
- repeats created by the draw;
- edits outside the draft;
- consolidating the meal TS types.

## Architecture / Approach

The cooked meal gets a nullable, unique self-reference (`feeds_meal_id`), and the carried-over meal keeps the same `recipe_id`.
- **`MealLinkRules`** holds the allowed pairs and `canFeed()`, with the pair list mirrored in `resources/js/lib/mealLinks.js`.
- **The swap endpoint** accepts either `recipe_id` or `from_meal_id`. It runs one transaction under a plan-row lock that keeps the link consistent: the same recipe is a no-op, changing the cooked meal empties the carried-over one, and changing the carried-over meal only unlinks.
- **The scaler** doubles the cooked meal after the cap and skips the carried-over one. The shopping list and the PDF recipe pages skip it too.
- **The resource** exposes `feeds_meal_id` and `from_meal_id`, and the grid, the dialog and the PDF render the markers from them.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Linked meals in data, edits and scaling (backend) | Migration, pair rules, link/unlink endpoint, resource fields, ×2 scaling, shopping-list skip | Two-row edits racing an approval; doubling interacting with the cap or the as-written path |
| 2. Linked meals in the UI and PDF | Badges, "Jak wczoraj" item and warning, kitchen sink, PDF markers and recipe pages | Frontend pair mirror drifting from the server rules |
| 3. E2E and visual gate | Browser flow (link → Mąka 400 g, totals unchanged → unlink), pinned link in the seeded draft, 4 baselines | Unexpected baseline churn beyond the listed four |

**Prerequisites:** S-18 done (2026-11-16); a worktree environment (`.env` with own ports, `AI_SCALING_DRIVER=fake`, Sail, the queue worker, Mailpit).
**Estimated effort:** ~2–3 agent sessions across 3 phases, similar in size to S-18.

## Open Risks & Assumptions

- **E2E totals.** The seeded recipes have identical ingredients and a ×2 factor, so linking leaves the totals at 42 szt. / 4,2 kg / 2,1 l. The E2E test proves the ×2 by "Mąka — 400 g" on the cooked Obiad, not by the totals.
- **Hard-deleted recipes.** A hard-deleted recipe (seeder only) empties both meals and leaves a dangling link. Readers check the recipe first, so nothing renders it.
- **Unscaled recipes.** A recipe that is unscaled for a family is still doubled as written. The unscaled notice is unchanged.

## Success Criteria (Summary)

- The user links two consecutive Obiady in the draft and sees both markers on screen and in the PDF.
- After approval, the cooked day carries both meals' amounts, the next day carries none, and the shopping list buys the dish once.
- Changing either side of a pair leaves no stale link. Changing the cooked day empties the next day.
