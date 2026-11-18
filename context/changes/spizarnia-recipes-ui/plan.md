# Spiżarnia Recipes UI Implementation Plan

## Overview

Roadmap S-24 (M-4, SP-05). F-05, F-06 and S-22 gave the app the Spiżarnia tokens, primitives and the top-bar shell, and S-25 restyled meal plans. The recipe views are still Zielnik layouts on the new tokens: the list is a table with a `Select` category filter, the recipe page is a `PageHeader` plus bordered cards, and the form is one bordered card with a category `Select`.

This change restyles the three views from artifact screens 07–09 (`design-system.md` §10.1, §10.5):

- **Recipes list (ekran 07):** tiles in a grid (4 per row on desktop) with a band in the meal colour carrying the category and kcal. Above them sit a pill search field, category filter pills with counts („Wszystkie 21 · Śniadanie 7 · Obiad 7 · Kolacja 7”) and a sort pill. 12 tiles per page.
- **Recipe page (ekran 08):** a breadcrumb row with „Edytuj” and „Usuń” pills, then a block in the category colour holding the page's only `<h1>`, with kcal / porcje / kcal-per-porcja pills. Below it sit two tiles: ingredients as chips, and numbered steps on `highlight`.
- **Recipe form (ekran 09):** three section tiles („Podstawy”, „Składniki”, „Sposób przygotowania”), the category as three coloured radio tiles, a dashed „Dodaj składnik” and a large footer.

The only backend change is in `RecipeController@index`: faceted, owner-scoped category counts, unknown categories ignored, and 12 per page.

## Current State Analysis

**List (`resources/js/Pages/Recipes/Index.vue`)**
- Order on the page: `PageHeader` with the count in the description (:6-13), a first-run `EmptyState` „Brak przepisów” (:15-28), `RecipesToolbar` (:32-39), a no-results `EmptyState` „Brak wyników” (:41-51), `RecipesTable` + `ServerPagination` (:54-64), and a delete `ConfirmDialog` (:70-78).
- State is local, not derived from the URL:
  - `filterState` and `sortState` are `reactive` (:124-133).
  - They are restored from localStorage by `useLocalStorageStateSync('mm.recipes.filters')` (:141-142) without a request, so a restored pill or sort can disagree with the list the server sent.
  - `lib/useLocalStorageStateSync.js` has no other consumer.
- `updateFilters` sends `search`, `category`, `sort`, `direction` and `page` through `router.get`, without `replace` (:213-234).
- `RecipesToolbar.vue` is made of `SearchInput` (`aria-label="Szukaj przepisów"`, `type="search"`, maxlength 50), `CategoryFilterDropdown` (`Select` with an `'all'` sentinel), `ResetFiltersButton` and `CreateRecipeButton`.
- `RecipesTable.vue` and `RecipeRow.vue` build the rows:
  - `SortableHeader` for name, category, kcal and created_at.
  - Ghost icon buttons „Pokaż / Edytuj / Usuń {name}”.
  - `hover:text-primary` on the name, which no longer changes colour because `primary` = ink (roadmap carry-over F-05).

**Backend (`app/Http/Controllers/RecipeController.php:32-58`)**
- `Recipe::forUser(Auth::id())` with `search()` (unaccent ILIKE on name and instructions; needs PostgreSQL) and `byCategory()`.
- The `category` param is applied unvalidated, so `?category=foo` returns an empty list.
- `orderByField` allows `name`, `created_at`, `calories` and `category`, with a fallback of `created_at desc` (`app/Models/Recipe.php:72-81`).
- `paginate($request->get('per_page', 15))` is unclamped.
- Props: `recipes` (resource collection: `data`, `meta`), `filters` (`only(search, category, sort, direction)`, an empty JSON array when there are no params) and `categories` (`[{value,label}]` from `MealCategory`).
- There are **no per-category counts.** `MealPlanGenerator::recipeCountsPerCategory()` (`app/Services/MealPlanGenerator.php:35-44`) counts per category but ignores search, and only the meal-plan generator uses it.

**Recipe page (`resources/js/Pages/Recipes/Show.vue`)**
- `PageHeader` holds:
  - the breadcrumbs „Przepisy / {name}”, with the old `focus-visible:ring-2` contract (:9);
  - `CategoryBadge` in the description;
  - `ActionBar` („Edytuj przepis”, „Usuń przepis”).
- Below the header: `RecipeMetaBadges` (kcal, porcje), the „Składniki” `<h2 class="text-lg font-semibold">` with `IngredientCardGrid` (cards of `rounded-lg border p-4`, roadmap carry-over F-05), and `InstructionsSection` (`whitespace-pre-line rounded-lg bg-muted p-6`).
- Delete goes through `useDeleteRecipe`.
- `calories` is the total for the whole recipe (`decimal(10,2)`, serialised as a string like `"810.00"`).
- Instructions are plain text, one step per line. The importer appends `"\n\nŹródło: {source}"` (`app/Services/RecipeImport/MarkdownRecipeParser.php:369`), and `{source}` is not always a URL.

**Form (`resources/js/Pages/Recipes/Form.vue`, `Components/recipes/RecipeForm.vue`)**
- `max-w-2xl`. `RecipeForm` sits inside `<div class="rounded-lg border bg-card p-6">` (:16, carry-over F-05).
- Fields are hand-wired Label/Input with `border-destructive`, in this order: name, category `Select` (:28-43), instructions `Textarea`, kcal and servings.
- `IngredientManager` renders a list of `IngredientRow` (each a `role="group"` „Składnik n” card with `rounded-lg border p-4`, carry-over F-05) and an outline „Dodaj składnik”.
- The submit button is disabled until `isFormValid`.

**Shared S-25 components (`resources/js/Components/MealPlans/`)**
- `StatusFilterPills.vue`:
  - `Toggle` pills in `role="group" aria-label="Status"`;
  - exactly one is pressed, and a re-click is ignored;
  - an unknown value means „Wszystkie”;
  - no counts, no tone per option.
