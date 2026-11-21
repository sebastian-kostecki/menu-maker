# Menu-only PDF ("Sam jadłospis") Implementation Plan

## Overview

Add a third PDF variant for a finished (`done`) meal plan: a single landscape A4 page with only the menu, a grid of days (columns) × meals (rows) holding recipe names. It has no ingredients, shopping list, preparation steps, linked-meal markers or scaling notices. It is offered as an additional option ("Sam jadłospis") everywhere the two existing PDFs can be downloaded.

## Current State Analysis

- One route, `GET /meal-plans/{meal_plan}/pdf` (`routes/web.php:43`, name `meal-plans.pdf`), handled by `MealPlanController::downloadPdf` (`app/Http/Controllers/MealPlanController.php:353`). It authorizes through `DownloadMealPlanPdfRequest` (`downloadPdf` policy ability), returns 404 unless `status === 'done'`, and streams the PDF inline with `Cache-Control: no-store, private`.
- `DownloadMealPlanPdfRequest::rules()` accepts one optional flag, `recipes` (`sometimes|boolean`).
- `MealPlanPdfExporter` (`app/Services/MealPlanPdf/MealPlanPdfExporter.php`) builds a `MealPlanDocument` (days → `PdfMeal` with ingredients, shopping list, scaling notices, optional recipe pages). It renders `resources/views/pdf/meal-plan.blade.php` on A4 landscape with DejaVu Sans, and names the file `jadlospis-<start>-<end>[-z-przepisami].pdf`.
- The day label is built inline in `document()`: `Str::ucfirst(isoFormat('dddd')).' '.format('d.m')` with the `pl` locale, e.g. "Poniedziałek 16.11". Meals within a day sort by `MealCategory::displayIndex` (Śniadanie, Obiad, Kolacja). An empty slot (meal with `recipe === null`) is shown as "Bez posiłku".
- Plans are always 7 days from `start_date` (`GenerateMealPlanRequest`). Recipe names can be up to 255 characters (`StoreRecipeRequest`).
- Links reach the UI as `links.pdf` / `links.pdf_with_recipes` from `MealPlanResource.php:114-115` and `MealPlanCollection.php:103-104` (null unless done). They are consumed by `PdfDownloadMenu.vue` (used in `Pages/MealPlans/Show.vue:31`, `Components/MealPlans/MealPlanTile.vue:38`, `Components/Dashboard/LatestPlanHero.vue:36`), by `Components/MealPlans/ActionDropdown.vue:20-33`, and typed in `resources/js/types/meal-plan.ts:21`, `Show.vue:227` and `Index.vue:93`.
- `tests/Unit/Services/MealPlanPdfPaletteTest.php` guards the hex mirror of `app.css :root` tokens, but only in `pdf/meal-plan.blade.php`.
- The kitchen sink (`Pages/Dev/DesignSystem.vue:311,385,1238`) demos `PdfDownloadMenu`. The visual gate screenshots the open menu (`tests/e2e/visual/design-system.spec.ts:217`, `overlay-pdf-download-menu.png`).

## Desired End State

- `GET /meal-plans/{id}/pdf?menu=1` on a done plan returns an inline PDF named `jadlospis-<start>-<end>-sam-jadlospis.pdf`. It is **exactly one page**: the title "Jadłospis dd.mm.yyyy – dd.mm.yyyy", then a grid filling the page, with 7 day columns ("Poniedziałek 16.11"…) and 3 meal rows (Śniadanie, Obiad, Kolacja) whose headers carry the meal colours. Each cell holds only the recipe name, or "Bez posiłku" for an empty slot.
- `menu=1` together with `recipes=1` is rejected with a validation error. The existing authorization, 404-until-done and guest-redirect rules apply unchanged.
- Both Resources expose `links.pdf_menu` (null unless done). Every PDF dropdown (plan page, plans-list tile, dashboard hero, plans-list action menu) has a third item, "Sam jadłospis".

Verify with the Pest suite (page count = 1 for maximal 255-char names, content and absence checks), the visual gate, and opening the PDF by hand.

### Key Discoveries:

- `pdfText()` (`tests/Pest.php:88`) and `Smalot\PdfParser\Parser::getPages()` (used at `tests/Unit/Services/MealPlanPdfExporterTest.php:358`) are already available for text and page-count assertions.
- The palette test helper reads a hard-coded template path (`MealPlanPdfPaletteTest.php:17`). It must be parametrised to cover the new template, or the new template would escape the guard.
- `MealLinkRules` markers and `ScalingNotices` stay out of this variant (user decision: recipe names only).

## What We're NOT Doing

