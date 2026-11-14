<!-- PLAN-REVIEW-REPORT -->
# Plan Review: AI Ingredient Scaling (S-04)

- **Plan**: context/changes/ai-ingredient-scaling/plan.md
- **Mode**: Deep
- **Date**: 2026-11-14
- **Verdict**: REVISE → SOUND after triage
- **Findings**: 1 critical, 2 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding
28/28 paths ✓, 9/10 symbols ✓ (worker timeout ✗ → F1), brief↔plan ✓, Progress↔Phase ✓. Confirmed: the worked example and the E2E arithmetic; `unique(recipe_id, ingredient_id)` (the backfill is safe); full coverage of every place that reads recipe ingredients (no frontend consumer of `recipe.ingredients`); `anthropic-ai/sdk`, camelCase args, `outputConfig`, `stopReason`, and the Opus 5.5 thinking/400 rules.

## Findings

### F1 — Sail's queue:listen kills the job at 60 s, ignoring $timeout = 80

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Key Discoveries "Time budget"; Critical Implementation Details
- **Detail**: The job's `$timeout` overrides the worker's only under `queue:work` (`Worker.php:394`). Sail runs `queue:listen --tries=1` (`docker-compose.yml:29`), which wraps each child in a Symfony Process with the listener's `--timeout` (default 60, `Listener.php:122-128`). The worst case (~61 s) kills the process, the job then fails with MaxAttemptsExceeded after `retry_after` 90, and the plan ends in `error` instead of `computed`. The Migration Notes did not mention the production worker requirement.
- **Fix A ⭐ Recommended**: `queue:listen --tries=1 --timeout=85` in docker-compose.yml, plus a production worker note in Migration Notes
- **Fix B**: Shrink the budget to under 60 s (attempts ≤15 s, `$timeout = 55`)
- **Decision**: FIXED (Fix A)

### F2 — The Anthropic SDK does not enforce its own timeout option

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 §2 "Anthropic"
- **Detail**: `RequestOptions::timeout` exists, but the SDK never reads it; only the transport applies it. `maxRetries: 0` does work. With the plan's "set the SDK timeout to 20 s", a call can hang until the worker kills it.
- **Fix**: The gateway passes the SDK a Guzzle client with `timeout`/`connect_timeout` = 20, and a test asserts it.
- **Decision**: FIXED

### F3 — 20 s per attempt for 60–170 items on Opus (thinking always on) is unmeasured

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 3 §2–3; Performance Considerations
- **Detail**: About 1–3.5k output tokens plus thinking. If attempts routinely exceed 20 s, every plan silently ends `computed`, and this would only be found in manual check 3.5/3.6.
- **Fix A ⭐ Recommended**: Dedupe items per (ingredient, unit, computed quantity) and fan the answer out to the rows, plus a timing measurement on a real 21-meal payload (step 3.8)
- **Fix B**: Default `ANTHROPIC_MODEL` to a faster tier
- **Decision**: FIXED (Fix A)

### F4 — "computed" status shows the AI-failure notice when there was no AI call

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2 §3; Phase 3 §3; Phase 4 §1
- **Detail**: A family with only 0-kcal recipes drawn makes no `refine()` call, yet the status stays `computed`, so the "AI failed" notice is untrue.
- **Fix**: With no items to refine, the status is `refined`, plus one scaler test.
- **Decision**: FIXED
