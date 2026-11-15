# Shared App Components Implementation Plan

## Overview

S-07 in roadmap M-2 (DS-02 in `context/foundation/design-system.md` §8). This change creates `resources/js/Components/Common/` with three shared components:

- `ConfirmDialog`
- `ServerPagination`
- `SortableHeader`

It moves every caller of the eight duplicates onto them and deletes the duplicates. It also puts `CategoryBadge`, `StatusTag` and the inline category badge in `RecipeRow` on `Badge` variants. Afterwards, the user confirms destructive actions in one dialog that behaves the same everywhere (focus on „Anuluj”, open until the server answers). Recipe and meal-plan lists use the same Polish pagination and accessible sorting. Categories and statuses show the time-of-day and state colours from §4.3–4.4, always with a label.

The change also fixes `MealPlanCollection`, whose doubled `meta` values hide the meal-plan pagination entirely. Without that fix, the migrated pagination could not be seen or checked on meal plans.

## Current State Analysis

All paths are on `develop` at `bef3277`, which already includes F-03 `design-tokens-foundation` and F-04 `design-system-visual-gate`.

**Confirmation dialogs.** There are four implementations, and each behaves differently:

- `Components/MealPlans/ConfirmDialog.vue`
  - Built on `ui/dialog`, with `v-if` mounting and a hard-coded `bg-red-600` at :18.
  - It has no processing state. Its callers close it *before* the request: `ActionDropdown.vue:142-164,173-199` and `Pages/MealPlans/Show.vue:221-234`.
  - Call sites: 2 instances in `ActionDropdown.vue:49-66` and 1 in `Show.vue:95-102`.
- `Pages/FamilyMembers/ConfirmDialog.vue`
  - It sends `router.delete` and the toasts itself (:35-52), and blocks closing while `isDeleting`.
  - Its only call site is `FamilyMemberTable.vue:167-174`, which re-emits `deleted` to `FamilyMembers/Index.vue:51-56`.
- `Components/recipes/ConfirmDeleteModal.vue`
  - Hand-rolled with `Teleport`: no `role="dialog"`, no Esc, no focus trap.
  - Its text is in English ("Delete Recipe", "Cancel").
  - Used by `Pages/Recipes/Index.vue:70-75`. The request at :183-193 has no loading state and no error handling.
- `Components/recipes/DeleteConfirmationDialog.vue`
  - Built on `ui/dialog`, with English text and an `isLoading` spinner.
  - Esc and the close button still work while it is loading.
  - Used by `Pages/Recipes/Show.vue:72-78`, through `hooks/useDeleteRecipe.ts`, whose error toast is English and goes through `window.toast`.

**Pagination.** The two `ServerPagination` files are not identical:

- `MealPlans/ServerPagination.vue`
  - Navigates by itself (`router.get`, :190-197).
  - Renders ellipsis markers as buttons labelled "-1"/"-2", and repeats page 1 (:149-180).
  - Has no `<nav>`.
- `recipes/ServerPagination.vue`
  - Emits `page-change` (:117, :151-155).
  - Has two nested `nav aria-label="Pagination"` elements (:2, :41).
- Both:
  - All labels are English.
  - Neither sets `aria-current`.
  - Both carry palette literals.

**Sorting.** The two `SortableHeader` files are not identical either:

- `MealPlans/SortableHeader.vue` computes the direction and emits `{field, direction}` (:52-64).
- `recipes/SortableHeader.vue`
  - Emits only `field`; `Recipes/Index.vue:162-170` toggles the direction.
  - Has an English `aria-label="Sort by created_at"`.
  - Adds `@keydown.enter`/`.space` on a native `<button>` (:7-8). Enter then fires twice, so it changes nothing.
- Neither sets `aria-sort`.
- The callers wrap them in `<TableHead>`: `MealPlanTable.vue:9-35` and `RecipesTable.vue:9-43`.

**Meal-plan pagination data is broken.**

- `app/Http/Resources/MealPlanCollection.php:27-98` returns its own `data`/`meta`/`links`.
- Inertia resolves the collection through `toResponse()` (`vendor/inertiajs/inertia-laravel/src/PropsResolver.php:524-529`).
- `PaginatedResourceResponse` then merges Laravel's default `meta`/`links` into it with `array_merge_recursive` (`vendor/laravel/framework/src/Illuminate/Http/Resources/Json/PaginatedResourceResponse.php:20`, `ResourceResponse.php:72`).
- As a result, scalar keys become two-element arrays (`meta.last_page: [n, n]`). `meta.last_page > 1` is false in JS, so the meal-plan pagination never renders, and the count in `Pages/MealPlans/Index.vue:13` never shows.
- `MealPlans/Index.vue:45-48` passes no page handler.
- No Pest test asserts on `meta` (`MealPlanControllerTest.php:259-281` only checks that the keys exist).

**Badges:**

- `ui/badge/index.js:9-24` already has the variants `breakfast`, `supper`, `dinner`, `success`, `warning`, `info` and `destructive`, but no `muted`. §4.4 maps `pending` to `muted`, and `secondary` uses the darker `--secondary-foreground`.
- `recipes/CategoryBadge.vue:22-38` and `MealPlans/StatusTag.vue:67-96` override variants with palette classes (12 hits each).
- `RecipeRow.vue:13-14,89-100` renders its own category `<span>` in a third palette (dinner blue, supper purple, `dark:`).
- `CategoryBadge` has a single caller, `RecipeHeader.vue:5`.
- `StatusTag` is used in `MealPlans/Show.vue:16` and `MealPlanRow.vue:15`.

