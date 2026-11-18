# Spiżarnia Family and Profile UI — Plan Brief

> Full plan: `context/changes/spizarnia-family-profile-ui/plan.md`

## What & Why

Roadmap S-26 (M-4, SP-07) restyles the last two signed-in views, family members and the profile, to the Spiżarnia direction from artifact screens 13 and 14. People become sticker tiles coloured by meal tones, and the irreversible account deletion gets a clearly red tile. Behaviour, dialogs and the backend stay the same. Family members also gets the visual spec it never had.

## Starting Point

Family members is one page with a bordered table, a custom pagination and a hand-rolled empty state. Create and edit run in a dialog and delete in `ConfirmDialog`. The profile is three default `Card`s in a 672 px column. Tokens, primitives and the top-bar shell are already Spiżarnia (F-05, F-06, S-22).

## Desired End State

`/family-members` shows a grid of tiles, three per row at 1280 px. Each tile has:

- an initial avatar on tomato, indigo or honey;
- the name, gender and birth date („Kobieta · ur. 4 lip 1987”);
- three fact boxes (38 lat / 64 kg / 168 cm, with „—” when a child has no measurements);
- „Edytuj” and „Usuń” buttons.

A dashed „Dodaj członka rodziny” tile closes the grid. An empty family sees the shared `EmptyState`. `/profile` is a 720 px column of three tiles with paired fields, a confirmed „Zapisano.” and a red-outlined „Usuń konto” tile.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Avatar colour | Cycled by tile position: supper → dinner → breakfast | Neighbours always differ and it is deterministic for screenshots; a colour may move when the list changes (accepted) |
| Child without kg/cm | Always three boxes, „—” for missing; age pluralised via `lib/format.js` („1 rok”, „2 lata”, „0 lat”) | Equal tile heights and the same „—” the table shows today |
| Empty state | `Common/EmptyState` only, no dashed tile | Same pattern as recipes and meal plans, one CTA, keeps the E2E heading |
| Danger tile | New `Card variant="destructive"` (outline, shadow, title) | Views don't recolour primitives (§5.1); `shadow-destructive` recolours `shadow-sticker` with no new token |
| Dashed add tile | `Button variant="dashed"` with a new `size="tile"` (230 px, 20 px radius) | Honours the F-06 carry-over without a shape override in the view |
| Visual gate | New `family-members.spec.ts` (desktop, mobile, empty) and a new `profile-mobile`; 58 → 62 shots | Guards every new layout state, including the child tile |
| Visual data | New `e2e-family-visual` user with pinned Anna, Jan and child Zosia | The planner family drives AI scaling, so re-pinning it would shift meal-plan baselines |
| Pagination | `Common/ServerPagination` fed the raw paginator | Its `meta` keys already match, so no backend change |
| Birth dates | `formatDate` reads a bare Y-m-d as a local date | Today it parses as UTC, which shows the previous day west of UTC |

## Scope

**In scope:**
- `FamilyMembers/Index`, a new `FamilyMemberTile`, and deletion of `FamilyMemberTable`, `FamilyMemberPagination` and `pluralizePl`
- `Profile/Edit` and its three partials
- `ui/button` size `tile`, `ui/card` variant `destructive`, and `lib/format.js` (`formatDate` fix, `ageInYears`)
- Functional selectors in `family-members.spec.ts` and `confirm-dialog.spec.ts`
- Seeder user and its unit test, kitchen-sink demos, visual specs and baselines, `design-system.md` §5/§7, and the visual-gate line in `CLAUDE.md`

**Out of scope:**
- Any backend or props change, and any dialog change
- Re-pinning the planner family
- The `PageHeader` title size (shared, every page)
- Mockup copy changes
- Dashboard, recipes, meal plans, PDF and dark mode

## Architecture / Approach

The change is presentational on top of the existing paginator props. Feature components stay in `Components/FamilyMembers/`. Two primitives change (`Button` size, `Card` variant), each demoed in the kitchen sink. Each phase owns one visual spec, so its baselines are refreshed once; the kitchen-sink shots are refreshed in both phases.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Członkowie rodziny jako kafle | Ekran 13: tiles, dashed tile, EmptyState, pagination, selector updates, pinned user, 3 new baselines, docs | Functional E2E selectors move off `row`/`cell`; a wrong accessible name (e.g. the dashed tile matching `exact` „Dodaj członka”) breaks them |
| 2. Profil w trzech kaflach | Ekran 14: `Card destructive`, 720 px three-tile profile, `profile-mobile` plus a refreshed desktop baseline, docs | A second page-wide „Usuń konto” button (e.g. a title rendered as a button) breaks `profile.spec.ts` |

**Prerequisites:** S-22 merged into `develop` (done). This worktree needs its own Sail stack (`COMPOSE_PROJECT_NAME`, `APP_PORT`, `VITE_PORT`, `FORWARD_MAILPIT_DASHBOARD_PORT`), run with `VISUAL_DOCKER_NETWORK` and `MAILPIT_URL` (`lessons.md`), `AI_SCALING_DRIVER=fake`, and `E2eMealPlanSeeder` re-run before each E2E or visual run.
**Estimated effort:** about 2 sessions, one per phase.

## Open Risks & Assumptions

- S-23 and S-24 run in parallel and also edit `Pages/Dev/DesignSystem.vue`, and they use `ui/card`/`ui/button`. The roadmap merges S-26 first, and the kitchen-sink baseline gets one final refresh after all of them merge.
- The tiles keep `PageHeader`'s 24 px `<h1>`, not the mockup's 36 px. It is a known, shared deviation left for a separate change.
- In the app the first tile is Anna on tomato, whereas the mockup has Jan, because the list is sorted by first name.
- The main checkout has uncommitted roadmap edits. Commit them on `develop` before merging this branch.

## Success Criteria (Summary)

- Both views match screens 13 and 14 at 1280 px and stay usable at 1024 and 390 px with visible focus rings.
- Create, edit and delete of family members and every profile flow work as before. The functional E2E passes with only the family selectors updated, and Pest passes.
- The visual gate is green at 62 shots, with exactly the listed baselines changed and reviewed.