- No linked-meal markers ("Z wczoraj", "Na 2 dni", "Też na śniadanie"), no "(usunięty)" marker and no scaling notices in the menu PDF.
- No renaming of the existing "Z przepisami" / "Bez przepisów" options or their files.
- No combination of the menu with recipe pages or the shopping list.
- No change to the existing two variants' content, layout or tests beyond adding `pdf_menu` assertions next to the existing link assertions.
- No E2E spec change (the controller/Inertia feature tests cover the link wiring; the visual gate covers the menu item).
- No new route, policy or model: the existing `downloadPdf` ability covers the new flag.

## Implementation Approach

Reuse the endpoint and add a `menu` flag, which keeps authorization, the status check and headers in one place. The menu gets its own small DTO and Blade view instead of branches in the existing template. That way the full template stays untouched, the menu view can use a page-filling fixed-height layout, and the exporter skips the shopping-list and notices work it doesn't need. One page is guaranteed deterministically: names are truncated to a fixed limit with an ellipsis, and the grid uses fixed row heights in `pt` that fit A4 landscape. A test renders 21 maximal names and asserts one page.

## Critical Implementation Details

- **One-page guarantee**: dompdf splits a table that overflows onto a second page, and percentage heights are unreliable. So size the rows in absolute `pt` (A4 landscape is 842×595 pt minus page margins), set `@page` margins explicitly, and truncate names with `Str::limit()` to a class constant tuned so a cell full of the longest allowed text still fits. Tune the constant and the font size against the 255-character test, not by eye.

## Phase 1: Backend: menu-only PDF

### Overview

Accept `menu=1`, build the menu DTO, render the one-page grid, expose `links.pdf_menu`, and cover all of it with tests.

### Changes Required:

#### 1. Form Request flag

**File**: `app/Http/Requests/DownloadMealPlanPdfRequest.php`

**Intent**: Accept the new optional `menu` flag and reject asking for the menu together with recipe pages, so there is no ambiguous variant.

**Contract**: `rules()` gains `'menu' => ['sometimes', 'boolean']`. `recipes` must be prohibited when `menu` is accepted: use `prohibited_if_accepted:menu` if the installed Laravel 13 provides it (check with `search-docs`/vendor), otherwise an equivalent `after()` validation. The error is reported on `recipes`.

#### 2. Menu DTOs

**Files**: `app/Services/MealPlanPdf/MealPlanMenu.php`, `app/Services/MealPlanPdf/PdfMenuRow.php` (new, `declare(strict_types=1)`, `final readonly`, like siblings)

**Intent**: A typed shape for the grid, so the template only iterates.

**Contract**: `MealPlanMenu(string $title, list<string> $dayLabels, list<PdfMenuRow> $rows)`. `PdfMenuRow(string $category, string $categoryValue, list<string> $cells)`, with one row per `MealCategory` case in display order and `count($cells) === count($dayLabels)`. A cell is the recipe name (truncated), "Bez posiłku" for a meal without a recipe, or `''` when the plan has no meal for that day/category.

#### 3. Exporter

**File**: `app/Services/MealPlanPdf/MealPlanPdfExporter.php`

**Intent**: Build and render the menu without touching the shopping list, notices or ingredients, and name its file.

**Contract**:
- `menu(MealPlan $mealPlan): MealPlanMenu` loads `meals.recipe` only. Days come from the plan's meal dates, sorted. Deleted (trashed) recipes show their plain name with no marker. Names pass through `Str::limit($name, self::MENU_NAME_LIMIT)` (default ellipsis).
- `renderMenu(MealPlan $mealPlan): string` renders `pdf.meal-plan-menu` with the same dompdf options as `render()` (A4 landscape, DejaVu Sans, remote/PHP disabled, subsetting).
- `menuFilename(MealPlan $mealPlan): string` returns `jadlospis-<Y-m-d start>-<Y-m-d end>-sam-jadlospis.pdf`.
- Extract the day-label formatting into a private helper shared by `document()` and `menu()`, so both variants label days identically. `document()`'s output must not change.

#### 4. Menu template

**File**: `resources/views/pdf/meal-plan-menu.blade.php` (new)

**Intent**: The one-page grid, filling the whole landscape page.

**Contract**: `<html lang="pl">`, `<title>` = `$menu->title`, then an `<h1>` title and one table. The header row has an empty corner and the day labels. There are 3 body rows, each with a `td.category.meal-<value>` header (same colour rules as the full template) and 7 equal-width cells. The cells are vertically centred, wrap long words, and use row heights in `pt` so the table fills the page below the title. A palette mirror comment in the same format as `meal-plan.blade.php` lists only the tokens this template uses, and every listed hex is used.

#### 5. Controller

**File**: `app/Http/Controllers/MealPlanController.php`

**Intent**: Serve the menu variant from the existing action.

