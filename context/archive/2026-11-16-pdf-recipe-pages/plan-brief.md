# PDF Recipe Pages — Plan Brief

> Full plan: `context/changes/pdf-recipe-pages/plan.md`

## What & Why

Roadmap S-21 (MS-06): "w jadłospisie pdf musi być możliwość dodania sposobu wykonania — samego przepisu". When downloading the PDF, the user chooses whether to include recipes. The table stops carrying the long method text, and included recipes are printed in full on separate pages, ready to cook from.

## Starting Point

The PDF is rendered on demand by dompdf (`MealPlanPdfExporter` + `resources/views/pdf/meal-plan.blade.php`, A4 landscape). It has a 5-column table whose "Przygotowanie" column takes 40% of the width with the full method, followed by the shopping list. One `GET meal-plans/{id}/pdf` link is offered as a single "Pobierz PDF" action on the plan page, the dashboard and the plans list ⋯ menu.

## Desired End State

"Pobierz PDF" opens a menu with "Z przepisami" and "Bez przepisów" in all three places. Both variants show a 4-column table (no method) and the shopping list. "Z przepisami" adds one page per distinct recipe after the list, in first-use order. Each page shows the name, a category label, the family-scaled ingredients grouped by identical amounts and labelled with their days, and the method. The variant with recipes downloads as `…-z-przepisami.pdf`.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Meaning of "osobne strony" | Every recipe starts on a fresh page (long ones continue) | One sheet per dish can go to the kitchen. | Plan |
| Amounts on a recipe used several times | Scaled amounts, one list per distinct set, labelled with its days (e.g. `Poniedziałek 16.11 · Środa 18.11`) | No duplicate lists in the usual case, still exact when the AI refiner or S-19 doubling makes days differ. | Plan (resolves roadmap S-21 unknown) |
| How the user chooses | Two-option menu "Z przepisami" / "Bez przepisów"; two items in the ⋯ menu | No hidden state, both variants visible, reuses `DropdownMenu`. | Plan |
| Section order | Table → shopping list → recipes | The shopping list stays on page 2 either way; PRD US-010 order kept. | Plan |
| Recipe page order | First use in the week (day, then Śniadanie → Obiad → Kolacja) | Pages follow the cooking order and the table. | Plan |
| Preparation column in the table | Removed in both variants | Roadmap outcome: "tabela jadłospisu nie ma już długiego tekstu przygotowania". | Roadmap |
| HTTP contract | Same route plus validated `?recipes=1` (Form Request); new `links.pdf_with_recipes`; `links.pdf` = without | One route, existing links keep working, done-only rule unchanged. | Plan |
| Filename | `-z-przepisami` suffix for the variant with recipes | Both variants can be saved side by side. | Plan |

## Scope

**In scope:**
- recipe-page DTOs and exporter flag, the 4-column table, recipe pages in the template, a design-system §6 note
- the `DownloadMealPlanPdfRequest`, the controller flag, the filename suffix and the second link in resources
- a shared `PdfDownloadMenu` on the plan page and dashboard, two ⋯ menu items, TS types, the kitchen-sink demo and overlay shot
- Pest unit and feature tests, the E2E update and deliberate visual baseline refresh

**Out of scope:**
- the data model and scaling
- S-17…S-20 cell states (empty, repeat, carryover)
- page numbers or a table of contents, portrait or mixed orientation
- a remembered choice, PRD edits, new shadcn primitives

## Architecture / Approach

`MealPlanPdfExporter::document($plan, $withRecipes)` walks the meals once, in the same day → category order as the table. It collects the ingredient lines each meal already prints and accumulates them per `recipe_id` (first sighting fixes the page order), grouping uses by identical formatted lines. The Blade template renders the recipes after the shopping list, as block sections with `page-break-before: always`, not a table row, because of the global `tr { page-break-inside: avoid }`. It reuses only the 12 mirrored palette hexes. The controller reads `recipes` through a Form Request that also owns `downloadPdf` authorization, and the Vue entry points read the two links.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. PDF document and template | Recipe pages behind a `withRecipes` flag; table without the method | dompdf page breaks on very long methods; the palette guard (category label reuses `td.meal-*`, no new selectors) |
| 2. Download endpoint and links | `?recipes=1`, filename suffix, `links.pdf_with_recipes` | Moving authorization into the Form Request must keep 403-before-404 |
| 3. Download menu UI | Shared two-option menu in 3 places (the plan page's ⋯ menu also gets both items), kitchen-sink demo, E2E and visual updates | Mobile header width; baseline churn beyond the 7 expected PNGs |

**Prerequisites:** Sail up; `AI_SCALING_DRIVER=fake`; `E2eMealPlanSeeder` for E2E and visuals. No dependency on S-17…S-20.
**Estimated effort:** ~2 sessions across 3 phases.

## Open Risks & Assumptions

- Repeated recipes are rare today (the draw picks 7 distinct recipes per category). The grouping logic is proven by unit tests on hand-built plans and will meet real data with S-17/S-19.
- The variant with recipes can run to ~25 pages. Render time grows with page count, which is acceptable for an on-demand download.
- S-18…S-20 will add their own cell states to the same template and must keep the recipe pages' grouping semantics (a second use without ingredients shows `—`).

## Success Criteria (Summary)

- A user can download the plan with or without recipes from every place that offers the PDF.
- With recipes, each dish used that week is on its own page once, with the amounts the family actually needs on each day and the full method.
- CI stays green: Pest, Larastan, Pint, `lint:tokens`, E2E and the visual gate with only the intended baselines changed.
