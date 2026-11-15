# Family Members UI Implementation Plan

## Overview

DS-07 of the design-system rollout (`context/foundation/design-system.md` §8): move the "Członkowie rodziny" view onto semantic design tokens and the existing `ui/` primitives, collapse three copies of the member form into one dialog, and fix the data-flow bugs the view carries today (hidden 16th member, guessed rows after create, empty birth date in edit). The backend contract is trimmed to match: no `create`/`edit` pages, Polish validation messages, redirects that keep the current page.

This change runs **standalone** on the tokens that already exist in `resources/css/app.css`. DS-01 (`design-tokens-foundation`) and DS-02 (`shared-app-components`) have not landed; when they do, DS-01 repaints this view without touching it, and DS-02 replaces the feature-local confirm dialog, empty state and pagination with `Components/Common/*`.

## Current State Analysis

- **Three form copies.** `resources/js/Pages/FamilyMembers/InlineEditDialog.vue` is what `Index.vue` opens. `Create.vue` and `Edit.vue` are full Inertia pages served by `FamilyMemberController::create()/edit()` (`app/Http/Controllers/FamilyMemberController.php:38-65`), but nothing in the app links to them (only `Sidebar.vue:43` and `MealPlans/Show.vue:75` link to the index). Client validation (name, date, gender + `useFamilyMemberBodyValidation`) is copy-pasted in all three.
- **Hidden members.** `index()` returns `paginate(15)` (`FamilyMemberController.php:26-28`), the table renders `familyMembers.data` only, and there is no pagination control — member 16+ is invisible.
- **Local list copy.** `Index.vue:18` copies `props.familyMembers.data` into a `ref` once and patches it after save/delete. After create, `InlineEditDialog.vue:171-181` guesses the new row by matching name/date/gender and falls back to `id: Date.now()`; after edit it stores the raw form strings. Header count `Index.vue:23` counts the current page only and its plural rule is wrong for 12–14 and 22–24.
- **Birth date in edit.** `FamilyMember` casts `birth_date` as `date` (`app/Models/FamilyMember.php:25`), so the paginator JSON carries `1990-01-01T00:00:00.000000Z`; the dialog feeds that into `<input type="date">`, which rejects it, so edit opens with an empty date and client validation blocks saving.
- **Styling.** ~95 Tailwind palette classes and 23 `dark:` classes across the six files, arbitrary widths (`w-[200px]`, `sm:max-w-[425px]`), a hand-drawn `<svg>` in the empty state, hover colours overriding `Button` variants. Age column always says "lat".
- **Accessibility.** The gender `Select` has `<Label for="gender">` but `SelectTrigger` has no `id="gender"`, so the label is not associated. Error text is not linked to inputs.
- **Backend messages.** `FamilyMemberRequest::messages()` (`app/Http/Requests/FamilyMemberRequest.php`) is English for name/date/gender, Polish for weight/height. The controller flashes English `success` messages that are never shown (`HandleInertiaRequests::share()` does not share `flash`; toasts are client-side via `vue-sonner`, `<Toaster />` in `AuthenticatedLayout.vue:125`).
- **Tests.** `tests/Feature/FamilyMemberTest.php` covers CRUD, ownership and validation, including create/edit page renders and an edit-page 403; three tests assert the English flash. `tests/Unit/FamilyMemberPolicyTest.php` and `FamilyMemberRequestValidationTest.php` exist and are unaffected. No E2E covers family members.
- **E2E seeding.** `database/seeders/E2eMealPlanSeeder.php` seeds planner users (whose two-adult family is pinned for scaling assertions — must not be touched) and cook users.

## Desired End State