**Contract**: In `downloadPdf`, after the existing `done` check, `$request->boolean('menu')` selects `renderMenu()`/`menuFilename()`. Otherwise the current behaviour is unchanged. Same headers. Update the docblock to mention the menu variant.

#### 6. Resources

**Files**: `app/Http/Resources/MealPlanResource.php`, `app/Http/Resources/MealPlanCollection.php`

**Intent**: Expose the new link alongside the existing ones.

**Contract**: `links.pdf_menu` = `route('meal-plans.pdf', [$id, 'menu' => 1])` when `status === 'done'`, else `null`.

#### 7. Tests

**Files**: `tests/Unit/Services/MealPlanPdfExporterTest.php`, `tests/Feature/MealPlanControllerTest.php`, `tests/Feature/DashboardControllerTest.php`, `tests/Unit/Services/MealPlanPdfPaletteTest.php`

**Intent**: Cover the new variant's shape, content, one-page guarantee and access rules.

**Contract**:
- Exporter unit tests:
  - `menu()` gives 7 day labels in date order, rows in Śniadanie/Obiad/Kolacja order, a recipe name in its cell, "Bez posiłku" for a recipe-less meal, and a plain name with no "(usunięty)" for a trashed recipe.
  - A 255-character name is truncated to `MENU_NAME_LIMIT` with an ellipsis.
  - `renderMenu()` of a full 7×3 plan with 255-character names has exactly 1 page (`Parser::getPages()`).
  - The rendered text contains the names and does not contain "Lista zakupów", "Składniki", "Przygotowanie", a linked-meal marker or a scaling notice.
  - `menuFilename()` returns the expected name.
- Controller feature tests:
  - The owner gets `?menu=1` inline with the `-sam-jadlospis.pdf` filename and `no-store`.
  - `menu=1&recipes=1` and `menu=abc` are invalid.
  - Add a `with menu` row to the existing forbidden / not-done / guest datasets.
  - The Inertia `links.pdf_menu` assertions sit next to the existing `pdf`/`pdf_with_recipes` ones (Show, Index, Dashboard).
- Palette test: parametrise `pdfPaletteSources()` by template path and run its checks over both `pdf/meal-plan.blade.php` and `pdf/meal-plan-menu.blade.php` (dataset). The "colour rule for every meal category" check applies to both.

### Success Criteria:

#### Automated Verification:

- Pest suite passes: `vendor/bin/sail composer test`
- Menu exporter and controller tests pass: `vendor/bin/sail pest --filter=menu`
- Larastan passes with no new baseline entries: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`

#### Manual Verification:

- Opening `/meal-plans/<done id>/pdf?menu=1` in a browser shows one landscape page with the 7×3 grid filling the page, coloured meal headers, readable names and Polish characters rendered correctly.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Frontend: "Sam jadłospis" option

### Overview

Offer the new PDF in every PDF menu, demo it in the kitchen sink, and refresh the menu baseline.

### Changes Required:

#### 1. Types

**Files**: `resources/js/types/meal-plan.ts`, `resources/js/Pages/MealPlans/Show.vue` (local type, ~line 227), `resources/js/Pages/MealPlans/Index.vue` (~line 93), `resources/js/Components/MealPlans/ActionDropdown.vue` (~line 113)

**Intent**: Carry the new link through the typed props.

**Contract**: The `links` shapes gain `pdf_menu: string | null`.

#### 2. PdfDownloadMenu

**File**: `resources/js/Components/MealPlans/PdfDownloadMenu.vue`

**Intent**: Add the third option.

**Contract**: The `links` prop becomes `{ pdf: string; pdf_with_recipes: string; pdf_menu: string }`. A third `DropdownMenuItem` (anchor, `target="_blank" rel="noopener"`, like the others) with the `CalendarDays` icon and the label "Sam jadłospis" goes after "Bez przepisów". Keep the `w-48` width unless the label needs more (token utilities only).

#### 3. Call sites

**Files**: `resources/js/Pages/MealPlans/Show.vue:31-33`, `resources/js/Components/MealPlans/MealPlanTile.vue:38-40`, `resources/js/Components/Dashboard/LatestPlanHero.vue:17,36-39`

**Intent**: Pass the new link and keep the "all links present" guards consistent.

**Contract**: Each `v-if`/`hasPdfLinks` guard also requires `pdf_menu`, and each `:links` object passes `pdf_menu`.

#### 4. ActionDropdown

**File**: `resources/js/Components/MealPlans/ActionDropdown.vue`

**Intent**: The plans-list action menu offers the same third option.

**Contract**: After "PDF bez przepisów", add an item "PDF — sam jadłospis" (`CalendarDays` icon), `:disabled="!item.links.pdf_menu"`, `@click="handleDownloadPdf(item.links.pdf_menu)"`.

#### 5. Kitchen sink and visual baseline

**Files**: `resources/js/Pages/Dev/DesignSystem.vue` (~lines 311, 385, 1238), `tests/e2e/visual/**/overlay-pdf-download-menu*.png`

**Intent**: Keep the demo data typed and complete, and record the new menu look deliberately.

**Contract**: The demo `links` objects gain `pdf_menu` (`'#pdf-sam-jadlospis'`, or `#ds-plan-${id}-pdf-menu` / `null` following the `withPdf` pattern). Then run `npm run test:visual -- --update-snapshots` per CLAUDE.md, and review every changed PNG. Only the PDF-menu overlay (and any shot showing an open PDF menu) should change.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token lint passes: `vendor/bin/sail npm run lint:tokens`
- Pest suite still passes: `vendor/bin/sail composer test`
- Visual gate passes after the deliberate baseline update: `npm run test:visual`