**Gates:**

- `scripts/lint-tokens.paths` lists only `resources/js/Pages/Dev`. Every duplicate above would fail the scan as it stands.
- Kitchen sink `Pages/Dev/DesignSystem.vue`: 11 sections, plus an uncontrolled `AlertDialog` demo at :517-531.
- Visual spec `tests/e2e/visual/design-system.spec.ts`: 19 zero-tolerance baselines under `tests/e2e/__screenshots__/visual/design-system.spec.ts/`.
- `tests/e2e/meal-plan.spec.ts:63` uses `getByRole('dialog')` for the regenerate confirmation.
- No E2E spec covers a delete flow.
- There is no JS unit-test tooling (no vitest).

**E2E fixtures** (`database/seeders/E2eMealPlanSeeder.php`):

- Per browser project, the seeder creates one planner user with exactly 21 recipes (7 per category) and a pinned two-adult family, plus one cook user whose recipes are force-deleted on each run (:79-89).
- The cook's family members are not reset.

## Desired End State

- `resources/js/Components/Common/` contains `ConfirmDialog.vue`, `ServerPagination.vue` and `SortableHeader.vue`. Nothing imports the eight deleted duplicates.
- Every confirmation of a destructive or replacing action (delete a recipe from the list or the details page, delete a family member, delete or regenerate a meal plan) opens the same `alertdialog`:
  - focus starts on „Anuluj”;
  - Esc and „Anuluj” cancel without a request;
  - the confirm button shows a spinner and both buttons are disabled until the server answers;
  - the dialog then closes;
  - failures show a Polish toast;
  - all texts are Polish.
- Recipe and meal-plan lists:
  - share one pagination: `nav aria-label="Paginacja"`, „Poprzednia”/„Następna”, `aria-current` on the active page, a Polish results summary;
  - hide it when there is a single page;
  - keep filters and sort when the page changes;
  - share one sortable header that sets `aria-sort` on the active column's `<th>` and toggles with mouse, Enter and Space alike.
- The meal-plan list shows its pagination and plan count again. `mealPlans.meta.*` are scalars.
- Categories use `<Badge variant="breakfast|supper|dinner">` everywhere they appear as a badge: recipe details and the recipe list. Statuses use `muted`/`info` (with a spinner)/`success`/`destructive`.
- The kitchen sink has a 12th section, „Komponenty aplikacji”, that demos the shared components and domain badges. The visual gate has 22 baselines.
- `lint:tokens` covers `Components/Common`, `CategoryBadge.vue` and `StatusTag.vue`, and is green.
- `design-system.md`, the roadmap and `CLAUDE.md` describe the delivered contracts and the deferral of `PageHeader`/`EmptyState`/`FormField`.

Verify with Pest, `npm run build`, `npm run lint:tokens`, `npm run test:visual` and the E2E suite (including the new `confirm-dialog.spec.ts`), plus the manual checks per phase.

### Key Discoveries:

- `PaginatedResourceResponse::paginationInformation()` calls the collection's own `paginationInformation($request, $paginated, $default)` when one exists (`PaginatedResourceResponse.php:59-61`). Returning `[]` there removes the duplication and keeps the JSON branch (`MealPlanController.php:63-65`) unchanged.
- `ui/alert-dialog/AlertDialogAction.vue` renders reka's close primitive, so clicking it closes the dialog before the parent can set `processing`. It also has no `variant` prop (:7-23). `AlertDialogCancel` receives initial focus from reka by default, and `AlertDialogContent` already prevents closing on outside clicks.
- `ui/pagination` (`Pagination.vue:6-22`, reka `PaginationRoot`) is a controlled component (`page`, `itemsPerPage`, `total`, `update:page`). Its root is a `<nav>`, and its items set `aria-current`. The built-in English labels are overridden by slot content, as the kitchen sink already does with „Poprzednia”/„Następna” (`DesignSystem.vue:464-490`).
- `Recipes/Index.vue` already owns its query, including the page (`updateFilters`, :201-221; `handlePageChange`, :173-175). `MealPlans/Index.vue` owns it through `handleFiltersChange` (:165-177), which does not send `page`.
- Visual baselines use `snapshotPathTemplate: '{testDir}/__screenshots__/{testFilePath}/{arg}{ext}'` with zero tolerance (`playwright.config.ts:26-33`). Any markup change in a section shifts its PNG.
- E2E users are per project (`e2e-planner-<project>`, `e2e-cook-<project>`, seeder :27-37), and `fullyParallel: true` is set. The planner's recipes and family must not change, because `meal-plan.spec.ts` needs 7 recipes per category and a two-adult family.

## What We're NOT Doing

