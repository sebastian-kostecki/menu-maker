<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Meal Plans Index UI Implementation Plan

- **Plan**: context/changes/meal-plans-index-ui/plan.md
- **Mode**: Deep
- **Date**: 2026-11-15
- **Verdict**: SOUND
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

8/8 paths ✓, 5/6 symbols ✓ (overlay-menu.png ✗), brief↔plan ✓, Progress↔Phase ✓ (7/8/9/10 rows)

Verified with no finding:
- **Seeding.** `MealPlanGenerator::generate(MealPlan)` fills an existing plan. It does not set status, write logs, dispatch jobs or touch the plan's timestamps.
- **Fixture safety.** No observer or scheduled task changes `processing` rows.
- **Dashboard.** It renders no plans, so the `app-shell` baselines are unaffected.
- **Fallback `h1`.** Only `MealPlans/Index` hits it.
- **Baselines.** There is one `visual` project and no file-name suffix, so the run writes exactly 4 new PNGs.
- **Status labels.** They match the spec.
- **localStorage.** The `mm.mealPlans.filters` key is local to `Index.vue`.

## Findings

### F1 — Wrong baseline name: overlay-menu.png does not exist

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Key Discoveries, Phase 4 §3, criterion 4.4
- **Detail**: The kitchen-sink dropdown baseline is `design-system.spec.ts/overlay-dropdown-menu.png`. Gate 4.4 would treat its change as unexpected.
- **Fix**: Replace `overlay-menu.png` with `overlay-dropdown-menu.png`.
- **Decision**: FIXED

### F2 — Toolbar labels can't bind: the Select wrappers drop `id`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2 §3 — Toolbar
- **Detail**: `StatusFilter`/`PerPageSelect` declare no `id` prop. `ui/select/Select.vue` wraps reka `SelectRoot` (`inheritAttrs: false`), so the `id` is dropped and the labels are bound to nothing. The visual spec needs the accessible name "Status".
- **Fix**: Add an `id` prop forwarded to `SelectTrigger`. The spec opens the Select with `getByRole('combobox', { name: 'Status' })`.
- **Decision**: FIXED

### F3 — TableEmpty forces `whitespace-nowrap` on the empty state

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2 §4 — Empty states
- **Detail**: `ui/table/TableEmpty.vue:13` sets `whitespace-nowrap` inside an `overflow-auto` table. At 390px the description does not wrap and the table scrolls sideways. No mobile shot covers it.
- **Fix**: Add `class="whitespace-normal"` on `TableEmpty`, plus a 390px check in manual check 2.7.
- **Decision**: FIXED

### F4 — aria-label makes the sr-only "Otwórz menu" dead text

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3 §3 — Row menu
- **Detail**: An aria-label overrides the button content, so the sr-only span is never read. The kitchen-sink spec also clicks a non-exact `{ name: 'Otwórz menu' }`.
- **Fix**: Remove the sr-only span and use one aria-label, e.g. "Akcje: 15–21 czerwca 2026", that does not start with "Otwórz menu".
- **Decision**: FIXED

### F5 — `v-model:value` cannot bind to a read-only computed

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §2 — URL-only filter state
- **Detail**: `Index.vue:30` uses `v-model:value="localFilters"`. Once that becomes the computed `currentFilters`, the write triggers a readonly warning.
- **Fix**: Bind with `:value="currentFilters"` and `@update:value`, without v-model.
- **Decision**: FIXED
