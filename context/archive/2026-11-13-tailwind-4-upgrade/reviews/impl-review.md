<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Tailwind 4 Upgrade

- **Plan**: context/changes/tailwind-4-upgrade/plan.md
- **Scope**: Full plan (reviewed by the main agent, unattended)
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-13
- **Verdict**: APPROVED (after fix)
- **Findings**: 0 critical, 2 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING (Phase 2 deviation, documented) |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS (all Automated rows green: phpstan, pint, Pest 129, build client+SSR, E2E 6/6) |

## Findings

### F1 — Removed v3 opacity utilities left by the upgrade tool
- **Severity**: WARNING / **Impact**: LOW / **Dimension**: Safety & Quality
- **Location**: Components/recipes/RowActionDropdown.vue:15, Components/Dropdown.vue:76, Components/recipes/ConfirmDeleteModal.vue:9
- **Detail**: `ring-opacity-5` and `bg-opacity-50` no longer exist in v4 (solid black ring/overlay).
- **Fix**: `ring-black/5`, `bg-black/50`.
- **Decision**: FIXED (fix commit)

### F2 — Phase 2 deviates from plan (CLI regeneration rejected)
- **Severity**: WARNING / **Impact**: MEDIUM / **Dimension**: Plan Adherence
- **Detail**: shadcn-vue@2 CLI emits the redesigned style plus new deps (@lucide/vue, cn, vueuse 13); reverted to protect parity and the requested package versions. Documented in change.md.
- **Decision**: ACCEPTED

### F3 — Pre-existing: /recipes/{id} renders blank (recipe.data undefined)
- **Severity**: OBSERVATION / **Impact**: LOW / **Dimension**: Scope Discipline
- **Detail**: Present in baseline screenshots; unrelated to Tailwind.
- **Decision**: ACCEPTED (out of scope)
