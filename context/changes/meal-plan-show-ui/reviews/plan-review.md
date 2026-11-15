<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Meal Plan Show View in the New Design

- **Plan**: context/changes/meal-plan-show-ui/plan.md
- **Mode**: Deep
- **Date**: 2026-11-15
- **Verdict**: REVISE (after fixes: SOUND)
- **Findings**: 1 critical, 3 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding
14/14 paths ✓. 8/8 symbols ✓: the `meal-*-foreground` tokens, the alert variants, the `refiner` ctor param, `ScalingOutcome::toArray`, `ShoppingListBuilder`/`ShoppingListResource`, `links.self`, the Collapsible passthrough, and the lint regex against `min-w-160` and `data-[state]` variants. brief↔plan ✓.

## Findings

### F1 — Seeded visual plan still shows random recipe servings

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 4 §1 (Deterministic visual plan), §2 (Seeder test)
- **Detail**: `RecipeFactory:31` draws `servings` from 1–8, and the seeder creates recipes only when missing (`E2eMealPlanSeeder.php:62-72`). The grid prints `· {servings} porcji` in every cell (`MealPlanGrid.vue:39`). CI starts from a fresh DB, so the page baselines at threshold 0 fail on every run. Locally the bug hides, because the recipes survive between runs.
- **Fix**: Pin `servings` along with the names: update the first 7 recipes per category in place, and assert the servings in the seeder test.
- **Decision**: FIXED

### F2 — Parallel slices collide on the same shared files and baselines

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architectural Fitness
- **Location**: Phase 1 §4, Phase 4 §4–5, criterion 4.5
- **Detail**: Several parallel branches touch the same files:
  - `worktree-auth-profile-ui` (S-13) has already committed changes to `DesignSystem.vue` ("Komponenty aplikacji"), `section-komponenty-aplikacji.png` and other kitchen-sink PNGs, `design-system.spec.ts` and `design-system.md`.
  - The family-members-ui plan edits `E2eMealPlanSeeder.php` and the CLAUDE.md E2E bullet.
  - In the roadmap, S-10 and S-11 still say "the first one that needs them builds `PageHeader`/`EmptyState`".

  The exact PNG delta in 4.5 and the §7 counts are computed against today's develop.
- **Fix A ⭐ Recommended**: Claim the components in the roadmap at the start of Phase 1, and merge develop before recording baselines in Phase 4.
  - Strength: The delta check stays meaningful, there is one `PageHeader`, and the conflicts are resolved once.
  - Tradeoff: One extra sync step before Phase 4.
  - Confidence: HIGH — the S-13 branch diff shows the overlap directly.
  - Blind spot: The S-10/S-11 sessions must read the roadmap update.
- **Fix B**: Order the merges, with S-13 into develop before Phase 4.
  - Strength: Less work inside this slice.
  - Tradeoff: Couples S-09 to S-13's schedule, and does not fix the `PageHeader` race.
  - Confidence: MED.
  - Blind spot: The timing of S-12.
- **Decision**: FIXED (Fix A)

### F3 — How to read Inertia props in the visual spec is unspecified (v3)

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 4 §3
- **Detail**: Inertia 3.11 with SSR writes the page JSON to `<script data-page="app" type="application/json">`, not to `#app[data-page]` (inertia-laravel `Directive.php:24`). It holds only the initial load. No existing spec reads props.
- **Fix**: Specify the `script[data-page="app"]` locator, read right after `page.goto('/meal-plans')`.
- **Decision**: FIXED

### F4 — Seeder test misses two setup details

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 4 §2
- **Detail**: Two details are missing:
  - `RefreshDatabase` is not global (`tests/Pest.php:20`).
  - `phpunit.xml:22` pins `AI_SCALING_DRIVER=fake`, and the binding reads `services.ingredient_refiner.driver` (`AppServiceProvider.php:31-48`), so changing the env in the test does nothing.
- **Fix**: Spell out `uses(RefreshDatabase::class)` and `config(['services.ingredient_refiner.driver' => 'openrouter'])` plus `Http::preventStrayRequests()`.
- **Decision**: FIXED

### F5 — Kitchen-sink demos must not reuse names the spec clicks

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §4, Phase 2 §4
- **Detail**: `design-system.spec.ts` clicks `Otwórz menu` (:125), `Otwórz dialog` (:80) and `Otwórz panel` (:116) without `exact`. ActionDropdown's trigger is also named "Otwórz menu" (`ActionDropdown.vue:6`), so it would cause a strict-mode failure in a demo.
- **Fix**: State in Phase 1 §4 that the demos contain no `ActionDropdown` and no button whose name contains these locators.
- **Decision**: FIXED
