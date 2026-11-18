<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Spiżarnia Family and Profile UI

- **Plan**: context/changes/spizarnia-family-profile-ui/plan.md
- **Mode**: Deep
- **Date**: 2026-11-18
- **Verdict**: REVISE → SOUND after fixes
- **Findings**: 2 critical, 1 warning, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding

10/10 paths ✓, 6/7 symbols ✓ (Card `as` ✗), brief↔plan ✓.

Verified against the code:
- SSR does not run in the test stacks (fallback, `createApp`), so the client `new Date()` sees the frozen clock.
- `shadow-sticker shadow-destructive` survives tailwind-merge 3.7.0 and recolours the shadow.
- `rounded-lg` = 20 px.
- The `text-dialog-title`, `text-meta`, `text-label`, `meal-*` and `success-foreground` tokens exist.
- The paginator keys match `ServerPagination` `meta`.
- `formatDate` outputs „1 maj 1990”.
- The visual gate has 58 shots.
- lint-tokens does not flag `p-5.5`, `min-h-57.5` or `max-w-180`.

## Findings

### F1 — Card cannot render as <article>

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architectural Fitness
- **Location**: Phase 1, change 3 + change 6
- **Detail**: `Card.vue` has no `as` prop; its only prop is `class`, and it always renders a `<div>`. `<Card as="article">` gives `<div as="article">`, so `getByRole('article', { name })` in both functional specs would find nothing.
- **Fix A**: Add `as` (default 'div') to Card.vue in Phase 1
  - Strength: Real <article>; mirrors CardTitle `as`.
  - Tradeoff: Edits a ui/ primitive with 11 importers in Phase 1 and conflicts with S-23/S-24.
  - Confidence: HIGH.
  - Blind spot: None significant.
- **Fix B ⭐ Recommended**: `<Card role="article" aria-labelledby=…>` through attribute fallthrough
  - Strength: No primitive change. Same pattern as `role="region"` in `DeleteUserForm.vue:55`.
  - Tradeoff: An ARIA role instead of a native element.
  - Confidence: HIGH.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix B)

### F2 — Kitchen-sink tile ages drift: design-system.spec has no frozen clock

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 1, change 8 + change 9
- **Detail**: Unlike the other visual specs, `design-system.spec.ts` has no `page.clock.setFixedTime`, so `ageInYears(…, new Date())` in the kitchen sink would use the real date. The baseline would capture today's age, and the gate would go red on the next birthday of any demo person.
- **Fix A ⭐ Recommended**: An optional `today` prop on `FamilyMemberTile`; the kitchen sink passes `new Date(2026, 5, 15)`
  - Strength: Deterministic without touching the clock of the other 33 shots.
  - Tradeoff: A prop that exists only for the demo.
  - Confidence: HIGH.
  - Blind spot: None significant.
- **Fix B**: `page.clock.setFixedTime` in `design-system.spec.ts`
  - Strength: Same rule as the other specs.
  - Tradeoff: Changes the time for every section, so every kitchen-sink baseline needs checking.
  - Confidence: MED.
  - Blind spot: Date-dependent demos not surveyed.
- **Decision**: FIXED (Fix A)

### F3 — Dashed tile inside or outside the grid?

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1, change 4
- **Detail**: The contract lists the grid, the dashed Button and ServerPagination as siblings, while check 1.9 expects the "dashed tile on the second row".
- **Fix**: The dashed Button is the last child of the grid (on every page), and ServerPagination comes after the grid.
- **Decision**: FIXED

### F4 — 56 px avatar left as an either/or

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1, change 3
- **Detail**: `ui/avatar` has only 40/64 px sizes, so 56 px would mean overriding the primitive's shape in the view (§5.1).
- **Fix**: A token-only `<span>` (`size-14 rounded-full`), with the ui/avatar option dropped.
- **Decision**: FIXED

### F5 — "60 kg" locator not specified against the <dd> markup

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1, change 3 + change 6
- **Detail**: The value and unit sit in separate elements. Without whitespace between them, textContent reads „60kg”.
- **Fix**: Require whitespace between the elements and use the locator `createdTile.getByRole('definition').filter({ hasText: /^60\s*kg$/ })`.
- **Decision**: FIXED

### F6 — "Default renders byte-identical" vs data-variant/group class

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Phase 2, change 1
- **Detail**: `data-variant` or `group/card` would change the DOM of all 11 Card importers, contradicting "byte-identical".
- **Fix**: `provide`/`inject` of the variant (Card → CardTitle), with no DOM change; `default` has the same DOM and pixels.
- **Decision**: FIXED
