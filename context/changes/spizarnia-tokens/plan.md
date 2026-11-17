# Spiżarnia Tokens Implementation Plan

## Overview

Roadmap F-05 (M-4, SP-01). Switch the token layer of the app to direction C · Spiżarnia (`context/foundation/design-system.md` §10.2–10.3):
- the light palette in OKLCH, reproducing the §10.2 hexes exactly;
- the new `sidebar*`, `highlight*` and `overlay` tokens;
- explicit radii of 12/20/24 px and the two hard "sticker" shadows;
- named type-role utilities;
- Bricolage Grotesque instead of Figtree.

The PDF mirror gets the new category colours as solid blocks, and its palette test keeps guarding them (D-12). The kitchen sink shows the new colours, typography and shapes, and every visual baseline is deliberately refreshed. D-14 is settled: the `.dark` block is removed until a separate dark-mode change.

This change only touches tokens. Primitives (`ui/` variants: pill buttons, 2px outlines, dialog shadow, `bg-overlay`) belong to F-06 `spizarnia-primitives`. Views belong to S-22…S-26.

## Current State Analysis

- **Tokens.** `resources/css/app.css` holds the Zielnik palette in OKLCH:
  - `:root` (lines 85–118) and a `.dark` block (lines 120–152), mapped to Tailwind colours in `@theme inline` (lines 50–82);
  - radii derived from `--radius: 0.625rem` (`--radius-lg/md/sm`, lines 25–27);
  - `--font-sans: Figtree, …` (lines 21–23);
  - `body { @apply bg-background text-foreground; }` (line 161).
- **Font loading.** `resources/views/app.blade.php:8-9` preconnects to `fonts.bunny.net` and loads `figtree:400,500,600`.
- **PDF mirror.**
  - Template: `resources/views/pdf/meal-plan.blade.php:7-23` lists 12 tokens as `--token  oklch(...)  #hex` and uses those hexes in the rules on lines 24–42. The colour rules are on `body`, `th`/`td` borders, `th` background, `tr.day-start`, `td.meal-*`, `.meta`/`.note` and `.notices`.
  - Test: `tests/Unit/Services/MealPlanPdfPaletteTest.php` checks four things:
    - each mirrored OKLCH string equals the one in `app.css :root`, with whitespace normalised (lines 92–108);
    - each hex is the sRGB-clipped value of its OKLCH, rounded per channel (lines 60–86, 110–116);
    - the template uses no unlisted hex and no functional or named colour;
    - every listed hex is used, and every meal category has a `td.meal-<value>` rule.
  - The test's `:root` regex excludes `.dark`, so removing `.dark` does not affect it.
- **Visual gate font check.** `tests/e2e/visual/support/fonts.ts` exports `expectFigtreeLoaded`, which loads Figtree at 400/500/600. Seven files import it: `design-system`, `app-shell`, `account`, `meal-plans-index` and `meal-plan-show` specs, plus `support/auth.ts`. `recipes.spec.ts` reaches it through `auth.ts`.
- **Baselines.** There are 52 PNGs under `tests/e2e/__screenshots__/visual/`:

  | Spec | PNGs |
  | --- | --- |
  | `design-system` | 29 |
  | `app-shell` | 5 |
  | `account` | 2 |
  | `meal-plans-index` | 4 |
  | `meal-plan-show` | 5 |
  | `recipes` | 7 |

  The token change repaints every one of them.
- **Kitchen sink.** In `resources/js/Pages/Dev/DesignSystem.vue`:
  - `colorSwatches` (lines 104–123): 18 tokens.
  - `typographyRoles` (lines 125–131): the 5 Zielnik roles on `text-2xl`/`text-lg`/`text-sm`/`text-xs`.
  - `shapes` (lines 139–144): `rounded-sm/md/lg/full`.
  - The "Kształt i cienie" section (lines 343–359) demos a bordered card, `shadow-sm` and `shadow-lg`.
- **Radius consumers.**
  - Nothing outside `app.css` reads `--radius`.
  - In `ui/`, fields and buttons use `rounded-md` (14 uses), dropdown items and similar use `rounded-sm` (9), and dialogs and some others use `rounded-lg` (4).
  - `ui/card/Card.vue:12` uses `rounded-xl`, which today is Tailwind's default of 12 px.
  - Views use `rounded-lg` 27 times.
