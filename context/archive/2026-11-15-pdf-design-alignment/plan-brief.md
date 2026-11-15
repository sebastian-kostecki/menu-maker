# PDF Design Alignment — Plan Brief

> Full plan: `context/changes/pdf-design-alignment/plan.md`

## What & Why

The meal-plan PDF still uses its own greys and has no link to the screen. The design-system contract (§6, roadmap S-14) asks that what users see in the app is what they recognise on paper. This change gives the PDF the screen's colours:
- category colour pairs
- the warning-alert look for notices
- token neutrals
- the §4.5 type roles

It also guards the hand-copied colours against drifting from `app.css`.

## Starting Point

`resources/views/pdf/meal-plan.blade.php` (dompdf) styles itself with untokenised greys. Its `PdfMeal` input carries only the Polish category label, so the template can't pick a category colour. F-03 already put the OKLCH tokens in `resources/css/app.css`, and §4.2/§4.4 list their clipped hexes. This branch was rebased onto `develop` to pick them up.

## Desired End State

Every PDF (rendered per request, so existing plans included) shows:
- Śniadanie / Obiad / Kolacja cells in the same colour pairs as the on-screen badges
- the scaling notices in a warning-alert box
- token text, header and border colours
- a `--muted-foreground` day separator, much darker than the cell borders, that survives grayscale print
- headings sized after §4.5
- right-aligned shopping-list quantities

A Pest test fails in three cases: a mirrored token changes in `app.css`, a listed hex is not the clipped value of its OKLCH, or an unlisted colour enters the template (in `<style>` or inline).

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| What gets coloured | Category cell (badge pair), notices (warning pair), type roles | Stated in the contract and in the S-14 outcome | Design system §6 / roadmap S-14 |
| Colour source | Copied sRGB-clipped hexes, never recomputed OKLCH | dompdf has no CSS variables or `oklch()`; §4.4 fixes the clipped values | Design system §4.4 |
| Branch base | Rebased onto `develop` during planning | F-03 tokens, the M-2 roadmap and the design rule live only on `develop` | Plan |
| Neutral palette | Token foreground/muted/border, but the day rule in `--muted-foreground` | Every colour is a token, and day boundaries stay visible on grayscale paper | Plan |
| Drift guard | Mirror comment plus Pest guard against `app.css :root`, the clipped hex of each entry, and unlisted colours anywhere in the template | Turns the main S-14 risk (silent drift) into a CI failure, including a hex left stale after the OKLCH was updated | Plan + plan review (F1, F2) |
| Page colour | White paper (`--card`), not cream `--background` | Printing a tinted page wastes ink and dulls the category colours | Plan |
| Template input | `PdfMeal::$categoryValue` (raw enum value) next to the label | Picks the colour class without parsing Polish labels | Plan |

## Scope

**In scope:**
- `PdfMeal` raw category value
- the Blade `<style>` rewrite with the mirror comment
- the category-cell class and notices frame
- the §4.5 pt scale and right-aligned quantities
- HTML-level exporter tests
- the palette guard test
- the §4.3 hex column and §6 rewrite in design-system

**Out of scope:**
- cream page background and dark mode
- other state colours
- row stripes
- the PDF in the visual gate or as image snapshots
- PDF content, column, font or paper changes
- OKLCH→hex in `app/` code (only the guard test computes it)
- `lint:tokens` for Blade

## Architecture / Approach

`MealPlanPdfExporter` adds the raw category value to each `PdfMeal`, and the template writes it as a `meal-<value>` class on the "Posiłek" cell. The `<style>` block lists 12 mirrored tokens as `--token oklch(...) #hex` in a comment and uses only those hexes. `MealPlanPdfPaletteTest` parses that comment and the `:root` block of `app.css`. It fails on:
- a changed or missing token
- a listed hex that isn't the clipped value of its OKLCH
- an unlisted hex, functional or named colour anywhere in the template
- a listed hex that is no longer used

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Tokenised PDF template | Category colours, warning notices, token neutrals, type scale, HTML tests | dompdf rendering quirks (bold only, no variables); faint cell borders in print |
| 2. Drift guard and spec sync | Guard test, §4.3 hexes, §6 as built | Parser matching a prefixed or `.dark` token instead of the exact `:root` one |

**Prerequisites:** F-03 `design-tokens-foundation` (done, on `develop`); Sail running for tests and the manual PDF check.
**Estimated effort:** ~1 session across 2 small phases.

## Open Risks & Assumptions

- The inner cell borders (`--border`, `#e2ded5`) are faint on paper. That's accepted to match the screen; only the day rule was darkened.
- The guard recomputes the clipped hex from the OKLCH, so a stale hex fails CI. It does not check that the spec tables were updated too.
- `--border` on `--warning` is 1.19:1, so the notices frame may vanish in grayscale print. Manual check 1.7 covers it; the fallback is a `--warning-foreground` frame.
- Every existing plan's PDF changes look on deploy. That's intended, since nothing is stored.

## Success Criteria (Summary)

- A downloaded PDF's category cells and notices look like the badges and warning alert in `/dev/design-system`.
- In grayscale print, labels stay readable and days stay visually separated.
- Changing a mirrored token in `app.css` fails CI until the PDF mirror is updated.
