# PDF Design Alignment Implementation Plan

## Overview

Restyle the meal-plan PDF (`resources/views/pdf/meal-plan.blade.php`, rendered by dompdf) so it follows the design-system contract in `context/foundation/design-system.md` §6 (roadmap S-14, DS-09). The category cell gets the same colour pair as the on-screen category badge, the notices box looks like the warning alert, the neutral greys become token colours, and text sizes follow the five typography roles of §4.5. dompdf cannot read CSS variables, so the PDF keeps a hand-copied hex mirror of the tokens. A Pest guard fails when a mirrored token changes in `resources/css/app.css`.

## Current State Analysis

- The template has its own greys in an inline `<style>`: text `#111`, cell borders `#999`, header background `#e8e8e8`, day rule `2px #444`, and notices in `#c9a227` / `#fff8e1` (`resources/views/pdf/meal-plan.blade.php:6-21`). None of them are tokens.
- The template cannot colour categories. `PdfMeal::$category` holds only the Polish label (`app/Services/MealPlanPdf/PdfMeal.php:13`), set from `MealCategory::from(...)->label()` (`app/Services/MealPlanPdf/MealPlanPdfExporter.php:95-96`), and `PdfMeal` is constructed only there.
- F-03 (`design-tokens-foundation`, archived 2026-11-15) put the OKLCH tokens in `resources/css/app.css`: in `@layer base { :root { … } .dark { … } }`, one declaration per line, e.g. `--meal-breakfast: oklch(0.95 0.06 85);`. `.dark` repeats the same names with other values.
- On screen, `Badge` variants `breakfast|supper|dinner` pair `bg-meal-*` with `text-meal-*-foreground` (`resources/js/Components/ui/badge/index.js`). `Alert` variant `warning` uses `bg-warning text-warning-foreground` inside the default `border` frame, i.e. `--border` (`resources/js/Components/ui/alert/index.js`).
- design-system §4.2 and §4.4 list the sRGB-clipped hexes and say the PDF must use them rather than recompute the OKLCH. §4.3 (state pairs, including `--warning`) has no hex column.
- The PDF is rendered on every request, with no stored file (`MealPlanController::downloadPdf`, `app/Http/Controllers/MealPlanController.php:193`). The new look therefore applies to every existing plan at once, with no invalidation.
- Tests read the PDF only as extracted text (`pdfText()` in `tests/Pest.php:88`). They can't see colours, so colour checks have to look at the rendered HTML.
- This branch was rebased onto `develop` (`bef3277`) during planning, so F-03, F-04 and the M-2 roadmap with S-14 are present.

## Desired End State