- **`dark:` classes.** The only remaining `dark:` class is in `ui/checkbox/Checkbox.vue:39` (`dark:aria-invalid:…`); the views have none (`lint:tokens` enforces this). Nothing sets `.dark`.
- **Arbitrary values.** `lint:tokens` bans them in `resources/js` outside `ui/`. The §10.3 sizes (15/22, 13/18, 36/40, 56/56, 60/68) therefore need named utilities, not `text-[15px]`.
- **Bricolage Grotesque on Bunny.** `https://fonts.bunny.net/css?family=bricolage-grotesque:400,500,600,700,800` serves `latin`, `latin-ext` (Polish) and `vietnamese` subsets for each weight. These are static cuts with no `opsz` axis, and the files are hosted on `cdn.jsdelivr.net/fontsource/…`. Verified 2026-11-17.

## Desired End State

- `app.css :root` holds the §10.2 palette in OKLCH (table in Phase 1), plus `--sidebar`, `--sidebar-foreground`, `--sidebar-active`, `--sidebar-active-foreground`, `--highlight`, `--highlight-foreground` and `--overlay`. Each is mapped in `@theme inline`, so `bg-highlight`, `text-sidebar-foreground`, `bg-overlay` and so on exist.
- There is no `.dark` block. `@custom-variant dark` stays, because `ui/checkbox` still has a `dark:` class.
- Radii are explicit:

  | Class | Radius | Used for |
  | --- | --- | --- |
  | `rounded-sm` | 8 px | menu items |
  | `rounded-md` | 12 px | fields, week cells |
  | `rounded-lg` | 20 px | tiles, cards, hero |
  | `rounded-xl` | 24 px | dialogs |

  `rounded-full` covers the pills, and `--radius` is gone.
- `shadow-sticker` is `0 3px 0 0 var(--foreground)` and `shadow-dialog` is `0 6px 0 0 var(--foreground)`.
- There are seven type-role utilities: `text-display`, `text-page-title`, `text-stat`, `text-section-title`, `text-body`, `text-label` and `text-meta`. Each carries the size, line height, letter spacing and weight from §10.3. `body` defaults to `text-body` (15/22, 400).
- `font-sans` is Bricolage Grotesque, loaded from Bunny Fonts in weights 400–800. The visual specs assert that it loaded.
- `cn()` (`resources/js/lib/utils.js`) knows the seven `text-*` roles as font sizes and `shadow-sticker`/`shadow-dialog` as shadows. `cn('text-meta', 'text-muted-foreground')` keeps both classes, and `cn('text-sm', 'text-meta')` keeps only `text-meta`.
- The PDF mirror lists the same 12 tokens with Spiżarnia OKLCH and hexes. Its rules use the new hexes, so the category cells are solid honey, tomato and indigo blocks. `MealPlanPdfPaletteTest` passes unchanged.
- The kitchen sink shows the new swatches, the seven type roles, the four radii plus `full`, and the sticker and dialog shadows.
- All 52 baselines are regenerated and reviewed.
- `design-system.md` records:
  - D-14 as confirmed;
  - the token-to-class map;
  - the §6/§7.3 facts that changed (mirror source, font check).

**Verification.** `MealPlanPdfPaletteTest`, the Pest suite, the build, `lint:tokens`, E2E and the visual gate all pass. A human reviews every changed PNG and one generated PDF.

### Key Discoveries:

- All 21 Spiżarnia hexes round-trip through the test's converter (`MealPlanPdfPaletteTest.php:60-86`) with three-decimal OKLCH. Every value in the Phase 1 table was computed and checked.
- The test compares OKLCH strings literally after collapsing whitespace (`MealPlanPdfPaletteTest.php:42-45, 103-106`). The value written in `app.css` and in the mirror comment must be the same text, `oklch(0.9 0.026 76.8)` included (no trailing zero added).
- `--warning` and `--accent` share the hex `#ffe7a3`. Only `--warning` is mirrored, so no duplicate entry appears.
- `ui/card/Card.vue:12` uses `rounded-xl`. After the remap, cards round at 24 px instead of the 20 px tile radius until F-06 turns Card into a tile.
- `cn()` is plain `twMerge` (tailwind-merge 3.7.0). Without an extended config it reads the new type roles as text colours and `shadow-sticker` as a shadow colour. Checked against the installed package:
  - `cn('text-meta', 'text-muted-foreground')` returns `text-muted-foreground`, so the size is dropped;
  - `cn('text-sm', 'text-meta')` keeps both, so a conflict survives;
  - `cn('shadow-sticker', 'shadow-destructive')` drops the sticker.

  78 `ui/` files go through `cn()`, so F-06 would hit this first. With `extendTailwindMerge` (theme keys `text` and `shadow`), all three cases resolve correctly (verified 2026-11-17).
