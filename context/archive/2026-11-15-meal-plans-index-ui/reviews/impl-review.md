<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Meal Plans Index UI Implementation Plan

- **Plan**: context/changes/meal-plans-index-ui/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-15
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Automated verification (re-run during review)

| Check | Result |
|-------|--------|
| `vendor/bin/sail npm run build` | PASS |
| `vendor/bin/sail npm run lint:tokens` | PASS (26 files, 0 hits) |
| `bash scripts/lint-tokens.test.sh` | PASS |
| `grep localStorage Index.vue` / English table copy grep | PASS (no output) |
| `vendor/bin/sail composer test` | PASS (368 tests, 1365 assertions) |
| `vendor/bin/sail composer phpstan` / `pint-test` | PASS |
| Seeder twice in a row | PASS |
| `npm run test:visual` ×2 (worktree network) | PASS (34/34 both runs), screenshots unchanged |
| Full E2E after re-seed | PASS (22/22) |

Manual rows 1.6–1.7, 2.5–2.8, 3.6–3.9 and 4.8–4.10 are all unchecked (pending, not falsely ticked).

## Findings

### F1 — Unplanned baseline changes accepted, row 4.4 ticked

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/e2e/__screenshots__/visual/design-system.spec.ts/{overlay-confirm-dialog.png, overlay-confirm-dialog-processing.png, overlay-user-menu.png}
- **Detail**: Plan Phase 4 §3 expected only `section-komponenty-aplikacji.png`, `overlay-dropdown-menu.png` and possibly the corners of `overlay-dialog.png`/`overlay-alert-dialog.png`, and said "Any other changed PNG is a regression to investigate, not to accept". Three other PNGs changed. Commit db119ec accepted them as the S-07/S-08 corner class, and Progress 4.4 is ticked although its condition was not met. Pixel check: both confirm-dialog PNGs differ by 51 px in the bottom 9 rows, max channel delta 2 (noise). `overlay-user-menu.png` differs by 41 px in the rightmost 8 px column, max delta 214. The menu's right border and corner now touch the crop edge, so the menu or the crop box shifted by about 1–2 px. That is a visible edge change.
- **Fix**: Inspect the `overlay-user-menu.png` diff as part of manual row 4.8. If it is acceptable, record all three as an accepted deviation in a plan addendum next to row 4.4. Otherwise, investigate the shift.
- **Decision**: FIXED. I compared the old and new `overlay-user-menu.png`: the menu content is identical, and only its position in the crop moved by about 1 px. I recorded all three PNGs as an accepted fractional-position deviation in the plan's "Addenda (impl-review 2026-11-15)". A human look still belongs to manual row 4.8.

### F2 — `lib/format.js` throws on an invalid date and breaks the render

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/format.js:3-24
- **Detail**: These calls throw `RangeError: Invalid time value` (verified in Node):
  - `formatDateRange` with an end of `null`, `undefined` or `''`;
  - `formatDateRange` with a full ISO timestamp, because `parseYmd` splits on `-` and gets a NaN day;
  - `formatDateTime(undefined)` or `formatDateTime('garbage')`.

  The old inline `toLocaleDateString` returned "Invalid Date" instead. Because `ActionDropdown` now calls `formatDateRange` for its `aria-label`, a throw would break the whole table on Index and the header menu on `MealPlans/Show`. Today's data cannot trigger it: the columns are non-nullable and both resources emit `Y-m-d`. The module is meant for reuse in S-09/S-11, though.
- **Fix**: In `parseYmd`, read only the first 10 characters. In both formatters, return `''` (or `'—'`) when `Number.isNaN(date.getTime())`.
- **Decision**: FIXED. `parseYmd` now reads `slice(0, 10)`, and both formatters return `''` for invalid dates. Checked in Node: null, undefined, empty and garbage give `''`, a full ISO start parses, and valid output is unchanged.