Downloading any finished plan's PDF shows:
- category cells (Śniadanie / Obiad / Kolacja) in the badge colour pair from the screen, label kept
- the scaling notices in a warning-alert box
- body text, table header background and cell borders in token colours
- a `--muted-foreground` rule between days, much darker than the `--border` cell borders (though slightly lighter than today's `#444`), so day boundaries stay visible in grayscale print
- headings and labels sized after §4.5
- the shopping-list quantities right-aligned

Every colour in the template (in `<style>` and in inline `style=""` attributes) is one of the listed token mirrors. A Pest test proves two things: the mirrored tokens still match `app.css :root`, and each listed hex is the sRGB-clipped value of its OKLCH.

Verify with `vendor/bin/sail pest tests/Unit/Services/MealPlanPdfExporterTest.php tests/Unit/Services/MealPlanPdfPaletteTest.php`, then open a generated PDF next to the kitchen sink `/dev/design-system` (section "Odznaki").

### Key Discoveries:

- Clipped hexes recomputed during planning match §4.2/§4.4 exactly. The pairs used here and their contrast:
  - warning pair: `#fff1c4` / `#754b00`, 6.7:1
  - meal pairs: breakfast `#ffecc1` / `#784900` (6.5:1), supper `#ffe2d5` / `#99351b` (5.9:1), dinner `#e6e9ff` / `#4a4b96` (6.4:1)
  - other §4.3 pairs: success `#dcf7e1` / `#195c2e`, info `#ddf2ff` / `#0e5794`
- The repo already has a mirror convention: a comment naming the source plus "keep in sync" (`resources/js/lib/mealCategories.js:1` ↔ `app/Enums/MealCategory.php`).
- Existing PDF text assertions (`tests/Unit/Services/MealPlanPdfExporterTest.php:150-207`) must stay green unchanged. The restyle must not change any rendered text.

## What We're NOT Doing

- No cream `--background` page colour. The PDF page stays white paper (`--card`), so printing doesn't waste ink.
- No dark-mode variant of the PDF.
- No `.dark` tokens and no `success`/`info`/`destructive`/`primary` colours in the PDF. They have no role there.
- No `border-l-4` category stripe on rows: §6 colours only the "Posiłek" cell.
- No PDF in the Playwright visual gate (`tests/e2e/visual/`), and no PDF-to-image snapshot.
- No change to the PDF's content, column order, paper size, font (`DejaVu Sans`) or filename.
- No OKLCH→hex conversion in `app/` code. The template keeps hand-copied hexes. Only the guard test computes the clipped hex, to check the copy.
- No changes to `lint:tokens`. It scans JS/Vue only and the Blade template is outside it.

## Implementation Approach

Pass the raw category value to the template next to the label, and turn it into a `meal-<value>` class on the category cell. Rewrite the `<style>` block so each colour is a hex taken from a token, and add a structured comment listing `token  oklch(...)  #hex` per mirrored token. Phase 2 adds a Pest test that parses that comment and `app.css :root`. It also fails on any hex in `<style>` that isn't listed. Then the spec gets updated so §4.3 has hexes and §6 describes what was built.

## Critical Implementation Details

**dompdf constraints.** DejaVu Sans ships only normal and bold, so the §4.5 "medium/semibold" weights map to `font-weight: bold`. Use the keyword, not `500`/`600`. dompdf supports `background`, `border` and `border-radius` on block elements and table cells, but not CSS variables, `oklch()`, flex or grid.

**Guard parsing.** `--meal-breakfast` is a prefix of `--meal-breakfast-foreground`, and `.dark` reuses every name. The guard must match `--name:` exactly and read only the `:root` block. Otherwise it passes or fails for the wrong token.

**Clipped-hex check.** The conversion is OKLCH → OKLab (`a = C·cos H`, `b = C·sin H`), then OKLab → linear sRGB (Björn Ottosson's matrices), then clamp each channel to [0, 1], then the sRGB gamma, then `round(x·255)`. With these steps the plan review reproduced all 16 light hexes of §4.2–4.4 exactly. A helper in the test file is enough; it does not go in `app/`.

## Phase 1: Tokenised PDF template

### Overview

Give the template the raw category value and restyle it with the token mirror: category colour pairs, the warning notices box, neutral token colours with the darker day rule, the §4.5 type scale and right-aligned quantities.

### Changes Required:

#### 1. Raw category on the PDF meal

**File**: `app/Services/MealPlanPdf/PdfMeal.php`, `app/Services/MealPlanPdf/MealPlanPdfExporter.php`

**Intent**: Let the template pick a category colour without parsing Polish labels. Keep the label for display.

**Contract**: `PdfMeal` gains `public string $categoryValue`, the `MealCategory` backing value (`breakfast|supper|dinner`), next to the existing `$category` label. `MealPlanPdfExporter::pdfMeal()` fills it from the same `MealCategory::from($meal->meal_category)` it already builds. Use named arguments or keep positional order consistent; `PdfMeal` has a single constructor call site.

#### 2. Token-mirrored template styles

**File**: `resources/views/pdf/meal-plan.blade.php`

**Intent**: Replace every untokenised grey with a token hex, colour the category cell like the screen badge, frame notices like the warning alert, and apply the §4.5 type roles at PDF size.

**Contract**:
- The `<style>` block opens with a mirror comment. Its first line says it mirrors `resources/css/app.css :root`, should be kept in sync, and is guarded by `tests/Unit/Services/MealPlanPdfPaletteTest.php`; hexes are the sRGB-clipped values from design-system §4.2–4.4.
- After that, one line per mirrored token in the form `--token  oklch(L C H)  #rrggbb`. List these 12 tokens:
  - `--foreground`, `--muted`, `--muted-foreground`, `--border`
  - `--warning`, `--warning-foreground`
  - `--meal-breakfast`, `--meal-breakfast-foreground`, `--meal-supper`, `--meal-supper-foreground`, `--meal-dinner`, `--meal-dinner-foreground`
  - The OKLCH values come verbatim from `app.css :root`; the hexes are those in Key Discoveries and §4.2/§4.4.
- Colours by element:
  - body text: `--foreground`
  - cell borders: `1px --border`
  - `th` background: `--muted`
  - day separator `tr.day-start td`: `2px --muted-foreground` (instead of `#444`)
- Category cell: `<td class="category meal-{{ $meal->categoryValue }}">`.
  - Three rules `.meal-breakfast|.meal-supper|.meal-dinner` set background `--meal-*` and text `--meal-*-foreground`.
  - The cell text is bold at the metadata size, mirroring the badge's `text-xs font-semibold`. The label text stays exactly as today.
- `.notices`: background `--warning`, text `--warning-foreground`, `1px --border` frame, small radius. Mirrors `Alert variant="warning"`.
  - `--border` on `--warning` is only 1.19:1. If manual check 1.7 shows the box disappearing in grayscale, frame it in `--warning-foreground` instead. That token is already mirrored, so no new token is needed.
- The `(usunięty)` marker gets its own element so it can take the metadata style: `{{ $meal->recipeName }}@if ($meal->recipeDeleted) <span class="meta">(usunięty)</span>@endif`. Keep the space before the span. `.meta` and `.note` share the 8pt `--muted-foreground` rule.
- Type scale (body stays 9pt):

  | Role | Element | Size and style |
  | --- | --- | --- |
  | Page title | `h1` | 15pt bold |
  | Section title | `h2` | 12pt bold (was 13pt) |
  | Text | body | 9pt |
  | Label | `th`, `.day` | 9pt bold |
  | Metadata | `.meta` (the `(usunięty)` marker) and `.note` | 8pt in `--muted-foreground` |

- Shopping-list quantity cell is right-aligned (§4.5 "numbers right-aligned"). DejaVu Sans digits are already fixed-width, so no `tabular-nums` equivalent is needed.
- No other hex, named colour or `rgb()` remains in the template, inside `<style>` or in inline `style=""` attributes. Right-align the quantity with a class rule, not an inline style.

#### 3. HTML-level assertions

**File**: `tests/Unit/Services/MealPlanPdfExporterTest.php`

**Intent**: Prove the template applies the category classes and the notices frame. Text extraction can't see these.

**Contract**:
- Extend the existing ordering test so it also asserts `categoryValue` is `['breakfast', 'supper', 'dinner']`.
- Add a test that renders `view('pdf.meal-plan', ['document' => $exporter->document($plan)])->render()` for a plan with one meal of each category plus a scaling notice. It asserts:
  - each label sits in a cell with class `meal-<value>` (e.g. `class="category meal-breakfast">Śniadanie<`)
  - the notices `div` is present
- The existing text-based render tests stay unchanged and green.

### Success Criteria:

#### Automated Verification:

- PDF exporter tests pass: `vendor/bin/sail pest tests/Unit/Services/MealPlanPdfExporterTest.php`
- Template contains no pre-token greys: `grep -nE '#(111|999|e8e8e8|444|c9a227|fff8e1)\b' resources/views/pdf/meal-plan.blade.php` returns nothing
- Larastan passes: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`

#### Manual Verification:

- A generated PDF shows Śniadanie / Obiad / Kolacja cells in the same honey / tomato / indigo pairs as the category badges in `/dev/design-system` ("Odznaki")
- A plan without family members shows the "Dodaj członków rodziny…" notice in a warm-yellow box with brown text, like the warning alert
- Print preview in grayscale: category labels are readable, day boundaries are clearly visible, and the notices box still reads as a box
- Polish glyphs render, and the table header repeats on the second page

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase. Phase blocks use plain bullets; the checkboxes live in `## Progress`.

---

## Phase 2: Drift guard and spec sync

### Overview

Make a palette change in `app.css` break CI and point at the PDF, then record the PDF's token mapping in the design-system spec.

### Changes Required:

#### 1. Palette mirror guard

**File**: `tests/Unit/Services/MealPlanPdfPaletteTest.php` (new, `declare(strict_types=1);`, Pest)

**Intent**: Turn the "keep in sync" comment into a check. Mirrored tokens must still have the listed OKLCH value in `app.css :root`, and the template may use only listed hexes.

**Contract**: reads `resource_path('views/pdf/meal-plan.blade.php')` and `resource_path('css/app.css')`.
- Mirror entries are parsed from the `--token oklch(...) #hex` lines in the template's mirror comment. There must be at least the 12 entries from Phase 1, so the test can't pass if the comment format breaks.
- For each entry, the `:root` block of `app.css` declares `--token:` (exact name) with the same OKLCH value, compared after normalising whitespace.
  - A missing token fails with the token name.
  - A changed token fails with the token, the expected value and the actual value.
- Each entry's hex equals the sRGB-clipped hex computed from its OKLCH (see Critical Implementation Details). A mismatch fails with the token, the listed hex and the computed hex.
- The whole template, both `<style>` and inline `style=""` attributes, is scanned outside the mirror comment:
  - every hex is a listed mirror hex, case-insensitive
  - `rgb(`, `rgba(`, `hsl(`, `hsla(`, `oklch(` and the keywords `white|black|gray|grey` used as values all fail
- Every listed hex is used by at least one rule, so no stale entries are left.

#### 2. Design-system spec

**File**: `context/foundation/design-system.md`

**Intent**: Give §4.3 the hexes the PDF needs, and make §6 describe the contract as built.

**Contract**:
- §4.3 gains a "Hex tło / tekst (≈)" column:
  - success `#dcf7e1` / `#195c2e`
  - warning `#fff1c4` / `#754b00`
  - info `#ddf2ff` / `#0e5794`
- The out-of-gamut note under §4.4 points the PDF at the hex columns of both §4.3 and §4.4.
- §6 states:
  - the 12 mirrored tokens
  - white paper (`--card`), not `--background`
  - the day rule in `--muted-foreground` for grayscale print
  - the pt mapping of the §4.5 roles
  - the guard test that enforces the mirror
- Frontmatter `updated:` is bumped.

### Success Criteria:

#### Automated Verification:

- Guard test passes: `vendor/bin/sail pest tests/Unit/Services/MealPlanPdfPaletteTest.php`
- Guard catches drift: temporarily change `--meal-dinner` in `app.css :root` and see the guard fail naming `--meal-dinner`; changing one listed hex in the mirror comment, adding an unlisted hex to the template's `<style>`, and adding `style="color: black"` to a cell each also fail it; revert all
- Full suite passes: `vendor/bin/sail composer test`
- Pint passes: `vendor/bin/sail composer pint-test`

---

## Testing Strategy

### Unit Tests:

- `MealPlanPdfExporterTest`: `categoryValue` order, and the category class plus notices frame in the rendered HTML. Existing text, ordering, notice and glyph tests unchanged.
- `MealPlanPdfPaletteTest`:
  - each mirrored token's OKLCH still matches `app.css :root`
  - each listed hex is the clipped value of its OKLCH
  - no unlisted hex, functional colour or named colour is used, in `<style>` or inline
  - no listed hex is stale
  - failure messages name the token

### Integration Tests:

- None new. `tests/Feature/MealPlanControllerTest.php` and `tests/e2e/meal-plan.spec.ts` keep covering the download route; they assert text and status, which this change does not alter.

### Manual Testing Steps:

1. `vendor/bin/sail up -d`, log in as an E2E planner user, generate a plan and open the PDF.
2. Compare the category cells with the "Odznaki" section of `/dev/design-system`.
3. Use a user without family members to see the notices box.
4. Print preview in grayscale: labels readable, day boundaries visible.

## Performance Considerations

None. The same template renders with a few more CSS rules.

## Migration Notes

None. PDFs are rendered per request, so every existing plan gets the new look immediately. Nothing is stored.

## References

- Contract: `context/foundation/design-system.md` §4.2–4.5, §6
- Roadmap item: `context/foundation/roadmap.md` S-14
- Prerequisite: `context/archive/2026-11-15-design-tokens-foundation/plan.md`
- Previous PDF change: `context/archive/2026-11-14-meal-plan-pdf-export/plan.md`
- Mirror convention: `resources/js/lib/mealCategories.js:1`
- Screen pairings: `resources/js/Components/ui/badge/index.js`, `resources/js/Components/ui/alert/index.js`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Tokenised PDF template

#### Automated

- [x] 1.1 PDF exporter tests pass: `vendor/bin/sail pest tests/Unit/Services/MealPlanPdfExporterTest.php`
- [x] 1.2 Template contains no pre-token greys: `grep -nE '#(111|999|e8e8e8|444|c9a227|fff8e1)\b' resources/views/pdf/meal-plan.blade.php` returns nothing
- [x] 1.3 Larastan passes: `vendor/bin/sail composer phpstan`
- [x] 1.4 Pint passes: `vendor/bin/sail composer pint-test`

#### Manual

- [ ] 1.5 A generated PDF shows Śniadanie / Obiad / Kolacja cells in the same honey / tomato / indigo pairs as the category badges in `/dev/design-system` ("Odznaki")
- [ ] 1.6 A plan without family members shows the "Dodaj członków rodziny…" notice in a warm-yellow box with brown text, like the warning alert
- [ ] 1.7 Print preview in grayscale: category labels are readable, day boundaries are clearly visible, and the notices box still reads as a box
- [ ] 1.8 Polish glyphs render, and the table header repeats on the second page

### Phase 2: Drift guard and spec sync

#### Automated

- [ ] 2.1 Guard test passes: `vendor/bin/sail pest tests/Unit/Services/MealPlanPdfPaletteTest.php`
- [ ] 2.2 Guard catches drift: temporarily change `--meal-dinner` in `app.css :root` and see the guard fail naming `--meal-dinner`; changing one listed hex in the mirror comment, adding an unlisted hex to the template's `<style>`, and adding `style="color: black"` to a cell each also fail it; revert all
- [ ] 2.3 Full suite passes: `vendor/bin/sail composer test`
- [ ] 2.4 Pint passes: `vendor/bin/sail composer pint-test`