- `SortMenu.vue`: an outline pill on `DropdownMenuRadioGroup` with 4 hardcoded meal-plan options. It emits `sort-change({column, direction})`.
- Only `Pages/MealPlans/Index.vue` and `Pages/Dev/DesignSystem.vue` import them.

**Tests that lock the current markup**
- `tests/e2e/recipe-search.spec.ts:11` reads the first name through `getByRole('row').nth(1)`, so tiles break it.
- `tests/e2e/recipe-form.spec.ts`:
  - :16-19 picks the category with `getByLabel('Kategoria')` → `option 'Obiad'` → listbox hidden. Radio tiles break this.
  - :28-31 use `combobox 'Składnik'`, `getByLabel('Ilość')` and `getByLabel('Jednostka')` → `option 'g'`.
  - :40-41 use `getByText('Kasza jaglana E2E' / '200 g', { exact: true })` on the recipe page.
- `tests/e2e/confirm-dialog.spec.ts:11-38` needs one button per list item named `Usuń {name}`.
- `tests/e2e/visual/recipes.spec.ts` produces 7 baselines (`recipes-index-{desktop,mobile,no-results}`, `recipe-show-{desktop,mobile}`, `recipe-form-create-{desktop,mobile}`) for `e2e-planner-visual`, which has 21 recipes, 7 per category, with pinned dates and kcal.

## Desired End State

- `/recipes` shows the screen-07 layout:
  - a header with „Dodaj przepis”;
  - a toolbar with the pill search, the category pills with faceted counts and the „Najnowsze” sort pill;
  - a grid of recipe tiles (1 / 2 / 3 / 4 columns), 12 per page, then `ServerPagination`.
- The state comes only from the URL.
- Searching „rosół” for the visual user shows the pills „Wszystkie 1 · Śniadanie 0 · Obiad 1 · Kolacja 0”.
- `/recipes/{id}` shows the screen-08 layout:
  - a breadcrumb row and the action pills;
  - the category block with the `<h1>`;
  - the ingredient chips and the numbered steps.
- An imported recipe's trailing „Źródło: …” line appears as a note under the steps, not as a step.
- `/recipes/create` and `/recipes/{id}/edit` show the screen-09 layout: three section tiles, the category radio tiles, the dashed add-ingredient button and the large footer.
- The meal-plans list looks and behaves exactly as before, on the shared `Common/FilterPills` and `Common/SortMenu`.
- Pest, Larastan, Pint, the build, `lint:tokens`, E2E and the visual gate are green. Only the recipes baselines and the design-system sections named per phase changed.

Verify with the automated commands per phase and the manual checklist in Testing Strategy.

### Key Discoveries:

- `ui/toggle` already has the meal tones `breakfast` / `supper` / `dinner`. The base `data-[state=on]:bg-primary` turns a pressed tone pill ink, which design-system.md documents as „wybrana na atramencie”. The kitchen sink shows the count convention as `<span>{{ count }}</span>` after the label (`resources/js/Pages/Dev/DesignSystem.vue:436-460`).
- `Badge variant="tag" size="status"` renders a card pill with a 2px ink border (`resources/js/Components/ui/badge/index.js:26-34`). These are the screen-08 meta pills, so no new variant is needed.
- `text-hero` (48/52, −0.035em, 800) exists in `resources/css/app.css:34-36` and matches the screen-08 title.
- `MealPlans/Index.vue:144-224` is the URL-derived model to copy: `currentFilters` computed from `props.filters`, `buildQuery`, and `router.get(…, { preserveScroll: true, replace: true })`.
- `MealPlanController@index` handles filters leniently in the controller (no Form Request) and clamps `perPage` to 5–100 (`app/Http/Controllers/MealPlanController.php:66`). Recipes follow the same convention.
- Arbitrary Tailwind values (`w-[…]`, `grid-cols-[…]`) are banned outside `ui/` (`scripts/lint-tokens.sh`). Use the spacing scale (`w-75` = 300px, `max-w-205` = 820px) and 12- or 5-column grids instead of fractional templates.
- S-25 impl-review lessons:
  - A tile title link sits in an `<h2 class="text-section-title">` (F6).
  - Adding a size class to a text role drops the role in `cn()`: use the role alone.
  - Kitchen-sink height changes cause anti-aliasing noise in the shots of the sections below it. Review those shots rather than expecting them to be byte-identical.
- `context/foundation/lessons.md`: in a worktree, run the visual gate with `VISUAL_DOCKER_NETWORK=<COMPOSE_PROJECT_NAME>_sail` and E2E with `MAILPIT_URL=…`. Before any `--update-snapshots`, confirm with `docker inspect` that the stack mounts this worktree.

## What We're NOT Doing

- No change to `PageHeader` typography (`text-2xl font-semibold`) or other `Common/` components beyond the two moved S-25 ones. Index and Form keep `PageHeader`.
- No refactor of the meal-tone maps duplicated in `MealPlanGrid`, `MealPlanTile`, `RecipeSwapDialog` and `GuestLayout`. The fill-class and icon exports added to the existing `lib/mealCategories.js` are used only by recipe components.
- No change to the importer or the stored instructions format. The „Źródło” line is handled at render time only.
- No new `ui/` primitive or variant: no radio-group primitive, no selected meal-coloured pill.
- No sorting by category in the UI. `sort=category` stays valid in URLs, and the sort pill then reads „Sortuj”.
- No per-page selector. `per_page` is still honoured from the URL, clamped to 5–100.
- No mobile-specific redesign beyond responsive columns. The mockups are desktop only.
- `Common/SortableHeader` is not deleted, even though no page uses it after this change; the kitchen sink still demos it.
- No final cross-branch kitchen-sink baseline refresh. Per roadmap §10.6, that happens once after S-23, S-24 and S-26 merge (see Migration Notes).

## Implementation Approach

