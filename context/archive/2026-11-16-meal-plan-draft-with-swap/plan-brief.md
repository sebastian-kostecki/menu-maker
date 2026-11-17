# Meal Plan Draft with Recipe Swap — Plan Brief

> Full plan: `context/changes/meal-plan-draft-with-swap/plan.md`
> Roadmap: `context/foundation/roadmap.md` — S-17 (north star of M-3), MS-01 + MS-02

## What & Why

The user should see the drawn week as a draft before the AI scaling runs, swap a recipe in any meal, and only then approve it. This is the north star of milestone M-3: every later slice (empty meals, a dinner on two days, dinner → breakfast) is another edit of this same draft.

## Starting Point

Today "Wygeneruj" creates a `pending` plan and queues `GenerateMealPlanJob`, which draws 7 distinct recipes per category and then AI-scales them in one go. "Regeneruj" redraws and re-scales the whole plan. Nothing lets the user see or change the week before quantities and the shopping list exist.

## Desired End State

- **Generate.** "Wygeneruj" lands on a **Szkic** with 21 recipes and no quantities.
- **Edit the draft.**
  - Each cell has "Zmień", which opens a searchable list of the user's recipes in that category. The pick is saved at once and survives a reload.
  - "Losuj ponownie" redraws the whole week.
- **Approve.** "Zatwierdź" runs the existing AI scaling and ends on today's done view: quantities, the shopping list and the PDF.
- **Back to draft.** A done or failed plan offers "Wróć do szkicu". The recipes stay, and the next approval re-scales everything.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Where swapping is allowed | Only in a draft; a done plan goes back to the draft with its edits kept, and the next approval re-scales the whole plan | One editing state keeps the lifecycle simple and every approval consistent | Roadmap |
| Swap candidates | Same category only, excluding deleted recipes | Matches the draw (7 per category) and keeps the kcal-based scaling factor meaningful | Plan |
| Recipe already in the week | Selectable, labelled "jest już: wt"; the two meals are scaled and shopped separately | With the minimum 7 recipes per category, excluding them would leave nothing to swap; the linked "cooked once" repeat is S-19 | Plan |
| "Regeneruj" | Removed; redraw exists only in the draft as "Losuj ponownie", and done/error plans get "Wróć do szkicu" | One redraw path and no route that skips the preview | Plan |
| Rate limit | 5/h moves to "Zatwierdź" (same limiter key); creating a draft and redrawing get `throttle:30,1,meal-plan-draw` (own bucket) | The limit guards the AI cost, which now happens only at approval | Plan |
| Swap persistence | Saved immediately per swap (`PATCH meal-plans/{plan}/meals/{meal}`) | The draft survives reloads and going back to the draft; approval needs no payload | Plan |
| Swap UI | A "Zmień" button in each cell opens a Dialog with a combobox list | Reuses `ui/dialog` + `ui/combobox`; one picker, and it works on mobile where the grid scrolls | Plan |
| Where the draw runs | Synchronously in the request (DB only); the job only scales | Drawing is cheap, and the AI still never runs on a request path | Plan |

## Scope

**In scope:**
- `draft` status (migration)
- draft creation, redraw, approve and back-to-draft transitions
- the swap endpoint with `MealPolicy`
- the draft UI with `RecipeSwapDialog`
- status lists, kitchen-sink demos, E2E flow, a seeded draft, and new/updated visual baselines

**Out of scope:**
- empty meals and the end of the 7-per-category requirement (S-18)
- linked repeats (S-19/S-20)
- cross-category swaps
- swapping outside the draft
- partial re-scaling
- a PHP status enum
- PDF and shopping-list changes

## Architecture / Approach

`store` and the redraw call `MealPlanGenerator` inside the request. Approval flips `draft → pending` with the existing race-safe conditional update and dispatches the existing job, now scaling-only (`MealIngredientScaler` already re-scales a whole plan idempotently). Back to draft flips `done|error → draft`, deleting `meal_ingredients` and `generation_meta`. A swap is a conditional update of one meal, guarded by the plan still being a draft.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Draft lifecycle (backend) | Migration, draft creation, redraw, approve (5/h), back to draft, scaling-only job, tests | Many existing controller/job tests assume "generate = job" and must be rewritten, not patched |
| 2. Recipe swap endpoint | `MealPlanMealController` + `MealPolicy` + `UpdateMealRequest`, `recipeOptions` prop | A swap racing an approval; handled by the conditional update |
| 3. Draft UI | Draft state on Show, grid "Zmień", `RecipeSwapDialog`, "Wróć do szkicu", statuses, kitchen sink | Combobox inside a dialog on mobile; one control per state (strict-mode E2E selectors) |
| 4. E2E and visual gate | New E2E flow, seeded draft, draft and dialog screenshots, deliberate baseline updates | Baselines changing beyond the expected set |

**Prerequisites:** a worktree Sail stack set up as in the plan's Environment section; `AI_SCALING_DRIVER=fake`.
**Estimated effort:** ~3–4 agent sessions across 4 phases.

## Open Risks & Assumptions

- The UI and E2E stay inconsistent between Phases 1 and 4 (generation lands in a draft before the approve button exists). Merge only after Phase 4.
- Deploy with an empty queue: a job queued by the old code would now scale an empty plan and mark it done.
- The E2E shopping-list totals assume the seeder's identical pinned ingredients on all recipes, so a swap does not change them. This is re-checked in Phase 4.

## Success Criteria (Summary)

- A user can generate a draft, swap any meal to another recipe of that category (including one already in the week), reload without losing it, and approve to get quantities, the shopping list and the PDF.
- A done plan can go back to the draft with its recipes intact and be approved again.
- Pest, Larastan, the build, `lint:tokens`, E2E and the visual gate are green, and only the expected baselines changed.