- **`PageHeader`, `EmptyState`, `FormField`.** Deferred: `PageHeader`/`EmptyState` go to the first view slices that use them (S-09/S-10/S-11), and `FormField` to S-13 (decision: no component without a call site, and no restyling of headers or empty states outside their own view slice).
- **`AppShell`.** That is S-08.
- **Restyling the call-site files** (`Recipes/Index`, `Recipes/Show`, `MealPlans/Index`, `MealPlans/Show`, `MealPlanTable`, `RecipesTable`, `RecipeRow`, `FamilyMemberTable`, `ActionDropdown`). Their remaining palette literals, arbitrary widths, `dark:` classes and English column labels ("Name", "Date Range"…) stay for S-09…S-12. These files do **not** join `lint-tokens.paths`.
- **The double empty message on `Recipes/Index`** ("Brak przepisów" plus "No recipes found"). That belongs to S-11 with `EmptyState`.
- **The rest of the `MealPlans/Index` filter cleanup.** `filters['filter.status']` at `Index.vue:135,195` is always undefined: the controller's `$request->only(['filter.status', …])` (`MealPlanController.php:69`) nests it as `filters.filter.status`. This change fixes only those two reads (Phase 2 §4), because the new pagination has to keep the status filter. The localStorage persistence and the remaining filter UI stay for S-10.
- **Recipes keeping the current page on a sort change.** This is existing behaviour, unchanged.
- **`withQueryString()` on paginators.** The new pagination emits a page number and the parent builds the query, so server `links` are no longer used for navigation.
- **New npm dependencies or JS unit-test tooling.**
- **Dark mode, regenerating legacy `ui/` components (D-9) and the PDF (S-14).**
- **Real recipe and meal-plan deletes in E2E.** They would break the seeded 7-per-category data.

## Implementation Approach

The work goes bottom-up and ships one component family per phase, so each phase is a reviewable, green commit:

1. Fix the data first, so the meal-plan pagination can be exercised.
2. Build the list primitives.
3. Build the dialog (the riskiest behaviour change) with its own E2E spec.
4. Move the badges.
5. Do all kitchen-sink and baseline work in one place, so zero-tolerance PNGs change in a single deliberate commit.

Shared components are pure presentation:

- They take props and emit events.
- Callers own requests and URL state.
- They use only tokens and `ui/` primitives, so they can join `lint:tokens` immediately.
- New files use `<script setup lang="ts">` with typed `defineProps`, like the existing `MealPlans/*` components.

## Critical Implementation Details

**State sequencing (ConfirmDialog).** The confirm button must be a plain `ui/button` `Button`, not `AlertDialogAction`. The action's built-in close would emit `update:open(false)` before the caller sets `processing`, and the dialog would vanish mid-request. While `processing` is true, the component drops `update:open(false)` and calls `preventDefault()` on `escape-key-down`. Callers set `processing = true` before `router.*` and close in `onFinish`, never before the request. One rule for every caller: the dialog closes when the request finishes, on success and on error alike; an error additionally shows its toast.

**Focus while processing.** The confirm button gets `aria-disabled="true"` plus a guard in its click handler (`if (processing) return`), not the native `disabled` attribute. A native `disabled` on the focused button makes the browser blur it, and focus would leave the dialog for `<body>` while the request runs. Its disabled look comes from `aria-disabled:pointer-events-none aria-disabled:opacity-50`, which is token-lint-clean. „Anuluj” keeps the native `disabled`.

**User experience spec.** „Anuluj” must be rendered as `AlertDialogCancel`, so reka gives it initial focus. Do not set `autofocus` on the confirm button. The E2E spec asserts that „Anuluj” has focus.

## Phase 1: Meal-plan pagination data

### Overview

Make `mealPlans.meta` and `mealPlans.links` single-valued in the Inertia response, proven test-first.

### Changes Required:

#### 1. Regression test

**File**: `tests/Feature/MealPlanControllerTest.php`

**Intent**: Write a failing test before the fix. It requests the Inertia index with more plans than one page holds and asserts that the pagination props are scalars.

**Contract**: The new test creates 20 plans for the user, requests `meal-plans.index?perPage=5`, and asserts:
- `mealPlans.meta.current_page === 1`, `last_page === 4`, `total === 20`, `per_page === 5` (integers, via `->where`);
- `mealPlans.links.next` is a string;
- `mealPlans.meta` has exactly the keys `current_page`, `last_page`, `per_page`, `total`, `from`, `to`.

The test must fail on the current code before the fix lands.

#### 2. Pagination information override

**File**: `app/Http/Resources/MealPlanCollection.php`

**Intent**: Stop Laravel from merging its default pagination block into the collection's own `meta`/`links`.

**Contract**: A new public method `paginationInformation(Request $request, array $paginated, array $default): array` returns `[]`. It needs a PHPDoc with array shapes. `toArray()` is unchanged, so the JSON branch keeps its shape.

### Success Criteria:

#### Automated Verification:

- The new regression test fails before the fix and passes after: `vendor/bin/sail pest --filter=MealPlanControllerTest`
- Full Pest suite passes: `vendor/bin/sail composer test`
- Larastan passes: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`

#### Manual Verification:

- On `/meal-plans?perPage=5` with more than 5 plans, the header shows the plan count. The old pagination component now renders; its styling is irrelevant, because Phase 2 replaces it.

**Implementation Note**: After automated verification passes, pause for manual confirmation before Phase 2.

---

## Phase 2: ServerPagination and SortableHeader in Common

### Overview

Build the two list components on `ui/pagination` and `ui/table`, move both lists onto them, and delete the four duplicates.

### Changes Required:

#### 1. Shared pagination

**File**: `resources/js/Components/Common/ServerPagination.vue` (new)

**Intent**: Render server-side pagination from a paginator's `meta` and report the requested page to the parent. The component never navigates by itself.

**Contract**:

- **Props:** `meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null }`.
- **Emits:** `page-change(page: number)`, only for a page different from `current_page`.
- **Rendering:**
  - renders nothing when `meta.last_page <= 1`;
  - controlled `Pagination` with `:page="meta.current_page"`, `:items-per-page="meta.per_page"`, `:total="meta.total"`, sibling count 1, edges shown;
  - root `aria-label="Paginacja"`;
  - previous/next labels „Poprzednia”/„Następna”;
  - page items with the Polish accessible name `Strona N`;
  - a results summary `Wyniki {from}–{to} z {total}` in `text-sm text-muted-foreground tabular-nums`.
- **Styling:** tokens only (lint-clean).

#### 2. Shared sortable header

**File**: `resources/js/Components/Common/SortableHeader.vue` (new)

**Intent**: Give each sortable column a `<th>` with an accessible sort state and one toggle rule.

**Contract**:

- **Renders:** `ui/table` `TableHead` as its root. Extra attributes such as `class` pass through to it.
- **Props:** `column: string`, `sort: string` (the active column), `direction: 'asc' | 'desc'`.
- **`aria-sort`:** `"ascending"`/`"descending"` only when `column === sort`; otherwise omitted.
- **Contents:** a `<button type="button">` holding the default slot (the visible label) and one lucide icon, `aria-hidden`: `ChevronUp`/`ChevronDown` when active, `ChevronsUpDown` when inactive.
- **Emits:** `sort-change({ column, direction })`. The direction toggles when `column === sort`, and is `'asc'` for a newly chosen column.
- **No `keydown` handlers.** Native button activation covers Enter and Space.
- **No `aria-label`.** The visible label names the button.

#### 3. Recipe list callers

**Files**:
- `resources/js/Components/recipes/RecipesTable.vue`
- `resources/js/Pages/Recipes/Index.vue`

**Intent**: Use the shared components without restyling the table or the page.

**Contract**:
- Each `<TableHead><SortableHeader …>` pair becomes a single `<SortableHeader :column :sort :direction …>`, keeping the existing `TableHead` classes as attributes.
- `RecipesTable` re-emits `sort-change` with the payload.
- `Recipes/Index` `handleSortChange` takes `{ column, direction }` and stops toggling by itself.
- `ServerPagination` takes `:meta="recipes.meta"` and the existing `@page-change="handlePageChange"`.

#### 4. Meal-plan list callers

**Files**:
- `resources/js/Components/MealPlans/MealPlanTable.vue`
- `resources/js/Pages/MealPlans/Index.vue`

**Intent**: Same migration, plus a page handler so meal-plan pagination works.

**Contract**:
- `MealPlanTable` uses `SortableHeader` as above, with its column allowlist unchanged, and re-emits `{ column, direction }`.
- `Index.vue` maps that payload to its existing `sort`/`direction` filters.
- `Index.vue` builds its query in one helper, extracted from `handleFiltersChange`. It reads `localFilters`, with the keys `filter[status]` (only when set), `sort`, `direction` and `perPage`.
  - `handleFiltersChange` uses the helper and sends no `page`, so a filter change goes back to page 1.
  - A new `handlePageChange(page)` calls `router.get(route('meal-plans.index'), { ...helper(), page }, { preserveScroll: true, replace: true })`.
- The initial status reads `props.filters.filter?.status` instead of `props.filters['filter.status']`, at both `Index.vue:135` and `:195`. The `filters` type in `MealPlanCollectionProps` gains `filter?: { status?: string }`. Arriving by URL then keeps the status without relying on localStorage.
- `<ServerPagination :meta="mealPlans.meta" @page-change="handlePageChange" />`.

#### 5. Remove duplicates and extend the scan

**Files**:
- delete `resources/js/Components/MealPlans/ServerPagination.vue`
- delete `resources/js/Components/recipes/ServerPagination.vue`
- delete `resources/js/Components/MealPlans/SortableHeader.vue`
- delete `resources/js/Components/recipes/SortableHeader.vue`
- edit `scripts/lint-tokens.paths`

**Intent**: One implementation per role, guarded by the literal scan.

**Contract**:
- No import of the deleted paths remains (`grep -rn "ServerPagination\|SortableHeader" resources/js` shows only `Common/` and its callers).
- `scripts/lint-tokens.paths` gets the line `resources/js/Components/Common`.

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Literal scan passes with `Components/Common` listed: `vendor/bin/sail npm run lint:tokens`
- No references to the deleted duplicates remain: `grep -rnE "(MealPlans|recipes)/(ServerPagination|SortableHeader)" resources/js` returns nothing
- Pest suite passes: `vendor/bin/sail composer test`
- E2E suite passes: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e` (after `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`)

#### Manual Verification:

- On `/recipes` with more than 15 recipes:
  - the pagination is a single `nav` named „Paginacja” with Polish labels and a correct „Wyniki x–y z z”;
  - page changes keep the search, category and sort.