Four phases, each green and committed on its own:
1. The first phase moves the shared pills to `Common/` and adds the backend counts. It is invisible to users and proves the move with unchanged meal-plans baselines.
2. Phases 2–4 restyle one view each, so each refreshes only its own recipes baselines plus the kitchen-sink sections it touches.

Recipe-specific components stay in `Components/recipes/`. Reuse comes from `ui/` (`Card`, `Toggle`, `Badge`, `Button` variants `outline` / `secondary` / `dashed` / `destructive`) and `Common/` (`PageHeader`, `EmptyState`, `FormField`, `ConfirmDialog`, `ServerPagination`).

## Critical Implementation Details

**Search input while typing.** The list state comes from the URL, but the search field keeps a local `ref` for keystrokes, debounced at 300 ms. The `router.get` for search must use `preserveState: true`, or the input loses focus mid-typing. Pill, sort and page changes reset to page 1, except the page change itself.

**Re-sync search without erasing keystrokes.** A response for an earlier search („ros”) can arrive while the user has typed more („rosół”). Track `lastSentSearch` and the pending debounce timer. Overwrite the local `ref` from `props.filters.search` only when no debounce is pending and the incoming value differs from `lastSentSearch`, i.e. after „Wyczyść filtry” or an external visit, never because of the user's own in-flight search.

**`filters` may be an empty array.** With no query params, Laravel serialises `filters` as `[]` (see the comment at `Index.vue:120-121`). Normalise it before reading keys.

**Category radio tiles must stay real radios.** Use native `<input type="radio" name="category">` elements inside each `<label>` tile, made invisible with `absolute inset-0 opacity-0 cursor-pointer` so the input covers the whole tile. Do not use `sr-only`: its 1px, `clip-path: inset(50%)` box fails Playwright's hit-target check, so `.check()` times out with „<label> intercepts pointer events”. The tile is `relative`; its icon, label and indicator are `pointer-events-none`, so clicks land on the input. That keeps arrow-key navigation, `getByRole('radio', { name: 'Obiad' }).check()` and form semantics without a new primitive. Put the visible focus ring on the tile through `has-[:focus-visible]:outline-2 …` (the §10.4 contract).

## Phase 1: Shared pills and category counts

### Overview

Move the S-25 filter and sort pills to `Common/` with the options they need for recipes, and give `RecipeController@index` faceted, owner-scoped category counts, lenient category handling and a clamped page size (the default stays 15 until Phase 2, so the recipes-index baselines do not change here). Meal plans must render byte-identically.

### Changes Required:

#### 1. Filter pills to Common

**File**: `resources/js/Components/MealPlans/StatusFilterPills.vue` → `resources/js/Components/Common/FilterPills.vue` (`git mv`)

**Intent**: Generalise the status pills into a filter-pill group that can carry a tone and a count per option, without changing meal-plans output.

**Contract**:
- Props:
  - `modelValue: string` (`''` = all);
  - `options: { value: string; label: string; variant?: 'default' | 'breakfast' | 'supper' | 'dinner'; count?: number }[]`;
  - `ariaLabel: string`;
  - `allLabel?: string` (default „Wszystkie”);
  - `allCount?: number`.
- Emits `update:modelValue(string)`.
- Invariants kept from S-25:
  - exactly one pill is pressed;
  - re-clicking the pressed pill is ignored;
  - a value not in `options` presses „Wszystkie”.
- A defined `count` renders as `<span>{{ count }}</span>` after the label, formatted with `formatNumber`. Without counts and tones the DOM equals today's `StatusFilterPills`.

#### 2. Sort menu to Common

**File**: `resources/js/Components/MealPlans/SortMenu.vue` → `resources/js/Components/Common/SortMenu.vue` (`git mv`)

**Intent**: Make the sort options a prop so recipes can pass their own six.

**Contract**:
- New prop `options: { value: string; label: string; column: string; direction: 'asc' | 'desc' }[]`.
- `sort`, `direction` and the emitted `sort-change({column, direction})` are unchanged.
- The trigger label is the matching option's label, otherwise „Sortuj”.
- The four meal-plan options move into `Pages/MealPlans/Index.vue`.

#### 3. Meal plans and kitchen sink on the moved components

