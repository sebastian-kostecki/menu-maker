<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Design System Visual Gate

- **Plan**: context/changes/design-system-visual-gate/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-15
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Automated verification (re-run during review)

- `sail pest --filter=DesignSystemPageTest`: 2 passed
- `sail composer test`: 360 passed; `composer pint-test`: passed; `composer phpstan`: No errors
- `bash scripts/lint-tokens.test.sh`: all PASS; `sail npm run lint:tokens`: OK (1 file, 0 hits)
- `sail artisan route:list --path=dev`: `dev.design-system` listed
- `sail npm run build`: OK; `npm run test:visual` twice: 19 passed each time
- 19 baseline PNGs; wrapper refuses to run with `public/hot` present
- CI grep: both lint steps present; CLAUDE.md names the tools; roadmap unknown resolved
- Not re-run: shellcheck (image not pulled), deliberate-break checks (3.3), E2E suite, GitHub Actions run (3.9)

Manual rows 1.6–1.8, 2.6, 3.8, 3.9, 4.4 are unchecked in Progress, so they are pending (no ticked-without-evidence rows).

## Findings

### F1 — lint-tokens swallows grep failures

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: scripts/lint-tokens.sh:57
- **Detail**: `grep -noP ... || true` hides grep exit 2 (no `-P` support on BSD/macOS or busybox, unreadable file, invalid regex). The scanner then prints `OK (N files, 0 hits)`, a false pass. CI is protected by the self-test; a local run on such a system is not.
- **Fix**: Capture grep's status and `exit 2` with a message when it is greater than 1 (or probe `grep -P` once at the top).
- **Decision**: FIXED — grep failures now exit 2 with a message (scripts/lint-tokens.sh)

### F2 — "zero tolerancji" claim vs Playwright default threshold

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: playwright.config.ts:26-31, context/foundation/design-system.md:237 (§7.3)
- **Detail**: The plan keeps `maxDiffPixels` at 0 but leaves Playwright's per-pixel `threshold` at its default (0.2). The visible config sets no `expect.toHaveScreenshot` options, so a reader cannot see that, and §7.3 says "zero tolerancji". Small OKLCH lightness shifts could pass unnoticed.
- **Fix A ⭐ Recommended**: Set `expect: { toHaveScreenshot: { threshold: 0, maxDiffPixels: 0 } }` on the `visual` project.
  - Strength: Matches the docs and the gate's purpose (catching one-token changes); the renderer is already pinned.
  - Tradeoff: Any antialiasing noise becomes a failure; baselines may need a deliberate refresh.
  - Confidence: MED — pinned image should be deterministic, but not tested at threshold 0.
  - Blind spot: Not run twice in CI at threshold 0.
- **Fix B**: Keep the default and reword §7.3 to "zero dopuszczalnych różnych pikseli (domyślny próg koloru piksela)".
  - Strength: No behaviour change; follows the plan as written.
  - Tradeoff: Gate stays blind to small colour shifts.
  - Confidence: HIGH — documentation-only.
  - Blind spot: None significant.
- **Decision**: FIXED via Fix A — `threshold: 0, maxDiffPixels: 0` on the `visual` project; 19 screenshots green twice at zero threshold; design-system §7.3 and plan amended

### F3 — focus-visible test deviates from plan and is index-coupled

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/e2e/visual/design-system.spec.ts:62-69
- **Detail**: The plan said to focus the first button in Przyciski; the spec focuses `nth(3)`, tabs back and asserts `nth(2)` (the outline button), because the default button's ring matches its fill. The deviation is deliberate and recorded in design-system §7.3, but not in the plan, and `nth()` breaks silently if variants are reordered.
- **Fix**: Locate by accessible name (`getByRole('button', { name: 'outline', exact: true })`) and note the deviation in the plan.
- **Decision**: FIXED — focus test locates `destructive` then Tab to `outline` by name; plan amended with the deviation

### F4 — lint-tokens minor edges

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: scripts/lint-tokens.sh
- **Detail**: Paths resolve against the current directory (a run from elsewhere gives a confusing exit 2); `group-data-[state=open]/x:bg-accent` is a false positive because `/x` defeats the `(?!:)` lookahead.
- **Fix**: `cd "$(dirname "$0")/.."` at the top; widen the lookahead to `(?![:/])` and add a self-test case. Both can wait until a slice hits them.
- **Decision**: FIXED — default paths file resolves from the repo root; lookahead widened to `(?!:|/[\w-]+:)` with a self-test case (23 cases pass)

### F5 — Roadmap F-04 status vs plan wording

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/roadmap.md:46,107
- **Detail**: The plan says to leave Status to the 10x skills; the diff has F-04 as `in-progress` (done by the workflow, not by hand), while change.md says `implemented`. Status will settle on archive.
- **Fix**: None needed now; `/10x-archive` should move F-04 to its final status.
- **Decision**: SKIPPED — roadmap status settles on /10x-archive

## Reviewed and dropped

- Font from fonts.bunny.net (flakiness risk): an explicit plan decision ("No self-hosted Figtree"), mitigated by the load assertion.
- `bootstrap/cache` baked into the prod image: `docker/8.5/Dockerfile.prod:8` already removes `routes-v7.php` and the other cached files, and deploy runs `route:cache`.
- Image tag not pinned by digest and no `--init`: low risk, left out.
