# PDF Recipe Pages Implementation Plan

## Overview

Roadmap S-21 (MS-06). The meal-plan PDF gets an opt-in "with recipes" variant, chosen when the user clicks "Pobierz PDF". The plan table drops its 40%-wide "Przygotowanie" column. When recipes are included, every distinct recipe used in the week gets its own page after the shopping list, printed once however often it is used. A recipe page shows the recipe name, its category, the family-scaled ingredient amounts and the method. Uses with identical amounts share one ingredient list labelled with their days; uses with different amounts get separate labelled lists. All three download entry points (plan page, dashboard, plans list) offer both variants through one shared menu.

## Current State Analysis

- **Rendering.** The PDF is rendered on demand with no stored file. `MealPlanController::downloadPdf` (`app/Http/Controllers/MealPlanController.php:193`) authorizes `downloadPdf`, returns 404 unless the status is `done`, and streams `MealPlanPdfExporter::render()` inline with `Cache-Control: no-store`. The route is `GET /meal-plans/{meal_plan}/pdf`, named `meal-plans.pdf` (`routes/web.php:29`).
- **Exporter.** `MealPlanPdfExporter::document()` (`app/Services/MealPlanPdf/MealPlanPdfExporter.php:26`) groups meals by date (`sortKeys`), orders each day by `MealCategory::displayIndex`, and skips meals whose recipe is null. It maps each meal to `PdfMeal`, whose `instructions` field feeds the table's "Przygotowanie" column (`PdfMeal.php:18`, `MealPlanPdfExporter.php:102`). Ingredient lines come from the scaled per-meal snapshot (`meal_ingredients`, in `id` order, `Meal.php:39`). Zero quantities are skipped, and lines are formatted as `Nazwa — 100 g` by `QuantityFormatter`.
- **Template.** `resources/views/pdf/meal-plan.blade.php` is A4 landscape with DejaVu Sans. It renders a 5-column table (`Dzień 11% / Posiłek 9% / Nazwa 16% / Składniki 24% / Przygotowanie 40%`, lines 60–64), then the shopping list in a `page-break-before: always` section. The global rule `tr { page-break-inside: avoid; }` (line 31) applies to every table row. Category colours are scoped to `td.meal-*` (lines 36–38).
- **Palette guard.** `tests/Unit/Services/MealPlanPdfPaletteTest.php` allows only the 12 mirrored token hexes in the template and fails on an unused mirror entry (design-system §6).
- **Amounts across repeated uses.** The scale factor depends on the meal category and the recipe's kcal (`MealIngredientScaler.php:70`). Repeated uses of a recipe in the same category therefore usually have identical amounts, but the AI refiner can adjust each meal independently. Today a week cannot repeat a recipe: `MealPlanGenerator` draws 7 distinct recipes per category, and a recipe has a single category. Repeats arrive with S-17 (swap) and S-19/S-20 (cook once, eat twice). In S-19/S-20 the first use is scaled for both meals and the second use has no ingredients of its own.
- **Links.** `links.pdf` is set only for `done` plans in `MealPlanResource.php:109` and `MealPlanCollection.php:103`. The dashboard serializes `latestMealPlan` through `MealPlanResource` (`DashboardController.php:42`).
- **UI entry points.** There is a "Pobierz PDF" outline button in the plan page header (`resources/js/Pages/MealPlans/Show.vue:9`), another in the dashboard card (`resources/js/Pages/Dashboard.vue:74`), and a menu item in the ⋯ menu (`resources/js/Components/MealPlans/ActionDropdown.vue:21`, `window.open` in a new tab). The ⋯ menu is rendered both on the plans list (`MealPlanRow.vue:35`) and in the plan page header (`Show.vue:19`), so the plan page already offers the PDF twice. The link type `links: { self, pdf }` is declared locally in `types/meal-plan.ts:18`, `Show.vue:157`, `Index.vue:63`, `MealPlanTable.vue:95`, `MealPlanRow.vue:60` and `ActionDropdown.vue:105`.
- **Tests.** There are 12 unit tests in `tests/Unit/Services/MealPlanPdfExporterTest.php` and 6 PDF feature tests in `tests/Feature/MealPlanControllerTest.php:537-620`. PDF text is extracted with `pdfText()` (`tests/Pest.php:88`, smalot/pdfparser). The E2E spec `tests/e2e/meal-plan.spec.ts:50` finds `getByRole('link', { name: 'Pobierz PDF' })`. Visual baselines for the plan page and the dashboard show the button.