**File**: `resources/js/Pages/MealPlans/Index.vue`, `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: Point both importers at `Common/FilterPills` (`aria-label="Status"`, no counts) and `Common/SortMenu` (meal-plan options).

**Contract**: The rendered DOM and behaviour are unchanged.

#### 4. Meal tone and icon map

**File**: `resources/js/lib/mealCategories.js` (exists: extend it, do not overwrite)

**Intent**: One recipe-side source for the category fill classes and icons, used by the tile band, the recipe block and the radio tiles.

**Contract**:
- Keep the existing exports (`MEAL_CATEGORIES`, `MEAL_CATEGORY_LABELS`, `mealCategoryLabel`); five files import them. Recipe components get labels from `mealCategoryLabel`, which also covers an unknown category.
- Export `MEAL_CATEGORY_ICONS` (`breakfast: Sun`, `supper: Soup`, `dinner: Moon` from `lucide-vue-next`).
- Export `MEAL_CATEGORY_FILL_CLASSES` as static full strings (`'bg-meal-breakfast text-meal-breakfast-foreground'` …) so Tailwind sees them.
- Callers fall back to `bg-muted text-foreground` for an unknown category.

#### 5. Category counts and lenient filters in the index

**File**: `app/Http/Controllers/RecipeController.php`

**Intent**:
- Return per-category counts that apply the current search and ignore the selected category, so each pill says how many tiles a click would show.
- Ignore unknown categories.
- Clamp the page size.

**Contract**:
- New Inertia prop `categoryCounts: { all: int, breakfast: int, supper: int, dinner: int }`, computed as follows:
  - one grouped query on `Recipe::forUser()` plus `search()` when `search` is filled, then `groupBy('category')`;
  - the result is zero-filled for missing categories;
  - `all` is the sum;
  - soft-deleted recipes are excluded by the model's default scope.
- `byCategory()` is applied only when `MealCategory::tryFrom($request->input('category'))` is non-null. An unknown value filters nothing; the frontend then presses „Wszystkie”.
- `per_page` keeps its default of 15 and is clamped to 5–100. The default changes to 12 in Phase 2, together with the recipes-index baselines it changes.
- The file gets `declare(strict_types=1);` if it is missing.

#### 6. Feature tests

**File**: `tests/Feature/RecipeControllerTest.php`

**Intent**: Lock the counts and filter contract.

**Contract**: New tests, using factories:
- the counts are faceted: with `?search=x&category=breakfast`, every category is counted for matching names and `all` = the sum;
- the counts are owner-scoped and exclude trashed recipes;
- all three keys are present at zero for a user without recipes;
- an unknown `category` returns the unfiltered list;
- `per_page=1000` is clamped to 100.

Existing tests stay green unchanged.

#### 7. Docs

**File**: `context/foundation/design-system.md`

**Intent**: Move the `StatusFilterPills` and `SortMenu` entries from §5.3 to §5.2 as `FilterPills` (tones, counts, `ariaLabel`) and `SortMenu` (`options` prop).

**Contract**: §5.2 and §5.3 tables and lists.

### Success Criteria:

#### Automated Verification:

- Recipe controller tests pass: `vendor/bin/sail pest --filter=RecipeControllerTest`
- Full Pest suite passes: `vendor/bin/sail composer test`
- Larastan passes without baseline additions: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`
- Build passes: `vendor/bin/sail npm run build`
- Token lint passes: `vendor/bin/sail npm run lint:tokens`
- Functional E2E passes unchanged (`meal-plan.spec.ts` not edited): `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} MAILPIT_URL=… npm run test:e2e`
- Visual gate passes with no snapshot update (meal-plans and design-system baselines unchanged): `VISUAL_DOCKER_NETWORK=<COMPOSE_PROJECT_NAME>_sail npm run test:visual`

#### Manual Verification:

- `/meal-plans` status pills and sort pill behave as before (one pill always pressed, sort changes order)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Recipes list as tiles

### Overview

Rebuild `Recipes/Index` as the screen-07 tile list with the pill toolbar. Derive all state from the URL and remove the table components and the localStorage sync.

### Changes Required:

#### 1. Recipe tile

**File**: `resources/js/Components/recipes/RecipeTile.vue` (new)

**Intent**: One recipe as a Spiżarnia tile.

**Contract**:
- Props: `recipe` (index resource item). Emits `delete-requested(recipe)` and nothing else, which keeps it safe in the kitchen sink.
- Root: `Card` with `overflow-hidden flex flex-col`.
- Band: `MEAL_CATEGORY_FILL_CLASSES[category]` plus `border-b-2 border-foreground`. The category label (`text-meta font-bold`) sits on the left; „{formatNumber(calories)} kcal” (`font-extrabold tabular-nums`) on the right.
- Body:
  - `<h2 class="text-section-title">` with a `Link` to `recipes.show`, styled `hover:underline` with the §10.4 focus outline;
  - meta line „{servings} {porcja|porcje|porcji} · {formatDate(created_at)}” in `text-meta text-muted-foreground`;
  - actions pinned to the bottom: `Button variant="secondary" size="icon"`, as a `Link` with Pencil and `aria-label="Edytuj {name}"`, and a Trash2 button with `aria-label="Usuń {name}"`.
- No „Pokaż” button; the title link replaces it.
- An unknown category falls back to `bg-muted text-foreground` with the raw value.

#### 2. Toolbar

**File**: `resources/js/Components/recipes/RecipesToolbar.vue` (rewrite), `resources/js/Components/recipes/SearchInput.vue`

**Intent**: The screen-07 row: pill search, category pills with counts, then the sort pill on the far right.

**Contract**:
- `SearchInput`:
  - becomes a pill (`rounded-full` on `Input`, `w-full sm:w-75`, leading Search icon);
  - placeholder „Szukaj przepisów…”;
  - keeps `type="search"`, `aria-label="Szukaj przepisów"` and maxlength 50.
- `FilterPills` has `aria-label="Kategoria"`. Its options come from `categories`, with `variant = value` and `count = categoryCounts[value]`, and `allCount = categoryCounts.all`.
- `SortMenu` options, in this order:

  | Label | Column | Direction |
  | --- | --- | --- |
  | Najnowsze | `created_at` | `desc` |
  | Najstarsze | `created_at` | `asc` |
  | Nazwa A–Z | `name` | `asc` |
  | Nazwa Z–A | `name` | `desc` |
  | Najmniej kcal | `calories` | `asc` |
  | Najwięcej kcal | `calories` | `desc` |

- Emits `search(string)`, `category-change(string)` (`''` = all) and `sort-change({column, direction})`.
- The row wraps below `lg`.

#### 3. Index page

**File**: `resources/js/Pages/Recipes/Index.vue`

**Intent**: Recompose as header → toolbar → tiles or empty state → pagination, with the state derived from the URL.

**Contract**:
- New prop `categoryCounts`.
- Header: `PageHeader` „Przepisy” (the description keeps the count). The „Dodaj przepis” CTA (`Button`, Plus) goes in the `actions` slot and is hidden in the first-run state, where the `EmptyState` already carries it.
- State: `currentFilters` is computed from the normalised `props.filters` (search, category, sort with default `created_at`, direction with default `desc`, and `per_page` when the URL has it). `buildQuery` omits empty values and carries `per_page` through pill, sort and page changes, as `perPage` does in meal plans. `router.get(route('recipes.index'), …, { preserveState: true, preserveScroll: true, replace: true })`.
- Tiles: `<ul aria-label="Przepisy" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">`, one `<li>` per `RecipeTile`.
- Empty states:
  - First run (`categoryCounts.all === 0` and no search): `Card` with `EmptyState` „Brak przepisów” + „Dodaj przepis”.
  - No results: `Card` with `EmptyState` „Brak wyników” + outline „Wyczyść filtry” (`router.get` with `{}`).
