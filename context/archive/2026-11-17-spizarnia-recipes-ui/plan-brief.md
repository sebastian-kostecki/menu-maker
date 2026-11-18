# Spiżarnia Recipes UI — Plan Brief

> Full plan: `context/changes/spizarnia-recipes-ui/plan.md`

## What & Why

Roadmap S-24 (M-4, SP-05) restyles the three recipe screens to the Spiżarnia direction, following artifact screens 07–09:
- the list becomes meal-coloured tiles with category pills that carry counts;
- the recipe page becomes a block in the category colour with ingredient chips and numbered steps;
- the form becomes three section tiles with the category as coloured radio tiles.

The aim is quality. The recipe views are the last Zielnik-era layouts left in the Spiżarnia shell.

## Starting Point

- **List:** a table with a category `Select`, sortable headers and filters restored from localStorage, which can disagree with the server list.
- **Recipe page:** a `PageHeader` plus bordered cards.
- **Form:** one bordered card with a category `Select`.
- **Backend:** returns no per-category counts and applies any `category` string unvalidated.
- **Reusable pieces:** S-25 left status filter pills and a sort pill in `Components/MealPlans/`, with no counts and hardcoded options.

## Desired End State

- **`/recipes`:**
  - tiles 4 per row on desktop and 12 per page;
  - a pill search, pills „Wszystkie 21 · Śniadanie 7 · Obiad 7 · Kolacja 7” whose counts follow the search, and a sort pill;
  - every state readable from the URL.
- **Recipe page:**
  - a breadcrumb row with „Edytuj” and „Usuń” pills;
  - the title as the only `<h1>` inside the category block, with kcal, servings and kcal-per-serving pills;
  - ingredient chips, and steps numbered on indigo circles;
  - an imported recipe's „Źródło: …” shown as a note under the steps.
- **Form:** three tiles, coloured category radio tiles and a dashed „Dodaj składnik”.
- **Meal plans:** unchanged, now on the shared pills.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Pill counts | Faceted: search applied, selected category ignored | Each number equals what a click on that pill would show |
| Sort options | 6 options in a `SortMenu` pill: Najnowsze, Najstarsze, Nazwa A–Z / Z–A, Najmniej / Najwięcej kcal | Keeps every useful table sort; category sort is redundant next to the pills |
| Pressed meal pill | Turns ink (existing `Toggle`) | No primitive change; already documented and shown in the kitchen sink |
| Page size | 12 per page (clamped 5–100) | Full rows at 4, 3 and 2 columns, as in screen 07 |
| List state | URL only; localStorage sync removed | One source of truth, as in meal plans; no pill/list mismatch |
| „Źródło” line | Last `Źródło:` line becomes a note under the steps (a link only for http(s)) | Source stays visible without becoming a fake step |
| Delivery | One change, 4 phases | One roadmap item and one merge; each phase isolates its baselines |
| Show `<h1>` | Inside the category block, no `PageHeader`; §5.2 records the exception | Matches screen 08 and keeps one h1 per page |
| Shared pills | `StatusFilterPills` → `Common/FilterPills` (tone, count, label); `SortMenu` → `Common/SortMenu` (options prop) | Second use, as the roadmap's S-25 carry-over suggested |
| Category radio | Native radios styled as tiles, no new primitive | Arrow keys, roles and form semantics for free |
| Unknown `?category=` | Ignored (lists everything, „Wszystkie” pressed) | Same lenient handling as meal plans (S-25 F5) |

## Scope

**In scope:**
- `RecipeController@index` counts, category handling and page size, with feature tests.
- The move to `Common/FilterPills` and `Common/SortMenu`.
- The meal tone and icon map in `lib/mealCategories.js`.
- The Index, Show and Form restyle.
- `lib/recipeInstructions.js`.
- The E2E retargets, the 7 recipes baselines, kitchen-sink demos and design-system.md §5.2/§5.3.

**Out of scope:**
- `PageHeader` typography.
- Refactoring other duplicated meal-tone maps.
- Importer or data changes.
- New `ui/` primitives or variants.
- A per-page selector, and sorting by category in the UI.
- The final cross-branch kitchen-sink baseline refresh, which happens once after S-23, S-24 and S-26 merge.

## Architecture / Approach

The backend adds a single grouped `COUNT` on the owner's recipes, with the search predicate, and returns it as a `categoryCounts` prop. The frontend copies the meal-plans model:
- page state is computed from `props.filters`;
- `router.get(…, { replace: true })` writes changes back;
- the search keeps a local input value with a 300 ms debounce.

Recipe-specific components (`RecipeTile`, `RecipeHero`, `CategoryRadioTiles`) live in `Components/recipes/` and compose the existing `ui/` variants (`Card`, `Toggle` tones, `Badge tag/status`, `Button outline/secondary/dashed/destructive`) and the `Common/` components.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Shared pills and category counts | `Common/FilterPills` and `SortMenu`; faceted `categoryCounts`, lenient category, `per_page` clamp, Pest tests | The move must leave the meal-plans and design-system baselines byte-identical |
| 2. Recipes list as tiles | Screen 07: tiles, pill toolbar, URL-only state, 12 per page; table and localStorage removed | Search focus with `preserveState`; `recipe-search.spec.ts` retarget |
| 3. Recipe page | Screen 08: category block with h1, chips, numbered steps and the „Źródło” note | The §5.2 exception; E2E exact-text matches on ingredient chips |
| 4. Recipe form | Screen 09: section tiles, radio tiles, dashed add, large footer | `recipe-form.spec.ts` radio retarget; error wiring on native radios |

**Prerequisites:** S-22 done (it is). This worktree needs its own Sail stack (`COMPOSE_PROJECT_NAME`, `APP_PORT`, `VITE_PORT`, `FORWARD_*` ports), with `VISUAL_DOCKER_NETWORK` and `MAILPIT_URL` set for every gate run (`context/foundation/lessons.md`).
**Estimated effort:** about 3–4 implementation sessions across 4 phases, mostly frontend.

## Open Risks & Assumptions

- S-23, S-24 and S-26 run in parallel and all edit `Pages/Dev/DesignSystem.vue`. S-24 merges last, so it resolves the kitchen-sink conflicts and does the single final `design-system.spec.ts` refresh.
- Kitchen-sink height changes cause anti-aliasing noise in the shots below the touched sections. Review those PNGs instead of expecting them to be identical.
- The mockups are desktop only; the 390px layouts are designed by this change.
- The importer's `"\n\nŹródło: …"` format is assumed stable. A different trailing format would render as a normal step.

## Success Criteria (Summary)

- The three recipe screens match artifact screens 07–09 at 1280px and stay usable at 390px.
- Category pill counts always equal what clicking them shows, and the page state survives a reload from the URL alone.
- All gates are green (Pest, Larastan, Pint, build, `lint:tokens`, E2E, visual). Only the recipes baselines and the named kitchen-sink shots changed.
