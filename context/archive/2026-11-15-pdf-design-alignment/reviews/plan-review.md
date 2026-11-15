<!-- PLAN-REVIEW-REPORT -->
# Plan Review: PDF Design Alignment Implementation Plan

- **Plan**: context/changes/pdf-design-alignment/plan.md
- **Mode**: Deep
- **Date**: 2026-11-15
- **Verdict**: SOUND
- **Findings**: 0 critical, 1 warning, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding

10/10 paths ✓. 7/7 symbols ✓:
- `PdfMeal` has a single call site
- the `:root` tokens match
- the Alert border is `--border` via `* { @apply border-border }`
- badge variants, `pdfText` (Pest.php:88), `downloadPdf` (:193) and the Odznaki section exist

16/16 hexes recomputed ✓ (warning pair 6.74:1), brief↔plan ✓, Progress↔Phase ✓.

## Findings

### F1 — Guard does not cover all colour forms the plan bans

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: End-State Alignment
- **Location**: Phase 1 §2 (last bullet), Phase 2 §1, criterion 1.2
- **Detail**: The contract bans every hex, named colour and `rgb()`. The guard only checks hexes inside `<style>`, and the 1.2 grep only catches the six old literals. `color: white`, `rgb(...)` or an inline `style="color:#000"` would pass. The template already uses inline style attributes (lines 37–41, 69, 79).
- **Fix**: The guard scans the whole template (`<style>` and `style=""`). It fails on any unlisted hex, on `rgb/rgba/hsl/hsla/oklch(`, and on the keywords `white|black|gray|grey`. The quantity is right-aligned through a class.
- **Decision**: FIXED (plan: Desired End State, Phase 1 §2, Phase 2 §1, criterion 2.2, Testing Strategy; brief synced)

### F2 — The hex, the value actually printed, is not guarded

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: What We're NOT Doing ("No OKLCH→hex conversion in PHP"); brief, Open Risks #2
- **Detail**: The guard compares only the OKLCH. After a palette change, the test turns green once the comment's OKLCH is updated, even if the old hex stays. The old hex is what dompdf prints. A plain clamp conversion (OKLab → linear sRGB → clamp → gamma → round) reproduces all 16 hexes of §4.2–4.4 exactly.
- **Fix**: Add an `oklchToClippedHex()` helper in the test (not in `app/`), and assert each mirror entry's hex equals the computed one.
  - Strength: Closes accepted risk #2; the guard covers what is printed.
  - Tradeoff: Colour math in a test; it reverses one "not doing" item.
  - Confidence: HIGH — 16/16 spec hexes reproduced during review.
  - Blind spot: A future token near a rounding boundary may differ by 1 from a hand-computed spec hex.
- **Decision**: FIXED (plan: What We're NOT Doing, Critical Implementation Details "Clipped-hex check", Phase 2 §1, criterion 2.2; brief synced)

### F3 — Grayscale print: notices frame fades and day rule gets lighter

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §2 (`.notices`, day rule), manual 1.7
- **Detail**: The `--border` frame on the `--warning` fill is 1.19:1, and `#fff1c4` is nearly white in grayscale. The box may disappear, whereas today's `#c9a227` frame is visible. The day rule `#6c6158` is lighter than today's `#444`, although the plan called it "darker".
- **Fix**: Add the notices box to 1.7, with a fallback frame in `--warning-foreground`. Correct the "darker" wording.
- **Decision**: FIXED (plan: Desired End State, Phase 1 §2, 1.7 in phase and Progress; brief synced)

### F4 — "(usunięty)" marker needs new markup that the plan does not specify

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §2, type-scale table (Metadata row)
- **Detail**: The marker is bare text in the name cell (line 54). Styling it needs a wrapping element, and the leading space must survive. The existing test checks only `(usunięty)`.
- **Fix**: Specify `@if ($meal->recipeDeleted) <span class="meta">(usunięty)</span>@endif`, with `.meta` and `.note` sharing the 8pt `--muted-foreground` rule.
- **Decision**: FIXED (plan: Phase 1 §2 and type-scale table)