- Delete flow (`ConfirmDialog`) unchanged.

#### 4. Page size of 12

**File**: `app/Http/Controllers/RecipeController.php`, `tests/Feature/RecipeControllerTest.php`

**Intent**: Full rows at 4, 3 and 2 columns. This lands with the tiles because it changes the `recipes-index-*` baselines, which this phase refreshes anyway.

**Contract**:
- The `per_page` default changes from 15 to 12; the 5–100 clamp from Phase 1 stays.
- A new feature test asserts the default page size of 12.

#### 5. Removals

**File**: `resources/js/Components/recipes/{RecipesTable,RecipeRow,CategoryFilterDropdown,ResetFiltersButton,CreateRecipeButton}.vue`, `resources/js/lib/useLocalStorageStateSync.js`

**Intent**: Delete the table, the Select filter, the toolbar reset and create buttons, and the localStorage helper. None has another consumer.

**Contract**: The files are deleted, and `grep` finds no imports of them.

#### 6. E2E and visual specs

**File**: `tests/e2e/recipe-search.spec.ts`, `tests/e2e/visual/recipes.spec.ts`

**Intent**: Retarget the first-recipe lookup from a table row to the tile list. Keep every other assertion.

**Contract**:
- `recipe-search.spec.ts:11` reads the first name from `getByRole('list', { name: 'Przepisy' }).getByRole('listitem').first().getByRole('heading')`. The URL assertions (`search` present, no `category`) stay.
- The visual spec's index assertions (h1 „Przepisy”, link „Jajecznica ze szczypiorkiem”, „Brak wyników” after `zzzzzz`) still hold. Only the baselines change.
- `confirm-dialog.spec.ts` passes unchanged (one `Usuń {name}` per tile).

#### 7. Kitchen sink and docs

**File**: `resources/js/Pages/Dev/DesignSystem.vue`, `context/foundation/design-system.md`

**Intent**: Demo the recipe toolbar (pills with counts plus the sort pill) and three `RecipeTile`s, one per category, in „Komponenty aplikacji”, without adding a new section. Document `RecipeTile` and the recipe list in §5.3.

**Contract**:
- This change adds no new section; every demo goes into „Komponenty aplikacji” (or „Formularze” in Phase 4). Do not assert a section count: S-23 adds „Pulpit” and merges before this branch.
- The demo data is static, and tile delete only emits.

### Success Criteria:

#### Automated Verification:

- Full Pest suite passes: `vendor/bin/sail composer test`
- Build passes: `vendor/bin/sail npm run build`
- Token lint passes: `vendor/bin/sail npm run lint:tokens`
- Removed components have no importers: `grep -rE "RecipesTable|RecipeRow|CategoryFilterDropdown|ResetFiltersButton|CreateRecipeButton|useLocalStorageStateSync" resources/js` returns nothing
- Functional E2E passes (incl. `recipe-search.spec.ts`, `confirm-dialog.spec.ts`): `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} MAILPIT_URL=… npm run test:e2e`
- Visual gate passes after a deliberate update in which only `recipes-index-{desktop,mobile,no-results}.png` and the design-system shots of „Komponenty aplikacji” (plus the anti-aliasing-only shifts below it) changed: `VISUAL_DOCKER_NETWORK=<COMPOSE_PROJECT_NAME>_sail npm run test:visual`

#### Manual Verification:

- `/recipes` matches screen 07 at 1280px: 4 tiles per row, band colours, „Wszystkie 21 · Śniadanie 7 · Obiad 7 · Kolacja 7”, „Najnowsze” pill, 12 tiles on page 1 and 9 on page 2
- Searching „rosół” shows „Wszystkie 1 · Śniadanie 0 · Obiad 1 · Kolacja 0”; clicking „Obiad” keeps the search; the pressed pill turns ink
- Reloading `/recipes?category=dinner&sort=name&direction=asc` restores the „Kolacja” pill and „Nazwa A–Z”; `?category=foo` presses „Wszystkie” and lists everything
- Typing in search keeps focus and caret, fast typing across the 300 ms debounce loses no characters, and Back/Forward don't stack a history entry per keystroke
- Tile delete opens „Usuń przepis”, and confirming removes the tile with the flash toast
- At 390px the tiles stack in one column and the toolbar wraps without horizontal scroll

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Recipe page

### Overview

Rebuild `Recipes/Show` as screen 08: breadcrumb and actions row, the category block with the only `<h1>`, the ingredient chips and the numbered steps with an optional source note.

### Changes Required:

#### 1. Instructions parser

**File**: `resources/js/lib/recipeInstructions.js` (new)

**Intent**: Turn the plain-text instructions into steps and pull out the importer's trailing source line.

**Contract**:
- `parseInstructions(text: string): { steps: string[]; source: string | null }`.
- It splits on `/\r?\n/`, trims each line and drops empty lines.
- If the **last** remaining line starts with `Źródło:`, it is removed from `steps` and its trimmed remainder becomes `source`. An earlier `Źródło:` line stays a step.

#### 2. Category block

**File**: `resources/js/Components/recipes/RecipeHero.vue` (new), `resources/js/Components/recipes/RecipeMetaBadges.vue`

**Intent**: The screen-08 block in the category colour.

**Contract**:
- Props: `recipe` and `headingLevel?: 1 | 2` (default 1, the `EmptyState` convention). The page uses the default; the kitchen sink passes 2 to keep its single `<h1>`.
- `<section>` with `rounded-lg border-2 border-foreground shadow-sticker`, the fill classes from `MEAL_CATEGORY_FILL_CLASSES` (falling back to muted), and padding of about 7×8 on desktop.
- Contents, top to bottom:
  - a label line with the category icon and label (`font-bold`);
  - `<component :is="`h${headingLevel}`" class="break-words text-page-title md:text-hero">{name}</component>` (an `<h1>` on the page);
  - `RecipeMetaBadges`.
