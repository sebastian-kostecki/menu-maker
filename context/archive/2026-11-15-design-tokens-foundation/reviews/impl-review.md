<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Design Tokens Foundation

- **Plan**: context/changes/design-tokens-foundation/plan.md
- **Scope**: Full plan (Phases 1–3 of 3)
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-15
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Verification Run

- All automated greps from Phases 1–3 pass (1.1, 1.2, 2.1–2.5, 3.1–3.4). `ui/button` and `components.json` are byte-identical to the baseline `d35e964`.
- `vendor/bin/sail npm run build` OK (client and SSR). New utilities (`bg-meal-dinner`, `bg-success`, `bg-info`, `text-warning-foreground`, ...) are emitted in the built CSS. `bg-primary/90` compiles to `color-mix(in oklab, ...)`.
- `vendor/bin/sail composer test`: 358 passed.
- Playwright E2E (chromium + firefox): 12 passed after re-seeding. Needed `MAILPIT_URL=http://localhost:8026` (dashboard port here is 8026, not 8025) and `--output` outside the repo (`test-results/` is root-owned from Docker).
- `package-lock.json`: the only change is the `reka-ui` range (`^2.4.1` → `^2.11.0`); the locked `node_modules/reka-ui` was already 2.11.0, so no installed version changed. No new packages. D-5 is satisfied.
- WCAG contrast recomputed from `app.css` for every text/background pair in `:root` and `.dark`: all ≥ 4.5:1 (min 5.15 dark, 5.22 light).

## Findings

### F1 — Nine manual checks still unchecked in Progress

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/design-tokens-foundation/plan.md:303-305, 321-323, 336
- **Detail**: Rows 1.6–1.8, 2.8–2.10 and 3.5 are `[ ]`. All three phase commits landed although the plan says to pause for human confirmation after each phase. Nothing in the diff counts as evidence for them. 2.8 (package.json diff) can be ticked from this review: only the `reka-ui` range moved and the lock already pinned 2.11.0. 1.6, 1.7, 2.10 (browser: cream background, green focus ring, borders, dialogs and category select) and 3.5 need a human (1.8 and 2.9 are quick DevTools/file checks).
- **Fix**: Run the browser checks (Tab onto a button, open the recipe delete and generate dialogs, use the category select), then tick the rows.
- **Decision**: PARTIALLY FIXED — 2.8 ticked in plan.md (verified by this review). 1.6, 1.7, 1.8, 2.9, 2.10 and 3.5 remain for a human (browser).

### F2 — Six light tokens are slightly outside sRGB

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/css/app.css:92-99 (`--warning`, `--info`, `--meal-breakfast`, `--meal-supper`, `--meal-dinner`, plus the `--warning-foreground` and `--meal-breakfast-foreground` pair)
- **Detail**: The plan says every value is in sRGB gamut. Linear-sRGB channels overshoot by up to 0.074 (`--meal-supper` red 1.074; the others ≤ 0.04). Browsers clip these on sRGB displays and show them as written on P3. Contrast after clipping is still ≥ 5.9:1. The values come from design-system §4.3–4.4, so this is a spec property, not an implementation error. It matters later for S-14, because the PDF needs hex colours and will get clipped values.
- **Fix**: Add one line to design-system.md §4 saying that some light state/meal tokens are slightly out of sRGB and that S-14 should use the clipped hex values.
- **Decision**: FIXED — note added to design-system.md §4.4.

### F3 — New CLAUDE.md CLI bullet overlaps the existing one

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: CLAUDE.md:24, 26
- **Detail**: Line 24 says to add components with `npx shadcn-vue@2 add` and "never `--overwrite` existing components blindly". The new line 26 repeats the CLI step and then tells the reader to restore overwritten `ui/` files. They do not contradict each other, but an agent reads two partial CLI procedures. Plan check 3.5 asks for no duplication.
- **Fix**: Fold the CLI sentence from line 24 into line 26 so the CLI procedure appears once.
- **Decision**: FIXED — merged into the single CLI bullet in CLAUDE.md.
