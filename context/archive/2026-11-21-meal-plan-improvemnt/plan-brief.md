# Menu-only PDF ("Sam jadłospis") — Plan Brief

> Full plan: `context/changes/meal-plan-improvemnt/plan.md`

## What & Why

A finished meal plan can already be downloaded as a PDF with ingredients and the shopping list, optionally with recipe pages. Users also want a clean, printable week: a single page with only the menu, split by days and meals, without ingredients or preparation steps. It is an additional option, and the existing two PDFs stay as they are.

## Starting Point

`GET /meal-plans/{id}/pdf` (optional `recipes=1`) renders `pdf/meal-plan.blade.php` through `MealPlanPdfExporter`: a day/meal/name/ingredients table, the shopping list, and optionally recipe pages. The "Z przepisami" / "Bez przepisów" links (`links.pdf`, `links.pdf_with_recipes`) appear in `PdfDownloadMenu` (plan page, plans-list tile, dashboard) and in the plans-list `ActionDropdown`.

## Desired End State

Every PDF menu of a done plan offers a third item, "Sam jadłospis". It opens `jadlospis-<start>-<end>-sam-jadlospis.pdf`: exactly one landscape A4 page with the title and a grid that fills the page. The grid has 7 day columns and 3 meal rows (Śniadanie, Obiad, Kolacja, in meal colours), and each cell holds only the recipe name, or "Bez posiłku".

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Layout | Grid, days as columns × meals as rows, one page, filling it | You asked for a single page drawn nicely across the whole sheet, which suits the fridge. |
| Cell content | Recipe name only (or "Bez posiłku"); no markers, "(usunięty)" or scaling notices | Your choice: the cleanest layout; notices about quantities are meaningless without ingredients. |
| Placement | Everywhere PDFs are: `PdfDownloadMenu` (3 places) + `ActionDropdown` | Consistent; the option is wherever users download PDFs today. |
| Naming | "Sam jadłospis" (CalendarDays icon); existing labels unchanged | Short, clearly distinct, no churn in existing copy or E2E. |
| API shape | `menu=1` flag on the existing route; `menu` + `recipes` together is a 422 | Reuses authorization, the 404-until-done check and headers; no ambiguous variant. |
| One-page guarantee | Truncate names (`Str::limit`, class constant) + fixed `pt` row heights; test 21×255-char names → 1 page | Names go up to 255 chars and dompdf would otherwise spill onto page 2. |
| Template | New `pdf/meal-plan-menu.blade.php` + `MealPlanMenu`/`PdfMenuRow` DTOs; palette test covers both templates | Leaves the full PDF untouched and keeps the colour guard honest. |

## Scope

**In scope:**
- `menu` flag, menu DTO, exporter `menu()`/`renderMenu()`/`menuFilename()`, the grid view, the controller branch
- `links.pdf_menu` in `MealPlanResource` and `MealPlanCollection`
- The UI item in `PdfDownloadMenu` and `ActionDropdown`, TS types, kitchen-sink demo data
- Unit, feature and palette tests; deliberate update of the `overlay-pdf-download-menu.png` baseline

**Out of scope:**
- Markers, notices or "(usunięty)" in the menu PDF
- Renaming the existing PDF options or changing their content
- Combining the menu with recipes or the shopping list; E2E spec changes

## Architecture / Approach

The same controller action branches on `menu=1` after the existing authorization and `done` checks. `MealPlanPdfExporter::menu()` loads only `meals.recipe` and builds `MealPlanMenu` (title, 7 day labels shared with the full PDF's formatter, 3 `PdfMenuRow`s × 7 cells). `renderMenu()` renders the new Blade view with the same dompdf settings. The Resources add `pdf_menu`, and the Vue dropdowns render a third item.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Backend: menu-only PDF | `?menu=1` returns a one-page grid PDF; `links.pdf_menu`; tests | Tuning row heights and the truncation limit so dompdf never spills onto page 2 |
| 2. Frontend: "Sam jadłospis" option | Third item in all PDF menus, kitchen sink, refreshed baseline | Visual baseline updated against the wrong stack (see lessons.md worktree rule) |

**Prerequisites:** Sail up (`vendor/bin/sail up -d`); for the visual gate, the `E2eMealPlanSeeder` seed and a build without `public/hot`.
**Estimated effort:** ~1 session across 2 phases.

## Open Risks & Assumptions

- `prohibited_if_accepted` is assumed to exist in the installed Laravel 13. If it doesn't, an `after()` validator gives the same 422.
- Plans are assumed to always be 7 days, and the grid columns come from the plan's meal dates, so a shorter plan would still render.
- Truncated names lose their tail ("…"). That's acceptable for a printout; the full name stays in the other PDFs.

## Success Criteria (Summary)

- From any PDF menu of a done plan, "Sam jadłospis" opens a single-page PDF with only the week's recipe names in a day × meal grid.
- The existing two PDFs are unchanged, and access rules (owner-only, done-only) hold for the new variant.
- Pest, Larastan, Pint, build, `lint:tokens` and the visual gate are green.