#### Manual Verification:

- On the plan page, a plans-list tile, the plans-list action menu and the dashboard hero of a done plan, "Sam jadłospis" opens the one-page menu PDF in a new tab. The other two options still open their PDFs.
- The updated `overlay-pdf-download-menu.png` diff shows only the added third item.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful.

---

## Testing Strategy

### Unit Tests:

- `menu()` DTO shape: day order, category order, recipe name, "Bez posiłku", trashed recipe without a marker, truncation at `MENU_NAME_LIMIT`.
- `renderMenu()`: exactly one page with 21 names of 255 characters; no ingredient, shopping-list, preparation, marker or notice text.
- Palette guard over both templates.

### Integration Tests:

- `GET meal-plans.pdf?menu=1`: owner OK inline with the `-sam-jadlospis.pdf` name; forbidden for a non-owner whatever the status; 404 until done; guest redirect; `menu=1&recipes=1` and `menu=abc` invalid.
- Inertia props: `links.pdf_menu` on Show, Index and Dashboard for a done plan, `null` otherwise.

### Manual Testing Steps:

1. Seed (`vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`), log in as `e2e-planner-visual@example.com`, and open the done plan starting `2026-06-15`.
2. Open "Pobierz PDF" → "Sam jadłospis". Check that it is one page, the grid fills the page, and there are no ingredients or shopping list.
3. Repeat from the plans list (tile menu and action menu) and from the dashboard hero.
4. Open "Z przepisami" and "Bez przepisów" to confirm they are unchanged.

## Performance Considerations

The menu render loads only `meals.recipe` and skips the shopping list and ingredient formatting. A one-page document is cheaper than the existing variants. No caching is needed.

## Migration Notes

None. There is no schema change, the existing links and query flags are unchanged, and `pdf_menu` is additive.

## References

- Existing PDF pipeline: `app/Services/MealPlanPdf/MealPlanPdfExporter.php`, `resources/views/pdf/meal-plan.blade.php`
- Controller action: `app/Http/Controllers/MealPlanController.php:353`
- Existing PDF tests: `tests/Feature/MealPlanControllerTest.php:819-915`, `tests/Unit/Services/MealPlanPdfExporterTest.php`
- Palette guard: `tests/Unit/Services/MealPlanPdfPaletteTest.php`
- Prior PDF changes: `context/archive/2026-11-14-meal-plan-pdf-export/`, `context/archive/2026-11-16-pdf-recipe-pages/`, `context/archive/2026-11-15-pdf-design-alignment/`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Backend: menu-only PDF

#### Automated

- [x] 1.1 Pest suite passes: `vendor/bin/sail composer test` — f2fd471
- [x] 1.2 Menu exporter and controller tests pass: `vendor/bin/sail pest --filter=menu` — f2fd471
- [x] 1.3 Larastan passes with no new baseline entries: `vendor/bin/sail composer phpstan` — f2fd471
- [x] 1.4 Pint passes: `vendor/bin/sail composer pint-test` — f2fd471

#### Manual

- [ ] 1.5 Opening `/meal-plans/<done id>/pdf?menu=1` shows one landscape page with the 7×3 grid filling the page, coloured meal headers, readable names and correct Polish characters

### Phase 2: Frontend: "Sam jadłospis" option

#### Automated

- [x] 2.1 Client and SSR bundles build: `vendor/bin/sail npm run build`
- [x] 2.2 Token lint passes: `vendor/bin/sail npm run lint:tokens`
- [x] 2.3 Pest suite still passes: `vendor/bin/sail composer test`
- [x] 2.4 Visual gate passes after the deliberate baseline update: `npm run test:visual`

#### Manual

- [ ] 2.5 "Sam jadłospis" opens the one-page menu PDF from the plan page, plans-list tile, plans-list action menu and dashboard hero; the other two options still work
- [ ] 2.6 The updated `overlay-pdf-download-menu.png` diff shows only the added third item