## Desired End State

On a finished plan, "Pobierz PDF" opens a menu with "Z przepisami" and "Bez przepisów". The plan page, the dashboard card and the ⋯ menu on the plans list (two items there: "PDF z przepisami", "PDF bez przepisów") all offer both. Each opens the PDF inline in a new tab.

- **Both variants.** The PDF shows the title, the scaling notices, a 4-column table (Dzień, Posiłek, Nazwa, Składniki — no "Przygotowanie") and the shopping list on a new page.
- **"Z przepisami"** also appends one page per distinct recipe used in the plan. Each recipe starts on a fresh page and continues onto the next if long. Pages are ordered by the recipe's first use in the week: by day, then Śniadanie → Obiad → Kolacja. A recipe page contains:
  - the recipe name, with "(usunięty)" when the recipe is soft-deleted
  - the recipe's category label in its meal colour pair
  - a "Składniki" block with one list per distinct set of scaled amounts, each headed by the days it applies to (e.g. `Poniedziałek 16.11 · Środa 18.11`)
  - a "Przygotowanie" block with the method, line breaks kept
- **Filename.** The variant with recipes downloads as `jadlospis-<start>-<end>-z-przepisami.pdf`. The variant without keeps today's name.

Verify with `vendor/bin/sail pest tests/Unit/Services/MealPlanPdfExporterTest.php tests/Unit/Services/MealPlanPdfPaletteTest.php tests/Feature/MealPlanControllerTest.php tests/Unit/MealPlanRequestValidationTest.php`, the E2E spec `tests/e2e/meal-plan.spec.ts`, the visual gate, and by opening both PDF variants of a seeded plan.

### Key Discoveries:

- `PdfMeal::$instructions` is read only by the table column being removed (`meal-plan.blade.php:85`), so the field can go.
- Ingredient lines are already built per meal in `pdfMeal()` (`MealPlanPdfExporter.php:85-94`). The recipe pages can reuse exactly these lines, which keeps the table, the recipe pages and the scaled snapshot consistent by construction.
- The global `tr { page-break-inside: avoid; }` would be risky for a recipe body longer than one page, so recipe pages use stacked blocks, not a table row.
- `recipes.instructions` is required on create and update (`StoreRecipeRequest.php:34`), so every recipe page has a method.
- Form Requests in this codebase authorize through `Gate::allows(...)` on the route model (`RegenerateMealPlanRequest.php:29`). `authorize()` runs before `rules()`, so a non-owner still gets a 403 before any 404 or validation error.
- `DropdownMenuItem as-child` wrapping a link is an existing pattern (`resources/js/Components/Common/UserMenu.vue:54`).
- design-system §4.7 fixes the PDF icon as `FileDown` and the recipe icon as `ChefHat`. The ⋯ menu's PDF item uses `Download` today (`ActionDropdown.vue:79`), which this change corrects.

## What We're NOT Doing

- No change to the data model, the scaling pipeline or the shopping list contents.
- No S-17…S-20 behaviour: no empty cells, no repeat/carryover markers. Their slices add their own cell states to this template. Grouping by amounts already handles a use whose amounts differ or are empty (`—`).
- No page numbers, table of contents or cross-references from the table to recipe pages (dompdf has no reliable page references).
- No portrait orientation or mixed orientations: recipe pages stay A4 landscape.
- No remembered choice (localStorage or user setting): both variants are always offered.
- No change to `.ai/core/prd.md` (open roadmap question 3).
- No new shadcn primitive (no `switch`), and no change to `Components/ui/`.

## Implementation Approach

The work goes bottom-up, so each phase is independently testable:

1. **Phase 1** makes the document model and template support an optional recipe section, driven by a `bool $withRecipes` that defaults to `false` (today's callers keep working, minus the removed column).
2. **Phase 2** exposes the flag over HTTP through a validated `recipes` query parameter and publishes a second link.
3. **Phase 3** replaces the three single-action buttons with the shared two-option menu, and updates E2E and the visual baselines on purpose.

Recipe aggregation happens in one pass over the same day-ordered, category-ordered meals that build the table. The first sighting of a recipe id fixes its page order. Within a recipe, uses are grouped by their exact list of formatted ingredient lines.

## Critical Implementation Details

- **Ordering.** Recipe pages must follow the same day-then-category iteration that builds the table, so "first use" means the earliest table row. Do not sort recipes separately by id, name or insertion order.
- **Grouping key.** Group uses by the formatted ingredient lines (after the zero-quantity skip), not by raw decimals. Two uses that print identically are one group. A use with no printable lines forms its own group, shown as `—`.

## Phase 1: PDF document and template

### Overview

Teach `MealPlanPdfExporter` to build an optional list of recipe pages and render them after the shopping list. Remove the preparation column from the table.

### Changes Required:

#### 1. Recipe page DTOs

**File**: `app/Services/MealPlanPdf/PdfRecipe.php`, `app/Services/MealPlanPdf/PdfIngredientGroup.php` (new)

**Intent**: Typed input for one recipe page and for one ingredient list within it, following the existing `final readonly` DTO style (`PdfDay`, `PdfMeal`).

**Contract**:
- `PdfIngredientGroup(list<string> $dayLabels, list<string> $lines)`. Day labels use the table's day format (`Poniedziałek 16.11`), are deduplicated, and keep first-use order. `$lines` is empty when that use has no printable ingredients.
- `PdfRecipe(string $name, string $category, string $categoryValue, bool $deleted, list<PdfIngredientGroup> $ingredientGroups, string $instructions)`. The category comes from the recipe's own `category`, not the meal slot (a later S-20 breakfast can carry a dinner recipe).

#### 2. Document model

**File**: `app/Services/MealPlanPdf/MealPlanDocument.php`, `app/Services/MealPlanPdf/PdfMeal.php`

**Intent**: The document carries the recipe pages. The table row no longer carries the method.

**Contract**:
- `MealPlanDocument` gains `public array $recipes = []` (`list<PdfRecipe>`) as its last constructor parameter.
- `PdfMeal` loses `$instructions`.

#### 3. Exporter

**File**: `app/Services/MealPlanPdf/MealPlanPdfExporter.php`

**Intent**: Build the recipe pages from the same meal iteration as the table when asked, and pass the flag through `render()`.

**Contract**:
- `document(MealPlan $mealPlan, bool $withRecipes = false): MealPlanDocument`
- `render(MealPlan $mealPlan, bool $withRecipes = false): string`

With `$withRecipes = false`, `recipes` is `[]`. With `true`:
- one `PdfRecipe` per distinct `recipe_id` among meals with a non-null recipe, in first-use order (see Critical Implementation Details)
- the ingredient groups are built from the same lines as the table cell
- `instructions` is the raw recipe text

`filename()` is unchanged in this phase.

#### 4. Template

**File**: `resources/views/pdf/meal-plan.blade.php`

**Intent**: Drop the "Przygotowanie" column, then append one section per recipe after the shopping list, each starting on a new page.

**Contract**:
- **Table.** 4 columns, roughly `Dzień 12% / Posiłek 10% / Nazwa 30% / Składniki 48%`.
- **Category colours.** Leave the CSS unchanged. The recipe page's category label is a one-cell table, `<table><tr><td class="category meal-{{ $recipe->categoryValue }}">…</td></tr></table>`, which reuses the existing `td.meal-*` colour pairs and the 8pt bold `td.category` role. Add no new selectors and no hexes: the palette guard requires a literal `td.meal-<value>` rule per category (`MealPlanPdfPaletteTest.php:141-145`) and must pass unchanged.
- **Recipe section.** Each recipe is a `<section>` with `page-break-before: always` containing:
  - `h2` with the name and a `.meta` "(usunięty)" when deleted
  - the category label as the one-cell `td.category.meal-*` table above
  - a 9pt-bold "Składniki" label; for each group, its day labels joined with ` · ` in `.meta`, then the lines separated by `<br>` (`—` when the group is empty), or "Bez składników" when the recipe has no printable lines in any use
  - a 9pt-bold "Przygotowanie" label and `nl2br(e(...))` of the method
- Recipe content is block elements, not a table row (see Key Discoveries).

#### 5. Design-system spec

**File**: `context/foundation/design-system.md` (§6 PDF)

**Intent**: Record the recipe pages in the PDF contract so future slices (S-18…S-20) extend the same layout.

**Contract**:
- One bullet in §6: recipe pages come after the shopping list, one per page, and the category label uses the `--meal-*` pair.
- Add the label role ("Składniki"/"Przygotowanie", 9pt bold) to the roles table if it isn't covered by "Etykieta".

#### 6. Unit tests

**File**: `tests/Unit/Services/MealPlanPdfExporterTest.php`

**Intent**: Cover the new document shape and the rendered pages. Update the tests that relied on the removed column.

**Contract**: see Testing Strategy → Unit Tests. The existing "renders a PDF … Polish glyphs" test stops expecting the method text (`Piec w piekarniku`) in the variant without recipes.

### Success Criteria:

#### Automated Verification:

- Exporter and palette tests pass: `vendor/bin/sail pest tests/Unit/Services/MealPlanPdfExporterTest.php tests/Unit/Services/MealPlanPdfPaletteTest.php`
- Full Pest suite passes: `vendor/bin/sail composer test`
- Larastan passes without baseline additions: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`

#### Manual Verification:

- A PDF rendered with recipes for a seeded plan (e.g. via a temporary route or tinker writing to `storage/`) shows the 4-column table, the shopping list, then one page per recipe in first-use order, with readable category colours and line breaks in the method

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Download endpoint and links

### Overview

Accept the choice over HTTP, name the file after it, and publish a second link for done plans.

### Changes Required:

#### 1. Form Request

**File**: `app/Http/Requests/DownloadMealPlanPdfRequest.php` (new, `declare(strict_types=1)`)

**Intent**: Validate the optional flag and move the existing `downloadPdf` authorization into the request, following `RegenerateMealPlanRequest`.

**Contract**:
- `authorize()` returns `Gate::allows('downloadPdf', $this->route('meal_plan'))`.
- `rules()` returns `['recipes' => ['sometimes', 'boolean']]`.
- An invalid value (`?recipes=abc`, and also `?recipes=true`, since Laravel's `boolean` rule accepts only `true, false, 0, 1, '0', '1'`) is a validation error: a redirect with errors for a browser request, 422 for JSON. Links therefore always use `recipes=1`.

#### 2. Controller

**File**: `app/Http/Controllers/MealPlanController.php` (`downloadPdf`)

**Intent**: Take the new Form Request, drop the inline `$this->authorize()` (now in the request), keep the 404 for non-done plans, and pass `$request->boolean('recipes')` to `render()` and `filename()`.

**Contract**:
- Signature: `downloadPdf(DownloadMealPlanPdfRequest $request, MealPlan $mealPlan, MealPlanPdfExporter $exporter): Response`.
- Headers are unchanged except the filename.

#### 3. Filename

**File**: `app/Services/MealPlanPdf/MealPlanPdfExporter.php`

**Intent**: Let the user keep both variants side by side.

**Contract**: `filename(MealPlan $mealPlan, bool $withRecipes = false): string` returns `jadlospis-2026-11-16-2026-11-22.pdf` or `jadlospis-2026-11-16-2026-11-22-z-przepisami.pdf`.

#### 4. Links

**File**: `app/Http/Resources/MealPlanResource.php`, `app/Http/Resources/MealPlanCollection.php`

**Intent**: Publish the variant with recipes next to the existing link, with the same done-only rule.

**Contract**:
- `links.pdf_with_recipes` is `route('meal-plans.pdf', [$id, 'recipes' => 1])` when the status is `done`, otherwise `null`.
- `links.pdf` keeps meaning "without recipes".
- The dashboard inherits the link through `MealPlanResource`.

#### 5. Tests

**File**: `tests/Feature/MealPlanControllerTest.php`, `tests/Unit/MealPlanRequestValidationTest.php`, `tests/Feature/DashboardControllerTest.php` (if it asserts `links.pdf`)

**Intent**: Prove that the flag changes content and filename, that access rules hold for both variants, and that both links are published.

**Contract**: see Testing Strategy → Integration Tests.

### Success Criteria:

#### Automated Verification:

- PDF feature and request tests pass: `vendor/bin/sail pest tests/Feature/MealPlanControllerTest.php tests/Unit/MealPlanRequestValidationTest.php tests/Feature/DashboardControllerTest.php`
- Full Pest suite passes: `vendor/bin/sail composer test`
- Larastan passes without baseline additions: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`
- Route list still shows a single `meal-plans.pdf` route: `vendor/bin/sail artisan route:list --name=meal-plans.pdf`

#### Manual Verification:

- Logged in, `/meal-plans/{id}/pdf?recipes=1` and `/meal-plans/{id}/pdf` open inline in the browser with the expected content, and the save dialog proposes the two different filenames

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Download menu UI

### Overview

Replace the single "Pobierz PDF" action in all three places with the two-option choice. Demo the shared pattern in the kitchen sink and refresh the affected E2E and visual checks on purpose.

### Changes Required:

#### 1. Shared menu component

**File**: `resources/js/Components/MealPlans/PdfDownloadMenu.vue` (new)

**Intent**: One component for the header-style download, reusing `DropdownMenu` and `Button`, with token classes only.

**Contract**:
- Prop `links: { pdf: string; pdf_with_recipes: string }`.
- Trigger: an outline `Button` with `FileDown` + "Pobierz PDF" + `ChevronDown`.
- Items, in this order, each a `DropdownMenuItem as-child` wrapping `<a :href target="_blank" rel="noopener">`:
  - "Z przepisami" (`ChefHat`) → `links.pdf_with_recipes`
  - "Bez przepisów" (`FileDown`) → `links.pdf`

#### 2. Entry points

**File**: `resources/js/Pages/MealPlans/Show.vue`, `resources/js/Pages/Dashboard.vue`, `resources/js/Components/MealPlans/ActionDropdown.vue`

**Intent**: Show and Dashboard render `PdfDownloadMenu` where the button was, under the same `v-if` on the PDF link. The ⋯ menu replaces its single item with "PDF z przepisami" and "PDF bez przepisów": each is disabled when its link is null, and each opens its link in a new tab as today.

**Contract**:
- No new props on the pages. `ActionDropdown`'s `item.links` gains `pdf_with_recipes`.
- The two ⋯ items use `ChefHat` ("PDF z przepisami") and `FileDown` ("PDF bez przepisów") per design-system §4.7, replacing today's `Download`.
- On the plan page, the ⋯ menu in the header (`Show.vue:19`) also shows the two items next to `PdfDownloadMenu`. This duplication is accepted (the page offers the PDF twice today), and the header width is covered by manual check 3.7 and the Show mobile visual shot.

#### 3. Types

**File**: `resources/js/types/meal-plan.ts` and the local `links` interfaces in `Show.vue`, `Index.vue`, `MealPlanTable.vue`, `MealPlanRow.vue`, `ActionDropdown.vue`

**Intent**: Add `pdf_with_recipes: string | null` wherever `pdf: string | null` is declared.

**Contract**: Type-only change.

#### 4. Kitchen sink

**File**: `resources/js/Pages/Dev/DesignSystem.vue` (section "Jadłospis"), `tests/e2e/visual/design-system.spec.ts`

**Intent**: Demo the new shared pattern per CLAUDE.md (closed state in the section, open state as an overlay shot).

**Contract**:
- A labelled "Pobieranie PDF" demo with placeholder links.
- A new overlay test `pdf download menu` that clicks "Pobierz PDF" and screenshots the menu as `overlay-pdf-download-menu.png`, following the `dropdown menu` overlay test.

#### 5. E2E

**File**: `tests/e2e/meal-plan.spec.ts`

**Intent**: Replace the link lookup with the menu:
- open "Pobierz PDF"
- read the `href` of the "Z przepisami" and "Bez przepisów" menu items
- check that both answer `200`, `application/pdf`, `inline`, `%PDF-`, and that the variant with recipes has the `-z-przepisami` filename
- close the menu (Escape) before regenerating

**Contract**:
- After "Regeneruj" the "Pobierz PDF" button is hidden.
- After generation the same checks pass again.

#### 6. Visual baselines

**File**: `tests/e2e/__screenshots__/visual/**`

**Intent**: Update baselines deliberately with `npm run test:visual -- --update-snapshots` and review each changed PNG.

**Contract**: Expected changes:
- `meal-plan-show-desktop.png`, `meal-plan-show-mobile.png`
- `dashboard-desktop.png`, `dashboard-mobile.png`, `dashboard-mobile-drawer.png` (a full-page shot; the dashboard card with the button stays under the open drawer)
- `section-jadlospis.png`
- new `overlay-pdf-download-menu.png`

Any other changed PNG is a regression to fix, not to accept.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token lint passes: `vendor/bin/sail npm run lint:tokens`
- E2E meal-plan spec passes against Sail (after `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`): `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e -- tests/e2e/meal-plan.spec.ts`
- Visual gate passes with only the expected baselines changed: `npm run test:visual`
- Full Pest suite passes: `vendor/bin/sail composer test`

#### Manual Verification:

- On the plan page (the header menu and its ⋯ menu), the dashboard and the plans list ⋯ menu, both options open the right PDF in a new tab, and the menu is keyboard-operable (Enter/Arrow/Escape) with a visible focus
- On a 390px-wide screen the plan page header still fits without horizontal scroll
- Each changed baseline PNG was reviewed in the diff and matches the intended change

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

In `tests/Unit/Services/MealPlanPdfExporterTest.php`:
- `document()` without the flag has `recipes === []`.
- **Order.** Recipes come in first-use order, not insertion or id order: a Tuesday breakfast, a Monday kolacja and a Monday śniadanie, inserted in that order, yield `[Monday śniadanie, Monday kolacja, Tuesday breakfast]`.
- **Grouping.** One recipe used Monday and Wednesday with equal amounts and Friday with a changed snapshot quantity yields two groups: `['Poniedziałek 16.11', 'Środa 18.11']` and `['Piątek 20.11']`, each with its own lines. A use whose snapshot has only zero quantities forms a `[]`-lines group.
- **Recipe fields.** Name, category label/value, the deleted flag and the instructions come from the recipe.
- **Pages.** Rendered with recipes, the PDF page count equals the count without recipes plus one per recipe (smalot `getPages()`), and the method text appears after "Lista zakupów". Rendered without recipes, neither the method text nor the "Przygotowanie" header appears.
- **Deleted and empty recipes.** A soft-deleted recipe's page shows "(usunięty)". A recipe without ingredients shows "Bez składników".

The palette test (`MealPlanPdfPaletteTest`) runs unchanged and must stay green.

In `tests/Unit/MealPlanRequestValidationTest.php`, `DownloadMealPlanPdfRequest` accepts an absent `recipes`, the strings `'1'` and `'0'` (what a query string carries) and PHP `true`/`false`, and rejects `'abc'` and `'true'`.

### Integration Tests:

In `tests/Feature/MealPlanControllerTest.php`:
- **`?recipes=1`** returns a PDF whose text contains the recipe method and whose `Content-Disposition` names `…-z-przepisami.pdf`.
- **No parameter** keeps today's filename and has no method text.
- **`?recipes=abc`** is invalid (`assertInvalid(['recipes'])`).
- **Access rules.** The non-owner 403, the not-done 404 and the guest redirect also hold with `?recipes=1`, via datasets on the existing tests.
- **Links.** `links.pdf_with_recipes` is set only for done plans, on the show page and on the list; the dashboard too, if its test covers links.

E2E (`tests/e2e/meal-plan.spec.ts`) checks that both menu items serve a PDF before and after regeneration.

### Manual Testing Steps:

1. Seed with `E2eMealPlanSeeder` and open the plan starting 2026-06-15 as the visual user. Choose "Pobierz PDF" → "Z przepisami". Check the 4-column table, the shopping list on page 2, then one recipe per page in table order.
2. Choose "Bez przepisów" and check that the PDF ends after the shopping list.
3. Use the ⋯ menu on `/meal-plans` and on the plan page, and the dashboard card, and check that both options open the right variant.
4. Print preview the variant with recipes in grayscale, and check that the category labels and day labels stay readable.

## Performance Considerations

The variant with recipes adds up to 21 pages to a dompdf render that already happens per request. No extra queries are needed (`meals.recipe` and the ingredients are already eager-loaded). Rendering time grows roughly with the page count, which is acceptable for an on-demand, non-cached download.

## Migration Notes

None: no schema or stored-file change, and existing plans get both variants immediately. The link `links.pdf` keeps its meaning, so any bookmarked URL still serves the PDF (now without the preparation column).

## References

- Roadmap item: `context/foundation/roadmap.md` → S-21 (`pdf-recipe-pages`, MS-06)
- PDF design contract: `context/foundation/design-system.md` §6
- Prior PDF work: `context/archive/2026-11-14-meal-plan-pdf-export/`, `context/archive/2026-11-15-pdf-design-alignment/`
- Exporter: `app/Services/MealPlanPdf/MealPlanPdfExporter.php:26`
- Template: `resources/views/pdf/meal-plan.blade.php:57-113`
- Form Request pattern: `app/Http/Requests/RegenerateMealPlanRequest.php:25`
- Menu-item-as-link pattern: `resources/js/Components/Common/UserMenu.vue:54`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: PDF document and template

#### Automated

- [x] 1.1 Exporter and palette tests pass: `vendor/bin/sail pest tests/Unit/Services/MealPlanPdfExporterTest.php tests/Unit/Services/MealPlanPdfPaletteTest.php`
- [x] 1.2 Full Pest suite passes: `vendor/bin/sail composer test`
- [x] 1.3 Larastan passes without baseline additions: `vendor/bin/sail composer phpstan`
- [x] 1.4 Pint passes: `vendor/bin/sail composer pint-test`

#### Manual

- [ ] 1.5 A PDF rendered with recipes for a seeded plan shows the 4-column table, the shopping list, then one page per recipe in first-use order, with readable category colours and line breaks in the method

### Phase 2: Download endpoint and links

#### Automated

- [ ] 2.1 PDF feature and request tests pass: `vendor/bin/sail pest tests/Feature/MealPlanControllerTest.php tests/Unit/MealPlanRequestValidationTest.php tests/Feature/DashboardControllerTest.php`
- [ ] 2.2 Full Pest suite passes: `vendor/bin/sail composer test`
- [ ] 2.3 Larastan passes without baseline additions: `vendor/bin/sail composer phpstan`
- [ ] 2.4 Pint passes: `vendor/bin/sail composer pint-test`
- [ ] 2.5 Route list still shows a single `meal-plans.pdf` route: `vendor/bin/sail artisan route:list --name=meal-plans.pdf`

#### Manual

- [ ] 2.6 Logged in, `/meal-plans/{id}/pdf?recipes=1` and `/meal-plans/{id}/pdf` open inline with the expected content and propose the two different filenames

### Phase 3: Download menu UI

#### Automated

- [ ] 3.1 Client and SSR bundles build: `vendor/bin/sail npm run build`
- [ ] 3.2 Token lint passes: `vendor/bin/sail npm run lint:tokens`
- [ ] 3.3 E2E meal-plan spec passes against Sail after re-seeding `E2eMealPlanSeeder`
- [ ] 3.4 Visual gate passes with only the expected baselines changed: `npm run test:visual`
- [ ] 3.5 Full Pest suite passes: `vendor/bin/sail composer test`

#### Manual

- [ ] 3.6 On the plan page (the header menu and its ⋯ menu), the dashboard and the plans list ⋯ menu, both options open the right PDF in a new tab, and the menu is keyboard-operable with a visible focus
- [ ] 3.7 On a 390px-wide screen the plan page header still fits without horizontal scroll
- [ ] 3.8 Each changed baseline PNG was reviewed in the diff and matches the intended change