- On `/meal-plans?perPage=5`, the pagination appears, and page changes keep the status filter and sort. This also holds after opening `/meal-plans?filter[status]=done&perPage=5` with `mm.mealPlans.filters` removed from localStorage.
- In both tables:
  - clicking a sortable header, or pressing Enter or Space on it, toggles the sort once;
  - the active `<th>` carries `aria-sort` (check in DevTools);
  - a new column starts ascending.

**Implementation Note**: After automated verification passes, pause for manual confirmation before Phase 3.

---

## Phase 3: ConfirmDialog in Common

### Overview

Build the shared confirmation dialog on `alert-dialog`, move all five call sites onto it with caller-owned requests and Polish copy, delete the four old dialogs, and cover the behaviour with E2E.

### Changes Required:

#### 1. Shared confirmation dialog

**File**: `resources/js/Components/Common/ConfirmDialog.vue` (new)

**Intent**: One accessible confirmation that stays open while the confirmed action runs.

**Contract**:

- **Props:** `open: boolean` (`v-model:open`), `title: string`, `description: string`, `confirmLabel: string`, `variant?: 'default' | 'destructive'` (default `'default'`), `processing?: boolean` (default `false`).
- **Emits:** `update:open(boolean)`, `confirm()`.
- **Structure:** `AlertDialog` → `AlertDialogContent` → header with `AlertDialogTitle`/`AlertDialogDescription` → footer with `AlertDialogCancel` („Anuluj”) and a confirm `Button`.
- **Confirm button:**
  - uses `:variant="variant"`;
  - while processing: a `Loader2` icon with `animate-spin motion-reduce:animate-none` (`aria-hidden`), the label unchanged, and `aria-disabled="true"` with the click guard (not native `disabled`; see Critical Implementation Details).
- **While `processing`:** both buttons are disabled („Anuluj” natively, the confirm button through `aria-disabled`), `update:open(false)` is not emitted, and Esc is prevented (see Critical Implementation Details).
- **Styling:** tokens only.

#### 2. Recipe callers

**Files**:
- `resources/js/Pages/Recipes/Index.vue`
- `resources/js/Pages/Recipes/Show.vue`
- `resources/js/hooks/useDeleteRecipe.ts`
- `resources/js/Components/recipes/RecipeRow.vue` (trigger label only)

**Intent**: Replace `ConfirmDeleteModal` and `DeleteConfirmationDialog` with Polish confirmations that show progress.

**Contract**:
- **Copy:** title „Usuń przepis”, description `Czy na pewno chcesz usunąć przepis „{name}”? Tej operacji nie można cofnąć.`, `confirmLabel` „Usuń”, `variant="destructive"`.
- **`Recipes/Index`:**
  - adds a `deleting` ref passed as `processing`;
  - `router.delete` sets it, closes and resets the selection in `onFinish`;
  - shows `toast.error('Nie udało się usunąć przepisu.')` (`vue-sonner`) on error.
- **`Recipes/Show`:** keeps `useDeleteRecipe` and passes its `isLoading` as `processing`. The confirm handler wraps the hook call in `try { … } catch { /* toast already shown by the hook */ } finally { close the dialog }`. Today it closes only on success (`Show.vue:123-133`), and the hook's rejection goes unhandled.
- **`useDeleteRecipe.ts`:** reports errors through `vue-sonner` `toast.error`, with the same Polish message, instead of `window.toast`.
- **`RecipeRow`:** the delete button's `aria-label` becomes `Usuń {name}`, matching the family-member trigger.

#### 3. Family-member caller

**File**: `resources/js/Pages/FamilyMembers/FamilyMemberTable.vue`

**Intent**: Move the DELETE request and its toasts out of the dialog into the table, which already owns the selection.

**Contract**:
- The table owns `router.delete('/family-members/{id}', { preserveScroll: true })`, a `deleting` ref, and the existing success and error toasts (copied from the old dialog).
- On success it emits the existing `deleted` event. In `onFinish` (success or error) it resets `deleting`, closes the dialog and clears the selection.
- Copy: title „Usuń członka rodziny”, description `Czy na pewno chcesz usunąć członka rodziny „{first_name}”? Tej operacji nie można cofnąć.`, „Usuń”, destructive.
- `FamilyMembers/Index.vue` is unchanged.

#### 4. Meal-plan callers

**Files**:
- `resources/js/Components/MealPlans/ActionDropdown.vue`
- `resources/js/Pages/MealPlans/Show.vue`

**Intent**: Keep the existing copy and toasts, but keep each dialog open while its request runs.

**Contract**:
- The three instances (regenerate and delete in `ActionDropdown`, regenerate in `Show`) use `v-model:open` instead of `v-if`, and each has a `processing` ref.
- Handlers stop closing before `router.put`/`router.delete`, and close in `onFinish`.
- Titles, descriptions, `confirmLabel`s („Regeneruj”, „Usuń”) and variants stay as they are today.
- `ActionDropdown` opens both dialogs from a `DropdownMenuItem @click` (:28-45). Once the dialogs are no longer `v-if`-mounted, the closing menu's return-focus-to-trigger can race the dialog's focus on „Anuluj” and leave `pointer-events: none` on `<body>`. If check 3.7 shows either problem, call `preventDefault()` on `DropdownMenuContent`'s `close-auto-focus`, or open the dialog on `nextTick`.

#### 5. Remove old dialogs