- `/family-members` lists the user's members 15 per page, sorted by first name, with a Polish pagination control when there is more than one page; the header shows the total count with correct Polish plural.
- Add and Edit both open the single `FamilyMemberDialog`; Edit is pre-filled with a valid birth date. After save/delete the list is whatever the server returned (no client-side patching), the dialog closes, a Polish toast confirms, and after edit/delete the user stays on the same page (or on the last page if the one they were on no longer exists); after add they land on page 1 (the new member sorts by first name and may be on another page).
- `/family-members/create` and `/family-members/{id}/edit` return 404; `Create.vue`, `Edit.vue`, `InlineEditDialog.vue` are gone.
- Every file under `resources/js/Pages/FamilyMembers/` and `resources/js/Components/FamilyMembers/` uses only semantic token classes — no palette colours, `white`/`black`, `dark:` or arbitrary `[...]` values — and lucide icons only.
- Server-side validation errors are Polish and identical to the client-side strings.
- Verified by: Pest suite green, a grep for palette/`dark:`/arbitrary classes returning nothing, `npm run build` green, and a new Playwright spec walking empty state → validation error → add → edit → delete.

### Key Discoveries:

- Semantic classes are already available: `@theme` in `resources/css/app.css` maps `--color-card`, `--color-muted(-foreground)`, `--color-destructive`, `--color-border`, `--color-accent`, `--color-primary` → `bg-card`, `text-muted-foreground`, `text-destructive`, `border-border`, `bg-muted`, etc.
- Feature components belong in `resources/js/Components/<Feature>/` (CLAUDE.md "Structure & Conventions"); today they sit in `Pages/FamilyMembers/`.
- The existing `ServerPagination` components (`Components/MealPlans/`, `Components/recipes/`) expect the API-Resource `meta`/`links` shape and contain English text and gray literals — not reusable for a migrated view; the family list receives a raw `LengthAwarePaginator` (`current_page`, `last_page`, `total`, `from`, `to`, `prev_page_url`, `next_page_url`).
- `date:Y-m-d` changes serialization only; `$member->birth_date` stays a Carbon instance for `MealIngredientScaler.php:47-51`.
- Polish validation defaults exist in `lang/pl/validation.php` but without attribute names, so explicit messages in the Form Request remain the right place.
- E2E selectors use roles and labels (`tests/e2e/recipe-form.spec.ts`); login with `Password123!` users seeded idempotently.

## What We're NOT Doing

- No token changes in `app.css` (DS-01), no brand colour, no kitchen-sink page, no `lint:tokens` script, no screenshot gate.
- No `Components/Common/*` (`ConfirmDialog`, `EmptyState`, `PageHeader`, `FormField`, `ServerPagination`) and no new shadcn primitives (`alert-dialog`, `pagination`) — DS-02 owns them and will migrate this view's local versions.
- No restyle of `AuthenticatedLayout` / `Sidebar` (DS-03), even though the header bar around the page still has gray literals.
- No `Form.vue` page; the parked roadmap item "Ujednolicenie stron `FamilyMembers` do konwencji `Index/Show/Form.vue`" is resolved by deleting the pages, not by adding one.
- No sharing of Inertia `flash` props; toasts stay client-side.
- No optimistic updates, no API/JSON endpoints, no change to sorting (first name), page size (15) or validation rules themselves.
- No Vitest/unit tests for Vue components (no JS test runner in the repo).

## Implementation Approach

Backend first, because it fixes the data contract the UI reads (date format, pagination redirects) and removes the routes the deleted pages depend on; the old frontend keeps working against it (the `create`/`edit` pages are unreachable anyway). Then rebuild the frontend in its conventional location, rendering straight from Inertia props. E2E last, against the finished view.

## Critical Implementation Details

- **Redirect target after mutations.** `update` and `destroy` must return to the page the user was on (`redirect()->back()`), and `index` must redirect a `?page` beyond `lastPage()` to the last page — otherwise deleting the only member on page 2 lands on an empty page 2 that shows the "no members" empty state although members exist. `store` keeps redirecting to `family-members.index` (page 1).
- **Dialog lifecycle with server-rendered list.** Inertia visits that redirect back re-render `Index` with fresh props; the dialog/confirm component must close and toast in `onSuccess`, and must not try to reconstruct the saved member from form values. Keep `preserveScroll: true` on mutations.
- **Local dates.** The date input's `max` must be yesterday in the browser's local time (not `toISOString()`, which is UTC and is off by one around midnight in Poland); age and date display must parse `yyyy-MM-dd` as a local date.

## Phase 1: Backend contract

### Overview

