# Meal Plan PDF Export (S-06) — Plan Brief

> Full plan: `context/changes/meal-plan-pdf-export/plan.md`

## What & Why

A user can download one PDF for a finished weekly plan. It shows first the plan table (day, category, name, ingredients, preparation), then the shopping list, so the family has the week offline (US-010, FR-7). This is the last slice of stream B (S-02 → S-05 → S-06) in milestone M-1.

## Starting Point

There are already a `/meal-plans/{id}/pdf` route, a `downloadPdf` action, `pdf_path`/`pdf_size` columns and a disabled "Pobierz PDF" menu item. Nothing ever creates a PDF, and no PDF library is installed.

The action has no owner check, so any logged-in user can request another user's plan. It also forces a download instead of opening in the browser. S-05 left `ShoppingListBuilder` with Polish display strings ready to reuse.

## Desired End State

On a done plan, a "Pobierz PDF" button in the header opens a new tab. The same action is available in the "⋯" menu on the plan view and the list. The tab shows an A4 landscape PDF with correct Polish characters:
- a 21-row table grouped by day ("Poniedziałek 16.11"), with ingredients as written and the full preparation text;
- then "Lista zakupów" on a new page, identical to the card on screen.

Another user's plan gives 403. A generating or failed plan gives 404 and shows no button. After "Regeneruj" the PDF matches the new draw.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Generation model | Render on every request, store nothing | The PDF always matches the plan view, and regeneration needs no invalidation because there is no file to go stale. |
| "Latest generated version" | The plan's current meal draw with live recipe data | Same rule as the grid and S-05's list; a recipe edit shows in all three. |
| Library | `barryvdh/laravel-dompdf` ^3.1 (DejaVu Sans) | Pure PHP, low memory for the Mikrus VPS, bundled font covers Polish; supports Laravel 13. |
| Browser behaviour | `inline` in a new tab, filename `jadlospis-<start>-<end>.pdf`, `Cache-Control: no-store` | Literally "opens in the browser"; no-store stops the browser showing the previous draw at the same URL. |
| Layout | A4 landscape, one 5-column table, 21 rows, day cell spans its rows, list on a new page | Exactly FR-7's column list; landscape gives room for instructions. |
| `pdf_path` / `pdf_size` | Dropped by migration; resource exposes `links.pdf` (URL when done, else null) | Removes dead fields that imply a stored file and leak a storage path. |
| Entry points | Header button on the plan view + existing menu item, both driven by `links.pdf` | Makes the US-008.3 download option visible where the result is shown. |
| Authorization | New `MealPlanPolicy::downloadPdf` (owner), checked before the status check | Closes the existing cross-user access gap without revealing another user's plan status. |
| Test depth | `smalot/pdfparser` (dev) extracts the PDF's text in Pest; E2E checks headers and `%PDF` | Automates the roadmap's Polish-character risk: a missing glyph becomes `?` and fails. |

## Scope

**In scope:**
- A `MealPlanPdfExporter` service, its value objects and a Blade/dompdf template
- A shared `QuantityFormatter` taken out of `ShoppingListBuilder`
- The `downloadPdf` policy ability and a rewritten action (inline, no-store, Polish 404s, a correct filename)
- A migration dropping the PDF columns, plus cleanup in the model, factory, resource and `destroy()`; `links.pdf` on the resource
- The header button, the dropdown gating and the TypeScript types
- Pest unit, feature and policy tests, and an E2E step
- Updates to `.ai/core/{db,api,test}-plan.md`

**Out of scope:**
- A queued PDF job, stored files, cleanup commands and a rate limit (parked)
- A snapshot at generation time
- Scaling quantities to the family (S-04)
- The dashboard "Ostatni PDF" card
- Page numbers, images and a JSON PDF API

## Architecture / Approach

`downloadPdf` authorizes, checks `done`, and calls `MealPlanPdfExporter::render()`. The exporter builds a `MealPlanDocument`:
- days come from the meals, sorted by date, then Śniadanie/Obiad/Kolacja;
- ingredient lines are formatted by the shared `QuantityFormatter`;
- the `ShoppingList` comes from `ShoppingListBuilder::forMealPlan()`.

It renders `resources/views/pdf/meal-plan.blade.php` with dompdf and returns the bytes inline. `MealPlanResource.links.pdf` is the only "can download" signal. Polling of `mealPlan` already exists, so the button shows up by itself when generation finishes.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. PDF document | Dependencies, shared formatter, exporter and template, unit tests that read PDF text | dompdf layout limits (rowspan at page breaks), with a fallback defined |
| 2. Download endpoint and cleanup | Policy fix, inline no-store response, columns dropped, `links.pdf`, feature tests, spec docs | Many tests use the factory's `done()` state, so removing its fake `pdf_path` has to keep them green |
| 3. UI and E2E | Header button, dropdown via `links.pdf`, E2E PDF checks before and after regeneration | E2E timing around the button hiding and returning during regeneration |

**Prerequisites:** S-05 done (it is). Sail up with the `queue` service. Network access for `composer require`.
**Estimated effort:** about 2–3 sessions across 3 phases.

## Open Risks & Assumptions

- Each download renders in the web process (about 1–3 s). This is fine for a weekly action; a rate limit stays parked until the server shows otherwise.
- Text extraction can split cells, so tests assert short substrings inside a single cell.
- The F-02 production image must keep ext-dom and ext-mbstring enabled. dompdf brings its own fonts.
- Plans generated before S-04 show recipe quantities as written. S-04 will later change both the list and the PDF through the same seam.

## Success Criteria (Summary)

- On a done plan, one click opens a correct Polish PDF in the browser: the table first, then the list.
- The PDF always matches the plan shown on screen, including right after a regeneration.
- Nobody can download another user's plan, and the tests catch broken Polish characters automatically.