- `RecipeMetaBadges` is restyled to `Badge variant="tag" size="status"` pills: Flame „{kcal} kcal”, Users „{n} porcja|porcje|porcji”, and „{round(kcal / servings)} kcal / porcja”.

#### 3. Ingredient chips and steps tiles

**File**: `resources/js/Components/recipes/IngredientCardGrid.vue`, `resources/js/Components/recipes/IngredientCard.vue`, `resources/js/Components/recipes/InstructionsSection.vue`

**Intent**: The two screen-08 tiles. This also resolves the F-05 `rounded-lg` carry-over in these files.

**Contract**:
- Both tiles: `Card` with padding 6 and `<h2 class="text-section-title">` with an icon: ShoppingBasket „Składniki”, ScrollText „Sposób przygotowania”.
- Ingredient chip:
  - `rounded-md bg-muted px-3.5 py-2.5`, name on the left (`font-medium`), quantity on the right (`font-bold tabular-nums`);
  - grid `sm:grid-cols-2 gap-2`;
  - the quantity text stays exactly `"{formatNumber(quantity)} {unit.code}"` and the name its own text node (E2E `getByText(…, { exact: true })`).
  - No ingredients: `EmptyState`.
- Steps:
  - `<ol aria-labelledby="{heading id}">` from `parseInstructions`;
  - each step shows an `aria-hidden` number circle (`size-9 rounded-full bg-highlight text-highlight-foreground font-extrabold`) and the text;
  - a `source` renders below as `text-meta text-muted-foreground` „Źródło: …”, as an external `<a target="_blank" rel="noopener noreferrer">` only when it matches `^https?://`.
  - No steps: `EmptyState`.

#### 4. Show page

**File**: `resources/js/Pages/Recipes/Show.vue`, `resources/js/Components/recipes/ActionBar.vue`

**Intent**: Replace `PageHeader` with the screen-08 composition.

**Contract**:
- Top row:
  - `<nav aria-label="Ścieżka nawigacji">` with the ArrowLeft link „Przepisy”, in the S-25 breadcrumb link classes, then „/” and the name with `aria-current="page"`;
  - on the right, `ActionBar` with an outline pill „Edytuj” (Pencil) and a destructive pill „Usuń” (Trash2), both shown only when `canEdit`.
- Then `RecipeHero`, then `grid gap-6 lg:grid-cols-5` with the ingredients tile in `lg:col-span-2` and the steps tile in `lg:col-span-3`.
- `useDeleteRecipe` and `ConfirmDialog` are unchanged. `CategoryBadge` is no longer used here but is kept, since the kitchen sink uses it.

#### 5. E2E, kitchen sink and docs

**File**: `tests/e2e/recipe-form.spec.ts`, `resources/js/Pages/Dev/DesignSystem.vue`, `context/foundation/design-system.md`

**Intent**: Cover step parsing end to end, demo the new pieces, and record the §5.2 exception.

**Contract**:
- `recipe-form.spec.ts` fills the instructions with two steps, a blank line and `Źródło: https://example.com/kasza`. On the recipe page it asserts:
  - `getByRole('list', { name: 'Sposób przygotowania' }).getByRole('listitem')` has count 2;
  - a link named `https://example.com/kasza` is visible.
  The category step stays the `Select` until Phase 4.
- The kitchen sink adds, in „Komponenty aplikacji”:
  - one `RecipeHero` with `:heading-level="2"`, so the page keeps its single h1;
  - the ingredient chips;
  - steps with a source note and without one.