- **Expected radius side-effects** of the remap (not regressions; they are fixed in F-06/S-2x):
  - `ui/skeleton` bars (`rounded-md` on `h-4`) become pills.
  - `ui/alert` goes to 20 px (13 uses in views).
  - Button `xs` is 8 px on 28 px; `sm`/`lg` are 12 px.
  - Focus rings on inline links go to 8–12 px (`MealPlanGrid.vue:37,60`, `MealPlanRow.vue:7`, `RecipeRow.vue:6`, `Recipes/Show.vue:9`, `Recipes/Form.vue:9`, `Login.vue:80`, `Register.vue:91`, `GuestLayout.vue:16`).
  - The week-grid wrapper (`MealPlanGrid.vue:2`) goes to 20 px.
  - Checkbox (`rounded-[4px]`) and badges (`rounded-full`) do not change.

## What We're NOT Doing

- **No `ui/` changes.** No pill buttons, 2px outlines, `draft` dashed badge, `bg-black/80` to `bg-overlay`, Dialog moving to `rounded-xl` or Card moving to `rounded-lg`. All of that is F-06. The 24 px Card radius between F-05 and F-06 is accepted.
- **No view migration.** No views move to `text-body`, `text-page-title`, `shadow-sticker` or the meal blocks; `PageHeader` keeps `text-2xl`. That is S-22…S-26. Explicit `text-sm`/`text-xs` stays 14/12 px.
- **No dark palette.** D-14 is settled as removal, and a dark Spiżarnia is a separate later change. `@custom-variant dark` stays.
- **No destructive sticker variant.** The account-deletion zone (S-26) can combine `shadow-sticker` with a shadow-colour utility, or F-06 can add one.
- **No change to the PDF font or layout.** It stays DejaVu Sans (§6, §10.3); only the hexes change.
- **No new npm dependency.** No self-hosted font and no variable `opsz` axis.
- **No change to the `MealPlanPdfPaletteTest` logic.** It already guards the new values.
- **No edit to `.cursor/rules/test-plan.mdc`.** It is a historical snapshot (it still shows a Tailwind 3 `fontFamily`/`borderRadius` config), so its mentions of Figtree and `--radius` stay as they are.

## Implementation Approach

Change the tokens first, in one phase with the PDF mirror. Editing `app.css :root` without the mirror breaks `MealPlanPdfPaletteTest`, so both land together and the phase ends green. The font switch goes in the same phase, including the visual specs' font assertion, because the font and its check have to change together.

The kitchen sink comes next, because it needs the new utilities. Baselines are refreshed last, once, after every visual change is in. `design-system.md` is updated in that same final phase, so the docs describe what shipped.

## Critical Implementation Details

- **OKLCH strings are a contract.** Copy them exactly from the Phase 1 table into both `app.css` and the PDF mirror comment. Reformatting `0.9` as `0.900`, or `274.0` as `274`, in only one of the two files fails `keeps every mirrored token equal to app.css :root`.
- **Font check before baselines.** `expectAppFontLoaded` must pass inside the Playwright Docker image before `--update-snapshots`. Otherwise the baselines can capture a fallback font. Bunny now serves the files from `cdn.jsdelivr.net`, so a failing check most likely means that host is unreachable from the container.

## Phase 1: Tokens, font and PDF mirror

### Overview

Put the Spiżarnia values into the token layer and the PDF mirror. Switch the app font to Bricolage Grotesque and its visual-gate assertion with it. The repaint takes effect across the app here; baselines are refreshed in Phase 3.

### Changes Required:

#### 1. Palette and new tokens

