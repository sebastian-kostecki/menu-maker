<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Auth & Profile UI Implementation Plan

- **Plan**: context/changes/auth-profile-ui/plan.md
- **Mode**: Deep
- **Date**: 2026-11-15
- **Verdict**: REVISE → SOUND after fixes
- **Findings**: 0 critical, 4 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | PASS |

## Grounding
12/12 paths ✓, 6/7 symbols ✓ (`ProfileController::destroy` default error bag, `/` renders `Welcome`, `ConfirmablePasswordController` → `intended(dashboard)`, the exact set of 8 Breeze importers, `DesignSystem.vue:365-374`, `Dashboard.vue:13-17`; CardTitle `<h2>` ✗ → F1), brief↔plan ✓, Progress↔Phase ✓ (7/10/11 rows)

## Findings

### F1 — CardTitle can't render the promised <h2>

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architectural Fitness
- **Location**: Phase 3 §2 (Card contract), manual 3.11
- **Detail**: The plan says "CardTitle, rendered as an `<h2>`", but `ui/card/CardTitle.vue:10` is a hard-coded `<h3>` with no `as`/`asChild` prop. As written, 3.11 ("one `<h1>` and three `<h2>`") cannot pass.
- **Fix A ⭐ Recommended**: Add an `as` prop (default `'h3'`) to CardTitle; the partials pass `as="h2"`
  - Strength: One heading style; existing callers (FamilyMembers Create/Edit, kitchen sink) still render `<h3>`.
  - Tradeoff: Another local edit to a `ui/` file that the D-9 regeneration must keep.
  - Confidence: HIGH — backward-compatible default.
  - Blind spot: None significant.
- **Fix B**: Skip CardTitle and write a hand-styled `<h2>` in CardHeader
  - Strength: No primitive change.
  - Tradeoff: Copies CardTitle's classes into 3 partials.
  - Confidence: HIGH
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A)

### F2 — Phase 1 PNG whitelist too narrow

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §5, manual 1.5
- **Detail**: Overlay element screenshots show the page behind their rounded corners (`app-shell-restyle/plan.md:476`). Phase 1 changes the content around the ConfirmDialog triggers (Komponenty aplikacji) and the select/combobox (Formularze), not only around dialog/alert-dialog, so `overlay-confirm-dialog(-processing)`, `overlay-select` and `overlay-combobox` may shift too. "Any other changed PNG is a regression" would flag a legitimate diff.
- **Fix**: Expected = the two sections + the new overlay; any `overlay-*.png` may change only in its corner pixels; reword 1.5 to match.
- **Decision**: FIXED

### F3 — Seeder missing before 1.3 and the visual gates

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Environment; criteria 1.3, 1.4, 2.6, 3.7
- **Detail**: The Environment section only migrates the fresh worktree DB. `confirm-dialog.spec.ts` logs in as `e2e-planner-{chromium,firefox}` (and deletes a family member), and the visual specs log in as `e2e-planner-visual`. Only 2.5 and 3.6 seed first.
- **Fix**: Prefix 1.3, 1.4, 2.6 and 3.7 with `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder &&`.
- **Decision**: FIXED

### F4 — Input invalid style restyles the un-migrated recipe form

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Phase 1 §2
- **Detail**: `Components/recipes/IngredientRow.vue:22-23,60,89` already passes `:aria-invalid` (plus `border-red-500`) to `ui/input`, so its error state changes outside the slice, with no visual or E2E check covering it.
- **Fix**: Name IngredientRow as an accepted side effect in Phase 1 §2, leave `border-red-500` to its slice, and add a Migration Notes line for recipes-ui.
- **Decision**: FIXED

### F5 — Enter in the delete field bypasses the processing guard

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3 §3, Critical Implementation Details
- **Detail**: ConfirmDialog guards only its own confirm button. Enter in the slot field calls `deleteUser` directly, so a second Enter while processing sends a second DELETE after logout.
- **Fix**: `if (form.processing) return` at the top of `deleteUser`.
- **Decision**: FIXED

### F6 — Doc anchors and "same helper" don't exist as named

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §6, Phase 3 §6
- **Detail**: `design-system.md` has no §7.2/§7.3 (§7 is a numbered list; the visual gate is item 3, line 250). `logIn` in `app-shell.spec.ts:16` is a local function, not an importable helper.
- **Fix**: Point to "§7, item 3", and move `logIn` into `tests/e2e/visual/support/auth.ts`, imported by both visual specs.
- **Decision**: FIXED
