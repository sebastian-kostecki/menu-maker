# Recipes UI (S-11 / DS-06) Implementation Plan

## Overview

Migrate the three recipe views — list (`Pages/Recipes/Index.vue`), details (`Show.vue`) and create/edit form (`Form.vue`) with their components in `Components/recipes/` — onto the design-system contract (`context/foundation/design-system.md`): tokens instead of Tailwind palette literals, shared components, lucide icons, Polish copy and pl-PL number/date formatting. This change also builds the deferred shared components `Common/PageHeader` and `Common/EmptyState` (for S-09/S-10 to consume) and gates the three real pages with deterministic screenshots.

Roadmap: M-2, slice S-11 (`context/foundation/roadmap.md`), prerequisite S-07 (done). No backend behaviour changes; the only PHP touched is the E2E seeder.

## Current State Analysis

Researched on `develop` at `72ab6b0` (this branch was rebased onto it before planning).

- **Literals:** the 22 recipe files hold **118** scanner hits (95 palette, 0 hex, 8 arbitrary, 15 `dark:`); only `recipes/CategoryBadge.vue` is already in `scripts/lint-tokens.paths`. Worst: `RecipeRow.vue` (25), `Index.vue` (16), `RowActionDropdown.vue` (11, dead), `Show.vue` (11), `RecipeForm.vue` (10), `RecipesTable.vue` (15 incl. 6 arbitrary widths). No Breeze components are used anywhere in recipes.
- **Hand-written `<svg>`:** 8 — `Index.vue:45`, `Show.vue:25`, `ActionBar.vue:9,21`, `RecipeMetaBadges.vue:4,11`, `IngredientCardGrid.vue:12`, `InstructionsSection.vue:15`.
- **Structure:**
  - `Form.vue:1-25` renders outside the app — no `AuthenticatedLayout`, no `<Head>`, own `min-h-screen bg-gray-100`, `max-w-4xl`.
  - `Show.vue:7-35` puts breadcrumbs, an `<h2>Recipe Details`, a Back link and `ActionBar` in `#header`; the page `<h1>` lives in the body (`RecipeHeader.vue:4`, `text-3xl font-bold`, off the §4.5 scale); body adds `py-12 sm:px-6 lg:px-8` on top of the layout's `p-6`.
  - `Index.vue:43-55` and `RecipesTable.vue:55-59` both render an empty message at once ("Brak przepisów" with svg + "No recipes found"), and the "add your first recipe" text also shows when a search merely has no hits.
  - Row navigation is `<button @click="router.visit">` (`RecipeRow.vue:4-10`), not a link.
- **Copy & formatting:** ~20 English strings (table headers, toolbar, Show headings, `cal`/`serving(s)`, empty states, `View`/`Edit` aria-labels, Show `<Head>` "Recipe: …"); `RecipeRow.vue:91` formats dates `en-US`; `RecipeMetaBadges.vue:7` prints the raw `decimal:2` string ("350.00 cal") into a `Number` prop; `IngredientCard.vue:22-26` has its own dot-decimal `formatQuantity`. `resources/js/lib/` has no pl-PL formatter although §4.5 requires one. Index's plural ternary (`Index.vue:14`) is wrong for 22–24 ("przepisów").
- **Dead code:** `Components/recipes/RowActionDropdown.vue` is imported nowhere.
- **Form errors:** `RecipeForm.vue:13-98` repeats raw `<label>` + `border-red-500`/`text-red-600` five times without `aria-invalid`/`aria-describedby`; `IngredientRow.vue` already wires both and only needs token colours. `ui/input` has no built-in invalid style; the kitchen sink "Formularze" section shows the token pattern (`aria-invalid`, `aria-describedby`, `text-destructive`).

### Charges

| # | Category | Evidence | User impact | Addressed in |
| --- | --- | --- | --- | --- |
| C1 | Missing tokens | 118 literal hits across `Pages/Recipes`, `Components/recipes` (e.g. `RecipeRow.vue:30,39,48` blue/amber/red hovers + `dark:`) | Recipes look like the old grey admin panel next to the green shell; brand changes need per-file edits | Phases 2–4 |
| C2 | Missing shared component | Double empty state `Index.vue:43-55` + `RecipesTable.vue:55-59`; three ad-hoc headers (`Index.vue:5-19`, `Show.vue:5-35`, `Form.vue:6-10`) | User sees two contradicting empty messages; "add your first recipe" after an empty search suggests recipes were lost | Phase 1 (components), Phases 2–4 (use) |
| C3 | Accidental architecture | `Form.vue:1-25` outside `AuthenticatedLayout` | The form is the only page without navigation; user cannot leave it except by "Anuluj" | Phase 4 |
| C4 | Accidental architecture | Heading hierarchy: Show `<h2>` in header + `<h1>` in body; Index `h1→h3`; RecipeForm `h1→h3` | Screen-reader outline is wrong; page title not where the shell expects it | Phases 2–4 |
| C5 | Missing tokens (copy/format) | English copy, `en-US` dates, "350.00 cal" (`RecipeRow.vue:91`, `RecipeMetaBadges.vue:7`) | Polish user sees mixed languages and raw decimals | Phases 1–4 |

## Desired End State

