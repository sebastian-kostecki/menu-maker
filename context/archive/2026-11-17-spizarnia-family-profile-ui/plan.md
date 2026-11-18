# Spiżarnia Family and Profile UI Implementation Plan

## Overview

Roadmap S-26 (M-4, SP-07) restyles the last two signed-in views to the Spiżarnia direction from artifact screens 13 and 14 (`context/foundation/design-system.md` §10.5).

- **`FamilyMembers/Index`** becomes person tiles. Each tile has an initial avatar in a meal colour, the name, gender and birth date, and three fact boxes (age, weight, height). A dashed „Dodaj członka rodziny” tile follows the person tiles.
- **`Profile/Edit`** becomes a 720 px column of three tiles. The delete-account tile has a `destructive` outline, shadow and title.

Behaviour, the dialogs, the backend and the props stay as they are. The slice also adds the visual spec that family members never had (roadmap S-26 Risk).

## Current State Analysis

**Family members**

The page is a single screen. Create and edit happen in a dialog and delete uses `ConfirmDialog`. There are no `Create`/`Edit`/`Form` pages: `/create` and `/{id}/edit` return 404 (`tests/Feature/FamilyMemberTest.php:298`).

- `resources/js/Pages/FamilyMembers/Index.vue`:
  - `PageHeader` „Członkowie rodziny” with the description „Zarządzaj członkami swojej rodziny (n osób)”, built with `pluralizePl`, and an actions button „Dodaj członka” (:47-58).
  - A box `overflow-hidden rounded-lg border border-border bg-card` (:61, the F-05 carry-over) that wraps either a hand-rolled empty state (:62-70, h2 „Brak członków rodziny”, CTA „Dodaj pierwszego członka”) or `FamilyMemberTable` + `FamilyMemberPagination` (:72-84).
- `Components/FamilyMembers/FamilyMemberTable.vue`:
  - Columns: Imię, Data urodzenia, Wiek, Płeć, Waga, Wzrost, Akcje.
  - Local formatters: a long-month date at :26, client-side age from `new Date()` at :31-41, and „—” for null measurements at :45-51.
  - Icon-only `ghost` buttons with `aria-label` „Edytuj {imię}” / „Usuń {imię}” (:80, :89).
- `Components/FamilyMembers/FamilyMemberPagination.vue` is a custom „Strona X z Y” nav that is not `Common/ServerPagination`.
- `FamilyMemberDialog.vue` and `FamilyMemberDeleteDialog.vue` (form dialog and delete confirm) are already on Spiżarnia primitives. They stay unchanged.
- **Props:** the controller passes the raw `LengthAwarePaginator` (`app/Http/Controllers/FamilyMemberController.php:26-36`), ordered by `first_name` with 15 per page. Rows carry `id, first_name, birth_date (Y-m-d), gender (male|female), weight_kg, height_cm`. Weight and height are `decimal:1` strings such as „75.0”, and both are nullable under 19 years (`app/Http/Requests/FamilyMemberRequest.php:30-55`). The top-level paginator keys (`current_page`, `last_page`, `per_page`, `total`, `from`, `to`) are exactly what `Common/ServerPagination` reads as `meta`.

**Profile**

- `resources/js/Pages/Profile/Edit.vue` puts `PageHeader` „Profil” and three partials in `mx-auto grid max-w-2xl gap-6` (:14-18).
- Each partial is a `Card role="region" aria-labelledby=…` with a `CardTitle as="h2"`:
  - „Dane profilu”: name and e-mail, „Zapisz”, plain „Zapisano.”
  - „Zmiana hasła”: three fields stacked, „Zapisz”, „Zapisano.”
  - „Usuń konto”: a destructive button that opens a `ConfirmDialog` with a password field.
- `ProfileController::edit` passes no props, and there is no e-mail verification block.

**Primitives**

- `ui/card/Card.vue:12` is `rounded-lg border-2 border-foreground … shadow-sticker`, with `class` as its only prop.
- `--shadow-sticker` compiles to `0 3px 0 0 var(--tw-shadow-color, var(--foreground))`. A `shadow-destructive` colour utility therefore recolours it, and tailwind-merge keeps it, because `shadow-<colour>` is a separate group from the `sticker` size in `resources/js/lib/utils.js:4-11`.
- `ui/button` `dashed` (`rounded-md border-dashed bg-transparent`) is unused outside the kitchen sink.
- `ui/avatar` has `default` and `sticker` (`meal-breakfast`) variants, in sizes `sm` (40 px), `base` (64 px) and `lg`.