**File**: `resources/css/app.css`

**Intent**: Replace the `:root` values with the Spiżarnia palette in OKLCH, add the new tokens and map them for Tailwind, and delete the `.dark` block (D-14).

**Contract**: `:root` values (each reproduces the §10.2 hex through the palette test's converter):

| Token(s) | Value |
| --- | --- |
| `--background` | `oklch(0.982 0.014 74.4)` (`#fff8ef`) |
| `--foreground`, `--card-foreground`, `--popover-foreground`, `--secondary-foreground`, `--accent-foreground`, `--primary` | `oklch(0.234 0.036 288.1)` (`#1d1b2e`) |
| `--card`, `--popover`, `--destructive-foreground`, `--highlight-foreground` | `oklch(1 0 0)` |
| `--primary-foreground` | `oklch(0.982 0.014 74.4)` |
| `--muted`, `--secondary` | `oklch(0.952 0.017 76.1)` (`#f6eee3`) |
| `--muted-foreground` | `oklch(0.48 0.032 296.6)` (`#5f5a6e`) |
| `--accent`, `--warning` | `oklch(0.932 0.09 90.9)` (`#ffe7a3`) |
| `--border`, `--input` | `oklch(0.9 0.026 76.8)` (`#e8dccb`) |
| `--ring`, `--highlight` | `oklch(0.532 0.186 274.0)` (`#4f5bd5`) |
| `--destructive` | `oklch(0.551 0.186 32.6)` (`#c7361b`) |
| `--success` / `--success-foreground` | `oklch(0.92 0.056 153.0)` / `oklch(0.349 0.072 156.1)` (`#c9f0d3` / `#12452a`) |
| `--warning-foreground` | `oklch(0.344 0.071 84.0)` (`#4a3500`) |
| `--info` / `--info-foreground` | `oklch(0.919 0.04 280.9)` / `oklch(0.335 0.134 272.9)` (`#dfe2ff` / `#232a7a`) |
| `--meal-breakfast` / `-foreground` | `oklch(0.885 0.129 86.1)` / `oklch(0.297 0.061 84.8)` (`#ffd36e` / `#3b2a00`) |
| `--meal-supper` / `-foreground` | `oklch(0.755 0.151 38.3)` / `oklch(0.245 0.067 37.6)` (`#ff8a65` / `#3a1206`) |
| `--meal-dinner` / `-foreground` | `oklch(0.697 0.136 279.0)` / `oklch(0.225 0.073 276.5)` (`#8c93f0` / `#14163d`) |
| `--sidebar`, `--sidebar-foreground`, `--sidebar-active`, `--sidebar-active-foreground` | `var(--background)`, `var(--muted-foreground)`, `var(--primary)`, `var(--primary-foreground)` |
| `--overlay` | `oklch(0.234 0.036 288.1 / 0.5)` (= `rgba(29, 27, 46, 0.5)`) |

`@theme inline` gains `--color-sidebar`, `--color-sidebar-foreground`, `--color-sidebar-active`, `--color-sidebar-active-foreground`, `--color-highlight`, `--color-highlight-foreground` and `--color-overlay`. The `.dark { … }` block is deleted; `@custom-variant dark` is kept.

#### 2. Radii, shadows, type roles, font, body default

**File**: `resources/css/app.css`

**Intent**: Write the §10.2 shapes and the §10.3 type scale as Tailwind theme tokens, so later changes can use them without arbitrary values. Make Bricolage Grotesque the sans font and 15/22 the default text.

**Contract**:
- **Radii.** Explicit `--radius-sm: 0.5rem`, `--radius-md: 0.75rem`, `--radius-lg: 1.25rem` and `--radius-xl: 1.5rem`, with no `calc()`. `--radius` is removed.
- **Shadows.** `--shadow-sticker: 0 3px 0 0 var(--foreground)` and `--shadow-dialog: 0 6px 0 0 var(--foreground)`. They reference a variable, so they belong in `@theme inline`.
- **Type roles.** Use Tailwind 4's `--text-<name>`, `--text-<name>--line-height`, `--text-<name>--letter-spacing` and `--text-<name>--font-weight`:

  | Utility | Size / line height | Letter spacing | Weight |
  | --- | --- | --- | --- |
  | `display` | 3.75rem / 4.25rem | −0.035em | 800 |
  | `page-title` | 2.25rem / 2.5rem | −0.03em | 800 |
  | `stat` | 3.5rem / 3.5rem | −0.04em | 800 |
  | `section-title` | 1.25rem / 1.5rem | — | 700 |
  | `body` | 0.9375rem / 1.375rem | — | 400 |
  | `label` | 0.875rem / 1.25rem | — | 600 |
  | `meta` | 0.8125rem / 1.125rem | — | 500 |

  `text-stat` does not include `tabular-nums`; callers add it, as §10.3 says.
- **Font.** `--font-sans: 'Bricolage Grotesque', ui-sans-serif, system-ui, sans-serif, <the existing emoji fallbacks>`.
- **Body default.** The `body` base rule applies `bg-background text-foreground text-body`.

#### 3. Font loading

**File**: `resources/views/app.blade.php`

**Intent**: Load Bricolage Grotesque instead of Figtree from the same CDN.

**Contract**: The stylesheet link becomes `https://fonts.bunny.net/css?family=bricolage-grotesque:400,500,600,700,800&display=swap`. The preconnect to `fonts.bunny.net` stays.

#### 4. Visual gate font assertion

**Files**: `tests/e2e/visual/support/fonts.ts`, `tests/e2e/visual/support/auth.ts`, and the 5 specs that import the helper (`design-system`, `app-shell`, `account`, `meal-plans-index`, `meal-plan-show`).

**Intent**: Assert that the new font actually loaded, so baselines never capture a fallback.

**Contract**: `expectFigtreeLoaded` is renamed to `expectAppFontLoaded`, and every call site and import is updated. The helper loads `"Bricolage Grotesque"` with the family name quoted in the `document.fonts.load` shorthand, at weights 400, 500, 600, 700 and 800. It asserts that a face with family `Bricolage Grotesque` and status `loaded` came back for each weight. The failure message names `fonts.bunny.net`/`cdn.jsdelivr.net`.

#### 5. PDF palette mirror

**File**: `resources/views/pdf/meal-plan.blade.php`

**Intent**: Give the PDF the Spiżarnia colours (solid category blocks, D-12) while keeping the same 12 mirrored tokens.

**Contract**:
- The mirror comment lists `--foreground`, `--muted`, `--muted-foreground`, `--border`, `--warning`, `--warning-foreground` and `--meal-breakfast/supper/dinner` with their `-foreground`. Each line has the OKLCH from the table above, character-for-character, and its hex.
- The comment's source reference changes from "design-system.md 4.2-4.4" to "design-system.md 10.2".
- Every colour in the rules on lines 24–42 is replaced with the new hex of the same token:

  | Rule | Token(s) | New hex |
  | --- | --- | --- |
  | `body` | `--foreground` | `#1d1b2e` |
  | borders | `--border` | `#e8dccb` |
  | `th` background | `--muted` | `#f6eee3` |
  | day-start line, `.meta`, `.note` | `--muted-foreground` | `#5f5a6e` |
  | `td.meal-breakfast` | `--meal-breakfast` pair | `#ffd36e` / `#3b2a00` |
  | `td.meal-supper` | `--meal-supper` pair | `#ff8a65` / `#3a1206` |
  | `td.meal-dinner` | `--meal-dinner` pair | `#8c93f0` / `#14163d` |
  | `.notices` | `--warning` pair | `#ffe7a3` / `#4a3500` |

  No new rule or token is added.

#### 6. Class merging in `cn()`

**File**: `resources/js/lib/utils.js`

**Intent**: Teach tailwind-merge the new theme names, so `cn()` in `ui/` and the views does not drop the type roles or the sticker and dialog shadows, and does resolve real conflicts with them.

**Contract**: `twMerge` becomes a module-level `extendTailwindMerge(...)` instance, and `cn()` keeps its signature:

```js
const twMerge = extendTailwindMerge({
  extend: {
    theme: {
      text: ['display', 'page-title', 'stat', 'section-title', 'body', 'label', 'meta'],
      shadow: ['sticker', 'dialog'],
    },
  },
})
```

The lists must match the `--text-*` and `--shadow-*` names in `app.css`. A new role or shadow gets added in both places (recorded in `design-system.md` §10.2–10.3 in Phase 3). This is not a `ui/` change: `lib/utils.js` is shared infrastructure.

### Success Criteria:

#### Automated Verification:

- PDF palette test passes against the new `app.css`: `vendor/bin/sail pest tests/Unit/Services/MealPlanPdfPaletteTest.php`
- Full Pest suite passes: `vendor/bin/sail composer test`
- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token lint and its self-test pass: `vendor/bin/sail npm run lint:tokens` and `bash scripts/lint-tokens.test.sh`
- No `Figtree`/`figtree` reference remains in `resources/` or `tests/`: `grep -rni figtree resources tests` returns nothing
- `app.css` has no `.dark {` block and no `--radius:` declaration
- `cn()` merges the new utilities correctly: `vendor/bin/sail node --input-type=module -e "import { cn } from './resources/js/lib/utils.js'; const ok = cn('text-meta','text-muted-foreground') === 'text-meta text-muted-foreground' && cn('text-sm','text-meta') === 'text-meta' && cn('shadow-sm','shadow-sticker') === 'shadow-sticker'; if (!ok) process.exit(1)"`

#### Manual Verification:

- After `vendor/bin/sail npm run build`, the logged-in dashboard renders in Bricolage Grotesque (DevTools › Fonts), ink-coloured primary buttons and the cream background, with Polish characters (ą, ę, ł, ż) in the same face
- A generated "Z przepisami" PDF of a `done` plan shows solid honey/tomato/indigo category cells with dark text, a cream warning box when notices exist, and stays readable in grayscale print preview

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Kitchen sink

### Overview

Make the kitchen sink the reference for the new tokens, so F-06 and the view changes can see every colour, type role, radius and shadow in one place.

### Changes Required:

#### 1. Colour swatches

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: Show the new tokens beside the existing ones.

**Contract**: `colorSwatches` gains five entries:
- `sidebar`: `bg-sidebar text-sidebar-foreground`
- `sidebar-active`: `bg-sidebar-active text-sidebar-active-foreground`
- `highlight`: `bg-highlight text-highlight-foreground`
- `overlay`: `bg-overlay text-card`
- `sidebar-foreground`: `bg-sidebar-foreground text-background`

The existing 18 entries stay, in their current order, with the new ones appended.

#### 2. Typography roles

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: Replace the five Zielnik roles with the seven §10.3 roles, using the new utilities.

**Contract**:

| Role | Classes | Sample |
| --- | --- | --- |
| Display | `text-display` | "Cały tydzień na jednej kartce" |
| Tytuł strony | `text-page-title` | "Pulpit" |
| Liczba w kafelku | `text-stat tabular-nums` | "21" |
| Tytuł sekcji | `text-section-title` | "Ten tydzień" |
| Tekst | `text-body` | "Dodaj przepisy, a Menu Maker ułoży tydzień." |
| Etykieta | `text-label` | "Dodaj przepis" |
| Metadane | `text-meta text-muted-foreground` | "3 porcje · 12 lis 2026" |

The role names are in Polish, matching the existing labels. The `tabular-nums` amounts list stays.

#### 3. Shape and shadows

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: Show the explicit radii and the sticker and dialog shadows in place of the Zielnik "card without shadow / `shadow-sm` / `shadow-lg`" demo.

**Contract**:
- `shapes` lists the following, each labelled with its class and pixel value:
  - `rounded-sm` (8 px)
  - `rounded-md` (12 px)
  - `rounded-lg` (20 px)
  - `rounded-xl` (24 px)
  - `rounded-full`
- The demo row shows three boxes:
  - a tile: `rounded-lg border-2 border-foreground bg-card shadow-sticker`, labelled "Kafel: kontur 2px, shadow-sticker";
  - a dialog surface: `rounded-xl border-2 border-foreground bg-card shadow-dialog`, labelled "Dialog: shadow-dialog";
  - the existing `shadow-sm` box (menus and popovers keep it).
- No arbitrary values.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token lint passes: `vendor/bin/sail npm run lint:tokens`

#### Manual Verification:

- `/dev/design-system` shows all 23 swatches with readable labels, the seven type roles at visibly distinct sizes and weights, the five radii and the sticker and dialog shadows as hard ink offsets

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Baselines and documentation

### Overview

Regenerate every visual baseline once, now that tokens, font and kitchen sink are final. Record what shipped in `design-system.md`.

### Changes Required:

#### 1. Visual baselines

**Files**: `tests/e2e/__screenshots__/visual/**/*.png` (all 6 specs)

**Intent**: Accept the deliberate repaint as the new reference.

**Contract**:
1. Build with `vendor/bin/sail npm run build`, with `npm run dev` stopped and no `public/hot`.
2. Seed with `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`.
3. From the host, run `npm run test:visual -- --update-snapshots`.
4. Run `npm run test:visual` again without the flag; it must pass.

Every changed PNG is reviewed in the diff. No spec's screenshot list changes.

#### 2. Design system document

**File**: `context/foundation/design-system.md`

**Intent**: Keep the source of truth in sync with what F-05 shipped.

**Contract**:
- **Frontmatter:** `updated` set to the date of the change.
- **§9 D-14:** "Potwierdzone <date>: blok `.dark` usunięty w SP-01; ciemna Spiżarnia to osobna zmiana po M-4 (D-1 obowiązuje)".
- **§4.8:** one sentence saying the `.dark` values below are historical (Zielnik) and were removed in SP-01.
- **§6:** the mirror hexes now come from §10.2.
- **§7.3:** the spec checks that Bricolage Grotesque loaded, at weights 400–800. The shot counts are corrected to the real ones: `design-system` 29, `app-shell` 5, `meal-plan-show` 5. Today they read 28, 4 and 2.
- **§10.2:** a short "W Tailwindzie" list covering:
  - the radius classes (`rounded-sm/md/lg/xl/full` = 8/12/20/24/full; 8 px `sm` is this change's addition to §10.2);
  - `shadow-sticker` and `shadow-dialog`;
  - the colour utilities for the new tokens;
  - the note that `ui/card` stays at `rounded-xl` (24 px) until F-06;
  - the rule that every new `--shadow-*` is also added to `extendTailwindMerge` in `resources/js/lib/utils.js`.
- **§10.3:** the seven `text-*` utility names, the `body` default, and the rule that a new role is also added to `extendTailwindMerge` in `resources/js/lib/utils.js`.
- **Header note at the top:** SP-01 is done, so §4.2–4.6 now describe the previous state.

### Success Criteria:

#### Automated Verification:

- Visual gate passes on the refreshed baselines: `npm run test:visual`
- E2E suite passes against Sail after re-seeding `E2eMealPlanSeeder`: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- Full Pest suite still passes: `vendor/bin/sail composer test`
- Token lint passes: `vendor/bin/sail npm run lint:tokens`

#### Manual Verification:

- Each of the 52 changed baseline PNGs was reviewed in the diff: Bricolage Grotesque in every shot, ink primary, cream background, new category colours, and no fallback font or missing glyphs
- `design-system.md` §9 D-14, §4.8, §6, §7.3, §10.2 and §10.3 match what `app.css`, the PDF template and the visual specs now contain

The radius side-effects listed under Key Discoveries (pill skeletons, 20 px alerts, rounder focus rings, 20 px week-grid wrapper) are expected in the PNGs and are not regressions.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- `MealPlanPdfPaletteTest` (unchanged) is the regression guard for the token-to-PDF contract. It fails if `app.css` and the mirror disagree, if a hex is not the clipped OKLCH, or if a category rule is missing. No new PHP test is needed, because no PHP behaviour changes.

### Integration Tests:

- The E2E suite selects by role and text, not class (design-system §8). It should pass unchanged and confirms the repaint broke no flow.
- The visual gate, with the renamed font assertion, covers the repaint itself.

### Manual Testing Steps:

1. Open the dashboard, recipes and a meal plan. Check the font (DevTools › Fonts › Bricolage Grotesque), the ink primary and the cream background.
2. Download "Z przepisami" for a `done` plan. Check the solid category cells in colour and in grayscale print preview.
3. Open `/dev/design-system` and review the Kolory, Typografia and Kształt i cienie sections.
4. Review every changed PNG in the Phase 3 diff.

## Performance Considerations

The font request grows from 3 weights to 5 (latin plus latin-ext subsets, loaded per `unicode-range`). That's acceptable for a home tool, and `display=swap` keeps text visible while it loads.

## Migration Notes

No data or schema changes. Rollback means reverting the commits; the baselines revert with them.

## References

- Roadmap item: `context/foundation/roadmap.md` F-05
- Spec: `context/foundation/design-system.md` §10.2–10.3, §6, §7.3, §9 (D-10, D-12, D-14)
- Artifact (screens, `project/tokens.json`): https://claude.ai/artifact/Py7fzUajDk1Ny57FG7oZMx (D-11: `design-system.md` wins)
- Palette guard: `tests/Unit/Services/MealPlanPdfPaletteTest.php:60-139`
- Token layer: `resources/css/app.css:20-163`
- Font check: `tests/e2e/visual/support/fonts.ts:5-25`
- Kitchen sink data: `resources/js/Pages/Dev/DesignSystem.vue:104-144`, sections at lines 310–359

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Tokens, font and PDF mirror

#### Automated

- [x] 1.1 PDF palette test passes against the new `app.css`: `vendor/bin/sail pest tests/Unit/Services/MealPlanPdfPaletteTest.php` — 42e3165
- [x] 1.2 Full Pest suite passes: `vendor/bin/sail composer test` — 42e3165
- [x] 1.3 Client and SSR bundles build: `vendor/bin/sail npm run build` — 42e3165
- [x] 1.4 Token lint and its self-test pass: `vendor/bin/sail npm run lint:tokens` and `bash scripts/lint-tokens.test.sh` — 42e3165
- [x] 1.5 No `Figtree`/`figtree` reference remains in `resources/` or `tests/`: `grep -rni figtree resources tests` returns nothing — 42e3165
- [x] 1.6 `app.css` has no `.dark {` block and no `--radius:` declaration — 42e3165
- [x] 1.9 `cn()` merges the new utilities correctly: `vendor/bin/sail node --input-type=module -e "import { cn } from './resources/js/lib/utils.js'; const ok = cn('text-meta','text-muted-foreground') === 'text-meta text-muted-foreground' && cn('text-sm','text-meta') === 'text-meta' && cn('shadow-sm','shadow-sticker') === 'shadow-sticker'; if (!ok) process.exit(1)"` — 42e3165

#### Manual

- [ ] 1.7 After `vendor/bin/sail npm run build`, the logged-in dashboard renders in Bricolage Grotesque (DevTools › Fonts), ink-coloured primary buttons and the cream background, with Polish characters (ą, ę, ł, ż) in the same face
- [ ] 1.8 A generated "Z przepisami" PDF of a `done` plan shows solid honey/tomato/indigo category cells with dark text, a cream warning box when notices exist, and stays readable in grayscale print preview

### Phase 2: Kitchen sink

#### Automated

- [x] 2.1 Client and SSR bundles build: `vendor/bin/sail npm run build` — 3692281
- [x] 2.2 Token lint passes: `vendor/bin/sail npm run lint:tokens` — 3692281

#### Manual

- [ ] 2.3 `/dev/design-system` shows all 23 swatches with readable labels, the seven type roles at visibly distinct sizes and weights, the five radii and the sticker and dialog shadows as hard ink offsets

### Phase 3: Baselines and documentation

#### Automated

- [x] 3.1 Visual gate passes on the refreshed baselines: `npm run test:visual` — 4daea42
- [ ] 3.2 E2E suite passes against Sail after re-seeding `E2eMealPlanSeeder`: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e` — NOT GREEN: 35/36 pass; firefox meal-plan.spec.ts:3 fails identically on pre-change tokens (pre-existing)
- [x] 3.3 Full Pest suite still passes: `vendor/bin/sail composer test` — 4daea42
- [x] 3.4 Token lint passes: `vendor/bin/sail npm run lint:tokens` — 4daea42

#### Manual

- [ ] 3.5 Each of the 52 changed baseline PNGs was reviewed in the diff: Bricolage Grotesque in every shot, ink primary, cream background, new category colours, and no fallback font or missing glyphs
- [ ] 3.6 `design-system.md` §9 D-14, §4.8, §6, §7.3, §10.2 and §10.3 match what `app.css`, the PDF template and the visual specs now contain
