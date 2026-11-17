<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Spiżarnia Tokens Implementation Plan

- **Plan**: context/changes/spizarnia-tokens/plan.md
- **Mode**: Deep
- **Date**: 2026-11-17
- **Verdict**: REVISE → SOUND after fixes
- **Findings**: 0 critical, 2 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding

The plan's paths, symbols and counts were checked against the code:
- 10/10 paths ✓
- 5/5 symbols ✓
- 19/19 OKLCH→hex round-trips under the test's converter ✓
- 52/52 baselines ✓
- brief↔plan ✓
- Progress↔Phase ✓

## Findings

### F1 — cn()/tailwind-merge doesn't recognise the new utilities

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architectural Fitness
- **Location**: Phase 1 §2 (type roles, shadows); design-system.md §10.2–10.3 sync
- **Detail**: `resources/js/lib/utils.js` is plain `twMerge` (tailwind-merge 3.7.0). Checked against the installed package:
  - `cn('text-meta','text-muted-foreground')` returns `text-muted-foreground`;
  - `cn('text-sm','text-meta')` keeps both classes;
  - `cn('shadow-sticker','shadow-destructive')` drops the sticker.

  78 `ui/` files use `cn()`, so F-06 would break silently.
- **Fix A ⭐ Recommended**: Register the names in `cn()` via `extendTailwindMerge` (theme `text` and `shadow`) as part of F-05.
  - Strength: The names are created in this change, so the gap closes before any consumer exists. About 10 lines, outside `ui/`.
  - Tradeoff: `lib/utils.js` is shared. There is no JS unit runner, so the check is a node one-liner.
  - Confidence: HIGH — reproduced on 3.7.0; the extended config verified.
  - Blind spot: None significant.
- **Fix B**: Defer to F-06 and record the constraint in design-system.md.
  - Strength: F-05 stays tokens-only.
  - Tradeoff: The trap stays open until F-06.
  - Confidence: MED.
  - Blind spot: None significant.
- **Decision**: FIXED via Fix A. The plan changed in six places:
  - Phase 1 §6 `lib/utils.js`;
  - automated criterion 1.9;
  - a Desired End State bullet;
  - a Key Discoveries note;
  - the Phase 3 §10.2/§10.3 doc rule;
  - the brief's decision table and scope.

### F2 — Stale Figtree and Zielnik facts outside the plan's doc list

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 §2 (design-system.md); criterion 1.5
- **Detail**:
  - §7.3 shot counts read 28/4/2; the real counts are 29/5/5.
  - `.cursor/rules/test-plan.mdc:598,645-647` mentions Figtree and `--radius`.
  - §4.5 is already covered by the header note ("§4.2–4.6 describe the previous state").
- **Fix**: Correct the §7.3 counts in Phase 3 and state explicitly that `.cursor/rules/test-plan.mdc` (a historical Tailwind 3 snapshot) is not edited.
- **Decision**: FIXED. The plan gained the §7.3 counts in Phase 3 §2 and a "What We're NOT Doing" entry.

### F3 — Radius remap side-effects not listed for the PNG review

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Open Risks / Phase 3 manual 3.5
- **Detail**: Only Card is flagged. These also change:
  - skeleton bars become pills;
  - alerts go to 20 px;
  - button xs goes to 8 px;
  - focus rings on inline links go to 8–12 px;
  - the week-grid wrapper goes to 20 px.
- **Fix**: List them as expected in Key Discoveries and in the brief's Open Risks, and reference them from the Phase 3 manual check.
- **Decision**: FIXED.