**Formatting**

- `lib/format.js:47` `formatDate` passes a bare Y-m-d to `new Date()`, which parses it as UTC midnight. West of UTC a birth date would render a day early. The private `parseYmd` at :16 already reads Y-m-d as a local date.
- There is no age helper. `lib/pluralizePl.js` is used only by the two family files.

**Tests**

- `tests/e2e/family-members.spec.ts` relies on `getByRole('row', { name: /Ewa E2E/ })`, the long date „1 maja 1990” and „60 kg” inside the row (:31-42).
- `tests/e2e/confirm-dialog.spec.ts:56,63` relies on `getByRole('cell', { name, exact: true })`.
- Both specs rely on the exact button names „Dodaj członka”, „Edytuj {imię}” and „Usuń {imię}”, the empty heading „Brak członków rodziny” and the dialog labels. `profile.spec.ts` relies on the regions „Dane profilu” / „Zmiana hasła”, a single page-wide „Usuń konto” button before the dialog opens, and the dialog labels.
- Pest asserts `component('FamilyMembers/Index')` and the paginator shape (`FamilyMemberTest.php:26-29, :257-296`). There are no Inertia assertions for the profile.
- **Seeded family for the visual gate:**
  - `E2eMealPlanSeeder::syncFamily` (:385-406) gives the planner users Jan and Anna born `now()->subYears(40)`. Those dates are not pinned, so they are unusable for a frozen-clock screenshot.
  - That family also feeds `MealIngredientScaler` (`dailyKcal($gender, $birth_date, $mealPlan->start_date, …)`, `app/Services/IngredientScaling/MealIngredientScaler.php:49`). Changing its dates would shift the scaled amounts behind the meal-plan baselines.
  - `e2e-dashboard-empty-visual@example.com` is guaranteed to have no family members (`tests/Unit/Seeders/E2eMealPlanSeederTest.php:139`).
- **Visual gate:** 6 specs with 58 shots. `account.spec.ts:31-38` shoots `profile-desktop.png` as `e2e-planner-visual` with the clock fixed at 2026-06-15 10:00 and `timezoneId: 'UTC'`. There is no family-members spec.
- The kitchen sink (`resources/js/Pages/Dev/DesignSystem.vue`) has 14 sections and no family demo. `design-system.spec.ts:9-24` screenshots each section by name.

## Desired End State

**`/family-members` at 1280 px** (matches ekran 13):

- Header: the `PageHeader` with its description and the „Dodaj członka” button.
- A grid of tiles, 3 per row from `lg`, 2 from `sm` and 1 on a phone.
- Each tile is a `Card` with `role="article"`, named by its `<h2>` (the first name). It contains:
  - a 56 px initial circle in `meal-supper`, `meal-dinner` or `meal-breakfast`, cycled by position on the page;
  - the meta line „Kobieta · ur. 4 lip 1987”;
  - three `muted` fact boxes, for example „38 / lat”, „64 / kg”, „168 / cm”, with „—” for a missing measurement;
  - „Edytuj” (`outline sm`) and „Usuń” (`ghost sm`) buttons with visible text, whose accessible names stay „Edytuj {imię}” / „Usuń {imię}”.
- After the last person, a dashed tile „Dodaj członka rodziny” opens the same create dialog.
- With more than 15 members, `Common/ServerPagination` sits under the grid.
- With no members, the page shows only `Common/EmptyState` („Brak członków rodziny”, CTA „Dodaj pierwszego członka”), with no dashed tile.

**`/profile` at 1280 px** (matches ekran 14):

- A column at most 720 px wide with three tiles.
- „Dane profilu” has a `User` icon and puts „Imię” and „Adres e-mail” side by side from `sm`. „Zmiana hasła” puts „Nowe hasło” and „Potwierdź hasło” side by side from `sm`.
- „Zapisano.” shows a `Check` icon in `success-foreground`.
- „Usuń konto” is a `Card variant="destructive"` with a `Trash2` icon. The delete dialog flow is unchanged.

**Verification:** the functional E2E passes with the updated family selectors, Pest passes unchanged, `lint:tokens` is clean, and the visual gate has 62 shots: 58, plus 3 family shots, plus `profile-mobile`.

### Key Discoveries:

- No backend change is needed. Age is already computed client-side, and the paginator's top-level keys fit `ServerPagination`'s `meta` (`ServerPagination.vue:3-13`).
- The destructive tile needs no new token: `shadow-sticker` reads `--tw-shadow-color`, so `shadow-destructive` recolours it, and tailwind-merge keeps both classes.
- A seeded family used by meal plans must not be re-pinned (it drives scaling, `MealIngredientScaler.php:49`). The family visual needs its own user.
- The table → tile switch breaks `row`/`cell` selectors in two functional specs. They have to change, which S-25 avoided but cannot be avoided here.
- `pluralizePl.js` becomes dead once both family files use `lib/format.js` `pluralize`, as CLAUDE.md requires.
- Avoid size classes next to type roles: tailwind-merge drops `text-section-title` when `text-lg`/`text-sm` sits beside it (S-25 Key Discoveries).

## What We're NOT Doing

- **No backend change:** no new props, no API Resource for family members, no server-side age, and no change to ordering (still `first_name`) or to the 15 per page. Pest passes untouched.
- **No dialog change:** `FamilyMemberDialog` and `FamilyMemberDeleteDialog` keep their fields, labels, validation and toasts. The `ConfirmDialog` for account deletion is unchanged.
- **No re-pinning of the planner family:** Jan and Anna born `now()->subYears(40)` stay as they are, because they drive scaling for the meal-plan baselines.
- **No `PageHeader` restyle:** its `<h1>` is still `text-2xl font-semibold`, not the 36 px `text-page-title` from the mockups. It is shared by every page, and changing it would refresh every real-page baseline, so it is not in this slice's scope.
- **No copy changes from the mockup:**
  - The password tile keeps „Używaj długiego, losowego hasła, aby zabezpieczyć swoje konto.” instead of the mockup's shorter line.
  - Both buttons keep „Zapisz”.
  - Field labels stay as they are, including the asterisks in the family dialog.
- **No gender-coded or id-based avatar colour:** the colour is by tile position only.
- **No changes to dashboard (S-23), recipes (S-24), meal plans, PDF or dark mode (D-14), no `ui/` regeneration (D-9), and no new `design-system.spec.ts` section.**

## Implementation Approach

There are two phases, one per visual spec, so each spec's baselines are refreshed exactly once:

1. Family members, with its new spec.
2. Profile, with `account.spec.ts`.

`design-system.spec.ts` changes in both phases: phase 1 adds the family demo, the dashed tile size and the `Button` size, and phase 2 adds the `Card` variant. Its section shots are refreshed in each phase and reviewed for knock-on anti-aliasing noise in the sections below. The roadmap asks the parallel slices S-23 to S-26 to refresh the kitchen-sink baseline once more at the end of M-4.

New feature components go in `resources/js/Components/FamilyMembers/`. Replaced components are deleted, not orphaned.

Two primitives change, each with a kitchen-sink demo:

- `Button` gets `size="tile"`. The mockup's 230 px, 20 px-radius dashed tile then comes from the primitive, and the view does not override shape (§5.1).
- `Card` gets `variant: default | destructive`.

Every value comes from tokens or the spacing scale. Arbitrary values are banned outside `ui/`.

## Critical Implementation Details

**Selectors the functional E2E keeps depending on**

- Header button „Dodaj członka” (matched `exact`).
- The dashed tile must be named „Dodaj członka rodziny”, so it never collides with `exact` „Dodaj członka”.
- Buttons whose accessible name starts with „Edytuj {imię}” / „Usuń {imię}”. Keep an `aria-label` that contains the visible word, per WCAG label-in-name.
- The empty-state heading „Brak członków rodziny”, the dialog labels, and the toast „Członek rodziny "{imię}" został usunięty”.
- On the profile, a single page-wide button named „Usuń konto” must exist before the dialog opens. The tile title is a heading, not a button.
- Both profile regions keep their names.

**Avatar colour index**

Cycle by the tile's index on the current page: `['supper', 'dinner', 'breakfast'][index % 3]`. Because the list is sorted by `first_name`, the colour can change when someone earlier in the list is added or removed. That was accepted at planning time.

**Bare Y-m-d dates**

`formatDate` must treat a bare `YYYY-MM-DD` string as a local date through `parseYmd`. Full ISO timestamps keep going through `new Date()`, so `RecipeRow`'s `created_at` is unchanged. The visual project runs in UTC, so `MealPlans/Show`'s existing output and its baselines are unaffected.

## Phase 1: Członkowie rodziny jako kafle

### Overview

Ekran 13: person tiles, the dashed add tile, the `EmptyState` and `ServerPagination`. This phase also updates the functional selectors, adds a pinned visual user and the new `family-members.spec.ts` with 3 baselines, extends the kitchen sink, and updates the docs.

### Changes Required:

#### 1. Formatting helpers

