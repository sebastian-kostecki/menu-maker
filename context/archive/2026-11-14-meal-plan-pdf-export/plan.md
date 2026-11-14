# Meal Plan PDF Export (S-06) Implementation Plan

## Overview

A user downloads one PDF for a finished meal plan. It contains, first, the plan table (day, category, name, ingredients, preparation), then the shopping list (FR-7, US-010). The PDF is rendered from the plan's current meals on every request with dompdf and opens inline in a new tab. Nothing is stored, so a regeneration can never leave a stale file behind. The same change closes an authorization gap on the existing download route and removes the `pdf_path`/`pdf_size` columns that nothing writes. This is roadmap S-06 `meal-plan-pdf-export`.

## Current State Analysis

- The download route exists, but nothing produces a PDF.
  - `GET /meal-plans/{meal_plan}/pdf` is named `meal-plans.pdf` and sits in the `auth`+`verified` group (`routes/web.php:38-39`).
  - `MealPlanController::downloadPdf` (`app/Http/Controllers/MealPlanController.php:192-215`) serves `pdf_path` from the `local` disk. No PDF library is installed (`composer.json:11-20`), and `GenerateMealPlanJob` never writes `pdf_path`.
- **Security gap.** `downloadPdf` has no owner check, so any verified user can request another user's plan by id.
  - `authorizeResource()` (`MealPlanController.php:32`) covers only resource actions, and `MealPlanPolicy` (`app/Policies/MealPlanPolicy.php`) has no download ability.
  - `.ai/core/api-plan.md:82` already names a `downloadPdf` policy ability.
- Bugs in the current action:
  - It sends `Content-Disposition: attachment` (`:214`), which works against "otwiera się w przeglądarce".
  - The filename `sprintf`s the `date`-cast attributes (`:206-210`, casts at `app/Models/MealPlan.php:33-34`), so it contains `Y-m-d H:i:s`.
  - The 404 messages are in English.
- `pdf_path`/`pdf_size` are dead weight:
  - The migration creates them (`database/migrations/2025_07_31_145657_create_meal_plans_table.php:21-22`). The model makes them fillable and casts them (`app/Models/MealPlan.php:28-29,36`).
  - The factory's `done()` state sets a fake path and size (`database/factories/MealPlanFactory.php:75-76`), and there is a `withPdf()` state (`:130-135`).
  - `MealPlanResource` exposes the raw storage path (`app/Http/Resources/MealPlanResource.php:35-36`).
  - `destroy()` deletes the file (`MealPlanController.php:222-225`), and only `tests/Feature/MealPlanControllerTest.php:231-248` tests that.
  - `ActionDropdown` enables "Pobierz PDF" only when `pdf_path` is set (`resources/js/Components/MealPlans/ActionDropdown.vue:117`).
- Pieces S-05 left for reuse:
  - `ShoppingListBuilder::forMealPlan()` (`app/Services/ShoppingList/ShoppingListBuilder.php:74-116`) returns `ShoppingList` with Polish `display` strings and the names of recipes without ingredients. It eager-loads `meals.recipe.recipeIngredients.{ingredient,unit}` and includes soft-deleted recipes (`app/Models/Meal.php:31-34`).
  - Its Polish number formatting is private (`ShoppingListBuilder.php:46-49,138-146`).
- Labels:
  - Category labels and display order are in `app/Enums/MealCategory.php:23-50`. Unit labels are in `Unit::LABELS` (`app/Models/Unit.php:65-71`).
  - Day names exist only in the frontend (`resources/js/Components/MealPlans/MealPlanGrid.vue:109-110`), and the app locale is `pl`. Preparation is `recipes.instructions` (text).
- Meal ordering (date, then category order) is already in `MealPlanResource.php:44-49`.
- `Show.vue` shows "Regeneruj" and the `ActionDropdown` in its header (`resources/js/Pages/MealPlans/Show.vue:15-22`), and polls `only: ['mealPlan', 'shoppingList']` while the plan is generating (`:155`).
  - The `{ self }` link shape is declared locally in `Show.vue:124-126`, `ActionDropdown.vue:99-101`, `MealPlanRow.vue:68`, `MealPlanTable.vue:86`, `Pages/MealPlans/Index.vue:79` and `resources/js/types/meal-plan.ts:18-20`.
