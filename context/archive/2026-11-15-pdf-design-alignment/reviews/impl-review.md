<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: PDF Design Alignment

- **Plan**: context/changes/pdf-design-alignment/plan.md
- **Scope**: Full plan (both phases; all Automated rows are `[x]`, Phase 1 Manual rows 1.5–1.8 are still pending)
- **Reviewed phases**: 1, 2
- **Date**: 2026-11-15
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Evidence

- Plan vs diff (`94d29b4..HEAD`): every planned file is changed and nothing outside the plan except workflow files (`change.md`, `plan-brief.md`, `reviews/plan-review.md`). All 10 files match the plan's "Changes Required".
- Automated checks re-run against this worktree (the Sail containers mount the main checkout, so tests were run with the `sail-8.5/app` image and the worktree mounted):
  - `pest MealPlanPdfExporterTest MealPlanPdfPaletteTest`: 17 passed (104 assertions)
  - full `pest`: 366 passed (1333 assertions)
  - `phpstan analyse`: No errors
  - `pint --test`: PASS, 162 files
  - `grep -nE '#(111|999|e8e8e8|444|c9a227|fff8e1)\b'` on the template: no matches
- Row 2.2 (guard catches drift) was not re-run: an in-place mutation of `app.css` was blocked, so it was assessed from reading the guard code only. The guard compares normalised OKLCH strings per exact token name inside `:root`, recomputes the clipped hex, scans the template outside the mirror comment, and flags stale entries.
- The 12 mirror tokens, their OKLCH values and hexes in the template comment match the plan and design-system §4.2–4.4. The `design-system.md` §4.3 hex column and §6 are updated as planned.
- No `context/foundation/lessons.md` exists, so no lessons were applied.

## Findings

### F1 — Phase 1 manual checks 1.5–1.8 still pending while the change is `implemented`

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: context/changes/pdf-design-alignment/plan.md:261-264
- **Detail**: The plan says to pause after Phase 1 for human confirmation of the manual checks, but Phase 2 and the epilogue were committed and `change.md` is `implemented` with 1.5–1.8 unticked. Colours, the grayscale legibility of the day rule and the table header repeating on page 2 cannot be seen by any test (tests only see extracted text and HTML). The plan itself flags one open risk: `--border` on `--warning` is 1.19:1, so the notices frame may vanish in grayscale (check 1.7); the stated fallback is a `--warning-foreground` border.
- **Fix**: Generate a PDF (a plan with scaling notices and one without family members), compare it with `/dev/design-system` "Odznaki", check grayscale and page 2, tick 1.5–1.8, and switch the `.notices` border to `#754b00` if the box disappears.
  - Strength: These are the only checks that cover the visible result; the fallback needs no new token (already in the mirror).
  - Tradeoff: A few minutes of manual work before archiving.
  - Confidence: HIGH — the fallback is already specified in the plan.
  - Blind spot: Actual rendering in dompdf (border-radius with collapsed borders, header repeat) was not observed by this review.
- **Decision**: PARTIALLY DONE — visual evidence gathered, ticks left to the human. The real template was rendered through dompdf from a hand-built document (4 days, notices, a deleted recipe), then converted with Ghostscript to colour and grayscale PNG. Observed: category cells show the honey / tomato / indigo pairs with readable labels; the notices box has a warm-yellow fill with brown text and a rounded frame, and still reads as a box in grayscale (frame is faint, fill carries it), so the `--warning-foreground` fallback is not needed; the 2px day rule is clearly visible in grayscale; Polish glyphs render; the table header repeats on page 2; `(usunięty)` renders as small grey metadata. Not covered: comparison against the on-screen badges in `/dev/design-system` and a real print preview. Rows 1.5–1.8 stay unticked in plan.md for the human to confirm.

### F2 — Guard does not cover a new meal category or other named colours

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Unit/Services/MealPlanPdfPaletteTest.php:122; resources/views/pdf/meal-plan.blade.php:73
- **Detail**: The class is built as `meal-{{ $meal->categoryValue }}`. A fourth `MealCategory` case would render with no colour and no test would fail. The named-colour check only knows `white|black|gray|grey` (as the plan says), so `border: 1px solid red` would pass although the plan's end state says every colour is a listed mirror.
- **Fix**: Add a guard test that every `MealCategory::values()` has a `td.meal-<value>` rule in the template (and optionally widen the named-colour regex).
- **Decision**: FIXED — added test `has a colour rule in the template for every meal category` to MealPlanPdfPaletteTest.php. The named-colour regex was left as the plan specifies.

### F3 — New Pest closures omit `: void`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Unit/Services/MealPlanPdfPaletteTest.php:86
- **Detail**: Sibling tests in `tests/Unit/Services/` use `function (): void`; the five `it()` closures in the new file use `function ()`. Helper functions are prefixed (`pdfPaletteSources`, …), so there is no global name clash.
- **Fix**: Add `: void` to the five closures.
- **Decision**: FIXED — `: void` added to all closures in the file, including the new one.

### F4 — Uncommitted roadmap change (S-14 `in-progress`) left in the working tree

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: context/foundation/roadmap.md:54,210
- **Detail**: `git status` shows `roadmap.md` modified (S-14 `proposed` → `in-progress`) after the epilogue commit. It is a workflow bookkeeping edit, not part of the plan, and it is not committed.
- **Fix**: Commit it with the change (or revert), and set S-14 to its final status when the manual checks are done.
- **Decision**: SKIPPED — committing is left to the human (this session did not create the worktree). `roadmap.md` and the fixes from F2/F3 stay uncommitted.