**File**: `resources/js/lib/format.js`

**Intent**: Make birth dates render on the right day in any timezone. Give age one shared, pluralised helper instead of the table's local copies.

**Contract**:
- `formatDate(value)` keeps its signature and its output for ISO timestamps and `Date`. A string that is exactly `YYYY-MM-DD` is parsed with `parseYmd`.
- New export `ageInYears(birthYmd, today = new Date())` returns whole completed years (0 for under one year), or `null` for invalid input.
- Pluralise the age unit at the call site with the existing `pluralize`: `{ one: 'rok', few: 'lata', many: 'lat' }`. That gives „1 rok”, „2 lata”, „5 lat”, „0 lat”, „41 lat” and „38 lat”.

#### 2. Button tile size

**File**: `resources/js/Components/ui/button/index.js`

**Intent**: Let the dashed „Dodaj członka rodziny” tile be a real `Button variant="dashed"` (F-06 carry-over) at tile scale without the view overriding its shape.

**Contract**: a new `size: 'tile'` makes the button a full-width column at least 230 px tall (`min-h-57.5`), with centred content, a 10 px gap, the tile radius `rounded-lg` (20 px) and `text-label` weight 700. The `dashed` variant's colours, its hover (`accent`) and the §10.4 focus contract apply unchanged. No other variant/size combination changes.

#### 3. Member tile

**File**: `resources/js/Components/FamilyMembers/FamilyMemberTile.vue` (new)

**Intent**: Render one person as in ekran 13, with accessible names the functional E2E can target.

**Contract**:
- Props `member` (one paginator row), `colour: 'breakfast' | 'supper' | 'dinner'` and an optional `today: Date` (default `new Date()`), which is passed to `ageInYears`. The kitchen sink sets `today` because `design-system.spec.ts` does not freeze the clock. Emits `edit` and `delete` with the member.
- The root is `<Card role="article" aria-labelledby="family-member-{id}-name">`, with padding 22 px (`p-5.5`) and a 16 px column gap. `Card` has no `as` prop and always renders a `<div>`. The role reaches it through attribute fallthrough, the same way the profile partials pass `role="region"` (`DeleteUserForm.vue:55`). The functional E2E then finds the tile with `getByRole('article', { name })`.
- Top row:
  - The avatar: a token-only `<span aria-hidden="true">` (`size-14 rounded-full`, 56 px) with `bg-meal-{colour} text-meal-{colour}-foreground`, a 2px `foreground` outline, weight 800 and the uppercased first letter of `first_name`. It is not `ui/avatar`, because that only has 40/64 px sizes and the view must not override a primitive's shape (§5.1).
  - `<h2 id=…>` with the first name (24/28 px, weight 800, reusing `text-dialog-title`).
  - The meta line `{Mężczyzna|Kobieta} · ur. {formatDate(birth_date)}` in `text-meta text-muted-foreground`.
- Facts: a `<dl>` with three `muted` `rounded-md` boxes, each holding:
  - an `sr-only` `<dt>` („Wiek”, „Waga”, „Wzrost”);
  - a `<dd>` with the value (20/24 px, weight 800, `tabular-nums`) over the unit (`text-xs text-muted-foreground`, weight 500). Put whitespace between the value and unit elements in the template, so the text content reads „60 kg” and not „60kg”.
  - Values: age plus the pluralised unit; `formatNumber(weight_kg)` plus „kg” and `formatNumber(height_cm)` plus „cm”, or „—” when null (the unit stays).
- Actions:
  - `Button variant="outline" size="sm"` with `Pencil` and „Edytuj”, `aria-label="Edytuj {first_name}"`.
  - `Button variant="ghost" size="sm"` with `Trash2` and „Usuń”, `aria-label="Usuń {first_name}"`.
  - Icons are `aria-hidden`.

#### 4. Index page

**File**: `resources/js/Pages/FamilyMembers/Index.vue`

**Intent**: Replace the bordered box, the table and the custom pagination with the tile grid. Reuse the shared components and fix the F-05 carry-over at :61.