Trim routes and controller to what the dialog-based view uses, make pagination and redirects page-aware, serialize birth date as `Y-m-d`, translate validation messages, drop dead flash, and align Pest.

### Changes Required:

#### 1. Routes

**File**: `routes/web.php`

**Intent**: The family-members resource only exposes what the view uses.

**Contract**: `Route::resource('family-members', FamilyMemberController::class)->only(['index', 'store', 'update', 'destroy'])->where(['family_member' => '[0-9]+'])->names('family-members')` — route names `family-members.create` / `family-members.edit` no longer exist. The numeric constraint is required: without it `GET /family-members/create` matches `family-members/{family_member}` (PUT/PATCH/DELETE) and Laravel answers 405 Method Not Allowed instead of 404 (`AbstractRouteCollection::checkForAlternateVerbs`).

#### 2. Controller

**File**: `app/Http/Controllers/FamilyMemberController.php`

**Intent**: Remove the page actions, keep users on their page after update/delete, recover from out-of-range pages, and stop flashing unused English messages.

**Contract**:
- Delete `create()` and `edit()`.
- `index()`: same query (owner-scoped, `orderBy('first_name')`, `paginate(15)`); if `currentPage() > lastPage()`, return a redirect to `route('family-members.index', ['page' => lastPage()])`. Return type becomes `Response|RedirectResponse`.
- `store()`: redirect to `route('family-members.index')`, no `->with(...)`.
- `update()`, `destroy()`: `redirect()->back()` (falls back to the index via the referer/session previous URL), no `->with(...)`.
- `authorizeResource()` stays.

#### 3. Model serialization

**File**: `app/Models/FamilyMember.php`

**Intent**: Send `birth_date` in the format `<input type="date">` accepts.

**Contract**: cast `'birth_date' => 'date:Y-m-d'`. Attribute access still returns Carbon. Add `declare(strict_types=1);` if missing.

#### 4. Polish validation messages

**File**: `app/Http/Requests/FamilyMemberRequest.php`

**Intent**: Server errors read the same as the client-side ones.

**Contract**: `messages()` uses these exact strings (weight/height entries unchanged):

| Key | Message |
| --- | --- |
| `first_name.required` | `Imię jest wymagane` |
| `first_name.string` | `Imię jest wymagane` |
| `first_name.max` | `Imię nie może być dłuższe niż 255 znaków` |
| `birth_date.required` | `Data urodzenia jest wymagana` |
| `birth_date.date` | `Podaj poprawną datę urodzenia` |
| `birth_date.before` | `Data urodzenia musi być wcześniejsza niż dzisiaj` |
| `gender.required` | `Płeć jest wymagana` |
| `gender.in` | `Nieprawidłowa wartość płci` |

#### 5. Feature tests

**File**: `tests/Feature/FamilyMemberTest.php`

**Intent**: Remove tests for deleted pages, drop flash assertions, cover the new behaviour.