**Files**:
- delete `resources/js/Components/MealPlans/ConfirmDialog.vue`
- delete `resources/js/Pages/FamilyMembers/ConfirmDialog.vue`
- delete `resources/js/Components/recipes/ConfirmDeleteModal.vue`
- delete `resources/js/Components/recipes/DeleteConfirmationDialog.vue`

**Intent**: One confirmation implementation.

**Contract**: No import of these paths remains.

#### 6. E2E

**Files**:
- `tests/e2e/meal-plan.spec.ts`
- `tests/e2e/confirm-dialog.spec.ts` (new)
- `database/seeders/E2eMealPlanSeeder.php`

**Intent**: Keep the regenerate path green and cover the new dialog's focus, cancel and confirm behaviour without touching the planner's seeded data.

**Contract**:

- **`meal-plan.spec.ts:63`:** `getByRole('dialog')` becomes `getByRole('alertdialog')`.
- **`confirm-dialog.spec.ts`, test 1 (planner `e2e-planner-<project>`, non-destructive):**
  1. On `/recipes`, click the first `Usuń …` row button. Expect `alertdialog` with heading „Usuń przepis”, and „Anuluj” focused.
  2. Press Escape. Expect the dialog hidden and the row still listed.
  3. Reopen, click „Anuluj”. Expect the same result.
- **`confirm-dialog.spec.ts`, test 2 (cook `e2e-cook-<project>`):**
  0. Log in inline, as `recipe-form.spec.ts:4-10` does. There is no shared login helper or `storageState`.
  1. Create a family member through `/family-members/create` with a name unique to the project. The required fields (`FamilyMemberRequest.php:31-35`) are `first_name`, `birth_date` (an adult, e.g. 1990-01-01), `gender` and, for an adult, `weight_kg` (30–300, e.g. 70) and `height_cm` (100–250, e.g. 175). The store action redirects to `/family-members`.
  2. On `/family-members`, click `Usuń {name}` and confirm with „Usuń” in the `alertdialog`.
  3. Expect the row gone and the success toast `Członek rodziny "{name}" został usunięty` (straight quotes, copied from the old dialog) visible.
- **Seeder:** the cook loop also deletes that user's family members, so test 2 can be re-run.

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Literal scan passes (`Components/Common` includes `ConfirmDialog.vue`): `vendor/bin/sail npm run lint:tokens`
- No references to the old dialogs remain: `grep -rnE "ConfirmDeleteModal|DeleteConfirmationDialog|(MealPlans|FamilyMembers)/ConfirmDialog" resources/js` returns nothing
- Pest suite passes: `vendor/bin/sail composer test`
- E2E suite, including `confirm-dialog.spec.ts`, passes in chromium and firefox after re-seeding: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`

#### Manual Verification:

- Deleting a recipe from `/recipes` and from a recipe's details page:
  - Polish dialog, focus on „Anuluj”;
  - spinner on „Usuń” while the request runs (throttle the network in DevTools);
  - Esc does nothing while it runs;
  - the dialog closes when done.
- Deleting and regenerating a meal plan, from the list's action menu and from the details page, behaves the same way, and the existing toasts still appear. From the action menu, focus lands on „Anuluj”, and after the dialog closes the page responds to clicks (no `pointer-events: none` left on `<body>`).
- Deleting a family member shows the success toast and removes the row. A forced failure (for example, the member deleted in another tab) shows the error toast.

**Implementation Note**: After automated verification passes, pause for manual confirmation before Phase 4.

---

## Phase 4: Category and status badges

### Overview

Put the domain badges on `Badge` variants, add the `muted` variant §4.4 requires, and replace the recipe list's inline badge.

### Changes Required:

#### 1. Muted badge variant

**File**: `resources/js/Components/ui/badge/index.js`

**Intent**: Give `pending` the §4.4 `muted` pair without an override at the call site (rule 3, „wariant zamiast nadpisania”).

**Contract**: New `muted` variant: `border-transparent bg-muted text-muted-foreground`. Existing variants are unchanged.

#### 2. CategoryBadge

**File**: `resources/js/Components/recipes/CategoryBadge.vue`

**Intent**: The category colour comes only from the variant.

**Contract**:
- Prop `category: string` (unchanged).
- Renders `<Badge :variant="category">{{ mealCategoryLabel(category) }}</Badge>` when `category` is in `MEAL_CATEGORIES`, otherwise `variant="outline"` with the raw value.
- No colour classes.

#### 3. StatusTag

**File**: `resources/js/Components/MealPlans/StatusTag.vue`

**Intent**: Status colours from state tokens, plus a visible „in progress” cue.

**Contract**:
- Prop `value` (unchanged).
- Variant mapping: `pending`→`muted`, `processing`→`info`, `done`→`success`, `error`→`destructive`; unknown values→`muted` with the raw value.
- Labels are unchanged (Oczekuje / Generowanie / Gotowy / Błąd).
- `processing` prefixes a `Loader2` icon `size-3 animate-spin motion-reduce:animate-none`, `aria-hidden`.
- No colour classes.

#### 4. Recipe list badge

**File**: `resources/js/Components/recipes/RecipeRow.vue`

**Intent**: The recipe list shows categories in the same colours as recipe details.

**Contract**: The category `<span>` and the `categoryClasses` computed are replaced by `<CategoryBadge :category="recipe.category" />`. The rest of the row is untouched.

#### 5. Scan list

**File**: `scripts/lint-tokens.paths`

**Intent**: Guard the migrated domain badges.

**Contract**: Append `resources/js/Components/recipes/CategoryBadge.vue` and `resources/js/Components/MealPlans/StatusTag.vue`.

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Literal scan passes with both badge files listed: `vendor/bin/sail npm run lint:tokens`
- E2E suite passes after re-seeding: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`