**Contract**:
- Keep `<Head>`, `PageHeader` with its actions button, and both dialogs with their state handling.
- The description count uses `pluralize(total, { one: 'osoba', few: 'osoby', many: 'osób' })` from `lib/format.js`.
- When `familyMembers.total === 0`, show `Common/EmptyState` with `icon=Users`, `title="Brak członków rodziny"`, `description="Rozpocznij od dodania pierwszego członka rodziny."`, `class="py-12"`, and a CTA `Button` „Dodaj pierwszego członka” in its slot. There is no outer box.
- Otherwise:
  - A `<div>` grid (`grid gap-5 sm:grid-cols-2 lg:grid-cols-3`) of `FamilyMemberTile` with `colour` from the index rule.
  - The **last child of that grid** (on every page) is `Button variant="dashed" size="tile"`, with an ink plus-circle (48 px, `bg-primary text-primary-foreground`, `aria-hidden`) and the text „Dodaj członka rodziny”, opening the create dialog. With three members at `lg` it falls into the second row.
  - After the grid, outside it, comes `ServerPagination :meta="familyMembers"`. Its `page-change` visits `family-members.index` with `page`, preserving state, as `MealPlans/Index` does.
- The page container stays `mx-auto flex max-w-6xl flex-col gap-6`, the same as `MealPlans/Index`.

#### 5. Remove the replaced pieces

**Files**: `resources/js/Components/FamilyMembers/FamilyMemberTable.vue`, `resources/js/Components/FamilyMembers/FamilyMemberPagination.vue`, `resources/js/lib/pluralizePl.js` (delete)

**Intent**: No orphaned table, pagination or second plural helper.

**Contract**: `grep -rn -e FamilyMemberTable -e FamilyMemberPagination -e pluralizePl resources/js tests/e2e` returns nothing.

#### 6. Functional E2E selectors

**Files**: `tests/e2e/family-members.spec.ts`, `tests/e2e/confirm-dialog.spec.ts`

**Intent**: Move the assertions from table rows and cells to the tiles while keeping what they check: the member appears with its date and weight, editing renames it, and deleting removes it.

**Contract**:
- Rows become `page.getByRole('article', { name: /…/ })`.
- „1 maja 1990” becomes the tile's meta line „Kobieta · ur. 1 maj 1990”.
- „60 kg” becomes the „Waga” fact reading 60 with unit kg: `createdTile.getByRole('definition').filter({ hasText: /^60\s*kg$/ })` is visible. Use a role/text locator, not classes.
- In `confirm-dialog.spec.ts`, `getByRole('cell', { name, exact: true })` becomes `getByRole('heading', { name, exact: true, level: 2 })`.
- Everything else stays byte-identical.

#### 7. Pinned visual family

**Files**: `database/seeders/E2eMealPlanSeeder.php`, `tests/Unit/Seeders/E2eMealPlanSeederTest.php`

**Intent**: Give the family screenshots a user whose data is stable against the frozen clock (2026-06-15) without touching the planner family that drives scaling.

**Contract**:
- New `public const FAMILY_VISUAL_EMAIL = 'e2e-family-visual@example.com'`, with the same password and the same idempotent create-or-update as the other e2e users.
- On every run its family is deleted and recreated as:

  | Name | Gender | Born | Weight | Height | Tile colour | Age on 2026-06-15 |
  | --- | --- | --- | --- | --- | --- | --- |
  | Anna | female | 1987-07-04 | 64 | 168 | supper | 38 |
  | Jan | male | 1985-03-12 | 82 | 180 | dinner | 41 |
  | Zosia | female | 2019-09-01 | null | null | breakfast | 6 |

- It gets no recipes or plans.
- Update the seeder's class docblock.
- Add one Pest test: after seeding twice, the user has exactly those three members with those values.

#### 8. Kitchen sink

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: Demo the new pattern and the new button size with their states (§5.4).

**Contract**:
- In „Komponenty aplikacji”, add three `FamilyMemberTile` demos in a grid (adult supper, adult dinner, child breakfast with „—”) followed by the dashed `size="tile"` button. The data is static. `design-system.spec.ts` does **not** freeze the clock, unlike the other visual specs, so every demo tile gets `:today="new Date(2026, 5, 15)"`. Its ages (38, 41, 6) then do not change with the real date and do not break the baseline on a birthday. Do not add `page.clock` to `design-system.spec.ts`.
- Exclude `tile` from the generic `buttonSizes` loop (:169) so „Przyciski” does not render a 230 px button per variant.
- No new section; the total stays 14.

#### 9. Visual spec and baselines

**Files**: `tests/e2e/visual/family-members.spec.ts` (new), `tests/e2e/__screenshots__/visual/family-members.spec.ts/*.png`, `tests/e2e/__screenshots__/visual/design-system.spec.ts/*.png`

**Intent**: Guard ekran 13 like the other restyled views.

