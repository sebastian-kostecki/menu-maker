# Recipes UI (S-11 / DS-06) — Plan Brief

> Full plan: `context/changes/recipes-ui/plan.md`

## What & Why

Move the recipe list, details and create/edit form onto the design-system contract — tokens instead of Tailwind palette literals, shared components, lucide icons, Polish copy and pl-PL formatting — so recipes match the green app shell and CI keeps them literal-free. It also builds the deferred shared `PageHeader` and `EmptyState` that the parallel slices S-09 and S-10 will consume.

## Starting Point

On `develop` the recipe views hold 118 literal hits and 8 hand-written SVGs, mix English and Polish copy, format dates `en-US` and calories as "350.00 cal". The form renders outside the app shell, Show has a broken heading hierarchy, and `/recipes` shows two contradicting empty messages. Tokens, `Common/ConfirmDialog|ServerPagination|SortableHeader`, `lint:tokens` and the Docker-pinned visual gate already exist (S-07, S-08, F-03, F-04).

## Desired End State

All four recipe pages render inside the shell with one `PageHeader` `<h1>`, token-only styling and Polish copy. `/recipes` shows exactly one empty state: "Brak przepisów" + "Dodaj przepis" for a user without recipes, or "Brak wyników" + "Wyczyść filtry" when filters match nothing. Both recipe directories are scanned by `lint:tokens`, and seven zero-tolerance screenshots of real pages (seeded deterministically) gate future regressions.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Branch base | Rebased onto `develop` before planning | The old `main` base lacked every prerequisite this change uses. |
| Scope split | One change, one phase per view | Shared pieces land once and serve all three views; one review. |
| PageHeader/EmptyState ownership | Built here in Phase 1; S-09/S-10 consume | Recipes needs both on every view; one owner with a written contract. |
| PageHeader placement | In `AuthenticatedLayout`'s `#header` slot | Matches Dashboard/MealPlans and leaves the shell (and its baselines) untouched. |
| Screenshot strategy | Deterministic visual-user recipes in the seeder + `recipes.spec.ts` | Real pages gated pixel-exact without masks hiding the data. |
| FormField | Not built; inline token error markup | Roadmap assigns it to S-13; avoids a third colliding shared component. |
| Copy & formatting | Polish copy + `resources/js/lib/format.js` (pl-PL numbers, dates, plurals) | Meets §1/§4.5; pl-PL keeps E2E "200 g" and leaves 1000 ungrouped. |
| Form layout | Inside the app shell, `max-w-2xl` | Every page keeps navigation; E2E heading still matches. |
| Empty states | Two variants (no recipes / no results) | Correct message per case; one empty state instead of two. |
| Small calls | Drop Show's Back button; delete `RecipeHeader` and dead `RowActionDropdown` | Breadcrumb covers "back"; h1 moved to PageHeader; nobody renders the dropdown. |

## Scope

**In scope:**
- `Pages/Recipes/{Index,Show,Form}.vue` and the components under `Components/recipes/` they use
- New `Common/PageHeader`, `Common/EmptyState`, `lib/format.js` + kitchen-sink demos
- `E2eMealPlanSeeder` fixed recipes for the visual user; `tests/e2e/visual/recipes.spec.ts`
- `scripts/lint-tokens.paths`, `design-system.md` §4.5/§5.2/§7.3, CLAUDE.md reuse rule, roadmap S-09/S-10/S-11 notes

**Out of scope:**
- `FormField` (S-13), `ui/*`, `AuthenticatedLayout`, existing `Common/*` components
- Backend, sorting/filtering/pagination behaviour, `useDeleteRecipe`
- Formatter adoption in MealPlans/FamilyMembers; dark mode; replacing the ingredient `<datalist>`

## Architecture / Approach

Tokens → `ui/` primitives → `Common/` (new `PageHeader`, `EmptyState`) → `Components/recipes/` → `Pages/Recipes/`. Each page puts its `PageHeader` into the layout's `#header` slot and renders its body at `max-w-6xl` (list/details) or `max-w-2xl` (form). All existing E2E selectors (labels, "Zapisz przepis", the `Usuń …` row buttons, the `Składnik` combobox, "200 g") are invariants of the contracts.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Shared pieces | `PageHeader`, `EmptyState`, `lib/format.js`, kitchen-sink demos, docs/rule/roadmap ownership | Kitchen-sink demos shift overlay baselines beyond the expected section PNG |
| 2. Recipe list | `/recipes` on tokens, links, Polish copy, two empty variants, dead dropdown deleted | Breaking the `/^Usuń /` row-button count in `confirm-dialog.spec.ts` |
| 3. Recipe details | `/recipes/{id}` with PageHeader, badges, ingredients, instructions | Sticky header crowding at 390px; "200 g" exact-text match |
| 4. Recipe form | Form inside the shell, token errors, lint paths as directories | Accidental label/role changes breaking `recipe-form.spec.ts` |
| 5. Visual gate & close-out | Deterministic seed, 7 real-page PNGs, 7-state matrix, final docs | Seeder conflicts with S-09 if it also edits the visual user |

**Prerequisites:** S-07 done (yes); this worktree's own Sail stack per the plan's Environment section (`.env` ports, `vendor/`, `VISUAL_DOCKER_NETWORK=recipes-ui_sail`).
**Estimated effort:** ~3–4 sessions across 5 phases; ~1.5–2k changed lines, frontend-only plus one seeder.

## Open Risks & Assumptions

- S-09/S-10 sessions are planning now; until this branch merges they don't see the ownership note — tell them to consume `PageHeader`/`EmptyState` from `recipes-ui` (or wait).
- The sticky header may wrap to 2–3 lines on Show at 390px; accepted with the placement decision.
- `family-members-ui` and `auth-profile-ui` worktrees are also based on `main` and need the same rebase.

## Success Criteria (Summary)

- A user browses, opens, creates and edits recipes in Polish, inside the shell, with brand-consistent styling at desktop and 390px.
- `/recipes` never shows two empty messages, and a fruitless search says "Brak wyników", not "add your first recipe".
- `npm run lint:tokens`, the visual gate (twice), Pest and the full E2E suite are green.