**Contract**:
- Delete: `user can view create form`, `user can view edit form`, `user cannot edit other user's family member` (ownership stays covered by the update/delete 403 tests).
- In `user can store/update/delete family member`: remove `assertSessionHas('success', ...)`; update/delete send `->from(route('family-members.index', ['page' => 2]))` and assert the redirect goes back to that URL; store still asserts redirect to `family-members.index`.
- Add:
  - `index paginates fifteen members per page` — 16 own members (+ others' members): page 1 has 15 `familyMembers.data`, `familyMembers.total` is 16, `?page=2` has 1.
  - `index redirects a page past the end to the last page` — 16 members, `?page=5` → redirect to `?page=2`; 0 members, `?page=3` → redirect to `?page=1`.
  - `index serializes birth date as Y-m-d` — `familyMembers.data.0.birth_date` equals `'1990-01-01'`.
  - `create and edit pages return not found` — as an authenticated user, `get('/family-members/create')->assertNotFound()` and `get('/family-members/{own member id}/edit')->assertNotFound()` (URL-based, no route names, so the Phase 2 grep for `family-members.(create|edit)` stays clean).
  - `validation messages are Polish` — empty POST yields `first_name` = `Imię jest wymagane`, `birth_date` = `Data urodzenia jest wymagana`, `gender` = `Płeć jest wymagana`; future date yields `Data urodzenia musi być wcześniejsza niż dzisiaj`.

### Success Criteria:

#### Automated Verification:

- Family member feature tests pass: `vendor/bin/sail pest tests/Feature/FamilyMemberTest.php`
- Policy and request unit tests still pass: `vendor/bin/sail pest tests/Unit/FamilyMemberPolicyTest.php tests/Unit/FamilyMemberRequestValidationTest.php`
- Full suite passes: `vendor/bin/sail composer test`
- Larastan passes without baseline changes: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`
- Route list shows only index/store/update/destroy for family-members: `vendor/bin/sail artisan route:list --name=family-members`

#### Manual Verification:

- Visiting `/family-members/create` in the browser returns 404

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Frontend consolidation and restyle

### Overview

Rebuild the view in `Components/FamilyMembers/` with one dialog, render straight from paginated props, add Polish pagination, and use semantic tokens only.

### Changes Required:

#### 1. Delete obsolete files

**Files**: `resources/js/Pages/FamilyMembers/Create.vue`, `Edit.vue`, `InlineEditDialog.vue`, `FamilyMemberTable.vue`, `ConfirmDialog.vue`

**Intent**: One form, components in their conventional folder.

**Contract**: After this phase `resources/js/Pages/FamilyMembers/` contains only `Index.vue`.

#### 2. Page

**File**: `resources/js/Pages/FamilyMembers/Index.vue`

**Intent**: Thin page that renders server data and owns only dialog state.

**Contract**:
- Prop `familyMembers` = raw Laravel paginator (`data`, `current_page`, `last_page`, `total`, `from`, `to`, `prev_page_url`, `next_page_url`).
- No local copy of the members list; table reads `familyMembers.data`.
- State: `dialogOpen`, `editingMember` (null = create), `memberToDelete`.
- Header: `<h1>` "Członkowie rodziny" (`text-2xl font-semibold tracking-tight`), description `text-sm text-muted-foreground` with total count from `familyMembers.total` and correct Polish plural (1 osoba, 2–4 osoby except 12–14, otherwise osób; 0 → no count). Primary `Button` "Dodaj członka" with `Plus` icon.
- Content container `max-w-6xl`, card surface with `bg-card border border-border rounded-lg` (no shadow).
- Empty state (when `familyMembers.total === 0`): lucide `Users` icon `size-10 text-muted-foreground`, title "Brak członków rodziny", text "Rozpocznij od dodania pierwszego członka rodziny.", button "Dodaj pierwszego członka". The table is not rendered in this state.
- Renders `FamilyMemberTable`, `FamilyMemberPagination`, `FamilyMemberDialog`, `FamilyMemberDeleteDialog`.

#### 3. Table

**File**: `resources/js/Components/FamilyMembers/FamilyMemberTable.vue`

**Intent**: Presentational table with token styling and correct Polish formatting.

**Contract**:
- Props `members: FamilyMember[]`; emits `edit(member)`, `delete(member)`. No dialog inside.
- Columns: Imię | Data urodzenia | Wiek | Płeć | Waga | Wzrost | Akcje. Header row `bg-muted`, header text `text-sm font-medium`; secondary cells `text-muted-foreground`; weight/height/age `tabular-nums text-right`.
- Date: `yyyy-MM-dd` parsed as local date, formatted `pl-PL` long month ("1 stycznia 1990").
- Age: Polish plural — "1 rok", "2–4 lata" (except 12–14), otherwise "lat"; e.g. 1 rok, 3 lata, 5 lat, 12 lat, 22 lata.
- Weight/height: `Intl.NumberFormat('pl-PL')` + unit, `—` when null.
- Actions: `Button variant="ghost" size="icon"` with lucide `Pencil` / `Trash2` (`size-4`), `aria-label` "Edytuj {imię}" / "Usuń {imię}"; no colour overrides (delete icon may use `text-destructive`).
- No fixed column widths with arbitrary values.

#### 4. Add/Edit dialog (the single form)

**File**: `resources/js/Components/FamilyMembers/FamilyMemberDialog.vue`

**Intent**: The one add/edit form, posting via Inertia and closing on success.

**Contract**:
- Props `open: boolean`, `member: FamilyMember | null`; emits `update:open`.
- Fields: Imię (`first_name`), Data urodzenia (`birth_date`, `type="date"`, `max` = yesterday local), Płeć (`gender`, `Select` with `SelectTrigger id="gender"` so `<Label for="gender">` associates), Waga (kg) (`weight_kg`), Wzrost (cm) (`height_cm`). Labels "Imię *", "Data urodzenia *", "Płeć *", "Waga (kg)", "Wzrost (cm)".
- Form resets from `member` (or empty) every time the dialog opens.
- Client validation keeps today's rules and strings (name required/≤255, date required/before today, gender male|female, `useFamilyMemberBodyValidation` for weight/height); server 422 errors replace client errors per field.
- Errors: `<p id="<field>-error" class="text-sm text-destructive">`, input gets `aria-invalid="true"`, `aria-describedby="<field>-error"` and `border-destructive`.
- Submit: `router.post('/family-members')` or `router.put('/family-members/{id}')` with `preserveScroll: true`; `onSuccess` → close + toast "Nowy członek rodziny został dodany" / "Członek rodziny został zaktualizowany"; `onError` → field errors + toast "Sprawdź poprawność wprowadzonych danych". Buttons "Anuluj" (outline) and "Zapisz" (shows "Zapisywanie..." while processing, both disabled while processing). Submit button is the form's `type="submit"` (Enter submits).
- Dialog width via scale classes (e.g. `sm:max-w-md`), title "Dodaj członka rodziny" / "Edytuj członka rodziny".

#### 5. Delete confirmation

**File**: `resources/js/Components/FamilyMembers/FamilyMemberDeleteDialog.vue`

**Intent**: Token-styled confirm on the existing `ui/dialog`, to be replaced by `Common/ConfirmDialog` in DS-02.

**Contract**: Props `member: FamilyMember | null` (open when non-null); emits `close`. Title "Usuń członka rodziny" (`text-destructive`, lucide `AlertTriangle`), description "Czy na pewno chcesz usunąć {imię}? Tej operacji nie można cofnąć." Buttons "Anuluj" (outline) and "Usuń" (destructive, "Usuwanie..." while processing). `router.delete` with `preserveScroll: true`; `onSuccess` → close + toast `Członek rodziny "{imię}" został usunięty`; `onError` → toast "Nie udało się usunąć członka rodziny". No `console.error`.

#### 6. Pagination

**File**: `resources/js/Components/FamilyMembers/FamilyMemberPagination.vue`

**Intent**: Minimal Polish pager for the raw paginator, until DS-02's `Common/ServerPagination`.

**Contract**: Props = paginator fields above. Hidden when `last_page <= 1`. `<nav aria-label="Paginacja">` with text "Strona {current} z {last}" (`text-sm text-muted-foreground`) and two Inertia `Link`s rendered as outline buttons "Poprzednia" / "Następna" (`ChevronLeft`/`ChevronRight`), `preserve-scroll`; the missing direction is rendered disabled (`aria-disabled`), not hidden.

#### 7. Shared formatting helper

**File**: `resources/js/lib/pluralizePl.js`

**Intent**: One Polish plural rule used by the header count and the age column.

**Contract**: `pluralizePl(count, one, few, many): string` returns the form only (caller formats the number). Rule: `count === 1` → one; `count % 10` in 2–4 and `count % 100` not in 12–14 → few; else many.

#### 8. Design-system audit paths

**File**: `context/foundation/design-system.md`

**Intent**: DS-02 starts from the real paths of the feature-local components it will migrate.

**Contract**: In the "Duplikaty" row of the audit table (§ stan obecny, ~line 36) replace `FamilyMembers/ConfirmDialog` with `FamilyMembers/FamilyMemberDeleteDialog` and change "2 × `ServerPagination`" to "2 × `ServerPagination` (+ `FamilyMembers/FamilyMemberPagination`)". No other edits; the `.ai/` specs stay as they are (already marked partly superseded in References).

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Only Index.vue remains in the page folder: `ls resources/js/Pages/FamilyMembers` prints `Index.vue` only
- No palette, dark or arbitrary classes in the feature: `grep -rEn '(^|[^a-z-])(bg|text|border|ring|from|to|via|fill|stroke|divide|outline)-(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-[0-9]|(bg|text|border)-(white|black)\b|dark:|-\[[^]]+\]|<svg' resources/js/Pages/FamilyMembers resources/js/Components/FamilyMembers` returns no matches
- No references to deleted components or routes remain: `grep -rEn 'InlineEditDialog|FamilyMembers/(Create|Edit)|family-members\.(create|edit)' resources/js app routes tests` returns no matches
- Full Pest suite still passes: `vendor/bin/sail composer test`

#### Manual Verification:

- With 0 members the empty state shows the Users icon and "Dodaj pierwszego członka" opens the dialog
- Editing an existing member opens the dialog with the birth date pre-filled and saves without errors
- With 16+ members the pager appears, "Następna" shows page 2, and deleting the only member on the last page lands on the new last page with the list visible
- Header count and age read correctly in Polish (e.g. "1 osoba", "3 osoby", "5 osób"; "1 rok", "22 lata", "12 lat")
- Keyboard: Tab reaches every field and button, focus ring is visible, Escape closes the dialog, clicking the "Płeć" label focuses the select

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: E2E coverage

### Overview

A dedicated, idempotently seeded user per browser project and one Playwright spec that walks the whole dialog flow.

### Changes Required:

#### 1. Seeder users

**File**: `database/seeders/E2eMealPlanSeeder.php`

**Intent**: Isolated family-members fixtures that do not disturb the planner users' pinned two-adult family.

**Contract**: New `public const FAMILY_EMAILS = ['e2e-family-chromium@example.com', 'e2e-family-firefox@example.com'];` `run()` creates each with `firstOrCreate` (name "E2E Family", `PASSWORD`, verified) and deletes all of that user's family members on every run.

#### 2. Spec

**File**: `tests/e2e/family-members.spec.ts`

**Intent**: Prove add → validation → edit → delete end to end through the dialog, using roles and labels only.

**Contract**: One `test` using `e2e-family-${testInfo.project.name}@example.com` / `Password123!`, logging in the same way as `recipe-form.spec.ts`, then on `/family-members`:
1. Empty state "Brak członków rodziny" is visible.
2. "Dodaj członka" → fill Imię "Ewa E2E", Data urodzenia "1990-05-01", Płeć "Kobieta", leave weight/height empty → "Zapisz" → "Podaj wagę (wymagana od 19 lat)." visible, dialog still open.
3. Fill Waga 60, Wzrost 165 → "Zapisz" → row "Ewa E2E" visible with "1 maja 1990" and "60 kg".
4. "Edytuj Ewa E2E" → birth date field has value "1990-05-01" → change Imię to "Ewa Edytowana" → "Zapisz" → row "Ewa Edytowana" visible, "Ewa E2E" gone.
5. "Usuń Ewa Edytowana" → confirm "Usuń" → empty state visible again.

#### 3. Docs

**File**: `CLAUDE.md`

**Intent**: Keep the E2E command description accurate.

**Contract**: In the E2E bullet, the seeder description mentions the family users ("…creates the two planner users with 21 recipes each, the cook users and the family-members users, and deletes their plans/recipes/family members…"). One sentence edit, no new section.

### Success Criteria:

#### Automated Verification:

- Seeder is idempotent and runs: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder` (twice in a row, both exit 0)
- Bundles build before E2E: `vendor/bin/sail npm run build`
- New spec passes in both browsers: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npx playwright test tests/e2e/family-members.spec.ts`
- Whole E2E suite still passes (re-seed first): `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- Pint passes: `vendor/bin/sail composer pint-test`

#### Manual Verification:

- Review the commits and the deleted files list before merging (the three removed Pest tests and five removed Vue files are expected)

---

## Testing Strategy

### Unit Tests:

- Existing `FamilyMemberPolicyTest` and `FamilyMemberRequestValidationTest` stay as they are (rules unchanged).

### Integration Tests:

- Pest feature tests: pagination (15/page, total), out-of-range page redirect (with and without members), redirect back after update/delete, `Y-m-d` birth date, Polish messages, removed routes, ownership 403s (existing).
- Playwright: the full dialog flow including the adult-without-weight validation and the edit pre-fill regression.

### Manual Testing Steps:

1. Log in, open "Członkowie rodziny" with no members; check empty state, add a child (no weight/height) and an adult.
2. Edit the adult; confirm the birth date is pre-filled and the save works.
3. Seed or add 16 members; page to 2; delete the only member there; confirm you land on page 1 with the list.
4. Check plurals in header and age column; tab through the dialog; press Escape.

## Performance Considerations

None — one paginated, owner-scoped query of at most 15 rows, unchanged.

## Migration Notes

No schema change. The `date:Y-m-d` cast changes only the JSON shape of `birth_date`, consumed solely by this view. Bookmarked `/family-members/create` or `/edit` URLs will 404.

## References

- Design system rollout and rules: `context/foundation/design-system.md` (§3 rules, §4.5–4.7, §8 DS-07)
- Original view spec (partly superseded — JSON API and optimistic state are not used): `.ai/ui/family-members-view-implementation-plan.md`
- Controller: `app/Http/Controllers/FamilyMemberController.php:24-87`
- Current page and components: `resources/js/Pages/FamilyMembers/*.vue`
- Client body validation: `resources/js/hooks/useFamilyMemberBodyValidation.ts`
- E2E pattern: `tests/e2e/recipe-form.spec.ts`, `database/seeders/E2eMealPlanSeeder.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Backend contract

#### Automated

- [x] 1.1 Family member feature tests pass: `vendor/bin/sail pest tests/Feature/FamilyMemberTest.php`
- [x] 1.2 Policy and request unit tests still pass: `vendor/bin/sail pest tests/Unit/FamilyMemberPolicyTest.php tests/Unit/FamilyMemberRequestValidationTest.php`
- [x] 1.3 Full suite passes: `vendor/bin/sail composer test`
- [x] 1.4 Larastan passes without baseline changes: `vendor/bin/sail composer phpstan`
- [x] 1.5 Pint passes: `vendor/bin/sail composer pint-test`
- [x] 1.6 Route list shows only index/store/update/destroy for family-members: `vendor/bin/sail artisan route:list --name=family-members`

#### Manual

- [ ] 1.7 Visiting `/family-members/create` in the browser returns 404

### Phase 2: Frontend consolidation and restyle

#### Automated

- [ ] 2.1 Client and SSR bundles build: `vendor/bin/sail npm run build`
- [ ] 2.2 Only Index.vue remains in the page folder
- [ ] 2.3 No palette, dark or arbitrary classes in the feature
- [ ] 2.4 No references to deleted components or routes remain
- [ ] 2.5 Full Pest suite still passes: `vendor/bin/sail composer test`

#### Manual

- [ ] 2.6 With 0 members the empty state shows the Users icon and "Dodaj pierwszego członka" opens the dialog
- [ ] 2.7 Editing an existing member opens the dialog with the birth date pre-filled and saves without errors
- [ ] 2.8 With 16+ members the pager appears, "Następna" shows page 2, and deleting the only member on the last page lands on the new last page with the list visible
- [ ] 2.9 Header count and age read correctly in Polish
- [ ] 2.10 Keyboard: Tab reaches every field and button, focus ring is visible, Escape closes the dialog, clicking the "Płeć" label focuses the select

### Phase 3: E2E coverage

#### Automated

- [ ] 3.1 Seeder is idempotent and runs
- [ ] 3.2 Bundles build before E2E: `vendor/bin/sail npm run build`
- [ ] 3.3 New spec passes in both browsers
- [ ] 3.4 Whole E2E suite still passes (re-seed first)
- [ ] 3.5 Pint passes: `vendor/bin/sail composer pint-test`

#### Manual

- [ ] 3.6 Review the commits and the deleted files list before merging