**Contract**:
- Same skip guard and fixed clock as `account.spec.ts`. Log in with `logIn(page, 'e2e-family-visual@example.com')`, go to `/family-members`, call `expectAppFontLoaded`, wait for the h1 „Członkowie rodziny”, move the mouse to (0, 0), and take a full-page shot.
- Shots:
  - `family-members-desktop.png`
  - `family-members-mobile.png` at 390×844
  - `family-members-empty.png`, logged in as `e2e-dashboard-empty-visual@example.com` and waiting for the heading „Brak członków rodziny”
- **Expected changed baselines:** the 3 new family PNGs and `section-komponenty-aplikacji.png`, plus anti-aliasing-only noise on sections below it (`section-jadlospis.png`). Any other changed PNG is a regression to fix, not to accept.

#### 10. Docs

**Files**: `context/foundation/design-system.md`, `CLAUDE.md`

**Intent**: Keep the contract docs true.

**Contract**:
- `design-system.md`:
  - §5.1 `button`: add size `tile`.
  - §5.3: add `FamilyMemberTile` (avatar colour by position, facts with „—”) and the dashed add tile.
  - §7: add the seventh spec, `family-members.spec.ts`, with its 3 shots and the `e2e-family-visual` user; the total goes from 58 to 61.
- `CLAUDE.md`: in the Visual gate line, add the family members page and the `e2e-family-visual@example.com` user with its pinned family.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token lint passes and its self-test passes: `vendor/bin/sail npm run lint:tokens` and `bash scripts/lint-tokens.test.sh`
- Removed family pieces are gone: `grep -rn -e FamilyMemberTable -e FamilyMemberPagination -e pluralizePl resources/js tests/e2e` returns nothing
- Seeder test passes: `vendor/bin/sail pest tests/Unit/Seeders/E2eMealPlanSeederTest.php`
- Pest suite passes: `vendor/bin/sail composer test`
- Larastan and Pint pass: `vendor/bin/sail composer phpstan` and `vendor/bin/sail composer pint-test`
- Full E2E suite passes after re-seeding: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder` then `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} MAILPIT_URL=http://localhost:${FORWARD_MAILPIT_DASHBOARD_PORT} npm run test:e2e`
- Visual gate is green after a deliberate refresh against this worktree's stack (`VISUAL_DOCKER_NETWORK=<COMPOSE_PROJECT_NAME>_sail npm run test:visual -- --update-snapshots`, then the same without the flag), with only the PNGs listed in change 9 changed

#### Manual Verification:

- `/family-members` matches ekran 13 at 1280 px (three tiles in a row, colours supper/dinner/breakfast, fact boxes, dashed tile on the second row) and stays usable at 1024 and 390 px (2 then 1 column, no horizontal page scroll)
- Zosia's tile shows „6 lat” and „—” for weight and height without breaking the facts grid; a member under one year shows „0 lat”
- Keyboard: Tab reaches „Edytuj”, „Usuń” and the dashed tile with a visible 2px ring; the dashed tile and „Dodaj członka” open the create dialog, „Edytuj” opens it prefilled, „Usuń” opens the confirm
- With 16+ members the pagination appears under the grid and keeps the page after edit and delete; the empty user sees only the EmptyState with its CTA
- Every changed baseline PNG reviewed in the diff, with anti-aliasing-only shots confirmed as noise

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Profil w trzech kaflach

### Overview

Ekran 14: the `Card destructive` variant, a 720 px column of three tiles with paired fields and a confirmed „Zapisano.”, the `profile-mobile` shot, a refreshed `profile-desktop`, and the docs.

### Changes Required:

#### 1. Card variant

**Files**: `resources/js/Components/ui/card/Card.vue`, `resources/js/Components/ui/card/CardTitle.vue`, `resources/js/Components/ui/card/index.js` (the `provide` key, and `cardVariants` if they live there)

**Intent**: Give the irreversible zone its own primitive state instead of having a view recolour a `Card` (§5.1, ekran 14).

**Contract**:
- `Card` takes `variant?: 'default' | 'destructive'` (cva, default `default`).
- `destructive` swaps the outline to `border-destructive` and the sticker shadow colour to `destructive` (`shadow-sticker shadow-destructive`).
- `Card` passes its variant down with `provide` (a key exported from `ui/card/index.js`). `CardTitle` reads it with `inject` (default `'default'`) and adds `text-destructive` for `destructive`, so the call site needs no class. This adds no attribute or class to the DOM.
- `default` renders the same DOM and pixels as today, with no baseline change for any of Card's 11 importers. Radius, background and padding are unchanged in both variants.

#### 2. Profile page and partials

