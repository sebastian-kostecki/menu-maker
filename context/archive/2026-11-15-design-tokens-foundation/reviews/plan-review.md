<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Design Tokens Foundation Implementation Plan

- **Plan**: context/changes/design-tokens-foundation/plan.md
- **Mode**: Deep
- **Date**: 2026-11-15
- **Verdict**: REVISE → SOUND (after fixes)
- **Findings**: 0 critical, 3 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding

11/11 paths ✓, 6/6 symbols ✓, brief↔plan ✓, Progress↔Phase ✓ (2 stale line refs, see F5). Checked inline: the new-york-v4 registry JSON (`from "cn"` and `@lucide/vue` imports, `button` as the only registry dependency, no `cssVars`, `buttonVariants` used only with `ghost`/`outline`/`default` and sizes `default`/`icon`), the four icons in lucide-vue-next 1.0.0, and 17 dark + 6 light contrast pairs (every dark pair ≥ 4.72:1; light pairs match §4.2). No E2E test or view depends on classes, `chart-*` or `var(--color-*)`.

## Findings

### F1 — The CLAUDE.md rule leaves out how to normalise CLI output

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3 — §1 Design-system rule, bullet 2
- **Detail**: "A missing primitive is added with the CLI" does not tell the next agent that v4 output imports `cn` from npm and icons from `@lucide/vue`, and that it overwrites `ui/button`. D-9 lives only in design-system.md.
- **Fix**: Extend bullet 2 with the import rewrite, `npm uninstall cn @lucide/vue`, and restoring overwritten `ui/` files (D-9).
- **Decision**: FIXED

### F2 — Rule bullet 3 does not say how far it reaches into unmigrated views

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 3 — §1 Design-system rule, bullets 2–3
- **Detail**: The ban on literals lands while every view still has them, so an agent editing an unmigrated view must either break the rule or restyle the whole view, which breaks the per-slice rollout in §8. `Components/Common/` does not exist until S-07.
- **Fix A ⭐ Recommended**: The rule covers new files and the view being migrated in its own slice. Unmigrated views get no new literals and are not restyled outside their slice. Common/ is noted as arriving in S-07.
  - Strength: Matches §8 per-slice migration and bounded runs.
  - Tradeoff: Literals stay in old views until their slice.
  - Confidence: HIGH — §8 explicitly plans the migration per slice.
  - Blind spot: None significant.
- **Fix B**: Boy-scout replacement of literals on touched lines.
  - Strength: Gradual cleanup before the slices.
  - Tradeoff: Mixed colours inside one view; visual changes in non-UI PRs.
  - Confidence: MED — depends on agent discipline.
  - Blind spot: The F-04 screenshot gate would flag unrelated changes.
- **Decision**: FIXED (Fix A)

### F3 — The uncommitted M-2 roadmap will mix into the phase commits

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Prerequisites / Phase 3 §3 Roadmap
- **Detail**: `roadmap.md` holds an uncommitted v1→v2 rewrite (+175/−136) and the change folder is untracked. Per-phase commits would sweep them in. Phase 2's `git diff HEAD` baseline also assumes Phase 1 is committed.
- **Fix**: Prerequisite: commit the roadmap and the change folder as `docs:` before Phase 1, and commit Phase 1 before the CLI step.
- **Decision**: FIXED

### F4 — Gaps in Phase 2/3 automated verification

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 / Phase 3 — Success Criteria
- **Detail**: The `--overwrite` check covered only 3 paths, the alert grep had no expected value, and `rounded-full` and the F-04 roadmap note had no checks.
- **Fix**: `git status --porcelain` allowlist, alert grep prints `3`, a `rounded-full` grep, and a `lint:tokens` grep on the roadmap (new 3.4; manual check moved to 3.5).
- **Decision**: FIXED

### F5 — Stale line numbers and imprecise reasoning about the gray-200 layer

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Key Discoveries / Current State Analysis
- **Detail**: `app.css:89-99` → 85-101, `150-158` → 160-167. The gray-200 rule also targets pseudo-elements, which `*` does not match. No pseudo-element in `resources/js` has a border, so removing it changes nothing.
- **Fix**: Correct the line numbers and the reasoning.
- **Decision**: FIXED
