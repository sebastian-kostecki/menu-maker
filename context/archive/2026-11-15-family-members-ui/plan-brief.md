# Family Members UI — Plan Brief

> Full plan: `context/changes/family-members-ui/plan.md`

## What & Why

DS-07 of the design-system rollout: move the "Członkowie rodziny" view onto semantic tokens and existing `ui/` primitives, and fix what the view gets wrong today. Members past the 15th are invisible, new rows are guessed after create, the edit dialog opens with an empty birth date, and three copies of the same form drift apart.

## Starting Point

`Pages/FamilyMembers/` holds six files: `Index` with an add/edit dialog, plus orphaned `Create`/`Edit` pages nothing links to. Between them they carry ~95 palette classes and 23 `dark:` classes. The controller paginates 15 per page with no pager in the UI, sends `birth_date` as an ISO datetime, and flashes English messages nobody sees. DS-01 (tokens) and DS-02 (shared components) have not landed yet.

## Desired End State

One page and four feature components in `Components/FamilyMembers/`, styled only with semantic token classes. The list renders straight from paginated server props, with a Polish pager. Add and Edit share one dialog with a correctly pre-filled date, and the user stays on the same page after saving or deleting. Server validation messages are Polish, and the `create`/`edit` URLs return 404. Pest and one Playwright spec prove it.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| DS-01/DS-02 prerequisites | Standalone on today's semantic tokens; no `Common/*` | Keeps the change bounded and unattended-friendly without pre-empting DS-02's shared contracts; DS-01 later repaints it for free. |
| Form copies | Dialog only; delete `Create.vue`/`Edit.vue`, their routes and 3 Pest tests | Matches how users reach the form today; a 5-field form fits a dialog. |
| Hidden 16th member | Keep `paginate(15)`, add a feature-local Polish pager | Keeps the prop shape; DS-02 swaps it for `Common/ServerPagination`. |
| List sync | Render from Inertia props, no local copy | The server is the single source of truth; removes the row-guessing and stale-type bugs (a new member sorts by name rather than appearing on top). |
| Redirects | `update`/`destroy` go back; `index` redirects a page past the end to the last page | Deleting the only member on page 2 must not land on an empty "no members" page. |
| Birth date | Cast `date:Y-m-d` | The date input rejects ISO datetimes; serialization-only change. |
| Backend messages | Polish strings identical to the client's; drop dead flash | A server-side 422 must not show English in a Polish UI. |
| Testing | Pest + one E2E spec on new `e2e-family-<browser>` users | Proves the dialog flow without touching the planner users' pinned two-adult family. |

## Scope

**In scope:**
- Routes `only(index, store, update, destroy)`, controller redirects and page clamp, `Y-m-d` cast, Polish request messages
- `Index.vue`, plus `FamilyMemberTable`, `FamilyMemberDialog`, `FamilyMemberDeleteDialog` and `FamilyMemberPagination` in `Components/FamilyMembers/`
- `lib/pluralizePl.js` for "1 osoba / 3 osoby / 5 osób" and "1 rok / 22 lata / 12 lat"
- Label association, `aria-invalid`/`aria-describedby`, local-time date max
- Pest updates, seeder users, `tests/e2e/family-members.spec.ts`, one-line CLAUDE.md update

**Out of scope:**
- Token values, brand colour, kitchen sink, `lint:tokens`, screenshot gate (DS-01)
- `Components/Common/*`, new shadcn primitives (DS-02)
- Layout/sidebar restyle (DS-03)
- A `Form.vue` page, Inertia flash sharing, optimistic updates, Vue unit tests

## Architecture / Approach

Inertia request → `FamilyMemberController@index` (owner-scoped paginator, page clamp) → `Index.vue` renders `familyMembers` props directly. The dialogs mutate via `router.post/put/delete` with `preserveScroll`; the controller redirects back, and Inertia re-renders `Index` with fresh props. The dialogs close and toast in `onSuccess`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Backend contract | Trimmed routes, page-aware redirects, `Y-m-d` date, Polish messages, updated Pest | Deleting 3 tests: ownership must stay covered by the update/delete 403 tests |
| 2. Frontend consolidation & restyle | One dialog, render-from-props, Polish pager, token-only styling | Dialog lifecycle across Inertia re-renders (close and toast in `onSuccess`) |
| 3. E2E coverage | Seeded family users + CRUD/validation spec in both browsers | Needs the full Sail stack, queue and fake AI driver like the other specs |

**Prerequisites:** Sail up; `.env` set for E2E per CLAUDE.md (`AI_SCALING_DRIVER=fake`, Mailpit). Neither DS-01 nor DS-02 is required.
**Estimated effort:** ~1 session across 3 phases; suitable for an unattended `/10x-goal-implement` run, with the Manual rows left for review.

## Open Risks & Assumptions

- Families are assumed small; 15 per page stays as the API contract.
- DS-02 must later migrate `FamilyMemberDeleteDialog` and `FamilyMemberPagination`. Phase 2 updates the design-system audit table to the new `FamilyMembers/FamilyMemberDeleteDialog` / `FamilyMemberPagination` paths.
- The page header still sits inside `AuthenticatedLayout`'s gray header bar until DS-03.

## Success Criteria (Summary)

- A user with any number of members sees all of them, page by page, and can add, edit (date pre-filled) and delete through one dialog without leaving their page.
- The feature folders contain no palette, `dark:`, arbitrary-value classes or hand-drawn SVGs, and all user-facing text, including server errors, is Polish.
- Pest, the build and the new Playwright spec are green.
