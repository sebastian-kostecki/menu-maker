# Spiżarnia Meal Plans UI — Plan Brief

> Full plan: `context/changes/spizarnia-meal-plans-ui/plan.md`

## What & Why

Roadmap S-25 (M-4, SP-06) restyles the core of the product, the meal-plans list and the meal-plan page with its dialogs, to the Spiżarnia direction from artifact screens 10–12 and 15. Colour comes from the meals (honey, tomato, indigo as full blocks), the brand recedes to ink, and the UI reads as stickers: a 2px outline, large radii and a hard shadow. Behaviour, data and the functional E2E stay exactly as they are.

## Starting Point

The tokens, primitives and top-bar shell are already Spiżarnia (F-05, F-06, S-22), but these views are still Zielnik layouts. The list is a sortable table with a `Select` status filter and „Na stronie”. The week grid is a bordered table with a 4px category stripe per cell and `hover:text-primary` links, which no longer show any cue now that `primary` is ink.

## Desired End State

The list shows full-width week tiles. Each tile has a date link, a status pill, a 7-square fill bar with an „n/21” label, and „PDF” / „Otwórz” / „…”. Above the tiles are status pills and a „Najnowsze” sort pill.

The meal-plan page has the week as its `<h1>` with a „Jadłospisy” breadcrumb. The days × meals grid has cells filled in each meal's colour, with tags such as „Z wczoraj”. A draft shows „Zmień” pills in the cells and the empty meal as a dashed frame with „Wybierz”. The shopping list tile sits beside the grid, and a draft shows a dashed placeholder in its place. The swap dialog shows the current recipe on the slot's colour.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| List layout | Tiles plus status pills and a sort pill; „Na stronie” removed | Matches ekran 10 1:1 with no backend change; `perPage` still works from the URL | Plan |
| Sort options | Najnowsze/Najstarsze (`created_at`), Tydzień ↓/↑ (`start_date`) | Covers the useful orders; sort by status leaves the UI but stays valid in URLs | Plan |
| Pill counts | None | Ekran 10 draws none and the backend has no per-status counts | Research (mockup) |
| Fill bar | `floor(meals_count/3)` of 7 squares filled, label `meals_count/21` | Uses the existing integer; the label carries the exact info, and the squares are decorative `aria-hidden` | Plan |
| Show `<h1>` | Long date range plus a „Jadłospisy” breadcrumb; bottom back button removed; `<title>` unchanged | Matches the mockup and §5.2; the h1 says which week it is | Plan |
| Mobile grid | Stays a `<table>`, scrolls sideways in its tile with a sticky day column | Keeps table semantics and `meal-plan.spec.ts` untouched | Plan |
| Empty slot | Dashed `muted-foreground` frame plus a „Wybierz” pill, not a whole-cell dashed button | Matches ekran 12 and „Zmień”; the mockup wins over the F-06 carry-over | Plan |
| Linked-meal tags | New `Badge variant="tag"` (`card` pill, ink text) above the name | Reads on all three meal fills, while `muted` and `outline` don't match the mockup | Research |
| Kept despite mockup | „Składniki” toggle and the „(cały przepis)” kcal note | Behaviour and copy stay; only the plural is fixed | Research (prior plan) |
| Kitchen sink bug | Fix swapped `supper`/`dinner` chip labels | Labels contradicted `lib/mealCategories.js` | Research |

## Scope

**In scope:**
- `MealPlans/Index`, with new `MealPlanTile`, `StatusFilterPills` and `SortMenu` components, the deletion of `MealPlanTable`, `MealPlanRow`, `FiltersBar`, `StatusFilter` and `PerPageSelect`, and the `ActionDropdown` / `PdfDownloadMenu` triggers
- `MealPlans/Show`, `MealPlanGrid`, `ShoppingList` (with a `pending` placeholder) and `RecipeSwapDialog` options
- The `Badge tag` variant, kitchen-sink demos, `design-system.md` §5 and §7, and the two visual specs and their baselines

**Out of scope:**
- Backend changes of any kind: no counts, no per-day data, no props
- Sort by status in the UI
- Any change to the swap, approve or PDF flows
- A CSS-stacked mobile grid
- Dashboard, recipes, family and profile, PDF, dark mode, `ui/` regeneration
- `CLAUDE.md` wording

## Architecture / Approach

The change is presentational only, on top of the existing Inertia props and URL contract. List pieces are new feature components in `Components/MealPlans/`. The grid keeps its `<table>` skeleton and only changes the cell blocks, so every selector in the functional E2E still resolves. The only primitive touched is `Badge`, which gets the `tag` variant. Each phase owns one visual spec, so its baselines are refreshed exactly once. The kitchen-sink shots are refreshed in both phases and reviewed for anti-aliasing knock-on noise.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Jadłospisy jako kafle | Ekran 10: tiles, status pills, sort pill, empty states, kitchen sink, list spec and 4 baselines | The toggle group must never show zero pressed pills, and the filter URL semantics must match the old `Select` exactly |
| 2. Siatka i strona jadłospisu | Ekrany 11–12 and the swap dialog from 15: filled grid, tags, pills, dashed empty slot, date h1, shopping tile and placeholder, show spec and 5 baselines, docs | Breaking a selector the functional E2E relies on (`tbody td a`, column order, `span.font-medium`), or focus rings that disappear on the meal fills |

**Prerequisites:** S-22 merged into `develop` (done). Sail up with the `queue` service and Mailpit, `.env` with `AI_SCALING_DRIVER=fake`, and `E2eMealPlanSeeder` re-run before each E2E/visual run.
**Estimated effort:** about 2 sessions, one per phase.

## Open Risks & Assumptions

- S-23, S-24 and S-26 run in parallel and also edit `Pages/Dev/DesignSystem.vue`. Merge conflicts are expected there, and the kitchen-sink baseline needs one final refresh after all four merge (§10.6).
- Tile column widths at 1280 px are approximated with spacing-scale widths, because arbitrary values are banned. Small deviations from the mockup's `1fr 150 230 220` are accepted.
- `Button outline xs` (28px, sticker shadow) stands in for the mockup's 26px „Zmień” pill. No new button size is introduced.

## Success Criteria (Summary)

- Both views match screens 10–12 and the swap dialog matches 15 at 1280 px, and both stay usable at 1024 and 390 px with a visible focus ring on every fill.
- `tests/e2e/meal-plan.spec.ts` passes untouched, the Pest suite passes unchanged, and `lint:tokens` is clean.
- The visual gate is green, with exactly the listed baselines refreshed and reviewed.