- design-system.md §5.2 states the exception: on `Recipes/Show` the single `<h1>` lives in `RecipeHero` and there is no `PageHeader`; the breadcrumb is its own `<nav aria-label="Ścieżka nawigacji">`. §5.3 gains entries for `RecipeHero`, the chips and the steps.

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Token lint passes: `vendor/bin/sail npm run lint:tokens`
- Functional E2E passes incl. the new steps/source assertions in `recipe-form.spec.ts`: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} MAILPIT_URL=… npm run test:e2e`
- Visual gate passes after a deliberate update in which only `recipe-show-{desktop,mobile}.png` and the affected design-system shots changed: `VISUAL_DOCKER_NETWORK=<COMPOSE_PROJECT_NAME>_sail npm run test:visual`

#### Manual Verification:

- „Rosół z makaronem” matches screen 08 at 1280px: tomato block, 48px title, pills „1080 kcal · 4 porcje · 270 kcal / porcja”, two-column tiles, numbered steps on indigo circles
- An imported recipe (Markdown import with a source URL) shows „Źródło: …” as a clickable note under the steps, not as a numbered step
- The page has exactly one `<h1>` (the recipe name); screen-reader heading navigation reads h1 → „Składniki” → „Sposób przygotowania”
- „Edytuj” and „Usuń” are hidden for a recipe the user can't edit; „Usuń” deletes and redirects to the list
- At 390px the title wraps without overflow and the tiles stack

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: Recipe form

### Overview

Rebuild create/edit as screen 09: three section tiles, the category radio tiles, ingredient rows without per-row cards, a dashed add button and the large footer.

### Changes Required:

#### 1. Category radio tiles

**File**: `resources/js/Components/recipes/CategoryRadioTiles.vue` (new)

**Intent**: Pick the category from three coloured tiles instead of a `Select`.

**Contract**:
- Props: `modelValue: string | null`, `categories: {value,label}[]`, `error?: string`, `disabled?: boolean`. Emits `update:modelValue`.
- Markup: `<fieldset role="radiogroup" aria-labelledby>` with a `<legend class="text-label">Kategoria *</legend>` and `grid gap-3 sm:grid-cols-3`.
- Each tile is a `<label>` (`relative h-16 rounded-md border-2 border-foreground px-4 font-bold`) wrapping:
  - a native radio with `absolute inset-0 opacity-0 cursor-pointer` covering the tile (not `sr-only`; see Critical Implementation Details);
  - the category icon and label (`pointer-events-none`);
  - a 22px circle indicator (`pointer-events-none`).
- Checked: fill classes plus `shadow-sticker` and a filled dot. Unchecked: `bg-card`.
- Focus: `has-[:focus-visible]:outline-2 outline-offset-2 outline-ring`.
- Error: the message under the group with `id="category-error"`, wired to the inputs through `aria-describedby` and `aria-invalid`.

#### 2. Form sections

**File**: `resources/js/Components/recipes/RecipeForm.vue`, `resources/js/Pages/Recipes/Form.vue`

**Intent**: Lay the form out as three tiles and use `Common/FormField` for the plain fields.

**Contract**:
- `Form.vue`:
  - drops the `rounded-lg border bg-card p-6` wrapper (:16);
  - becomes `w-full max-w-205`, left-aligned;
  - its `PageHeader` breadcrumb is the S-25 ArrowLeft link „Przepisy” only.
- `RecipeForm` sections are `Card`s with `<h2 class="text-section-title">`:
  - **„Podstawy”:** `FormField` „Nazwa przepisu *”, `CategoryRadioTiles`, and `md:grid-cols-2` with „Kaloryczność całkowita (kcal) *” and „Liczba porcji *”.
  - **„Składniki”:** `IngredientManager`.
  - **„Sposób przygotowania”:** a `FormField` textarea, `rows="6"`, with a hint that each line is one step.
- Footer:
  - `Button size="lg"` „Zapisz przepis” / „Zapisz zmiany” (loading = disabled + `Loader2`);
  - outline `size="lg"` „Anuluj”;
  - a spacer, then „* Pola wymagane” in `text-meta text-muted-foreground`.
- Field ids and the label texts that E2E relies on (`Nazwa przepisu`, `Sposób przygotowania`, `Kaloryczność całkowita (kcal)`, `Liczba porcji`) are unchanged, and so is `isFormValid`.

#### 3. Ingredient rows

**File**: `resources/js/Components/recipes/IngredientManager.vue`, `resources/js/Components/recipes/IngredientRow.vue`

**Intent**: Screen-09 rows. This also resolves the F-05 `rounded-lg` carry-over and the F-06 dashed add button.

**Contract**:
- `IngredientRow`:
  - loses its card border and padding;
  - becomes `grid gap-3 md:grid-cols-12 md:items-end`: name `md:col-span-7`, quantity `md:col-span-2`, unit `md:col-span-2`, remove `md:col-span-1`;
  - keeps `role="group"` „Składnik n”, the per-row labels („Składnik *”, „Ilość *”, „Jednostka *”), the `datalist` combobox and the unit `Select`;
  - its remove button is `Button variant="secondary" size="icon"` with X and `aria-label="Usuń składnik {name || n}"`.
- `IngredientManager`: „Dodaj składnik” becomes `Button variant="dashed"` at full width and h-12. The hand-rolled empty circle is replaced by `EmptyState` (`headingLevel` 3).

#### 4. E2E, visual, kitchen sink and docs

**File**: `tests/e2e/recipe-form.spec.ts`, `resources/js/Pages/Dev/DesignSystem.vue`, `context/foundation/design-system.md`

**Intent**: Replace the `Select` interaction with the radio, and demo the radio tiles.

**Contract**:
- `recipe-form.spec.ts:16-19` becomes `page.getByRole('radio', { name: 'Obiad' }).check()` plus `toBeChecked()`. The rest of the spec is unchanged.
- The kitchen sink „Formularze” section shows `CategoryRadioTiles` with nothing selected, one selected, the error state and disabled.
- design-system.md §5.3 gains a `CategoryRadioTiles` entry and the form layout. The roadmap F-06 carry-overs (dashed add, secondary icon actions, pill search, Toggle filter) are covered.

### Success Criteria:

#### Automated Verification:

- Full Pest suite passes: `vendor/bin/sail composer test`
- Build passes: `vendor/bin/sail npm run build`
- Token lint passes: `vendor/bin/sail npm run lint:tokens`
- Functional E2E passes incl. `recipe-form.spec.ts` with the radio step: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} MAILPIT_URL=… npm run test:e2e`
- Visual gate passes after a deliberate update in which only `recipe-form-create-{desktop,mobile}.png` and the „Formularze” design-system shots (plus the anti-aliasing-only shifts below) changed: `VISUAL_DOCKER_NETWORK=<COMPOSE_PROJECT_NAME>_sail npm run test:visual`
- No recipe file outside `ui/` uses `rounded-lg border bg-card` or `hover:text-primary`: `grep -rnE "rounded-lg border( |-border )bg-card|hover:text-primary" resources/js/Pages/Recipes resources/js/Components/recipes` returns nothing

#### Manual Verification:

- `/recipes/create` matches screen 09 at 1280px: three tiles, radio tiles with icons, the checked tile in the meal colour with the sticker shadow, the dashed „Dodaj składnik”, the large footer
- Arrow keys move the category selection; Tab shows a visible focus outline on the tile
- Submitting with kcal 40 shows „Kaloryczność całkowita musi wynosić co najmniej 50 kcal.” on the kcal field; submitting without a category shows the category error under the tiles
- Editing an existing recipe pre-selects its category tile and saves as before
- At 390px the radio tiles stack and the ingredient rows stack field by field

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- No new unit tests. The backend change is covered by feature tests, and there is no JS unit runner. `parseInstructions` is covered end to end in `recipe-form.spec.ts`.

### Integration Tests:

- `tests/Feature/RecipeControllerTest.php`:
  - faceted counts (search applied, category ignored);
  - owner scope and trashed exclusion;
  - zero-filled keys;
  - an unknown category ignored;
  - a page size of 12 and the clamp.