**Files**: `resources/js/Pages/Profile/Edit.vue`, `resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue`, `resources/js/Pages/Profile/Partials/UpdatePasswordForm.vue`, `resources/js/Pages/Profile/Partials/DeleteUserForm.vue`

**Intent**: Lay the page out as ekran 14 while keeping every region, label, flow and message the specs rely on.

**Contract**:
- **Container:** `mx-auto flex w-full max-w-180 flex-col gap-5`.
- **Tile titles:** `CardTitle as="h2"`, with an icon where the mockup has one: `User` for „Dane profilu”, `Trash2` for „Usuń konto”, both `aria-hidden` and laid out as a flex row with an 8 px gap.
- **Paired fields:** two-column grids (`sm:grid-cols-2`, stacked below `sm`) for Imię with Adres e-mail, and for Nowe hasło with Potwierdź hasło. Obecne hasło stays full width.
- **„Zapisano.”:** keeps its `Transition` and text, and gains a `Check` icon. Its text is `text-success-foreground`, sized `text-meta` with weight 600.
- **Delete tile:** uses `Card variant="destructive"`. The `destructive` „Usuń konto” button and the `ConfirmDialog` with the password field are unchanged.

#### 3. Kitchen sink

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: Demo the new card variant next to the default one.

**Contract**: in „Karta i tabela”, add a `Card variant="destructive"` with a title, a description and a `destructive` button beside the existing card. No new section.

#### 4. Visual spec and baselines

**Files**: `tests/e2e/visual/account.spec.ts`, `tests/e2e/__screenshots__/visual/account.spec.ts/*.png`, `tests/e2e/__screenshots__/visual/design-system.spec.ts/*.png`

**Intent**: Guard the profile on desktop and on a phone.

**Contract**:
- Add `profile mobile`: same steps as `profile`, at 390×844, saved as `profile-mobile.png`.
- **Expected changed baselines:**
  - `profile-desktop.png` (refreshed)
  - `profile-mobile.png` (new)
  - `section-karta-i-tabela.png`
  - anti-aliasing-only noise on the sections below it
- Guest shots must not change.

#### 5. Docs

**File**: `context/foundation/design-system.md`

**Intent**: Record the new primitive state and the final shot count.

**Contract**:
- §5.1 `card` row: variant `destructive` (outline, sticker shadow and title in `destructive`; the danger zone).
- §7: `account.spec.ts` grows to 4 shots (`profile-mobile`), and the total goes from 61 to 62.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token lint passes and its self-test passes: `vendor/bin/sail npm run lint:tokens` and `bash scripts/lint-tokens.test.sh`
- Pest suite passes unchanged: `vendor/bin/sail composer test`
- Full E2E suite passes after re-seeding, with `profile.spec.ts` untouched: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder` then `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} MAILPIT_URL=http://localhost:${FORWARD_MAILPIT_DASHBOARD_PORT} npm run test:e2e`
- Visual gate is green after a deliberate refresh against this worktree's stack (`VISUAL_DOCKER_NETWORK=<COMPOSE_PROJECT_NAME>_sail npm run test:visual -- --update-snapshots`, then the same without the flag), with only the PNGs listed in change 4 changed and 62 shots in total

#### Manual Verification:

- `/profile` matches ekran 14 at 1280 px (720 px column, paired fields, icons, red outline, shadow and title on „Usuń konto”) and stacks cleanly at 390 px with no horizontal scroll
- Saving name/e-mail and password shows „Zapisano.” with the check icon; a wrong current password still shows its error under the field
- „Usuń konto” opens the confirm dialog with the password field; a wrong password keeps it open with the error and focus on the field
- Every changed baseline PNG reviewed in the diff, with anti-aliasing-only shots confirmed as noise

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful.

---

## Testing Strategy

### Unit Tests:

- `E2eMealPlanSeederTest`: the family visual user has exactly Anna, Jan and Zosia with the pinned values, and stays that way across repeated seeding.
- Existing `FamilyMemberTest` and `ProfileTest` stay unchanged. There is no JS unit runner in the repo, so `formatDate` and `ageInYears` are covered through E2E and the visual shots: the meta line, ages 38/41/6 and „—”.

### Integration Tests:

- `family-members.spec.ts` and `confirm-dialog.spec.ts`: create, edit and delete through the tiles.
- `profile.spec.ts` runs untouched.
- Visual gate: 3 family shots, 2 profile shots and the kitchen-sink sections.

### Manual Testing Steps:

1. Seed, log in as `e2e-family-visual@example.com`, and compare `/family-members` side by side with ekran 13 at 1280, 1024 and 390 px.
2. Add a child born last month: the tile shows „0 lat” and „—”. Add members up to 16 and page through.
3. Log in as the empty user: only the EmptyState is shown, and its CTA opens the dialog.
4. Open `/profile`, save each form, and walk the delete dialog with a wrong password, using the keyboard only.

## Performance Considerations

None. The change is presentational, uses the same props, and the query is unchanged.

## Migration Notes

There is no schema change. The new e2e user is created by the idempotent seeder. In the main checkout, `context/foundation/roadmap.md` has uncommitted edits (S-23 to S-26 notes). Commit them on `develop` before merging this branch, because both touch the roadmap.

## References

- Roadmap entry: `context/foundation/roadmap.md` S-26 (carry-overs F-05 `Index.vue:61`, F-06 dashed tile)
- Design: `context/foundation/design-system.md` §5.1, §5.3, §7, §10.2–10.5; artifact screens 13–14 (`project/components/Spizarnia13FamilyMembers`, `Spizarnia14Profile`, `bundle.css` `.s-fam`, `.s-mem`, `.s-facts`, `.s-new`, `.s-prof`, `.s-danger`)
- Similar implementation: `context/archive/2026-11-17-spizarnia-meal-plans-ui/plan.md` (tiles, per-phase baselines, selector constraints)
- Lessons: `context/foundation/lessons.md` (point E2E and the visual gate at the worktree's own Sail stack)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Członkowie rodziny jako kafle

#### Automated

- [x] 1.1 Client and SSR bundles build: `vendor/bin/sail npm run build` — 1d5a21c
- [x] 1.2 Token lint passes and its self-test passes: `vendor/bin/sail npm run lint:tokens` and `bash scripts/lint-tokens.test.sh` — 1d5a21c
- [x] 1.3 Removed family pieces are gone: `grep -rn -e FamilyMemberTable -e FamilyMemberPagination -e pluralizePl resources/js tests/e2e` returns nothing — 1d5a21c
- [x] 1.4 Seeder test passes: `vendor/bin/sail pest tests/Unit/Seeders/E2eMealPlanSeederTest.php` — 1d5a21c
- [x] 1.5 Pest suite passes: `vendor/bin/sail composer test` — 1d5a21c
- [x] 1.6 Larastan and Pint pass: `vendor/bin/sail composer phpstan` and `vendor/bin/sail composer pint-test` — 1d5a21c
- [x] 1.7 Full E2E suite passes after re-seeding — 1d5a21c
- [x] 1.8 Visual gate is green after a deliberate refresh against this worktree's stack, with only the PNGs listed in change 9 changed — 1d5a21c

#### Manual

- [ ] 1.9 `/family-members` matches ekran 13 at 1280 px and stays usable at 1024 and 390 px
- [ ] 1.10 Zosia's tile shows „6 lat” and „—” for weight and height; a member under one year shows „0 lat”
- [ ] 1.11 Keyboard: Tab reaches „Edytuj”, „Usuń” and the dashed tile with a visible ring; each opens the right dialog
- [ ] 1.12 With 16+ members the pagination appears and keeps the page after edit and delete; the empty user sees only the EmptyState
- [ ] 1.13 Every changed baseline PNG reviewed in the diff, with anti-aliasing-only shots confirmed as noise

### Phase 2: Profil w trzech kaflach

#### Automated

- [x] 2.1 Client and SSR bundles build: `vendor/bin/sail npm run build` — 846bbee
- [x] 2.2 Token lint passes and its self-test passes: `vendor/bin/sail npm run lint:tokens` and `bash scripts/lint-tokens.test.sh` — 846bbee
- [x] 2.3 Pest suite passes unchanged: `vendor/bin/sail composer test` — 846bbee
- [x] 2.4 Full E2E suite passes after re-seeding, with `profile.spec.ts` untouched — 846bbee
- [x] 2.5 Visual gate is green after a deliberate refresh against this worktree's stack, with only the PNGs listed in change 4 changed and 62 shots in total — 846bbee

#### Manual

- [ ] 2.6 `/profile` matches ekran 14 at 1280 px and stacks cleanly at 390 px
- [ ] 2.7 Saving name/e-mail and password shows „Zapisano.” with the check icon; a wrong current password still shows its error
- [ ] 2.8 „Usuń konto” opens the confirm dialog; a wrong password keeps it open with the error and focus on the field
- [ ] 2.9 Every changed baseline PNG reviewed in the diff, with anti-aliasing-only shots confirmed as noise