### F3 — Rapid filter changes can drop an earlier change

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/Pages/MealPlans/Index.vue:116-174
- **Detail**: `buildQuery` merges each change into `currentFilters`, which comes from the last response's `props.filters`. Inertia cancels an in-flight visit when a new one starts. So a second change made before the first response arrives is built on stale props, and the first change is lost (status, then page size; or two quick sort clicks that don't toggle). The old `localFilters` ref accumulated changes synchronously. The plan required derived state ("Filter state is derived, never stored") to fix the S-07 select/list desync, so this is the cost of that design.
- **Fix A ⭐ Recommended**: Accept, and document it in the plan's Migration Notes.
  - Strength: Keeps the single-source-of-truth contract that fixed S-07 F2. Each Select needs an open and a pick, so the window is a few hundred ms.
  - Tradeoff: Quick double interactions on a slow network still lose the first change.
  - Confidence: HIGH — the window is small and the outcome is visible in the URL and controls.
  - Blind spot: Not measured under a throttled network.
- **Fix B**: Keep a `pendingChanges` ref that is merged into `buildQuery` and cleared in `onFinish`/`onCancel`.
  - Strength: No lost update.
  - Tradeoff: Brings back the local state the plan removed. If it is not cleared on cancel or error, the controls can disagree with the list again.
  - Confidence: MED — depends on Inertia's cancel and finish callback order.
  - Blind spot: Interaction with `replace: true` and the back button is unverified.
- **Decision**: FIXED via Fix A. Documented as an accepted trade-off in the plan's Migration Notes.

### F4 — Two undocumented minor deviations from the plan

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/Pages/MealPlans/Index.vue:169-174; tests/e2e/visual/meal-plans-index.spec.ts:51-56
- **Detail**:
  - **Page changes.** They now send `{ ...buildQuery(), page }`, so the normalised defaults (`sort=created_at&direction=desc&perPage=15`) are always present, and a raw `direction=ASC` becomes `desc`. The plan said page changes keep the S-07 behaviour (the current query plus `page`) and that the parameters sent are unchanged. The server result is the same.
  - **Filtered-empty shot.** The spec reloads the page before this shot ("avoids corner jitter") and asserts that the Select shows "Oczekuje". The baseline therefore comes from a hard load rather than in-app navigation. The extra assertion usefully proves that the URL drives the Select.
- **Fix**: Add a one-line addendum for each to the plan. No code change.
- **Decision**: FIXED. Both deviations are recorded in the plan's "Addenda (impl-review 2026-11-15)".

### F5 — `EmptyState` title is an `<h3>` directly under the page `h1`

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/Common/EmptyState.vue:12
- **Detail**: On `/meal-plans` the heading outline goes `h1` "Jadłospisy" → `h3`, skipping a level. The plan did not specify the element. `EmptyState` is a shared Common component that S-09/S-11 will reuse.
- **Fix**: Render the title as an `<h2>` (same classes).
- **Decision**: FIXED. `EmptyState.vue:12` is now an `<h2>`. The visual baselines are unchanged.

### F6 — Visual baselines depend on the container's timezone

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: playwright.config.ts:27 (visual project)
- **Detail**: `formatDateTime(created_at)` renders in the browser's timezone, and the `visual` project sets no `timezoneId`. `meal-plans-index-desktop.png` is stable only because the pinned Playwright image runs in UTC.
- **Fix**: Add `timezoneId: 'UTC'` to the visual project's `use`. Baselines should not change.
- **Decision**: FIXED. Added to the visual project's `use`. The visual gate passed 34/34 with no PNG changes.

### F7 — Roadmap status change left uncommitted

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: context/foundation/roadmap.md:50, :163
- **Detail**: The working tree moves S-10 from `ready` to `in-progress` (2 lines). That change is not in any commit, while `change.md` already says `implemented`.
- **Fix**: Commit the roadmap sync with this branch, or set it to `done` at archive time.
- **Decision**: FIXED. The roadmap's S-10 `in-progress` status is committed with the review fixes; it moves to `done` at archive time.

## Triage summary

- Fixed: F1, F2, F3 (Fix A), F4, F5, F6, F7 (7)
- Re-verified after the fixes: build, lint:tokens, the visual gate (34/34, no PNG changes) and the full E2E suite (22/22).