- `/recipes`, `/recipes/{id}`, `/recipes/create` and `/recipes/{id}/edit` render inside the app shell with one `PageHeader` `<h1>` each, token-only styling, lucide icons, Polish copy and pl-PL numbers/dates.
- `/recipes` shows exactly one empty state: **"Brak przepisów"** + CTA **"Dodaj przepis"** when the user has no recipes and no filters; **"Brak wyników"** + CTA **"Wyczyść filtry"** when a search/category filter (or an out-of-range page) yields nothing.
- `Common/PageHeader`, `Common/EmptyState` and `lib/format.js` exist, are demoed in the kitchen sink, and are documented as the components S-09/S-10 consume.
- `scripts/lint-tokens.paths` covers `resources/js/Pages/Recipes` and `resources/js/Components/recipes` as directories; `npm run lint:tokens` is green.
- `tests/e2e/visual/recipes.spec.ts` gates list, no-results, details and create form at desktop and 390px against seeded, deterministic data; existing E2E specs pass unchanged.

Verify: `vendor/bin/sail npm run lint:tokens`, `npm run test:visual` (twice, against this worktree's stack), full E2E and Pest green; manual 7-state walk (Phase 5).

### Key Discoveries:

- `AuthenticatedLayout.vue:69-72` renders `#header` inside the sticky bar (`flex-1` wrapper, no `min-w-0`) and falls back to `<h1>Menu Maker</h1>` when the slot is empty — so every page must fill `#header` with its `PageHeader`, as `Dashboard.vue:13-17` does.
- `RecipeController.php:102-105` passes Show's `recipe` **unwrapped** (`resolve()`), while edit (`:115-120`) passes it **wrapped** (`recipe.data`) — `Form.vue:8` relies on that asymmetry; keep it.
- `config/inertia.php` `testing.ensure_pages_exist` is on and `RecipeControllerTest` asserts `Recipes/Index`, `Recipes/Show`: page files must not move or be renamed.
- E2E invariants (`tests/e2e/recipe-form.spec.ts`, `tests/e2e/confirm-dialog.spec.ts`):
  - heading "Nowy przepis" (`:13`); labels "Nazwa przepisu", "Kategoria", "Sposób przygotowania", "Kaloryczność całkowita (kcal)", "Liczba porcji" linked by `for`/`id`; buttons "Zapisz przepis" (natively `disabled` until valid, `:22,31`), "Dodaj składnik";
  - `getByRole('combobox', { name: 'Składnik' })` (`:26`) works only because the name field is `<input list>` + `<datalist>`; `getByLabel('Ilość')` / `getByLabel('Jednostka')` are substring matches — no new accessible name may contain "Ilość", "Jednostka" or "Składnik";
  - `getByText('Kasza z E2E').first().click()` must navigate (`:34-36`) — the name stays a clickable element;
  - `getByText('Kasza jaglana E2E', { exact: true })` and `getByText('200 g', { exact: true })` (`:38-39`) — ingredient name and "quantity unit" each stay the whole text of one element;
  - on `/recipes`, `getByRole('button', { name: /^Usuń / })` counts one delete button per row and reads its `aria-label` (`confirm-dialog.spec.ts:15-37`) — no other visible control may have a name starting with "Usuń ".
- `Intl.NumberFormat('pl-PL', { maximumFractionDigits: 2 })` gives `200`/`"200.00"` → `"200"`, `1.5` → `"1,5"`, `1000` → `"1000"` (CLDR minimum grouping digits 2), `10000` → `"10 000"` with a non-breaking space. Dates `{ day: 'numeric', month: 'short', year: 'numeric' }` → `"1 cze 2026"`.
- `E2eMealPlanSeeder.php:61-71` tops the visual user up with `Recipe::factory()` (faker names/servings/instructions, created only when missing); ingredients (`syncFixedIngredients`) and calories (`syncPinnedCalories`) are already pinned. Default list sort is `created_at desc` with no tie-breaker (`RecipeController.php:47-49`), 15 per page.
- Visual gate (`scripts/visual-test.sh`, `playwright.config.ts` project `visual`): Docker-pinned chromium, zero tolerance, `updateSnapshots` on the project is ignored — new baselines only via `npm run test:visual -- --update-snapshots`; `public/hot` must be absent. Adding anything to a kitchen-sink section has twice shifted `overlay-dialog.png`/`overlay-alert-dialog.png` corner pixels (S-07, S-08).
- `useDeleteRecipe.ts` already settles on every outcome (S-07 fix) and Show closes the dialog in `finally` — the restyle must not touch that flow.

## What We're NOT Doing

- No `Common/FormField` — it stays with S-13 (`auth-profile-ui`); `RecipeForm` gets inline token error markup instead.
- No changes to `Components/ui/*`, `AuthenticatedLayout.vue`, `Common/ConfirmDialog|ServerPagination|SortableHeader`, `CategoryBadge.vue`.
- No backend, controller, resource, route or validation changes; no change to sorting, filtering, pagination or the localStorage filter sync.
- No migration of MealPlans/FamilyMembers views to `lib/format.js` (their own slices S-09/S-10/S-12).
- No replacement of the ingredient name `<input list>`/`<datalist>` with a combobox; no change to the unit label (`unit.code` on Show, `szt.` in the form select).
- No change to `IngredientManager`'s inline "Nie dodano jeszcze składników." hint (already on tokens).
- No dark mode (D-1): `dark:` classes are deleted, not translated.
- No change to the submit button's native `disabled` (E2E asserts it) or to `useDeleteRecipe`.

## Implementation Approach

Shared pieces first (Phase 1), so every view phase only consumes them. Then one view per phase — list, details, form — each leaving its files literal-free, added to `lint:tokens`, and E2E green. Screenshots of real pages come last (Phase 5) together with the seeder change that makes them deterministic, so all new real-page PNGs land in one deliberate commit; Phase 1 updates only the kitchen-sink baselines its demos shift.

Decisions recorded during planning: one change with a phase per view; `PageHeader`/`EmptyState` built here and consumed by S-09/S-10; `PageHeader` goes into the layout's `#header` slot; deterministic visual seed + real-page spec; inline token errors (no `FormField`); Polish copy + `lib/format.js`; form inside the app shell; two empty-state variants. Small calls made from patterns: the Show "Back to Recipes" button is dropped (the breadcrumb "Przepisy" covers it); `RecipeHeader.vue` and `RowActionDropdown.vue` are deleted.

## Environment (prerequisite for every phase)

Every `sail` command, E2E run and visual run must exercise this worktree's code. Sail serves the checkout it was started from, and `scripts/visual-test.sh` mounts the checkout it runs from. The worktree `.claude/worktrees/recipes-ui` starts with no `vendor/`, `node_modules/` or `.env`, so it needs its own Sail stack:

- Copy `.env` from the main checkout; give it its own `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT`, `FORWARD_MAILPIT_DASHBOARD_PORT` and a matching `MAILPIT_URL`. Keep `AI_SCALING_DRIVER=fake`, `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`.
- Install `vendor/` with the Sail Composer container (not host PHP), then `vendor/bin/sail up -d`, `vendor/bin/sail npm ci`, `vendor/bin/sail artisan migrate`.
- Visual gate against this stack: `VISUAL_DOCKER_NETWORK=recipes-ui_sail VISUAL_BASE_URL=http://laravel.test npm run test:visual` (Compose project name = directory name). Below, `npm run test:visual` always means this form.
- Precheck before Phase 1: `vendor/bin/sail ps` lists `recipes-ui-*` containers and `curl -s -o /dev/null -w '%{http_code}' http://localhost:${APP_PORT}/login` prints `200`.
- Before any visual run: stop `npm run dev` (no `public/hot`) and run `vendor/bin/sail npm run build`. Before any E2E or visual run: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`.

## Critical Implementation Details

- **Sticky header width.** The `#header` wrapper in `AuthenticatedLayout` is `flex-1` without `min-w-0`; `PageHeader`'s root must be `min-w-0` and the title must wrap (`break-words`), or long recipe names push `UserMenu` off-screen at 390px.
- **Inertia `<Link>` inside `Button as-child`.** Use `<Button as-child><Link :href="route(...)">…</Link></Button>` for navigation. `<Link>` overwrites a passed `@click`; put any side effect in `@start`, never `@click`.
- **Empty-state predicate uses server filters.** Decide the variant from `props.filters` (search/category as returned by the server), not the local `filterState`, which changes before the 300 ms debounced request.
- **Seeder timestamps.** Set visual recipes' `created_at` at 12:00 UTC on distinct days so `created_at desc` has no ties and the pl-PL date is the same in any time zone within ±11 h; Eloquent overwrites timestamps on save unless they are force-filled with timestamps disabled for that write.

## Phase 1: Shared pieces

### Overview

Create `Common/PageHeader`, `Common/EmptyState` and `lib/format.js`, demo them in the kitchen sink, refresh only the baselines the demo shifts, and record ownership so S-09/S-10 consume instead of rebuilding.

### Changes Required:

#### 1. PageHeader

**File**: `resources/js/Components/Common/PageHeader.vue` (new)

**Intent**: One page-title block for every view, placed in `AuthenticatedLayout`'s `#header` slot, replacing hand-written headers and guaranteeing exactly one `<h1>`.

**Contract**: Props `title: string` (required), `description?: string`. Slots `breadcrumbs` (wrapped by PageHeader in `<nav aria-label="Ścieżka nawigacji">`, rendered above the title only when provided), `description` (overrides the prop; rendered in a `<div>` under the title, `text-sm text-muted-foreground`, may hold badges), `actions` (right of the title on `sm+`, below it on mobile, `gap-2`). Title is `<h1 class="text-2xl font-semibold tracking-tight">` (§4.5), wraps on long text. Root `min-w-0`. Tokens only.

#### 2. EmptyState

**File**: `resources/js/Components/Common/EmptyState.vue` (new)

**Intent**: One empty-state pattern (icon, title, description, CTA) for lists and sections.

**Contract**: Props `icon?: Component` (lucide, rendered `size-10 text-muted-foreground`, `aria-hidden`), `title: string` (required), `description?: string`, `headingLevel?: 2 | 3` (default 2). Slot `action` (CTA, rendered below the description). Centered, `py-12`, `text-lg font-semibold` title, `text-sm text-muted-foreground` description. Works standalone and inside a table cell. Tokens only.

#### 3. pl-PL formatter

**File**: `resources/js/lib/format.js` (new)

**Intent**: The single number/date/plural helper §4.5 asks for.

**Contract**: `formatNumber(value, options?)` — accepts number or numeric string, `Intl.NumberFormat('pl-PL', { maximumFractionDigits: 2, ...options })`, returns `''` for `null`/`''`/`NaN`. `formatDate(value)` — ISO string or Date → `Intl.DateTimeFormat('pl-PL', { day: 'numeric', month: 'short', year: 'numeric' })`. `pluralize(count, [one, few, many])` — Polish rule: `1` → one; last digit 2–4 except 12–14 → few; else many. Formatters created once at module level.

#### 4. Kitchen sink demos

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: Shared patterns get a demo with their states (CLAUDE.md rule).

**Contract**: In the existing "Komponenty aplikacji" section (no new section, so the 13-section list in `design-system.spec.ts` stays): a `PageHeader` with breadcrumbs, description (with a `CategoryBadge`) and two action buttons, and a title-only `PageHeader`; an `EmptyState` with icon + description + CTA button ("Dodaj przepis") and one without CTA ("Brak wyników"). Static fixtures; no new accessible name may contain a string that `design-system.spec.ts` matches non-exactly page-wide ("Otwórz dialog", "Otwórz potwierdzenie", "Otwórz panel", "Otwórz menu", "Menu użytkownika", "Pokaż powiadomienie"), and the demo `PageHeader` title must not contain "Design system". The demo `PageHeader` adds a second `<h1>` to the dev page; `openKitchenSink` matches `{ level: 1, name: 'Design system' }`, which stays unique.

#### 5. Baselines

**File**: `tests/e2e/__screenshots__/visual/design-system.spec.ts/*.png`

**Intent**: Refresh exactly what the demo shifts, deliberately.

**Contract**: `npm run test:visual -- --update-snapshots`; expected modified: `section-komponenty-aplikacji.png`; possibly the `overlay-*` dialog PNGs (page height behind the overlay corners). Every modified PNG is reviewed and named in Migration Notes with its reason; no other PNG changes.

#### 6. Lint paths

**File**: `scripts/lint-tokens.paths`

**Intent**: The new files are scanned.

**Contract**: Append `resources/js/lib/format.js` (`Components/Common` is already a directory entry).

#### 7. Docs, rule, roadmap ownership

**Files**: `context/foundation/design-system.md`, `CLAUDE.md`, `context/foundation/roadmap.md`

**Intent**: Record the contracts and ownership so parallel slices consume them.

**Contract**:
- `design-system.md` §5.2: `PageHeader` and `EmptyState` rows change from "Odroczony" to "Zrealizowany w S-11 (`recipes-ui`)" with the contracts above (PageHeader in `#header`); §4.5: the formatter is `resources/js/lib/format.js`; §8: rows DS-04/DS-05 say they consume `PageHeader`/`EmptyState` from DS-06 (`recipes-ui`), row DS-06 says it builds them (replacing "pierwszy, kto potrzebuje").
- `CLAUDE.md` "Structure & Conventions" reuse bullet: add `Common/PageHeader` (in the layout's `#header` slot), `Common/EmptyState` and `lib/format.js` (pl-PL numbers, dates, plurals).
- `roadmap.md`: S-09 and S-10 unknowns about `PageHeader`/`EmptyState` → "Buduje je S-11 (`recipes-ui`); S-09/S-10 ich używają."; S-11's two unknowns marked resolved (one change, phase per view; components built here).

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Token scan green with the new files listed: `vendor/bin/sail npm run lint:tokens`
- Kitchen sink page test passes: `vendor/bin/sail pest --filter=DesignSystemPageTest`
- Visual gate passes after the deliberate update: `npm run test:visual`
- Baseline delta limited: `git status --porcelain -uall tests/e2e/__screenshots__` lists only ` M` entries for `section-komponenty-aplikacji.png` and, if any, `overlay-*` dialog PNGs — no `??`, no other file

#### Manual Verification:

- `/dev/design-system`: PageHeader demo wraps cleanly at 390px; EmptyState demos centred, icon decorative
- Each modified PNG reviewed in the diff and its reason recorded in Migration Notes

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Recipe list

### Overview

Migrate `/recipes` to tokens, `PageHeader`, a single two-variant `EmptyState`, links and Polish copy; delete the dead dropdown.

### Changes Required:

#### 1. Page

**File**: `resources/js/Pages/Recipes/Index.vue`

**Intent**: Shell header via `PageHeader`, token body, one empty state with the right message.

**Contract**:
- `#header`: `<PageHeader title="Przepisy">` with description "Zarządzaj swoją kolekcją przepisów" plus, when `meta.total > 0`, `({total} {pluralize(total, ['przepis','przepisy','przepisów'])})`.
- Body: `mx-auto flex max-w-6xl flex-col gap-6`; no outer white/shadow card; toolbar, table, pagination.
- States: `noRecipes = meta.total === 0 && !(filters.search || filters.category)` → `EmptyState` (`ChefHat`, "Brak przepisów", "Dodaj pierwszy przepis, aby zaplanować tydzień.", CTA `Button as-child` Link "Dodaj przepis" → `recipes.create`), toolbar, table and pagination hidden. `noResults = data.length === 0 && !noRecipes` → toolbar visible, `EmptyState` (`SearchX`, "Brak wyników", "Na tej stronie nie ma przepisów lub żaden nie pasuje do filtrów.", CTA outline "Wyczyść filtry" → existing `handleReset`), table and pagination hidden. Otherwise table + pagination.
- Remove the inline svg empty block and unused `usePage`/`watch` imports. Delete flow and `ConfirmDialog` unchanged.

#### 2. Table

**File**: `resources/js/Components/recipes/RecipesTable.vue`

**Intent**: Token table without its own empty row (the page owns empty states).

**Contract**: Wrapper `rounded-lg border bg-card` (overflow-x-auto for 390px); header row `bg-muted`; widths `w-75`, `w-30`, `w-25` (no arbitrary values); Polish headers "Nazwa", "Kategoria", "Kalorie", "Porcje", "Dodano", "Akcje"; calories/servings headers and cells right-aligned. Remove the "No recipes found" row and all `dark:`. Props/emits unchanged.

#### 3. Row

**File**: `resources/js/Components/recipes/RecipeRow.vue`

**Intent**: Real links, token hovers, Polish labels and formatting.

**Contract**:
- Name: Inertia `<Link :href="route('recipes.show', id)">`, `font-medium hover:text-primary hover:underline`, visible focus ring; text is exactly the recipe name.
- Calories `${formatNumber(calories)} kcal`, servings, date `formatDate(created_at)`; numbers `tabular-nums text-right`; metadata `text-muted-foreground`.
- Actions: `Button variant="ghost" size="icon" as-child` Links with `aria-label` "Pokaż {name}" (`Eye`) and "Edytuj {name}" (`Pencil`); delete stays a `<button>` with `aria-label="Usuń {name}"` (`Trash2`, `hover:bg-destructive/10 hover:text-destructive`). Icons `size-4`. No other control in the row may have a name starting with "Usuń ". Row hover `hover:bg-muted/50`.

#### 4. Toolbar parts

**Files**: `resources/js/Components/recipes/RecipesToolbar.vue`, `SearchInput.vue`, `CategoryFilterDropdown.vue`, `ResetFiltersButton.vue`, `CreateRecipeButton.vue`

**Intent**: Tokens, no arbitrary widths, Polish copy, link for create.

**Contract**: `min-w-75` / `min-w-50`; search placeholder "Szukaj przepisów…", `aria-label="Szukaj przepisów"`, icon `text-muted-foreground`; category "Wszystkie kategorie", trigger `aria-label="Filtruj według kategorii"`, placeholder "Wybierz kategorię"; reset label "Wyczyść filtry"; `CreateRecipeButton` becomes `Button as-child` Link to `recipes.create` labelled "Dodaj przepis". English code comments in these files (`CategoryFilterDropdown.vue:54` "All Categories", `RecipesToolbar.vue:23` "Reset Filters") are translated to Polish or removed, so the copy grep (2.4) stays green. Events and v-model contracts unchanged.

#### 5. Dead code

**File**: `resources/js/Components/recipes/RowActionDropdown.vue` (delete)

**Intent**: Remove 11 literals nobody renders.

**Contract**: File removed; no imports reference it.

#### 6. Lint paths

**File**: `scripts/lint-tokens.paths`

**Intent**: Lock the migrated list files.

**Contract**: Append `resources/js/Pages/Recipes/Index.vue`, `resources/js/Components/recipes/RecipesTable.vue`, `RecipeRow.vue`, `RecipesToolbar.vue`, `SearchInput.vue`, `CategoryFilterDropdown.vue`, `ResetFiltersButton.vue`, `CreateRecipeButton.vue` (full paths, one per line).

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Token scan green: `vendor/bin/sail npm run lint:tokens`
- Dead dropdown gone: `test ! -e resources/js/Components/recipes/RowActionDropdown.vue && ! grep -rn "RowActionDropdown" resources/js`
- No English list copy left: `! grep -rnE "No recipes found|Search recipes|All Categories|Reset Filters|Select category|Filter by category|en-US|View \\$|Edit \\$" resources/js/Pages/Recipes/Index.vue resources/js/Components/recipes/`
- Recipe controller tests pass: `vendor/bin/sail pest --filter=RecipeControllerTest`
- List E2E specs pass: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npx playwright test tests/e2e/confirm-dialog.spec.ts tests/e2e/recipe-form.spec.ts --project=chromium --project=firefox`

#### Manual Verification:

- `/recipes` desktop and 390px: one header, table on tokens, no horizontal page scroll (table scrolls inside its border)
- Search "zzz" → only "Brak wyników" + "Wyczyść filtry"; clicking it restores the list
- New user with no recipes → only "Brak przepisów" + "Dodaj przepis"
- Keyboard: Tab reaches name link, Pokaż, Edytuj, Usuń with visible focus; sort headers still toggle

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Recipe details

### Overview

Migrate `/recipes/{id}` to `PageHeader` (h1 = recipe name), token sections, lucide icons and pl-PL formatting, keeping the delete flow intact.

### Changes Required:

#### 1. Page

**File**: `resources/js/Pages/Recipes/Show.vue`

**Intent**: One header with name, category, breadcrumbs and actions; token body.

**Contract**:
- `<Head :title="recipe.name">`.
- `#header`: `PageHeader :title="recipe.name"`; breadcrumbs Link "Przepisy" (`recipes.index`) / current name (`aria-current="page"`); `description` slot `CategoryBadge :category="recipe.category"` (no `'breakfast'` fallback); `actions` slot `ActionBar`. The "Back to Recipes" button is removed.
- Body: `mx-auto flex max-w-6xl flex-col gap-6` (no `py-12`/`sm:px-6`); `RecipeMetaBadges`; section "Składniki" (`<h2 class="text-lg font-semibold">`) with `IngredientCardGrid`; `InstructionsSection`.
- `ConfirmDialog` + `useDeleteRecipe` flow unchanged (dialog closes in `finally`); no hard-coded `/recipes` URLs — use `route()`.

#### 2. Actions

**File**: `resources/js/Components/recipes/ActionBar.vue`

**Intent**: Real edit link, lucide icons, Polish labels.

**Contract**: Props `canEdit: boolean`, `editHref: string`; emits `delete-request` only. "Edytuj przepis" = `Button as-child` Link (`Pencil`); "Usuń przepis" = `Button variant="destructive"` (`Trash2`). Icons `size-4`.

#### 3. Meta, ingredients, instructions

**Files**: `resources/js/Components/recipes/RecipeMetaBadges.vue`, `IngredientCardGrid.vue`, `IngredientCard.vue`, `InstructionsSection.vue`

**Intent**: Tokens, lucide, pl-PL numbers, shared empty states.

**Contract**:
- `RecipeMetaBadges`: props `calories: [Number, String]`, `servings: Number`; `Badge variant="outline"` with `Flame` "`{formatNumber(calories)} kcal`" and `Users` "`{servings} {pluralize(servings, ['porcja','porcje','porcji'])}`".
- `IngredientCardGrid`: single root; grid `sm:grid-cols-2 lg:grid-cols-3 gap-4`; empty → `EmptyState` (`Package`, "Brak składników", heading level 3).
- `IngredientCard`: `rounded-lg border bg-card p-4` (no shadow); name `<h3 class="text-sm font-medium">` whose text is exactly the name; `Badge variant="secondary"` with `tabular-nums` whose whole text is `` `${formatNumber(quantity)} ${unit.code}` `` (keeps "200 g"); local `formatQuantity` removed.
- `InstructionsSection`: `<h2 class="text-lg font-semibold">Sposób przygotowania</h2>`; text in `rounded-lg bg-muted p-6 text-sm leading-relaxed whitespace-pre-line`; no `prose`; empty → `EmptyState` (`ScrollText`, "Brak sposobu przygotowania", heading level 3).

#### 4. Redundant header

**File**: `resources/js/Components/recipes/RecipeHeader.vue` (delete)

**Intent**: Its h1 and badge moved into `PageHeader`.

**Contract**: File removed; no imports reference it.

#### 5. Lint paths

**File**: `scripts/lint-tokens.paths`

**Intent**: Lock the migrated details files.

**Contract**: Append full paths of `Pages/Recipes/Show.vue`, `recipes/ActionBar.vue`, `RecipeMetaBadges.vue`, `IngredientCardGrid.vue`, `IngredientCard.vue`, `InstructionsSection.vue`.

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Token scan green: `vendor/bin/sail npm run lint:tokens`
- No hand-written SVG and no RecipeHeader left in recipe views: `test ! -e resources/js/Components/recipes/RecipeHeader.vue && ! grep -rn "<svg\|RecipeHeader" resources/js/Pages/Recipes resources/js/Components/recipes`
- No English details copy left: `! grep -rnE "Recipe Details|Back to Recipes|Recipe:|>\s*Ingredients|Instructions<|Edit Recipe|Delete Recipe|'serving' :|No ingredients|No instructions| cal<" resources/js/Pages/Recipes/Show.vue resources/js/Components/recipes/`
- Recipe controller tests pass: `vendor/bin/sail pest --filter=RecipeControllerTest`
- Recipe E2E specs pass (incl. "200 g" on Show): `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npx playwright test tests/e2e/confirm-dialog.spec.ts tests/e2e/recipe-form.spec.ts --project=chromium --project=firefox`

#### Manual Verification:

- `/recipes/{id}` desktop and 390px: sticky header shows breadcrumbs, name, category badge and actions without overlapping `UserMenu`; "810 kcal", "N porcje/porcji" correct
- Delete from Show: dialog focuses "Anuluj"; confirm redirects to the list; a forced failure (e.g. offline) closes the dialog with a toast

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: Recipe form

### Overview

Bring `/recipes/create` and `/recipes/{id}/edit` into the app shell with `PageHeader`, `max-w-2xl`, and token error styling, keeping every E2E selector intact; switch lint paths to directories.

### Changes Required:

#### 1. Page

**File**: `resources/js/Pages/Recipes/Form.vue`

**Intent**: The form becomes a normal app page.

**Contract**: Wrapped in `AuthenticatedLayout`; `<Head :title>` and `#header` `PageHeader` with title "Nowy przepis" / "Edytuj przepis" and breadcrumbs Link "Przepisy" / current title. Body `mx-auto max-w-2xl rounded-lg border bg-card p-6`. Remove `min-h-screen bg-gray-100` and inner `h1`. Keep `useForm` shape and the wrapped `recipe.data` read; submit/cancel URLs via `route('recipes.store'|'recipes.update'|'recipes.index')`.

#### 2. Form fields

**File**: `resources/js/Components/recipes/RecipeForm.vue`

**Intent**: Token error styling with the a11y wiring `IngredientRow` already has.

**Contract**:
- Labels via `ui/label` with unchanged text ("Nazwa przepisu *", "Kategoria *", "Sposób przygotowania *", "Kaloryczność całkowita (kcal) *", "Liczba porcji *") and unchanged `for`/`id` pairs (`name`, `category`, `instructions`, `calories`, `servings`).
- On error: control gets `aria-invalid="true"`, `aria-describedby="<id>-error"` and `border-destructive`; message `<p id="<id>-error" class="text-sm text-destructive">` (the kitchen-sink "Formularze" pattern). Category `SelectTrigger` gets the same.
- "Składniki" heading becomes `<h2 class="text-lg font-semibold">`.
- Submit keeps native `:disabled="form.processing || !isFormValid"`, label "Zapisz przepis" / "Zapisz zmiany"; while processing shows `Loader2` (`animate-spin motion-reduce:animate-none`, `size-4`) + "Zapisywanie…". "Anuluj" unchanged.

#### 3. Ingredients

**Files**: `resources/js/Components/recipes/IngredientManager.vue`, `IngredientRow.vue`

**Intent**: Replace red literals with tokens only.

**Contract**: `text-red-600` → `text-destructive`; `border-red-500` → `border-destructive`; remove button `text-destructive hover:bg-destructive/10 hover:text-destructive`. Keep `<Input list>` + `<datalist>`, all ids, labels "Składnik *", "Ilość *", "Jednostka *", group `aria-label="Składnik {n}"` and remove `aria-label="Usuń składnik {n}"`. No new accessible name containing "Ilość", "Jednostka" or "Składnik".

#### 4. Lint paths as directories

**File**: `scripts/lint-tokens.paths`

**Intent**: Every recipe file — present and future — is scanned.

**Contract**: Replace all individual `resources/js/Pages/Recipes/*.vue` and `resources/js/Components/recipes/*.vue` entries (including the existing `CategoryBadge.vue` line) with two directory entries: `resources/js/Pages/Recipes` and `resources/js/Components/recipes`.

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Token scan green over both directories: `vendor/bin/sail npm run lint:tokens`
- Paths list uses directory entries only: `grep -qx "resources/js/Pages/Recipes" scripts/lint-tokens.paths && grep -qx "resources/js/Components/recipes" scripts/lint-tokens.paths && ! grep -nE "^resources/js/(Pages/Recipes|Components/recipes)/.+" scripts/lint-tokens.paths`
- Recipe controller tests pass: `vendor/bin/sail pest --filter=RecipeControllerTest`
- Recipe E2E specs pass: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npx playwright test tests/e2e/confirm-dialog.spec.ts tests/e2e/recipe-form.spec.ts --project=chromium --project=firefox`

#### Manual Verification:

- `/recipes/create` and edit show the sidebar and sticky header; ingredient rows fit `max-w-2xl` at desktop and stack at 390px
- Server validation error (e.g. ingredient quantity rejected) shows destructive border + message linked via `aria-describedby` (check in DevTools)
- Submitting shows the spinner + "Zapisywanie…" without layout shift

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 5: Visual gate & close-out

### Overview

Make the visual user's recipes deterministic, screenshot the three real pages, walk the 7-state matrix, and finish the docs.

### Changes Required:

#### 1. Deterministic visual recipes

**File**: `database/seeders/E2eMealPlanSeeder.php`

**Intent**: Screenshots of `/recipes` and `/recipes/{id}` must be identical locally and in CI.

**Contract**: For `e2e-planner-visual@example.com` only, a fixed list of 21 recipes (7 per category; Polish names, servings, short instructions) replaces the faker top-up: every trashed recipe of that user and every live one not in the list is force-deleted (`withTrashed()`), then listed ones are created/updated by `(user_id, name)` (`recipes` has no unique index on that pair, so purging trashed rows first prevents a live duplicate next to a trashed one) with fixed `category`, `servings`, `instructions` and `created_at`/`updated_at` at 12:00 UTC on 21 distinct days. The existing ingredient/calorie pinning and family sync still apply afterwards. Re-running the seeder yields identical rows (idempotent). Planner chromium/firefox and cook users unchanged. PHPDoc array shapes per project rules.

#### 2. Real-page visual spec

**File**: `tests/e2e/visual/recipes.spec.ts` (new)

**Intent**: Gate the migrated pages pixel-exact.

**Contract**: Same skeleton as `tests/e2e/visual/app-shell.spec.ts` (Docker skip guard, `page.clock.setFixedTime(new Date('2026-06-15T10:00:00'))`, log in as the visual user, `expectFigtreeLoaded`, `page.mouse.move(0, 0)`, `fullPage: true`, `MOBILE = 390×844`). Seven screenshots: `recipes-index-desktop.png`, `recipes-index-mobile.png`, `recipes-index-no-results.png` (search a string matching no recipe, wait for heading "Brak wyników"), `recipe-show-desktop.png`, `recipe-show-mobile.png` (open via the fixed newest recipe's link by exact name), `recipe-form-create-desktop.png`, `recipe-form-create-mobile.png`. Locators by role + exact name.

#### 3. Docs

**File**: `context/foundation/design-system.md`

**Intent**: Keep §7 counts true.

**Contract**: §7.3 mentions `tests/e2e/visual/recipes.spec.ts` with its 7 screenshots and the deterministic visual-user recipes.

### Success Criteria:

#### Automated Verification:

- Seeder is idempotent and Pint-clean: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && vendor/bin/sail composer pint-test`
- Static analysis passes: `vendor/bin/sail composer phpstan`
- Token scan green: `vendor/bin/sail npm run lint:tokens`
- Baselines created deliberately: `npm run test:visual -- --update-snapshots`, then `git status --porcelain -uall tests/e2e/__screenshots__` shows exactly 7 `??` entries under `visual/recipes.spec.ts/` and no ` M`
- Visual gate stable: `npm run test:visual && npm run test:visual`
- Full Pest suite passes: `vendor/bin/sail composer test`
- Full E2E passes: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`

#### Manual Verification:

- 7-state matrix confirmed, each mapped to evidence: default → the 7 recipe PNGs; hover → manual (row `bg-muted/50`, name link underline, icon buttons); focus-visible → manual keyboard walk on list, Show and form + `focus-visible.png`; disabled → `recipe-form-create-*.png` (empty form, submit disabled); error → `section-formularze.png` + Phase 4 manual server error; empty → `recipes-index-no-results.png` + kitchen-sink EmptyState demo; loading → manual "Zapisywanie…" + `overlay-confirm-dialog-processing.png`
- Each of the 7 new PNGs reviewed in the diff
- `/10x-ui` merge checklist (the skill's `references/ui-quality-checklist.md`) walked: charges C1–C5 addressed, contract/guard/gate in place, scope held to recipe views + shared pieces

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- No new Pest tests: no PHP behaviour changes except the E2E seeder, whose correctness is proven by the idempotent double run plus the zero-tolerance screenshots. No JS unit runner exists; `lib/format.js` is covered by the E2E `"200 g"` assertion and the screenshots ("810 kcal", pl-PL dates, plurals).

### Integration Tests:

- `RecipeControllerTest` guards page names and props after each view phase.
- `recipe-form.spec.ts` and `confirm-dialog.spec.ts` run unchanged after every phase; a selector change would signal a semantic change and is not allowed in this plan.
- `tests/e2e/visual/recipes.spec.ts` (7 PNGs) plus the refreshed kitchen-sink section.

### Manual Testing Steps:

1. As a new user, open `/recipes` → single "Brak przepisów" with "Dodaj przepis".
2. Add a recipe through the form inside the shell; trigger a server validation error; save.
3. On `/recipes`, search for nonsense → "Brak wyników"; "Wyczyść filtry" restores the list.
4. Open a recipe via its name link; check badges, ingredient quantities ("1,5" style decimals), instructions; delete it from Show.
5. Repeat 1–4 at 390px and keyboard-only.

## Performance Considerations

None beyond module-level `Intl` formatter instances in `lib/format.js` (avoid constructing one per row).

## Migration Notes

- Kitchen-sink baseline delta from Phase 1 must be listed here by file name with its reason when it lands (expected: `section-komponenty-aplikacji.png`; possibly `overlay-*` dialog PNGs through corner pixels).
- The visual user's existing local faker recipes are replaced on the next seeder run; this affects only `e2e-planner-visual@example.com`.
- Parallel slices: S-09 `meal-plan-show-ui` and S-10 `meal-plans-index-ui` consume `PageHeader`/`EmptyState`/`lib/format.js` from this change; if either implements first, it must rebase onto this branch or wait. S-09 may also edit `E2eMealPlanSeeder` for the visual user — expect a merge in that file.

## References

- Roadmap slice: `context/foundation/roadmap.md` (S-11, S-09, S-10)
- Design contract: `context/foundation/design-system.md` §3, §4.5–4.7, §5.2, §5.4, §7, §8 (DS-06)
- Similar implementation: `context/archive/2026-11-15-app-shell-restyle/plan.md` (Environment, real-page visual spec), `context/archive/2026-11-15-shared-app-components/plan.md` (kitchen-sink demos, baseline handling)
- Header pattern: `resources/js/Pages/Dashboard.vue:13-17`; layout slot `resources/js/Layouts/AuthenticatedLayout.vue:69-72`
- Error pattern: `resources/js/Pages/Dev/DesignSystem.vue` section "Formularze"
- Visual spec pattern: `tests/e2e/visual/app-shell.spec.ts`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Shared pieces

#### Automated

- [x] 1.1 Build passes — 0597e4c
- [x] 1.2 Token scan green with the new files listed — 0597e4c
- [x] 1.3 Kitchen sink page test passes — 0597e4c
- [x] 1.4 Visual gate passes after the deliberate update — 0597e4c
- [x] 1.5 Baseline delta limited — 0597e4c

#### Manual

- [ ] 1.6 PageHeader and EmptyState demos correct at desktop and 390px
- [ ] 1.7 Modified PNGs reviewed and reasons recorded

### Phase 2: Recipe list

#### Automated

- [x] 2.1 Build passes — 4b38a2f
- [x] 2.2 Token scan green — 4b38a2f
- [x] 2.3 Dead dropdown gone — 4b38a2f
- [x] 2.4 No English list copy left — 4b38a2f
- [x] 2.5 Recipe controller tests pass — 4b38a2f
- [x] 2.6 List E2E specs pass — 4b38a2f

#### Manual

- [ ] 2.7 List layout correct at desktop and 390px
- [ ] 2.8 No-results state and reset work
- [ ] 2.9 No-recipes state for a new user
- [ ] 2.10 Keyboard path through row actions and sort headers

### Phase 3: Recipe details

#### Automated

- [x] 3.1 Build passes — d54c624
- [x] 3.2 Token scan green — d54c624
- [x] 3.3 No hand-written SVG and no RecipeHeader left — d54c624
- [x] 3.4 No English details copy left — d54c624
- [x] 3.5 Recipe controller tests pass — d54c624
- [x] 3.6 Recipe E2E specs pass — d54c624

#### Manual

- [ ] 3.7 Details header and badges correct at desktop and 390px
- [ ] 3.8 Delete from Show works on success and failure

### Phase 4: Recipe form

#### Automated

- [x] 4.1 Build passes — 9e26e81
- [x] 4.2 Token scan green over both directories — 9e26e81
- [x] 4.3 Paths list uses directory entries only — 9e26e81
- [x] 4.4 Recipe controller tests pass — 9e26e81
- [x] 4.5 Recipe E2E specs pass — 9e26e81

#### Manual

- [ ] 4.6 Form inside the shell at desktop and 390px
- [ ] 4.7 Server validation error styled and linked
- [ ] 4.8 Processing state without layout shift

### Phase 5: Visual gate & close-out

#### Automated

- [x] 5.1 Seeder is idempotent and Pint-clean
- [x] 5.2 Static analysis passes
- [x] 5.3 Token scan green
- [x] 5.4 Baselines created deliberately
- [x] 5.5 Visual gate stable
- [x] 5.6 Full Pest suite passes
- [x] 5.7 Full E2E passes

#### Manual

- [ ] 5.8 7-state matrix confirmed
- [ ] 5.9 New PNGs reviewed
- [ ] 5.10 10x-ui merge checklist walked