- `meal-plan.spec.ts` covers generate → grid → list → regenerate and has no PDF step.
- The container and CI:
  - The Sail container has PHP `dom`, `mbstring` and `gd`.
  - CI's setup-php installs `mbstring, dom, …` (`.github/workflows/master.yml:80,169`).
  - `barryvdh/laravel-dompdf` v3.1.2 requires `illuminate/support ^13.0` and `dompdf/dompdf ^3.0`, which needs ext-dom and ext-mbstring. dompdf bundles DejaVu Sans.
  - `smalot/pdfparser` v2.13 requires ext-zlib and ext-iconv.

## Desired End State

On a `done` plan, the plan view's header shows a "Pobierz PDF" button, and the "⋯" menu item is enabled on both the plan view and the list. Either one opens `/meal-plans/{id}/pdf` in a new tab, and the browser shows the PDF inline. Saving it keeps a filename like `jadlospis-2026-11-16-2026-11-22.pdf`.

The PDF is A4 landscape with Polish diacritics intact:
- The header is "Jadłospis 16.11.2026 – 22.11.2026".
- Then comes one table with the columns Dzień / Posiłek / Nazwa / Składniki / Przygotowanie. It has 21 rows sorted by date, then Śniadanie/Obiad/Kolacja. Each day's label ("Poniedziałek 16.11") spans that day's rows. Ingredients appear as written in the recipe ("Mąka — 100 g"), followed by the full preparation text.
- "Lista zakupów" starts on a new page, with the same rows, order and notes as the card on the plan view.

While a plan is generating or has failed, there is no button, the menu item is disabled, and the URL returns 404. Another user's plan returns 403. After "Regeneruj", the button comes back with the new draw, and the PDF matches it, because it is rendered from the current meals.

