# Spiżarnia Tokens — Plan Brief

> Full plan: `context/changes/spizarnia-tokens/plan.md`

## What & Why

Roadmap F-05 (M-4, SP-01) is the first step of the Spiżarnia restyle. It swaps the token layer from the green Zielnik look to Spiżarnia:
- an ink brand colour, solid honey/tomato/indigo meal colours and a cream background;
- 2px-outline "sticker" shapes;
- Bricolage Grotesque as the font.

Every later M-4 change (primitives F-06, views S-22…S-26) reads these tokens, so they have to land first and in one go.

## Starting Point

`resources/css/app.css` holds the Zielnik palette in OKLCH, plus a `.dark` block nobody uses, radii derived from `--radius`, and Figtree from Bunny Fonts. The PDF (`meal-plan.blade.php`) mirrors 12 of those colours as hexes, guarded by `MealPlanPdfPaletteTest`. Six visual specs (52 PNGs) assert that Figtree loaded and pin the current look.

## Desired End State

The whole app renders in the Spiżarnia palette and font. The kitchen sink shows every new colour, type role, radius and shadow. The PDF prints solid category blocks. All baselines are regenerated and reviewed. Views and primitives look "Spiżarnia-coloured" but keep their Zielnik structure until F-06 and S-22+.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Palette values | §10.2 hexes as three-decimal OKLCH, verified to round-trip | `MealPlanPdfPaletteTest` requires each hex to be the exact clipped OKLCH. | Spec §10.2 / Plan |
| New tokens | `sidebar*` as `var()` aliases; `highlight*`; `overlay` = ink at 50% | §10.2 defines them as equal to existing tokens. | Spec §10.2 |
| D-14 dark mode | Remove the `.dark` block; keep `@custom-variant dark` | The stale Zielnik dark values would mislead, and nothing sets `.dark` (D-1). | Plan (user) |
| Radii | Remap `rounded-sm/md/lg/xl` to 8/12/20/24 px, explicit; drop `--radius` | `ui/` fields (`md`) and cards (`lg`) pick up the Spiżarnia shapes immediately. | Plan (user) |
| Shadows | `shadow-sticker` (3px) and `shadow-dialog` (6px), hard offsets in `--foreground` | Theme-neutral names for the two §10.2 shadows. | Plan |
| Typography | 7 role utilities (`text-display` … `text-meta`); `body` set to `text-body` 15/22 | `lint:tokens` bans arbitrary sizes, and §10.3 makes 15/22 the default. | Plan (user) |
| Class merging | `cn()` extended with the new `text`/`shadow` theme names | Otherwise tailwind-merge drops `text-meta` next to a text colour and `shadow-sticker` next to a shadow colour. | Plan review F1 |
| Font host | Bunny Fonts, `bricolage-grotesque:400–800` | Same pattern as Figtree, no new dependency; `latin-ext` verified. | Plan (user) |
| PDF | Same 12 mirrored tokens with new hexes; test logic unchanged | D-12 wants solid blocks in print; the guard already covers it. | Spec D-12 |

## Scope

**In scope:**
- `app.css`: palette, new tokens, radii, shadows, type roles, font, `body` default; `.dark` removed
- `app.blade.php` font link
- `lib/utils.js`: `cn()` learns the new `text-*` roles and shadows (`extendTailwindMerge`)
- `meal-plan.blade.php` mirror and its rules
- Visual-spec font assertion: `expectFigtreeLoaded` → `expectAppFontLoaded`
- Kitchen sink: Colours, Typography, Shape and shadows sections
- All 52 visual baselines
- `design-system.md` sync (D-14, §4.8, §6, §7.3, §10.2–10.3)

**Out of scope:**
- `ui/` variants: pill, outline, sticker, `bg-overlay`; Dialog → `rounded-xl`; Card → `rounded-lg` (all F-06)
- Views adopting the new utilities (S-22…S-26)
- A dark Spiżarnia palette
- The PDF font or layout
- A self-hosted or variable font

## Architecture / Approach

There is one source of truth, `app.css :root`, mapped into Tailwind by `@theme inline`. Everything else derives from it:
- **Tailwind utilities** in the views and the kitchen sink;
- **PDF mirror**, hex copies in the template, kept honest by `MealPlanPdfPaletteTest`;
- **Visual gate**, whose screenshots pin the result.

Tokens and the PDF mirror change in the same phase, so the suite never goes red. Baselines are refreshed once, at the end.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Tokens, font and PDF mirror | Spiżarnia in `app.css`, Bricolage loaded and asserted, PDF in new colours, palette test green | An OKLCH string that differs between `app.css` and the mirror (e.g. `0.9` vs `0.900`) fails the palette test. |
| 2. Kitchen sink | 23 swatches, 7 type roles, radii, sticker and dialog shadows on `/dev/design-system` | Arbitrary values creeping into the demo trip `lint:tokens`. |
| 3. Baselines and docs | 52 regenerated, reviewed PNGs; E2E green; `design-system.md` in sync | The font fails to load in the Playwright container (now served from `cdn.jsdelivr.net`), so the baselines capture a fallback font. |

**Prerequisites:** Sail up (with the `queue` and Mailpit services for E2E), `.env` with `AI_SCALING_DRIVER=fake`, network access to `fonts.bunny.net` and `cdn.jsdelivr.net` from the Playwright image.
**Estimated effort:** ~1 session across 3 phases. Most of the time goes into reviewing 52 PNGs.

## Open Risks & Assumptions

- **Temporary card radius.** `ui/card` uses `rounded-xl`, so cards are 24 px (not the 20 px tile radius) until F-06. This is accepted and documented.
- **Other radius side-effects.** Until F-06/S-2x, skeleton bars become pills, alerts go to 20 px, focus rings on links get rounder and the week-grid wrapper goes to 20 px. The full list is in the plan's Key Discoveries.
- **Larger text.** The `body` default of 15 px only enlarges inherited text. Elements with explicit `text-sm`/`text-xs` stay at 14/12 px until their view migrates, so mixed sizes are expected between F-05 and S-26.
- **Static font weights.** Bunny serves static weights, so the `opsz` axis from §10.3 is not available. The display sizes may look slightly different from the artifact mock-ups.

## Success Criteria (Summary)

- The app, kitchen sink and PDF show the Spiżarnia palette and Bricolage Grotesque, with no Figtree reference left.
- `MealPlanPdfPaletteTest`, Pest, build, `lint:tokens`, E2E and the visual gate pass on the refreshed baselines.
- `design-system.md` records D-14 and the Tailwind names (radii, shadows, type roles) that F-06 and the views will use.
