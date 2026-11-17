<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Spiżarnia Tokens Implementation Plan

- **Plan**: context/changes/spizarnia-tokens/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-17
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | FAIL (non-critical: the one failing E2E test is pre-existing and unrelated to tokens) |

## Success criteria re-run (2026-11-17, worktree Sail stack)

| Row | Command | Result |
|-----|---------|--------|
| 1.1 | `sail pest tests/Unit/Services/MealPlanPdfPaletteTest.php` | PASS: 6 tests, 70 assertions |
| 1.2 / 3.3 | `sail composer test` | PASS: 559 tests, 2283 assertions |
| 1.3 / 2.1 | `sail npm run build` | PASS: client and SSR |
| 1.4 / 2.2 / 3.4 | `sail npm run lint:tokens` + `bash scripts/lint-tokens.test.sh` | PASS: 74 files, 0 hits; self-test PASS |
| 1.5 | `grep -rni figtree resources tests` | PASS: no hits |
| 1.6 | no `.dark {` and no `--radius:` in app.css | PASS |
| 1.9 | `cn()` merge check | PASS (`cn('shadow-sticker','shadow-destructive')` also keeps both) |
| 3.1 | `npm run test:visual` (`VISUAL_DOCKER_NETWORK=spizarnia-tokens_sail`) | PASS: 52 of 52 |
| 3.2 | `npm run test:e2e` against `localhost:8091` after re-seeding | FAIL: 35 of 36 pass; `[firefox] meal-plan.spec.ts:3` fails (see F3) |
| Manual 1.7, 1.8, 2.3, 3.5, 3.6 | — | Pending: none is ticked, so there are no unsupported ticks |

## Findings

### F1 — Week-grid category bars all render the same ink colour

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — a real trade-off; think it through before deciding
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/MealPlans/MealPlanGrid.vue:149-153 (also context/foundation/design-system.md:219)
- **Detail**: The grid's category bars use `border-l-4 border-meal-{breakfast,supper,dinner}-foreground`. The Spiżarnia foregrounds are near-black (`#3b2a00`, `#3a1206`, `#14163d`), where the Zielnik ones were distinct mid-tones (`#784900`, `#99351b`, `#4a4b96`). On the meal plan page and the draft page, all three bars now read as one ink line. The new baselines confirm this (`meal-plan-show-desktop.png`). The plan's list of expected side-effects does not mention it. The rationale at design-system.md:219 ("the pale `meal-*` fill is invisible as a 4px line") is now reversed, because the Spiżarnia `meal-*` fills are saturated.
- **Fix A ⭐ Recommended**: Change the map to `border-meal-*` (fill colours), fix the rationale at design-system.md:219, and refresh the meal-plan-show and draft baselines.
  - Strength: The fix is a 3-line map change. It brings back the category cue that the token swap removed, and baselines are already being reviewed in this change.
  - Tradeoff: It touches a view, which crosses the "No view migration" line. About 5 PNGs need another review.
  - Confidence: HIGH — honey, tomato and indigo are clearly distinct at 4px on white, and the classes already exist in the theme.
  - Blind spot: S-24 may replace the bars with solid meal blocks anyway, so this could be short-lived work.
- **Fix B**: Accept this as an interim regression. Record it in the plan's Key Discoveries and in the S-24 brief, and fix design-system.md:219.
  - Strength: Keeps F-05 strictly to tokens.
  - Tradeoff: If F-05 merges before S-24, develop/main ships a grid with no colour coding. The column headers still name the categories.
  - Confidence: MED — this depends on how long the gap between F-05 and S-24 lasts.
  - Blind spot: On mobile, the column headers are not visible next to each cell.
- **Decision**: FIXED via Fix A. `CATEGORY_BAR_CLASSES` now uses `border-meal-*`. design-system.md §5.3 is updated, the plan's Key Discoveries addendum records the exception to "No view migration", and 6 baselines are refreshed (section-jadlospis plus 5 meal-plan-show shots).

### F2 — The font-load check skips the Polish subset and does not verify weights

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — a quick decision; the fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/e2e/visual/support/fonts.ts:10-21
- **Detail**: `document.fonts.load('<w> 16px "Bricolage Grotesque"')` has no text argument, so it defaults to U+0020. Only the latin face's `unicode-range` covers that character, so the latin-ext face (ą ę ł ś ż ź ć ń) is never loaded or checked. A fallback-glyph baseline could pass the gate, which is exactly the risk the plan's "font check before baselines" rule is meant to block. Separately, font matching returns the nearest face, so a missing 800 cut would pass via the 700 face. Today's baselines do show Polish glyphs in Bricolage, so the gap is in the gate, not in the current output.
- **Fix**: Call `document.fonts.load(font, 'Aąęłśżź')`. Require every returned face to be `loaded`, require a face whose `unicodeRange` covers U+0142, and assert `face.weight === String(weight)`.
- **Decision**: FIXED. `fonts.ts` loads `'Aąęłśżź'` and requires, for each weight, a Bricolage face at that exact weight, every such face `loaded`, and one covering U+0142. It passes in all 52 visual tests. §7.3 is updated.