#### Manual Verification:

- `/recipes` and a recipe's details page show Śniadanie (honey), Obiad (tomato) and Kolacja (evening indigo) badges with labels.
- `/meal-plans` and a plan's details page show Oczekuje (muted), Generowanie (info, with a spinner), Gotowy (success) and Błąd (destructive). The spinner stops with „reduce motion” enabled in the OS.

**Implementation Note**: After automated verification passes, pause for manual confirmation before Phase 5.

---

## Phase 5: Kitchen sink, gates and docs

### Overview

Demo the delivered components in the kitchen sink, refresh the visual baselines in one deliberate commit, and bring the docs in line with the delivered contracts.

### Changes Required:

#### 1. Kitchen sink section

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: Every new shared pattern and variant has a demo with its states (CLAUDE.md rule).

**Contract**:
- **„Odznaki”:** gains a `muted` entry, „Wyciszona”.
- **New 12th section, „Komponenty aplikacji”** (`aria-labelledby="ds-komponenty-aplikacji"`), after „Nakładki”. It contains:
  - `CategoryBadge` × 3;
  - `StatusTag` × 4;
  - a small table with three `SortableHeader`s (one active ascending, one inactive) driven by local state;
  - a `ServerPagination` fed a static `meta` fixture (page 2 of 5, 10 per page, 50 total) that updates local state on `page-change`;
  - two triggers, „Otwórz ConfirmDialog” (destructive) and „Pokaż dialog w toku” (`processing: true`), each opening a `ConfirmDialog`. Neither name may contain another trigger's name, because the visual spec matches names by substring (`design-system.spec.ts:77,86`).
- No palette literals (the file is already scanned).

#### 2. Visual spec and baselines

**Files**:
- `tests/e2e/visual/design-system.spec.ts`
- `tests/e2e/__screenshots__/visual/design-system.spec.ts/*.png`

**Intent**: Gate the new section and both dialog states.

**Contract**:
- `{ name: 'Komponenty aplikacji', slug: 'komponenty-aplikacji' }` is added to the sections list.
- Two overlay tests, following the `alert dialog` pattern, produce `overlay-confirm-dialog.png` and `overlay-confirm-dialog-processing.png`. Their trigger locators use `{ exact: true }`.
- Baselines are regenerated only through `npm run test:visual -- --update-snapshots`.
- Expected PNG delta, and nothing else:
  - new: `section-komponenty-aplikacji.png`, `overlay-confirm-dialog.png`, `overlay-confirm-dialog-processing.png`;
  - changed: `section-odznaki.png`.

#### 3. Design system and rule docs

**Files**:
- `context/foundation/design-system.md`
- `CLAUDE.md`
- `context/foundation/roadmap.md`

**Intent**: The specs describe what exists and where the deferred components went.

**Contract**:
- **`design-system.md`:**
  - §5.1: the `badge` row lists `muted`.
  - §5.2:
    - `ServerPagination` → props `meta`, emit `page-change`, no `links`;
    - `SortableHeader` → renders `TableHead`, emits `sort-change({column, direction})`;
    - `ConfirmDialog` → adds `open`/`v-model:open` and the `confirm` emit, and stays open while `processing`;
    - `PageHeader`/`EmptyState`/`FormField` marked as deferred to S-09/S-10/S-11 and S-13.
  - §7.2: 12 sections.
  - §7.3: 22 screenshots.
  - §8: the DS-02 row loses `PageHeader`/`EmptyState`/`FormField`; DS-04/05/06 gain `PageHeader`/`EmptyState`; DS-08 already has `FormField`.
- **`CLAUDE.md` line 28:** drop „(`Common/` is created in S-07)” and name `Common/ConfirmDialog`, `ServerPagination` and `SortableHeader` as the components to reuse.
- **`roadmap.md`:**
  - S-07 Unknown marked resolved (deferred, with the slices named).
  - S-10 notes that S-07 already fixed the `filters.filter.status` read in `MealPlans/Index`.
  - S-09/S-10/S-11 Risk or Unknowns note that the first of them to need `PageHeader`/`EmptyState` builds it.
  - S-13 notes `FormField`.

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- `lint:tokens` self-test and scan pass: `bash scripts/lint-tokens.test.sh && vendor/bin/sail npm run lint:tokens`
- Visual gate passes with 22 baselines: `npm run test:visual`
- The only PNG changes are the 3 new files and `section-odznaki.png`: `git status --porcelain tests/e2e/__screenshots__`
- Kitchen-sink Pest test passes: `vendor/bin/sail pest --filter=DesignSystemPageTest`
- Full Pest suite passes: `vendor/bin/sail composer test`

#### Manual Verification:

- Each new and changed PNG in the diff was reviewed and shows the intended component states.
- `/dev/design-system`: the „Komponenty aplikacji” demos work interactively (sorting toggles, pagination moves, both dialogs open, Esc closes only the non-processing one).
- `design-system.md` §5.2/§8, `CLAUDE.md` and the roadmap read consistently with the delivered code.

---

## Testing Strategy

### Unit Tests:

- Pest: `MealPlanCollection` pagination props are scalar and complete (Phase 1). There is no JS unit-test tooling, and adding one is out of scope.

### Integration Tests:

- **E2E `confirm-dialog.spec.ts`:** role `alertdialog`, initial focus on „Anuluj”, Esc and „Anuluj” cancel without deleting (seeded planner recipe), a real delete of a test-created family member (cook user).
- **E2E `meal-plan.spec.ts`:** the regenerate confirmation through `alertdialog`.
- **Visual gate:** 22 zero-tolerance screenshots, including both ConfirmDialog states and the new section.
- **`lint:tokens`:** `Components/Common`, `CategoryBadge.vue` and `StatusTag.vue`.

### Manual Testing Steps:

1. Throttle the network, delete a recipe from the list, and watch the spinner. Esc is ignored until the request finishes.
2. On `/meal-plans?perPage=5`, filter by status, sort by date, go to page 2. The filter and sort persist.
3. Sort a table with the keyboard (Tab to the header, Enter, Space). Each key toggles once, and `aria-sort` follows.
4. Check the category and status colours on `/recipes`, a recipe's details page, `/meal-plans` and a plan's details page.

## Performance Considerations

None significant. The components are presentational, and the meal-plan response gets smaller (no duplicated pagination block).

## Migration Notes

No data migration. The `meta` shape the meal-plan Inertia page receives changes from doubled arrays to scalars. Only `MealPlans/Index.vue` and its children read it. The JSON branch is unchanged.

## References

- Design contract: `context/foundation/design-system.md` §3, §4.3–4.4, §5.1–5.4, §7, §8 (DS-02)
- Roadmap: `context/foundation/roadmap.md` S-07
- Prerequisite plans:
  - `context/archive/2026-11-15-design-tokens-foundation/plan.md`
  - `context/archive/2026-11-15-design-system-visual-gate/plan.md`
- Laravel pagination hook: `vendor/laravel/framework/src/Illuminate/Http/Resources/Json/PaginatedResourceResponse.php:50-63`
- Existing kitchen-sink alert-dialog and pagination demos: `resources/js/Pages/Dev/DesignSystem.vue:464-531`
- Visual overlay test pattern: `tests/e2e/visual/design-system.spec.ts:84-91`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Meal-plan pagination data

#### Automated

- [x] 1.1 The new regression test fails before the fix and passes after
- [x] 1.2 Full Pest suite passes
- [x] 1.3 Larastan passes
- [x] 1.4 Pint passes

#### Manual

- [ ] 1.5 Meal-plan list with more than 5 plans shows the plan count and renders pagination

### Phase 2: ServerPagination and SortableHeader in Common

#### Automated

- [ ] 2.1 Build passes
- [ ] 2.2 Literal scan passes with Components/Common listed
- [ ] 2.3 No references to the deleted duplicates remain
- [ ] 2.4 Pest suite passes
- [ ] 2.5 E2E suite passes

#### Manual

- [ ] 2.6 Recipe pagination is one Polish „Paginacja” nav and keeps search, category and sort
- [ ] 2.7 Meal-plan pagination appears and keeps the status filter and sort
- [ ] 2.8 Sortable headers toggle once per click/Enter/Space and set aria-sort; a new column starts ascending

### Phase 3: ConfirmDialog in Common

#### Automated

- [ ] 3.1 Build passes
- [ ] 3.2 Literal scan passes
- [ ] 3.3 No references to the old dialogs remain
- [ ] 3.4 Pest suite passes
- [ ] 3.5 E2E suite including confirm-dialog.spec.ts passes in chromium and firefox

#### Manual

- [ ] 3.6 Recipe delete (list and details page) shows the Polish dialog, focus on Anuluj, spinner, Esc blocked while running
- [ ] 3.7 Meal-plan delete and regenerate (menu and details page) behave the same with existing toasts
- [ ] 3.8 Family-member delete shows success toast; a forced failure shows the error toast

### Phase 4: Category and status badges

#### Automated

- [ ] 4.1 Build passes
- [ ] 4.2 Literal scan passes with both badge files listed
- [ ] 4.3 E2E suite passes

#### Manual

- [ ] 4.4 Recipe list and details page show time-of-day category badges with labels
- [ ] 4.5 Meal-plan list and details page show the four status variants; spinner respects reduced motion

### Phase 5: Kitchen sink, gates and docs

#### Automated

- [ ] 5.1 Build passes
- [ ] 5.2 lint:tokens self-test and scan pass
- [ ] 5.3 Visual gate passes with 22 baselines
- [ ] 5.4 Only the 3 new PNGs and section-odznaki.png changed
- [ ] 5.5 Kitchen-sink Pest test passes
- [ ] 5.6 Full Pest suite passes

#### Manual

- [ ] 5.7 Each new and changed PNG reviewed
- [ ] 5.8 Kitchen-sink „Komponenty aplikacji” demos work interactively
- [ ] 5.9 design-system.md, CLAUDE.md and roadmap read consistently with the code
