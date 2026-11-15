# Meal Plan Show View in the New Design — Plan Brief

> Full plan: `context/changes/meal-plan-show-ui/plan.md`

## What & Why

The meal plan view (roadmap S-09, DS-04) is the core of the product and the "north star" of the design-system rollout, and it still carries the most palette literals in the main flow. This change moves it onto tokens and primitives. It also builds the first `Common/PageHeader` and `Common/EmptyState`, which S-10 and S-11 will reuse.

## Starting Point

Today `Show.vue` has a hand-written header in the layout's sticky `#header` bar and amber, red and yellow boxes for warnings and errors. `MealPlanGrid.vue` is a grey `<table>` with a native `<details>` around the ingredients and grey `div` skeletons. `ShoppingList.vue` is a grey section stacked under the grid.

`StatusTag` is already migrated, and amounts arrive formatted in Polish from PHP (`QuantityFormatter`). No screenshot covers the page.

## Desired End State

The page opens with a `PageHeader` showing "Jadłospis", the date range and the actions. From `xl` the grid and the shopping list sit side by side. Each grid cell has a coloured category bar, and its ingredients open from a "Składniki (n)" button.

Every state renders on a shared variant:

| State | Rendering |
| --- | --- |
| Generating | Skeleton grid |
| AI warning | Warning `Alert` |
| Generation error | Destructive `Alert` |
| Plan without meals | `EmptyState` |

The view passes `lint:tokens`. The kitchen sink gets a "Jadłospis" section, and two real-page baselines (desktop and mobile) guard the composition.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| PageHeader placement | Top of the content area. The layout's fallback "Menu Maker" becomes plain text | §5.2 says PageHeader replaces the `#header` slot. It keeps one `<h1>`, and no current page renders the fallback. |
| Grid + list layout | Side by side from `xl` (list `w-72`), stacked below | Fits 640px of grid at 1280px with the expanded sidebar. At `lg` it would scroll. |
| Category bar token | `border-l-4 border-meal-*-foreground` (§5.3 updated) | The pale `meal-*` fills (L≈0.95) are almost invisible as a 4px line. |
| Empty and error states | `EmptyState` for "no meals", destructive `Alert` for errors | Fulfils the roadmap unknown. An error is a message, not an empty state. |
| Visual coverage | Kitchen-sink section plus real-page screenshots of a seeded plan | Guards the composed north-star page the way S-08 guarded the shell. |
| Grid structure | Stays a `<table>`; `<details>` becomes `Collapsible` | E2E and screen readers rely on column headers and `td a`. Only the disclosure changes meaning. |
| E2E selector | `getByRole('button', { name: /^Składniki/ })` plus `aria-expanded`, reviewed as a change of meaning | Roadmap S-09 risk: a new selector signals new semantics. |
| One "Regeneruj" per state | Header when done with meals, `Alert` on error, `EmptyState` CTA when empty | E2E's non-exact locator would hit strict mode on a duplicate. |
| Visual plan source | Seeder writes meals and scales with `FakeIngredientRefiner`, fixed names and start `2026-06-15` | Deterministic, with no queue and no AI call, whatever the driver. |

## Scope

**In scope:** `Common/PageHeader` and `Common/EmptyState`; the layout fallback brand; `MealPlanGrid`, `ShoppingList` and `Show.vue` on tokens; the E2E selector at `meal-plan.spec.ts:28`; the kitchen-sink "Jadłospis" section; `lint-tokens.paths` (3 files); the seeded visual plan with its Pest test; `meal-plan-show.spec.ts` and baselines; `design-system.md` §5.2, §5.3, §7 and CLAUDE.md.

**Out of scope:** `ActionDropdown` literals (S-10); `StatusTag` and `CategoryBadge` (done); other pages' headers; a JS number formatter; pipeline, polling and props changes; breadcrumbs content; dark mode.

## Architecture / Approach

The order is contract before pixels:
1. shared `Common` components with kitchen-sink demos;
2. the domain components that own the E2E-visible structure (table, collapsible, list), together with the one selector change;
3. the page assembled from those parts;
4. reproducible data for screenshots, plus the baselines.

Each phase ends green on build, `lint:tokens` and the affected tests, and work runs on the worktree's own Sail stack (`meal-plan-show-ui_sail`).

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Shared PageHeader and EmptyState | Two `Common` components, a non-heading layout fallback, kitchen-sink demos | A second `<h1>` on the kitchen sink; solved with the `as="h2"` prop |
| 2. Grid and shopping list on tokens | Table plus bar, `Collapsible`, skeleton, `Card` list, E2E selector, "Jadłospis" section | The selector change slips through as a "fix" instead of a reviewed change of meaning |
| 3. Show view on tokens | `PageHeader` in content, `xl` columns, Alerts, `EmptyState`, docs | A duplicate "Regeneruj" button, or horizontal scroll at 1280px |
| 4. Visual gate | Seeded visual plan, seeder test, desktop and mobile baselines, PNG-delta review | Overlay corner-pixel drift, or non-determinism in the seeded plan |

**Prerequisites:**
- S-07 and S-08 done (they are).
- The worktree has its own Sail stack: `.env` with unique ports, `vendor/` via Sail Composer, `sail up -d`, `npm ci`, `migrate`.

**Estimated effort:** about 2–3 sessions across 4 phases.

## Open Risks & Assumptions

- **Parallel S-10:** it restyles the Index. The visual spec reads the plan URL from Index props (`mealPlans.data[0].links.self`), not from the UI. S-10 also must not assume the visual user has no plans.
- **Parallel slices on shared files:** S-13 already changes the kitchen sink ("Komponenty aplikacji") and its baselines, and S-12 changes `E2eMealPlanSeeder.php`. Phase 1 claims `PageHeader`/`EmptyState` in the roadmap for S-09. Phase 4 merges develop before recording baselines, and judges the PNG delta against that merge.
- **Seeded servings:** the factory draws `servings` at random, and the grid prints them, so the seeder pins them together with the names.
- **Seeded amounts and family age:** they depend on the family's age band at `2026-06-15`, but birth dates are `now()->subYears(40)`. That stays stable for years, but a band crossing would shift the quantities in the baseline.
- **Overlay corner pixels:** adding a kitchen-sink section may change `overlay-dialog.png` and `overlay-alert-dialog.png`. The expected delta allows only those two beyond the planned ones.

## Success Criteria (Summary)

- A user sees the week grid and the shopping list side by side (from `xl`), with category colours, an expandable ingredient list per cell, and every state on shared variants.
- `npm run lint:tokens`, the E2E suite (chromium and firefox) and the visual gate (twice in a row) pass. The PNG delta is exactly 3 new files, 1 changed and at most 2 overlay corner changes.
- `PageHeader` and `EmptyState` are demoed and documented, ready for S-10 and S-11.
