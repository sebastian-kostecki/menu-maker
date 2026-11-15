# Shared App Components — Plan Brief

> Full plan: `context/changes/shared-app-components/plan.md`

## What & Why

S-07 in roadmap M-2 (DS-02) creates `Components/Common/` with three shared components: `ConfirmDialog`, `ServerPagination` and `SortableHeader`. It replaces the eight duplicates that make the same pattern behave differently in each view. It also moves `CategoryBadge`, `StatusTag` and the recipe list's inline badge onto `Badge` variants, so categories and statuses use the time-of-day and state colours (§4.3–4.4). Every later view slice builds on these components instead of copying them again.

## Starting Point

- **Dialogs.** There are four confirmation dialogs:
  - one hand-rolled with no Esc and no focus trap;
  - two in English;
  - one that sends its own DELETE;
  - one whose callers close it before the request starts.
- **Pagination and sorting.** The two pagination and two sort-header copies differ in their API. Their labels are in English, nothing sets `aria-sort` or `aria-current`, and in the recipe header Enter fires twice.
- **Meal-plan pagination data.** Meal-plan pagination never renders. `MealPlanCollection` and Laravel's paginated response both add `meta`/`links`, so the values are doubled (`last_page: [n, n]`).

## Desired End State

- **One dialog.** Deleting a recipe, family member or meal plan, or regenerating a plan, opens the same Polish `alertdialog`:
  - focus starts on „Anuluj”;
  - Esc cancels;
  - a spinner shows until the server answers.
- **One pagination and one sort header.** Both lists share a Polish pagination that keeps filters and sort, and a sortable header with `aria-sort`.
- **Badges.** Categories and statuses show token colours with labels in the recipe list, recipe details and meal plans.
- **Gates.** The kitchen sink and visual gate cover all of it, and `lint:tokens` guards the new files.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Branch base | Worktree branch reset onto `develop` (bef3277) | F-03/F-04 exist only on `develop`; the branch had no commits of its own | Plan (user) |
| `PageHeader`, `EmptyState`, `FormField` | Deferred: `PageHeader`/`EmptyState` to S-09/S-10/S-11, `FormField` to S-13 | No component without a call site, and no restyling of headers outside their view slice | Roadmap unknown → Plan |
| Dialog while processing | Stays open, both buttons disabled, Esc blocked, closes in `onFinish`; the caller owns the request | One behaviour everywhere and no double submit, including meal plans, which used to close instantly | Plan |
| Confirm button | Plain `Button`, not `AlertDialogAction` | The action's built-in close would dismiss the dialog before `processing` is set | Research |
| Pagination contract | `meta` only, emits `page-change`; the parent builds the query; `links` dropped from §5.2 | Both pages already own their filters, and navigating from inside the component would leave Recipes' page state stale | Plan |
| Meal-plan data | `MealPlanCollection::paginationInformation()` returns `[]`, test-first | It is Laravel's documented hook, and it leaves the JSON branch untouched | Research + Plan |
| Sort header | Renders the `<th>` itself with `aria-sort`, computes the toggle, no keydown handlers | `aria-sort` belongs on the header cell, and native buttons already handle Enter/Space | Plan |
| `pending` status | New `muted` badge variant | §4.4 says `muted`; `secondary` has darker text | Design system |
| RecipeRow category badge | Replaced with `CategoryBadge` now (badge only; the rest of the row stays for S-11) | The S-07 outcome is visible in the main recipe list | Plan |
| E2E | New `confirm-dialog.spec.ts`: cancel path on a seeded recipe, real delete on a test-created family member | Covers focus/Esc without breaking the 7-per-category seed that `meal-plan.spec` needs | Plan |
| Baselines | All kitchen-sink work in the last phase: 3 new PNGs plus `section-odznaki.png` | Zero-tolerance PNGs change in a single reviewable commit | Plan |

## Scope

**In scope:**

- `Common/ConfirmDialog`, `Common/ServerPagination`, `Common/SortableHeader`.
- 5 dialog call sites, 2 lists, 2 tables; the 8 duplicates deleted.
- The `MealPlanCollection` fix plus a Pest test.
- The `badge` `muted` variant, `CategoryBadge`, `StatusTag` with a spinner, and the `RecipeRow` badge.
- `meal-plan.spec` selector, the new E2E spec, and the seeder reset of the cook's family members.
- A 12th kitchen-sink section, 22 baselines, `lint-tokens.paths`.
- `design-system.md` §5/§7/§8, the roadmap and `CLAUDE.md`.

**Out of scope:**

- `PageHeader`/`EmptyState`/`FormField` and `AppShell`.
- Restyling call-site files: their literals and English column labels stay for S-09…S-12.
- The double empty message on `/recipes`.
- The rest of the `MealPlans/Index` filter cleanup (S-10). Only the two `filter.status` reads are fixed here, because pagination has to keep the status.
- Real recipe or meal-plan deletes in E2E.
- New dependencies or JS unit tests.

## Architecture / Approach

The shared components are presentational: props in, events out, tokens and `ui/` primitives only, so they join `lint:tokens` right away. Callers keep the request and URL state:

- the pages build the paginated query;
- the tables map `{ column, direction }` to their filters;
- dialog callers set `processing` around `router.*` and close in `onFinish`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Meal-plan pagination data | Scalar `meta`/`links`, proven by a test written first | Low: one hook override, covered by the new test |
| 2. ServerPagination + SortableHeader | Shared list components; both lists migrated; 4 duplicates gone | Losing filters on page change; checked manually on both lists |
| 3. ConfirmDialog | Shared dialog with `processing`; 5 call sites; 4 dialogs gone; E2E | Focus and close behaviour (reka auto-close); pinned by `confirm-dialog.spec` |
| 4. Category and status badges | Variants only, `muted` added, RecipeRow badge | Colour or contrast surprises; manual pass on 4 views |
| 5. Kitchen sink, gates and docs | 12th section, 22 baselines, docs aligned | Unintended PNG drift; only 4 PNGs may change |

**Prerequisites:**

- Sail running (`vendor/bin/sail up -d`, with the queue and Mailpit).
- `.env` set as described for E2E in `CLAUDE.md` (`AI_SCALING_DRIVER=fake`).
- Docker able to pull the Playwright image for `npm run test:visual`.
- `npm run dev` stopped before visual runs.

**Estimated effort:** about 1–2 sessions across 5 phases. Phase 3 is the largest.

## Open Risks & Assumptions

- **Assumption: overriding attributes on reka items.** Passing `aria-label="Strona N"` to a `PaginationItem` overrides reka's English `Page N`, because Vue merges fallthrough attributes last. Phase 2's manual check confirms it in the DOM.
- **Assumption: the family-member form.** The form redirects to `/family-members` after creating a member, which the new E2E spec relies on.
- **Mixed styling until S-09…S-12.** Call-site pages keep their old literals around the new components, so mixed styling remains on those pages until then. This is intended, per the slice boundaries.

## Success Criteria (Summary)

- One Polish confirmation dialog with the same focus, Esc and progress behaviour everywhere, verified by E2E.
- Recipe and meal-plan lists paginate and sort through shared, accessible components. Meal-plan pagination works for the first time.
- Category and status badges use token variants only. `lint:tokens`, the visual gate (22 PNGs), Pest and E2E are green.