Verify with Pest (exporter unit tests that read the PDF's text, controller feature tests, policy test), Larastan, Pint, `npm run build`, and the extended `tests/e2e/meal-plan.spec.ts`.

### Key Discoveries:

- `authorizeResource` maps only the seven resource actions. Any extra action needs its own `$this->authorize(...)` (`MealPlanController.php:32`).
- One URL now serves different content after every regeneration. Without `Cache-Control: no-store`, a browser's PDF viewer can show the previous draw, which would break US-010.3.
- Inertia polls `mealPlan` (`Show.vue:155`), so putting the download URL in `MealPlanResource` (`links.pdf`) makes the button appear by itself once generation finishes. No new prop and no poll change are needed.
- dompdf supports CSS 2.1 tables, `thead` repeated on every page, `page-break-before` and `page-break-inside: avoid`. It does not support flex, grid or Tailwind, so the template gets its own small inline `<style>`.
- Recipe ingredient lines and shopping-list rows need the same Polish number format. Moving the formatter out of `ShoppingListBuilder` keeps the two consistent ("0,25 l" in both places).

## What We're NOT Doing

- A queued `GeneratePdfJob`, stored PDF files, invalidating them, or a `prune` command (`.ai/core/api-plan.md:110,129`). Rendering on request replaces all of them.
- A PDF rate limit (`.ai/core/api-plan.md:121`, parked in the roadmap).
- Snapshotting the plan at generation time. A recipe edited after generation changes the PDF, the grid and the list alike. "Latest generated version" means the plan's current meal draw.
- Scaling quantities to the family (S-04). Ingredients and the list use the recipe quantities as written until S-04 changes the seam.
- The dashboard's hard-coded "Ostatni PDF" card (parked: dashboard).
- A JSON/API variant of the PDF, page numbers or a footer, images, or printing styles for the web view.
- Changing `GenerateMealPlanJob`, `MealPlanGenerator` or the shopping-list rules.

## Implementation Approach

1. **Document data, then rendering.** A new service, `App\Services\MealPlanPdf\MealPlanPdfExporter`, builds a typed document from a plan: day groups, meal rows and the S-05 `ShoppingList`. It renders that document through a Blade view with dompdf (A4 landscape, DejaVu Sans) and returns the bytes. This is the `PdfExportService` of `api-plan.md:108`, renamed to follow the `ShoppingList` service folder pattern.
2. **Thin controller.** `downloadPdf` authorizes, checks `done`, asks the exporter for bytes and a filename, and returns them inline with `no-store`. Render errors propagate as a 500 and reach the error tracker (F-01).
3. **One source of truth for "can download".** The resource exposes `links.pdf` (the route URL when the plan is `done`, else `null`). Both UI entry points read only that field, and the dead columns are dropped.
4. **Tests that read the PDF.** `smalot/pdfparser` (dev only) extracts the text so tests can assert content, order and Polish glyphs. A missing glyph comes out as `?` and fails the test.

## Critical Implementation Details

- **Authorize before the status check.** Call `authorize('downloadPdf', …)` before the `done` check, so a non-owner gets 403 and cannot tell from a 404 whether someone else's plan is ready.
- **Rowspan across a page break.** dompdf can mis-draw a `rowspan` cell when its rows split across pages. Give each day its own `<tbody>` with `page-break-inside: avoid`; three rows always fit on a landscape page unless instructions are extremely long. If the manual check shows a broken day cell anyway, drop `rowspan`. Put the label in the day's first row, leave it empty in the next two, and keep a stronger top border per day. The day grouping stays the same either way.
- **Text extraction is approximate.** pdfparser may split table cells with tabs or newlines. Assert short substrings that live inside one cell ("Mąka — 600 g", "Poniedziałek 16.11", "1,2 kg"). Never assert whole rows. Check order with `strpos` comparisons, not by matching across cells.
- **dompdf font directory.** laravel-dompdf uses `storage/fonts` for the font cache. If the first render fails because that directory is missing, create it with a `.gitignore` (matching the other `storage/` subfolders); do not publish the config just for that.

## Phase 1: PDF document

### Overview

Add the dependencies and build a service that turns a plan into PDF bytes. Unit tests read the generated text. No HTTP or UI changes yet.

### Changes Required:

#### 1. Dependencies

**File**: `composer.json`, `composer.lock`

**Intent**: Add the approved renderer and the dev-only text extractor.

**Contract**:
- `vendor/bin/sail composer require barryvdh/laravel-dompdf:^3.1`
- `vendor/bin/sail composer require --dev smalot/pdfparser:^2.13`
- No config publish. Options are set per render in the exporter.

#### 2. Shared Polish quantity formatter

**File**: `app/Services/ShoppingList/QuantityFormatter.php` (new; `declare(strict_types=1)`), `app/Services/ShoppingList/ShoppingListBuilder.php`

**Intent**: One implementation of "dot-decimal quantity + unit code → Polish text". The shopping list and the PDF's ingredient lines then print numbers identically.

**Contract**:
- `format(string $quantity, string $unitCode): string`.
  - The input is a dot-decimal string such as "1.23", "100.00" or "0.25".
  - The formatter uses `NumberFormatter('pl_PL', DECIMAL)` with 0–2 fraction digits and grouping off, and keeps the existing comma fallback.
  - It appends `Unit::LABELS[$unitCode] ?? $unitCode`. Examples: "100.00" g → "100 g"; "0.25" l → "0,25 l"; "2" szt → "2 szt.".
- `ShoppingListBuilder::makeItem` calls it for `display`. Rounding and the `quantity` string stay in the builder. The list output does not change, and the existing `ShoppingListBuilderTest` cases must stay green unchanged.

#### 3. Document value objects

**File**: `app/Services/MealPlanPdf/MealPlanDocument.php`, `PdfDay.php`, `PdfMeal.php` (new; `declare(strict_types=1)`, readonly)

**Intent**: The typed view model the template renders, so a test can assert the content without parsing HTML.

**Contract**:
- `MealPlanDocument(string $title, list<PdfDay> $days, ShoppingList $shoppingList)`. `$title` is "Jadłospis 16.11.2026 – 22.11.2026" (`d.m.Y`, en dash).
- `PdfDay(string $label, list<PdfMeal> $meals)`. `$label` is "Poniedziałek 16.11": `Str::ucfirst` of Carbon `locale('pl')->isoFormat('dddd')`, a space, then `d.m`.
- `PdfMeal(string $category, string $recipeName, bool $recipeDeleted, list<string> $ingredients, string $instructions)`.
  - `$category` is the `MealCategory` label.
  - Each `$ingredients` entry is "<name> — <formatted quantity>", in recipe-ingredient id order, using the recipe's own unit with no conversion.

#### 4. Exporter service

**File**: `app/Services/MealPlanPdf/MealPlanPdfExporter.php` (new; `declare(strict_types=1)`)

**Intent**: The single place that knows what goes into the PDF and how it is rendered.

**Contract**:
- The constructor injects `ShoppingListBuilder` and `QuantityFormatter`.
- `document(MealPlan $mealPlan): MealPlanDocument`:
  - Loads the same relations as `forMealPlan()`, with `loadMissing`, so both share one eager load.
  - Groups meals by `meal_date` in ascending order. Within a day it orders them by `MealCategory::displayIndex`.
  - Skips meals with a `null` recipe. Days with no meals are omitted.
  - `shoppingList` is `$builder->forMealPlan($mealPlan)`.
- `render(MealPlan $mealPlan): string` returns the PDF bytes.
  - The call is `Pdf::loadView('pdf.meal-plan', ['document' => …])->setPaper('a4', 'landscape')`.
  - Options: `defaultFont` `DejaVu Sans`, `isRemoteEnabled` false, `isPhpEnabled` false. Then `->output()`.
- `filename(MealPlan $mealPlan): string` returns `jadlospis-<Y-m-d start>-<Y-m-d end>.pdf`.

#### 5. Blade template

**File**: `resources/views/pdf/meal-plan.blade.php` (new; the first non-Inertia view)

**Intent**: The FR-7 layout as dompdf-safe HTML with inline CSS.

**Contract**:
- `<html lang="pl">`, `<meta charset="utf-8">`, and `body { font-family: 'DejaVu Sans', sans-serif; font-size: ~9pt }`.
- `<h1>` holds `$document->title`.
- One `<table>` whose `<thead>` has Dzień / Posiłek / Nazwa / Składniki / Przygotowanie and repeats on every page.
  - Each day gets its own `<tbody style="page-break-inside: avoid">`.
  - The day cell has `rowspan` equal to that day's meal count.
  - A deleted recipe's name is followed by " (usunięty)", matching `MealPlanGrid.vue:31`.
  - Ingredients are one line each, or "—" when there are none.
  - Instructions go through `nl2br(e(…))`. Everything is escaped.
  - Column widths are roughly 11/9/16/24/40 %.
- The shopping list is a `<section style="page-break-before: always">` with `<h2>Lista zakupów</h2>`. It is a two-column table: name, then `display`.
  - When there are no items: "Przepisy w tym jadłospisie nie mają składników."
  - When `recipesWithoutIngredients` is not empty: "Bez składników (nie ma ich na liście): A, B." This copy matches `resources/js/Components/MealPlans/ShoppingList.vue`.

#### 6. Tests

**File**: `tests/Unit/Services/MealPlanPdfExporterTest.php` (new), `tests/Pest.php`

**Intent**: Prove the content rules, the FR-7 order and the Polish glyphs in the real rendered bytes.

**Contract**:
- Add a helper to `tests/Pest.php`: `pdfText(string $bytes): string`, built on `Smalot\PdfParser\Parser`. Reuse `attachIngredient()` and `mealPlanWithRecipes()` where they fit. When a case needs a fixed date, create the plan with factory attributes `start_date` 2026-11-16 (a Monday) and `end_date` 2026-11-22, plus `Meal` rows.
- `document()` cases:
  - Days in ascending date order. Within a day, Śniadanie → Obiad → Kolacja, whatever the insertion order.
  - Labels "Poniedziałek 16.11" and "Wtorek 17.11". The title is "Jadłospis 16.11.2026 – 22.11.2026".
  - Ingredients as written and in recipe order: "Mąka — 100 g", "Mleko — 0,25 l", "Jajka — 2 szt.". Nothing is converted: 1500 g stays "1500 g".
  - A recipe without ingredients gives an empty list. A soft-deleted recipe has `recipeDeleted` true.
  - `shoppingList` equals `ShoppingListBuilder::forMealPlan()` for the same plan.
- `render()` cases:
  - The bytes start with `%PDF-`.
  - The text contains "Poniedziałek 16.11", "Śniadanie", "Kolacja", the recipe names "Łosoś pieczony" and "Żurek", "Mąka — 600 g" and an instructions fragment.
  - `strpos("Łosoś pieczony") < strpos("Lista zakupów") < strpos("1,2 kg")`.
  - With a recipe that has no ingredients, the text contains "Bez składników".
  - With a soft-deleted recipe, the text contains "(usunięty)".
- `filename()` returns "jadlospis-2026-11-16-2026-11-22.pdf".
- `QuantityFormatter` is covered indirectly by the existing shopping-list cases plus the ingredient-line cases above. It gets no file of its own.

### Success Criteria:

#### Automated Verification:

- Exporter and shopping-list unit tests pass: `vendor/bin/sail pest tests/Unit/Services`
- Larastan passes without baseline changes: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`

**Implementation Note**: After completing this phase and all automated verification passes, pause for manual confirmation before proceeding to the next phase.

---

## Phase 2: Download endpoint and cleanup

### Overview

The route serves the rendered PDF, owner-only, inline and never cached. The dead PDF columns and their code are removed, and the resource tells the UI when a download is available.

### Changes Required:

#### 1. Policy

**File**: `app/Policies/MealPlanPolicy.php`, `tests/Unit/MealPlanPolicyTest.php`

**Intent**: Close the security gap with the ability `api-plan.md:82` already names. The repo rule requires a policy test.

**Contract**:
- `downloadPdf(User $user, MealPlan $mealPlan): bool` returns true for the owner only. Status is not part of authorization.
- Add `test_download_pdf_allows_owner` and `test_download_pdf_denies_non_owner` in the existing PHPUnit-style class.

#### 2. Controller action

**File**: `app/Http/Controllers/MealPlanController.php`

**Intent**: Render on request, and return a response that opens in the browser and never goes stale.

**Contract**:
- `downloadPdf(MealPlan $mealPlan, MealPlanPdfExporter $exporter): Response`:
  - First `$this->authorize('downloadPdf', $mealPlan)`.
  - If the status is not `done`: `abort(404, 'PDF jest dostępny tylko dla gotowego jadłospisu.')`.
  - Otherwise return `response($exporter->render($mealPlan), 200, [...])` with these headers:
    - `Content-Type: application/pdf`
    - `Content-Disposition: inline; filename="<exporter filename>"`
    - `Cache-Control: no-store, private`
- `destroy()`: drop the file-deletion block and its comment, and drop the `Storage` import if it is no longer used.
- The route stays the same (`meal-plans.pdf`).

#### 3. Drop the PDF columns

**File**: `database/migrations/<timestamp>_drop_pdf_columns_from_meal_plans_table.php` (new, via `vendor/bin/sail artisan make:migration drop_pdf_columns_from_meal_plans_table --table=meal_plans --no-interaction`; `declare(strict_types=1)`), `app/Models/MealPlan.php`, `database/factories/MealPlanFactory.php`

**Intent**: Remove fields that imply a stored file exists.

**Contract**:
- `up()` drops `pdf_path` and `pdf_size`. `down()` re-adds `pdf_path` (string, nullable) and `pdf_size` (unsigned big integer, nullable).
- The model removes both from `$fillable` and removes the `pdf_size` cast.
- The factory removes both keys from `definition()` and `done()`, and deletes the `withPdf()` state. Nothing else uses it (verify with grep before deleting).

#### 4. Resource

**File**: `app/Http/Resources/MealPlanResource.php`

**Intent**: Expose availability as a URL instead of a storage path.

**Contract**:
- Remove `pdf_path` and `pdf_size`.
- `links` becomes `{ self, pdf }`. `pdf` is `route('meal-plans.pdf', $this->id)` when `status === 'done'`, else `null`.
- This applies to the index collection, the Inertia show prop and the JSON responses alike.

#### 5. Tests

**File**: `tests/Feature/MealPlanControllerTest.php`

**Intent**: The HTTP contract and its failure modes (`test-plan.md` PDF-001..003, rewritten for on-request rendering).

**Contract**:
- Owner, `done` plan with a recipe "Łosoś pieczony" whose ingredient is "Mąka" 600 g:
  - Responds 200 with `Content-Type` `application/pdf`.
  - `Content-Disposition` is `inline; filename="jadlospis-<start>-<end>.pdf"`.
  - `Cache-Control` contains `no-store`.
  - The body starts with `%PDF-`, and `pdfText` contains "Łosoś pieczony" before "Lista zakupów".
- Another user's plan returns 403, for both a `done` and a `pending` plan (authorization comes before the status check).
- `pending`, `processing` and `error` plans return 404 (dataset).
- A guest is redirected to login.
- Regeneration is reflected: after the plan's meals are replaced (delete the meals, attach a different recipe), the next download contains the new recipe's name and not the old one.
- `links.pdf`:
  - On the Inertia show page, `mealPlan.links.pdf` equals the route URL for `done` and is `null` for `pending`.
  - `mealPlan.pdf_path` is missing.
- Update `'user can delete own meal plan'` to drop `Storage::fake`, `withPdf()` and the file assertions. It still asserts 204 and the missing row.

#### 6. Spec docs

**File**: `.ai/core/db-plan.md`, `.ai/core/api-plan.md`, `.ai/core/test-plan.md`

**Intent**: Keep the specs true to the chosen design, so later planning does not reintroduce stored PDFs.

**Contract**:
- `db-plan.md`: remove the `pdf_path`/`pdf_size` rows (`:96-97`) and rewrite note 5 (`:182`): the PDF is rendered on request and nothing is stored.
- `api-plan.md`:
  - `:108`: `MealPlanPdfExporter`, rendered on request (dompdf).
  - `:110`: remove the `GeneratePdfJob` row.
  - `:121`: mark the PDF limit as parked.
  - `:126`: AI only.
  - `:129`: drop the PDF prune.
- `test-plan.md` `:13` and `:131`: describe on-request rendering, and PDF-001..003 as 403/404/inline headers.

### Success Criteria:

#### Automated Verification:

- Migration applies and rolls back cleanly: `vendor/bin/sail artisan migrate && vendor/bin/sail artisan migrate:rollback --step=1 && vendor/bin/sail artisan migrate`
- Policy and meal-plan feature tests pass: `vendor/bin/sail pest tests/Unit/MealPlanPolicyTest.php tests/Feature/MealPlanControllerTest.php`
- No code references to the dropped columns remain: `grep -rnE "pdf_path|pdf_size|withPdf" app database/factories` returns nothing (tests may still name `pdf_path` in a `missing(...)` assertion)
- Full suite passes: `vendor/bin/sail composer test`
- Larastan passes without baseline changes: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`
- Client and SSR bundles build: `vendor/bin/sail npm run build`

#### Manual Verification:

- Opening `/meal-plans/{id}/pdf` of a done plan in the browser shows the PDF inline, and saving it suggests `jadlospis-<start>-<end>.pdf`
- The PDF shows Polish diacritics correctly (ą ć ę ł ń ó ś ź ż, Ś, Ł, Ż), A4 landscape, the table first and "Lista zakupów" on a new page
- Day cells stay intact where the table breaks across pages (no split or overlapping day cell)
- Another user's plan id returns 403, and a pending plan returns 404
- A full 21-meal plan renders in about 3 s or less in Sail

**Implementation Note**: After completing this phase and all automated verification passes, pause for manual confirmation before proceeding to the next phase.

---

## Phase 3: UI and E2E

### Overview

Both entry points read `links.pdf`. The browser test proves the PDF is available after generation and after regeneration.

### Changes Required:

#### 1. Header button

**File**: `resources/js/Pages/MealPlans/Show.vue`

**Intent**: Make US-008.3 "z opcją pobrania PDF" visible on the result screen.

**Contract**:
- Before "Regeneruj", add `<Button v-if="mealPlan.links.pdf" variant="outline" as-child>` around `<a :href="mealPlan.links.pdf" target="_blank" rel="noopener">`, with the lucide `Download` icon and the text "Pobierz PDF".
- The local `MealPlan` type removes `pdf_path` and gets `links: { self: string; pdf: string | null }`.
- The poll is unchanged: `links.pdf` arrives with `mealPlan`.

#### 2. Dropdown item

**File**: `resources/js/Components/MealPlans/ActionDropdown.vue`

**Intent**: Enable the menu item from the same field.

**Contract**:
- `canDownloadPdf` becomes `!!props.item.links.pdf`.
- The handler calls `window.open(props.item.links.pdf, '_blank', 'noopener')`.
- The local type removes `pdf_path` and gets `links.pdf: string | null`.

#### 3. Local types

**File**: `resources/js/Components/MealPlans/MealPlanRow.vue`, `resources/js/Components/MealPlans/MealPlanTable.vue`, `resources/js/Pages/MealPlans/Index.vue`, `resources/js/types/meal-plan.ts`

**Intent**: Keep the duplicated `links` shapes in line with the resource. These are type-only edits.

**Contract**: `links: { self: string; pdf: string | null }`. Remove `pdf_path`/`pdf_size` wherever they appear.

#### 4. Playwright spec

**File**: `tests/e2e/meal-plan.spec.ts`

**Intent**: The browser-level check of US-010 and of the poll wiring for the button.

**Contract**:
- After the first 21-cell grid:
  - `getByRole('link', { name: 'Pobierz PDF' })` is visible with `target="_blank"`.
  - `page.request.get(href)` returns 200, `content-type` `application/pdf`, a `content-disposition` that starts with `inline` and contains `jadlospis-`, and a body that starts with `%PDF-`.
- After "Regeneruj":
  - The link is hidden while "Generujemy jadłospis…" is shown.
  - It is visible again once the grid has 21 cells, and the same request checks pass.
- The PDF's content is covered by Pest. The E2E does not parse it.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- E2E suite passes in both browsers: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- Full Pest suite, Larastan and Pint pass: `vendor/bin/sail composer test && vendor/bin/sail composer phpstan && vendor/bin/sail composer pint-test`

#### Manual Verification:

- On a done plan the header shows "Pobierz PDF" and it opens the PDF in a new tab; during regeneration the button disappears and returns without a refresh
- On the plan list, "Pobierz PDF" in the "⋯" menu is enabled only for done plans and opens the same PDF
- The header with the new button still fits on a narrow (mobile) screen and in dark mode
- The CI `e2e-tests` job passes on the PR into `main`

**Implementation Note**: After completing this phase and all automated verification passes, pause for manual confirmation.

---

## Testing Strategy

### Unit Tests:

- `MealPlanPdfExporter::document()`:
  - day and category order
  - Polish day labels and title
  - ingredient lines as written, in recipe order, with no conversion
  - recipes without ingredients
  - soft-deleted recipes
  - the shopping list passed through
- `MealPlanPdfExporter::render()`:
  - `%PDF-` header
  - Polish glyphs in the extracted text
  - plan content before "Lista zakupów", which comes before the list rows
  - the "Bez składników" note and the "(usunięty)" marker
- `filename()`.
- `ShoppingListBuilderTest` stays unchanged and green as the regression check for the formatter extraction.
- `MealPlanPolicyTest`: `downloadPdf` for the owner and for a non-owner.

### Integration Tests:

- `MealPlanControllerTest`:
  - inline PDF, headers and content for the owner
  - 403 for a non-owner (also on a pending plan)
  - 404 for pending, processing and error plans
  - a guest is redirected
  - the PDF reflects the plan's current meals after a regeneration
  - `links.pdf` set for done and `null` otherwise; `pdf_path` gone
  - the delete test simplified
- Playwright `meal-plan.spec.ts`: generate → button → PDF response → regenerate → the button hides and returns → PDF response again.

### Manual Testing Steps:

1. In Sail with the `queue` service up, generate a plan from recipes with Polish names and multi-line instructions, click "Pobierz PDF", and check the layout, the diacritics and the page breaks.
2. Edit one of the plan's recipes (its ingredients or instructions), download again, and confirm the PDF shows the edit.
3. Click "Regeneruj", wait for done, download again, and confirm the PDF matches the new grid, not the previous one (this also checks there is no caching).
4. Log in as another user and open the first user's `/meal-plans/{id}/pdf`: 403.

## Performance Considerations

Each download renders the PDF in the web process: 4 eager-loaded queries plus a dompdf render of about 21 rows and the list. That's expected to take about 1–3 s and well under 128 MB, which suits the Mikrus VPS better than headless Chrome. Nothing is cached, so repeated downloads cost the same each time. This is acceptable for a once-a-week action, and the roadmap has parked a rate limit until needed. If renders turn out slow on the server, the first thing to measure is instruction length.

## Migration Notes

One migration drops `meal_plans.pdf_path` and `pdf_size`. No code ever wrote them, so no data is lost (local `storage/app/private` has no PDFs). `down()` restores both as nullable columns. The production image (F-02) must keep ext-dom and ext-mbstring enabled for dompdf. dompdf bundles its fonts, so no system fonts are needed.

## References

- Roadmap item: `context/foundation/roadmap.md` → S-06 `meal-plan-pdf-export`
- PRD: `.ai/core/prd.md` FR-7, US-010 (and US-008.3, US-009.3)
- Specs to update: `.ai/core/api-plan.md:82,108-110,121-129`, `.ai/core/db-plan.md:96-97,182`, `.ai/core/test-plan.md:13,131`
- Prior slices: `context/archive/2026-11-14-shopping-list/plan.md` (builder, display strings, card copy), `context/archive/2026-11-13-real-weekly-meal-plan/plan.md` (grid, polling, E2E)
- Code: `app/Http/Controllers/MealPlanController.php:192-235`, `app/Services/ShoppingList/ShoppingListBuilder.php`, `app/Http/Resources/MealPlanResource.php`, `resources/js/Components/MealPlans/ActionDropdown.vue:117-134`, `resources/js/Pages/MealPlans/Show.vue:15-22,155`

## Addendum (2026-11-14, after manual verification)

- **Rowspan fallback applied.** On a real 21-meal plan, one day with three-paragraph instructions did not fit on a landscape page. dompdf split the day's `<tbody>` despite `page-break-inside: avoid`, and the continued row lost its rowspan cell and shifted one column left (check 2.10 failed). As "Critical Implementation Details" allows, the template now has no `rowspan`. The day label is in the day's first row, the next rows have an empty day cell without a top border, a 2px top border separates days, and `page-break-inside: avoid` moved from `tbody` to `tr`, so a single meal is never split.
- **Font subsetting on.** `render()` also sets `isFontSubsettingEnabled` true. Before, dompdf embedded the full DejaVu fonts: the 21-meal plan was 896 KB, rendered in ~0.6 s. With subsetting it is 40 KB in ~0.6 s.

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: PDF document

#### Automated

- [x] 1.1 Exporter and shopping-list unit tests pass — ee29ed3
- [x] 1.2 Larastan passes without baseline changes — ee29ed3
- [x] 1.3 Pint passes — ee29ed3

### Phase 2: Download endpoint and cleanup

#### Automated

- [x] 2.1 Migration applies and rolls back cleanly — e215753
- [x] 2.2 Policy and meal-plan feature tests pass — e215753
- [x] 2.3 No code references to the dropped columns remain — e215753
- [x] 2.4 Full suite passes — e215753
- [x] 2.5 Larastan passes without baseline changes — e215753
- [x] 2.6 Pint passes — e215753
- [x] 2.7 Client and SSR bundles build — e215753

#### Manual

- [x] 2.8 Done plan's PDF opens inline and saves as jadlospis-<start>-<end>.pdf — verified 2026-11-14
- [x] 2.9 Polish diacritics, A4 landscape, table first and list on a new page — verified 2026-11-14
- [x] 2.10 Day cells stay intact across page breaks — verified 2026-11-14 after the rowspan fallback (see Addendum)
- [x] 2.11 Another user's plan returns 403 and a pending plan 404 — verified 2026-11-14
- [x] 2.12 A 21-meal plan renders in about 3 s or less — verified 2026-11-14

### Phase 3: UI and E2E

#### Automated

- [x] 3.1 Client and SSR bundles build — b18a927
- [x] 3.2 E2E suite passes in both browsers — b18a927
- [x] 3.3 Full Pest suite, Larastan and Pint pass — b18a927

#### Manual

- [ ] 3.4 Header button opens the PDF in a new tab and hides during regeneration
- [ ] 3.5 List menu item enabled only for done plans
- [ ] 3.6 Header fits on a narrow screen and in dark mode
- [ ] 3.7 CI e2e-tests job passes on the PR into main
