<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Recipes UI (S-11 / DS-06)

- **Plan**: context/changes/recipes-ui/plan.md
- **Mode**: Deep
- **Date**: 2026-11-15
- **Verdict**: REVISE → SOUND after fixes
- **Findings**: 1 critical, 2 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | FAIL |

## Grounding
22/22 paths ✓, 8/8 symbols ✓, brief↔plan ✓, Progress↔Phase ✓ (5 phases, 38 steps)

## Findings

### F1 — Copy-grep criteria cannot pass as written

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 criterion 3.4; Phase 2 criterion 2.4
- **Detail**: 3.4 grepped `serving` over all of `Components/recipes/`, which matches identifiers that stay: `RecipeForm.vue:86-99,205`, `RecipeRow.vue:19,76` and the plan's own `servings` prop on `RecipeMetaBadges`. The step could never go green. 2.4 also hit English code comments the contract never said to remove (`CategoryFilterDropdown.vue:54`, `RecipesToolbar.vue:23`).
- **Fix**: 3.4 pattern `serving` → `'serving' :` (the English ternary in `RecipeMetaBadges.vue:14`; bare `'servings'` would still match `RecipeRow.vue:76`). The Phase 2 Toolbar parts contract now requires those comments to be translated or removed.
- **Decision**: FIXED

### F2 — Seeder upsert ignores soft-deleted rows

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 5 §1 — Deterministic visual recipes
- **Detail**: Recipe uses SoftDeletes (`Recipe.php:17`) and `recipes` has no unique index on `(user_id, name)`. A trashed recipe whose name is on the list survives "force-delete not in list", and `updateOrCreate` then creates a live duplicate next to it, so the seed is no longer the same everywhere.
- **Fix**: Contract now force-deletes every trashed recipe of the visual user plus live ones not in the list (`withTrashed()`), then upserts.
- **Decision**: FIXED

### F3 — Kitchen-sink naming rule is inverted

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §4 — Kitchen sink demos
- **Detail**: `design-system.spec.ts` uses page-wide non-exact names (`:27,80,89,116,125,134,165`), so a new name must not *contain* those strings. The plan said new names must not be *substrings of* them.
- **Fix**: Rule reworded to list the six locator strings and to forbid "Design system" in the demo PageHeader title.
- **Decision**: FIXED

### F4 — "Brak wyników" copy on an out-of-range page

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2 §1 — States
- **Detail**: On `?page=99` without filters, "Żaden przepis nie pasuje do wyszukiwania lub filtra" is untrue. The reset works (`Index.vue:157-163` sets `page: 1`).
- **Fix**: Description changed to "Na tej stronie nie ma przepisów lub żaden nie pasuje do filtrów."
- **Decision**: FIXED

### F5 — "Existing practice of scanning visual specs" is false

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Lean Execution
- **Location**: Phase 5 §3 — Lint paths
- **Detail**: No `tests/e2e` path is in `scripts/lint-tokens.paths` or scanned anywhere (`master.yml:41`).
- **Fix**: Step removed; Phase 5 Docs renumbered to §3.
- **Decision**: FIXED

### F6 — design-system.md §8 rollout rows left stale

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §7 — Docs
- **Detail**: Rows DS-04/05/06 still say "+ PageHeader/EmptyState (pierwszy, kto potrzebuje)"; Phase 1 updated only §5.2/§4.5.
- **Fix**: Phase 1 docs contract now covers §8 (DS-04/05 consume from DS-06, DS-06 builds).
- **Decision**: FIXED