### F3 — E2E criterion 3.2 is red (pre-existing Firefox race)

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — a quick decision; the fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/e2e/meal-plan.spec.ts:3
- **Detail**: Re-run here: 35 of 36 pass. The Firefox run of the meal-plan flow times out on `getByText('Generujemy jadłospis…')`, a transient "generating" state that the queue with the fake driver can finish before the 5 s assertion. The locator is text-based and Chromium passes, so the token change cannot cause it. The implementer recorded that it fails identically on the pre-change tokens. This review did not reproduce it on the base commit.
- **Fix**: Leave 3.2 unticked with its note and open a follow-up change to make the spec tolerate a fast transition (assert generating OR done). Do not fix it inside F-05.
- **Decision**: FIXED (as recommended): row 3.2 stays unticked, and the follow-up is queued in `follow-ups/review-fixes.md`.

### F4 — Factual slips in design-system.md

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — a quick decision; the fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/design-system.md:258, :219
- **Detail**: Manual row 3.6 ("docs match the code") would currently fail on three points:
  - (a) In §7.3, the sentence about the empty dashboard (`e2e-dashboard-empty-visual@example.com`) sits in the description of `account.spec.ts`. That shot is taken in `app-shell.spec.ts:32`.
  - (b) §7.3 justifies the outline button in the focus-visible shot with "the default button's ring has the same colour as its background". That is no longer true: the ring is indigo and primary is ink.
  - (c) The §5.3 bar rationale at :219 is reversed (see F1).
- **Fix**: Move the empty-dashboard sentence into the app-shell description, reword or drop the focus-visible rationale, and update :219 to match the F1 decision.
- **Decision**: FIXED. All three points are corrected. The same stale comment in `design-system.spec.ts:52` is fixed too.

### F5 — Token side-effects the plan did not anticipate are not recorded

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — a quick decision; the fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/Layouts/GuestLayout.vue:16 (and others below)
- **Detail**: All of these are in the new baselines, but none is listed in the plan's expected side-effects:
  - `--primary` is now identical to `--foreground`, so `hover:text-primary` does nothing. The guest logo link at GuestLayout.vue:16 has no other hover cue. The "Składniki" toggle (MealPlanGrid.vue:60) no longer stands out from body text.
  - `bg-accent` (yellow, the same value as `--warning`) now marks the active sidebar item (AppSidebar.vue:58), the StatCard icon disc, and ghost/outline hover.
  - More radius changes: `overflow-hidden` tables at 20 px (RecipesTable, MealPlans/Index, FamilyMembers/Index, recipe sections), sidebar items at 12 px, and more 8 px focus rings (SortableHeader, MealPlans/Show:86,101).
  - `rounded-xl` (24 px) is now larger than Tailwind's default `rounded-2xl` (16 px). Nothing uses `2xl` today.
- **Fix**: Add these to the plan's Key Discoveries as an addendum and to the S-22…S-26 briefs in roadmap.md. Give GuestLayout a non-colour hover cue in S-22.
- **Decision**: FIXED. The addendum is in the plan's Key Discoveries, and "Carry-over z F-05" bullets are in the roadmap briefs for F-06 and S-22…S-26.

### F6 — No preconnect to the real font host

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — a quick decision; the fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/views/app.blade.php:8
- **Detail**: Bunny's CSS points every `src` at `cdn.jsdelivr.net/fontsource/…`, but the page only preconnects to `fonts.bunny.net`. The font payload grew from 3 to 5 weights (about 10 woff2 files with latin-ext), and the Bunny stylesheet blocks rendering. If a CSP is ever added, `font-src` needs `cdn.jsdelivr.net`.
- **Fix**: Add `<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>` after line 8.
- **Decision**: FIXED.

### F7 — The kitchen-sink overlay swatch label has low contrast

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — a quick decision; the fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Pages/Dev/DesignSystem.vue:126
- **Detail**: `bg-overlay text-card` puts white 12 px text on 50% ink over cream, about 3.4:1. Manual row 2.3 asks for "readable labels". The page is dev-only.
- **Fix**: Use `text-foreground` (about 4.8:1 on the blended mid-tone) and refresh `section-kolory.png`.
- **Decision**: FIXED. `section-kolory.png` is refreshed and reviewed.

### F8 — Change bookkeeping is out of sync

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — a quick decision; the fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/roadmap.md:45, :92
- **Detail**: The worktree has an uncommitted roadmap.md edit that sets F-05 to `in-progress`. This review moves `change.md` to `impl_reviewed`.
- **Fix**: Commit the roadmap edit together with the review (or the fixes from triage).
- **Decision**: FIXED. Committed together with the triage fixes.

## Triage notes (2026-11-17)

- Re-run after the fixes:
  - build OK;
  - `lint:tokens` OK (74 files, 0 hits);
  - Pest 559 passed;
  - visual gate: the 7 intended shots changed (Kolory, Jadłospis, 5 meal-plan-show); after the refresh, 51 of 52, with `shell nav hover` failing.
- `shell nav hover` is a flaky shot that the fixes don't touch: with an unchanged HEAD baseline it failed 2 of 4 runs, including 1 of 2 isolated runs. Its HEAD baseline is kept, and it is queued in `follow-ups/review-fixes.md`.
