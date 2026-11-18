# Drag-and-drop meals in the draft, any recipe in any slot — Plan Brief

> Full plan: `context/changes/meals-draft/plan.md`

## What & Why

In a draft meal plan the user wants to rearrange the week with the mouse — drag a meal onto another cell — and to put any recipe in any time of day (a breakfast for Kolacja, a dinner for Śniadanie). Today the only way to change a meal is the „Zmień” dialog, and it only offers recipes of the slot's own category.

## Starting Point

The draft grid (`MealPlanGrid.vue`) is a 7 × 3 table with no drag code. Each meal row is a fixed day × time-of-day slot; edits go one meal at a time through `PATCH meal-plans/{plan}/meals/{meal}`, whose validation (`UpdateMealRequest`) deliberately requires the recipe's category to match the slot. Links (Obiad on 2 days, Kolacja → Śniadanie) live in `feeds_meal_id` and are handled by `changeRecipe()`.

## Desired End State

On a draft with a mouse, a filled cell can be dragged onto any other cell: a filled target exchanges the two recipes, an empty target receives the recipe and the source becomes „Bez posiłku”. The „Zmień” dialog lists every own recipe, the slot's category first. A recipe outside its column's category carries a small category tag. Everything persists after refresh and scales on approval as before.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Drop semantics | Swap with a filled cell, move into an empty one | Nothing is lost, and rows keep their slot so two meals can never share a cell. |
| Links on a drop | Reuse `changeRecipe()` rules on both meals | Same rule as the dialog and PRD: moving a cooked meal empties its carried meal (with a toast). |
| Kcal for cross-category meals | Share of the slot (30/40/30) | The family still eats the day's biggest meal at Obiad; no scaler change, daily total stays 100%. |
| „Zmień” dialog | All categories, slot's own first, grouped | Keyboard and touch users get the same freedom as drag; one validation rule for both paths. |
| DnD technology | Native HTML5 DnD | No new dependency; 21 fixed cells need no sorting engine; Playwright `dragTo` supports it. |
| Touch / keyboard | Drag only for fine pointers; dialog elsewhere | Avoids fighting the mobile sideways scroll; result stays reachable without dragging. |
| Cross-category look | Column colour + `Badge tag` with the recipe's category | Columns stay readable, colour is not the only signal, matches the PDF; hidden on carried meals. |
| Tests and docs | Full gate + PRD update | Feature/unit + Playwright drag + deliberate baselines + kitchen sink + PRD FR 5/US-014/US-018. |

## Scope

**In scope:**
- `POST meal-plans/{plan}/meals/{meal}/move` (`target_meal_id`), transactional, draft-only, link-aware
- Dropping the category rule from `UpdateMealRequest`
- Grouped all-category swap dialog
- Native DnD in the grid, category tag, toasts, kitchen-sink demo, design-system doc
- Seeder fixture, extended `meal-plan.spec.ts`, visual baselines, PRD

**Out of scope:**
- Touch/long-press or keyboard dragging, a „Przenieś…” action, any DnD library
- Changes to scaling, PDF, link pairs, `shortCategories`, DB schema
- Copy-on-drop, cross-plan drag, editing outside draft

## Architecture / Approach

`MealPlanGrid` (draggable cells when `editable` and `(pointer: fine)`) → emits `move(source, target)` → `Show.vue` posts with Inertia (`only: ['mealPlan','shortCategories']`) → `MealPlanMealController::move` locks the plan, re-checks draft, returns early on no-ops (same meal or same recipe, which covers every linked pair), then applies `changeRecipe()` to both rows with each other's recipe → partial reload re-renders the grid.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Backend | Relaxed rule, `MoveMealRequest`, `move()` + route, tests | Link side effects on swap; the no-op check must run before `changeRecipe()` |
| 2. Swap dialog | Grouped all-category list | Search/Enter behaviour across groups |
| 3. Grid DnD + tag | Native DnD, toasts, tag, kitchen sink, doc | Firefox `setData`, nested link dragging the URL |
| 4. E2E, fixtures, baselines, PRD | Drag in Playwright, cross-category visual fixture, PRD | Baseline churn; worktree must target its own Sail stack (lessons.md) |

**Prerequisites:** Sail up (`queue`, Mailpit), `.env` with `AI_SCALING_DRIVER=fake`; no new dependencies.
**Estimated effort:** ~2–3 sessions across 4 phases.

## Open Risks & Assumptions

- HTML5 DnD on desktop Firefox/Chrome via Playwright `dragTo` is assumed reliable; if flaky, the spec dispatches drag events manually.
- A light breakfast recipe at Obiad gets ~33% larger portions (slot share) — accepted.
- The visual mobile spec uses a desktop device with a small viewport (fine pointer), so grip icons will appear in the mobile baseline too.

## Success Criteria (Summary)

- A user rearranges a draft week by dragging, including breakfast ↔ dinner, and the result survives a refresh and approval.
- The „Zmień” dialog can place any own recipe in any slot.
- CI gates (Pest, Larastan, Pint, build, token lint, E2E, visual) are green with reviewed baselines.