- E2E:
  - `recipe-search.spec.ts` retargeted to tiles;
  - `confirm-dialog.spec.ts` unchanged;
  - `recipe-form.spec.ts` with the radio category plus the steps and source assertions;
  - `meal-plan.spec.ts` unchanged.
- Visual: the 7 recipes baselines refreshed across Phases 2–4. Design-system shots refreshed only for the sections each phase touches. The meal-plans, app-shell and account baselines are unchanged.

### Manual Testing Steps:

1. Seed `E2eMealPlanSeeder`, log in as `e2e-planner-visual@example.com`, and compare `/recipes`, „Rosół z makaronem” and `/recipes/create` with artifact screens 07–09 at 1280px.
2. Search „rosół” and check the faceted counts; press each category pill and check that the list and URL agree after a reload.
3. Import a Markdown recipe with a source and check the „Źródło” note on its page.
4. Keyboard-only: Tab through the toolbar pills, the sort menu and the tile actions, then the radio tiles (arrow keys) and the form footer.
5. Repeat the three screens at 390px.

## Performance Considerations

`categoryCounts` adds one grouped `COUNT` per index request on the owner's recipes, with the search predicate when it is filled. Recipe counts per user are small (tens), so no index or cache is needed.

## Migration Notes

- No schema or data change. The `mm.recipes.filters` localStorage key stays in users' browsers, unused and harmless.
- Merge order per roadmap: S-26 → S-23 → S-24. Before merging, bring `develop` in and resolve the kitchen-sink (`Pages/Dev/DesignSystem.vue`) conflicts. As the last of the parallel slices, this branch does the single final `design-system.spec.ts` baseline refresh (§10.6).
- Worktree runs (lesson in `context/foundation/lessons.md`):
  - This worktree needs its own Sail stack (`COMPOSE_PROJECT_NAME`, `APP_PORT`, `VITE_PORT`, `FORWARD_*` ports in `.env`).
  - Every visual run needs `VISUAL_DOCKER_NETWORK=<COMPOSE_PROJECT_NAME>_sail`.
  - Every E2E run needs `MAILPIT_URL`.
  - Confirm the mount with `docker inspect` before `--update-snapshots`.

## References

- Roadmap item: `context/foundation/roadmap.md` S-24 (carry-overs from F-05, F-06 and S-25)
- Design: `context/foundation/design-system.md` §5.2–5.4, §10.2–10.5; artifact screens 07–09 (§10.1)
- Precedent: `context/archive/2026-11-17-spizarnia-meal-plans-ui/plan.md` (URL-derived filters, tiles, pills, visual baseline workflow) and its `reviews/impl-review.md` (F1 demo safety, F5 unknown filter, F6 tile heading)
- URL-state model: `resources/js/Pages/MealPlans/Index.vue:144-224`
- Lenient index filters: `app/Http/Controllers/MealPlanController.php:47-87`
- Lessons: `context/foundation/lessons.md` (worktree Sail stack for E2E and visual)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Shared pills and category counts

#### Automated

- [x] 1.1 Recipe controller tests pass — 75265bf
- [x] 1.2 Full Pest suite passes — 75265bf
- [x] 1.3 Larastan passes without baseline additions — 75265bf
- [x] 1.4 Pint passes — 75265bf
- [x] 1.5 Build passes — 75265bf
- [x] 1.6 Token lint passes — 75265bf
- [x] 1.7 Functional E2E passes unchanged — 75265bf
- [x] 1.8 Visual gate passes with no snapshot update — 75265bf

#### Manual

- [ ] 1.9 Meal plans status and sort pills behave as before

### Phase 2: Recipes list as tiles

#### Automated

- [x] 2.1 Full Pest suite passes — ea22c96
- [x] 2.2 Build passes — ea22c96
- [x] 2.3 Token lint passes — ea22c96
- [x] 2.4 Removed components have no importers — ea22c96
- [x] 2.5 Functional E2E passes — ea22c96
- [x] 2.6 Visual gate passes with only index and kitchen-sink shots changed — ea22c96

#### Manual

- [ ] 2.7 Recipes list matches screen 07
- [ ] 2.8 Faceted counts follow search and pressed pill turns ink
- [ ] 2.9 URL reload restores pills and sort; unknown category presses Wszystkie
- [ ] 2.10 Search keeps focus and does not stack history
- [ ] 2.11 Tile delete confirms and removes the tile
- [ ] 2.12 Mobile list stacks without horizontal scroll

### Phase 3: Recipe page

#### Automated

- [x] 3.1 Build passes — c84fd5a
- [x] 3.2 Token lint passes — c84fd5a
- [x] 3.3 Functional E2E passes incl. steps and source assertions — c84fd5a
- [x] 3.4 Visual gate passes with only show and kitchen-sink shots changed — c84fd5a

#### Manual

- [ ] 3.5 Recipe page matches screen 08
- [ ] 3.6 Imported source renders as a note, not a step
- [ ] 3.7 Exactly one h1 and correct heading order
- [ ] 3.8 Edit and delete pills respect permissions
- [ ] 3.9 Mobile recipe page wraps and stacks

### Phase 4: Recipe form

#### Automated

- [x] 4.1 Full Pest suite passes — 9767d79
- [x] 4.2 Build passes — 9767d79
- [x] 4.3 Token lint passes — 9767d79
- [x] 4.4 Functional E2E passes with the radio step — 9767d79
- [x] 4.5 Visual gate passes with only form and kitchen-sink shots changed — 9767d79
- [x] 4.6 No recipe file keeps the old card border or hover:text-primary — 9767d79

#### Manual

- [ ] 4.7 Recipe form matches screen 09
- [ ] 4.8 Radio tiles work with arrow keys and show focus
- [ ] 4.9 Validation errors show on kcal and category
- [ ] 4.10 Edit pre-selects the category and saves
- [ ] 4.11 Mobile form stacks radio tiles and ingredient rows
