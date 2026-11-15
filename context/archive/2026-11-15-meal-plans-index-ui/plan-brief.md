# Meal Plans Index UI — Plan Brief

> Full plan: `context/changes/meal-plans-index-ui/plan.md`

## What & Why

The meal plans list (`/meal-plans`) is the last part of the meal-plan flow still in grey palette literals, dead `dark:` classes and half-English copy. Roadmap slice S-10 (DS-05) moves it onto the M-2 design tokens and shared components. It also fixes a real defect carried over from S-07: saved filters in localStorage put a status into the select without reloading the list, so the select and the rows disagree.

## Starting Point

`Pages/MealPlans/Index.vue` and seven `Components/MealPlans/*` files render the header inside the layout's sticky top bar, a grey filter box with English labels, a table with arbitrary widths, `en-GB` dates and an internal "Logs" column, and an English empty row. Tokens, `Badge`/`Alert` variants, `ConfirmDialog`, `ServerPagination`, `SortableHeader` and `StatusTag` already exist (F-03, S-07). `PageHeader` and `EmptyState` were deferred to the first view that needs them, and this is that view.

## Desired End State

- **Header:** `PageHeader` with a correct Polish count ("22 jadłospisy") and the "Wygeneruj jadłospis" button.
- **Toolbar:** "Status" and "Na stronie" Selects whose values always match the URL and the list.
- **Table:** a bordered table with Tydzień (a link to the plan), Status, Posiłki, Utworzono and Akcje, with Polish dates.
- **Empty states:** one for first use ("Wygeneruj pierwszy jadłospis") and one for a filter with no results ("Wyczyść filtry").
- **Generate dialog:** on tokens, sending the identical request.
- **Gates:** every file is locked by `lint:tokens`, and a seeded visual spec guards the page.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Header placement | `Common/PageHeader` in the page body; layout fallback "Menu Maker" becomes a non-heading | §5.2 says PageHeader replaces the `#header` slot, and the sticky bar is too cramped at 390px. | Plan |
| Filter persistence | URL is the only source; localStorage save and restore removed | It removes the select/list mismatch and the reload-loop risk S-07 rejected, and makes links shareable. | Plan |
| Columns | Drop Logs; the week is a link to the plan | `logs_count` counts internal generation attempts, and the link gives a direct path besides the ⋯ menu. | Plan |
| Empty states | First run: CTA "Wygeneruj pierwszy jadłospis". Filtered: "Wyczyść filtry". | Helpful in both cases, and the CTA text keeps `meal-plan.spec.ts`'s non-exact selector unique. | Plan |
| Filter UI | Token toolbar with two labelled Selects; duplicate counter dropped | Same controls and query params, and no new primitive. | Plan |
| Visual gate | Fixed fixture plans for the visual user + `meal-plans-index.spec.ts` (4 shots) | It gates rows, badges, toolbar, empty state and dialog on real data. | Plan |
| Destructive menu item | Port new-york-v4 `variant="destructive"` to `DropdownMenuItem` | §3 rule 3: a variant instead of a call-site colour override. | Plan |
| Formatting | New `resources/js/lib/format.js`, explicit `pl-PL`, `Intl.PluralRules` | §4.5 asks for one helper, and Playwright runs `en-US`. | Plan |
| PageHeader / EmptyState ownership | Built here, in `Components/Common/` | Roadmap: the first of S-09/S-10/S-11 to need them builds them, and no other worktree has started them. | Roadmap |

## Scope

**In scope:**
- `Index.vue` and its components: `FiltersBar`, `StatusFilter`, `PerPageSelect`, `MealPlanTable`, `MealPlanRow`, `ActionDropdown`, `GenerateMealPlanDialog`.
- `Common/PageHeader` and `Common/EmptyState`, plus their kitchen-sink demos.
- The `DropdownMenuItem` variant.
- The layout fallback title.
- `lib/format.js`.
- Visual-user fixture plans.
- The new visual spec and its baselines.
- `lint-tokens.paths`.
- Updates to `design-system.md` §5.1, §5.2 and §7.3.

**Out of scope:**
- Any backend change (Form Request, status allowlist, enum).
- The restyle of `MealPlans/Show` (S-09).
- The plural bug in `Recipes/Index` (S-11).
- Polling processing rows.
- Toggle-group filters.
- Cleaning the stale localStorage key.
- Dark mode.

## Architecture / Approach

The work goes contract first, then pixels, then gate:
1. Phase 1 builds the reusable pieces and demos them in the kitchen sink.
2. Phases 2 and 3 compose them into the page. Filter state becomes a computed value over `props.filters`, and every change is a `router.get` with the full current query (no `page`).
3. Phase 4 seeds deterministic data for `e2e-planner-visual` only, so the chromium and firefox planners still start empty, as the E2E spec needs. It then updates all baselines in one reviewed step.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Shared building blocks | `format.js`, PageHeader, EmptyState, destructive menu variant, non-h1 fallback, kitchen-sink demos | `Y-m-d` parsed as UTC shifts dates; must parse as local |
| 2. Page, toolbar and URL-driven filters | Page on PageHeader, URL-only filters, token toolbar, two empty states | Query params must stay exactly as today; raw string echoes need normalising |
| 3. Table, actions and generate dialog | Polish columns, week link, destructive "Usuń", dialog on tokens | Shared with `Show` and `Dashboard`; the generate request must not change |
| 4. Fixtures, visual gate and docs | Seeded visual list, 4 new PNGs, deliberate baseline updates, full E2E | Unpinned timestamps or extra changed PNGs make baselines drift |

**Prerequisites:** S-07 done (it is). The worktree needs its own Sail stack (`.env` with unique ports, `vendor/`, `npm ci`, migrate). See the plan's Environment section.
**Estimated effort:** ~2–3 sessions across 4 phases.

## Open Risks & Assumptions

- **Overlap with S-09/S-11:** both may also want `PageHeader`/`EmptyState`. Whoever merges second rebases onto this contract.
- **Kitchen-sink baselines:** `overlay-dialog.png`/`overlay-alert-dialog.png` may shift at the corners, as in S-07 and S-08. Any other unexpected PNG change is a regression.
- **Behaviour change:** a bare `/meal-plans` visit no longer remembers the last filter. This is intended and supersedes the localStorage line in `.ai/ui/meal-plans-view-implementation-plan.md`.

## Success Criteria (Summary)

- A user sees the list in the brand look, in Polish. The status and page-size controls always match the rows after any reload or shared link.
- A new user lands on a helpful empty state that generates their first week. A filter with no results offers a one-click reset.
- CI stays green: `lint:tokens`, Pest, E2E unchanged, and a visual gate with 4 new screenshots of this page.
