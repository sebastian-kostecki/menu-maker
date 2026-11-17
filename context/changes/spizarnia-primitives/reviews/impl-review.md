<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Spiżarnia Primitives Implementation Plan

- **Plan**: context/changes/spizarnia-primitives/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-17
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 5 observations

All automated rows were re-run on the worktree build (served on `:5174`):
- build, `lint:tokens` (74 files, 0 hits) and its self-test pass;
- Pest: 559 passed;
- E2E: 36/36 passed. `password-reset.spec.ts` needs `MAILPIT_URL=http://localhost:8026` in this environment because the dashboard port is not 8025; it fails the same way on `develop` without it;
- visual gate: 56/56 passed;
- the kitchen-sink folder has 33 baselines.

All 13 Manual rows (1.5–1.6, 2.5–2.6, 3.5–3.7, 4.6–4.8) are still pending. They are unticked, not falsely ticked.

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Findings

### F1 — "Lista zakupów" title lost its bold weight

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/MealPlans/ShoppingList.vue:5 (with ui/card/CardTitle.vue)
- **Detail**: `CardTitle` went from `font-semibold` to the `text-section-title` role, which carries its 700 weight through `--text-section-title--font-weight`. `ShoppingList` passes `text-lg`. tailwind-merge treats both as font sizes and drops the role, and the weight goes with it. The title now renders at 400, which you can see in the regenerated `meal-plan-show-desktop.png`. The plan's premise that "views never override shape or colour" missed size overrides. This is the only `CardTitle` with a size override.
- **Fix**: Drop `text-lg` from `ShoppingList.vue:5` (the role gives 20px/700), then regenerate the two `meal-plan-show-*` baselines and review them.
- **Decision**: FIXED — dropped `text-lg` in ShoppingList; meal-plan-show baselines regenerated

### F2 — FamilyMemberPagination did not get the page-pill contract

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: resources/js/Components/FamilyMembers/FamilyMemberPagination.vue:15
- **Detail**: Phase 1 #3 promised `.s-pages` pills for both `ServerPagination` and `FamilyMemberPagination` "without touching either wrapper". `FamilyMemberPagination` doesn't use `ui/pagination`. It calls `buttonVariants({ variant: 'outline', size: 'sm' })` directly, so its prev/next links carry the sticker shadow and the 3px press-in. The plan contract excludes both on page controls, and `PaginationPrevious` and `PaginationNext` drop them. Its disabled state is an `aria-disabled` `<span>`, and `disabled:shadow-none` never matches a span, so the "disabled" link keeps its shadow. That breaks the §10.4 disabled rule. No visual shot covers this view. The flaw is in the plan, not the implementation.
- **Fix A ⭐ Recommended**: Add `'shadow-none active:translate-y-0'` to `linkClass` so it matches `PaginationPrevious`/`PaginationNext`.
  - Strength: A one-line view edit makes both pagination wrappers consistent, and the disabled shadow disappears with it.
  - Tradeoff: It touches a view, which the plan meant to avoid; no baseline covers it, so check it by hand on `/family-members` with more than one page.
  - Confidence: HIGH — the same class string is already used in `ui/pagination/PaginationPrevious.vue`.
  - Blind spot: None significant.
- **Fix B**: Leave the code and add a carry-over to S-26 (family members) in `roadmap.md`.
  - Strength: Keeps F-06 free of view edits; S-26 re-lays out this screen anyway.
  - Tradeoff: The inconsistent and disabled-with-shadow state ships until S-26.
  - Confidence: MED — depends on when S-26 lands.
  - Blind spot: S-26's scope may not pick up pagination.
- **Decision**: FIXED (Fix A) — `shadow-none active:translate-y-0` in `linkClass`

### F3 — input-focus-visible.png does not capture the focus ring

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/e2e/visual/design-system.spec.ts:86
- **Detail**: The shot is `input.toHaveScreenshot()`, which captures the input's own box. The focus outline (`outline-2 outline-offset-2`) sits 2–4px outside that box, so it is clipped away. The committed PNG shows only the ink border and the selected text, with no ring. The guard would stay green if field focus styling regressed completely. The chip shot also clips the ring on its left edge. Manual row 4.7 is meant to catch this.
- **Fix**: Screenshot `input.locator('..')`, as the button and chip shots already do (or pass a `clip` padded by at least 6px). Regenerate `input-focus-visible.png` and confirm the ring is visible.
- **Decision**: FIXED — padded viewport `clip` (`focusRingClip`, 8px) for both the input and the chip shot, since `locator('..')` is as wide as the input and would still clip the ring; both PNGs regenerated and show the full ring

### F4 — Button `xs` and `lg` sizes lose the 600 label weight

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Components/ui/button/index.js:23,25
- **Detail**: The base has `text-label` (14px/600). The `xs` size adds `text-xs` and `lg` adds `text-base`, and tailwind-merge drops `text-label` and its weight. `sm` re-adds `font-semibold`; `xs` and `lg` don't. In `section-przyciski.png`, "Mały xs" and "Duży" are visibly lighter than "Mały" and "Domyślny". This breaks "Etykieta 600 – przyciski". No view uses these sizes yet, so the impact is latent.
- **Fix**: Add `font-semibold` to the `xs` and `lg` sizes, then regenerate `section-przyciski.png`.
- **Decision**: FIXED — `font-semibold` on `xs` and `lg`

### F5 — "Błąd" status pill has a soft shadow

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Components/ui/badge/index.js:11,15
- **Detail**: The badge's `default` and `destructive` variants keep the shadcn `shadow-sm`, and `size="status"` doesn't clear it. The error pill from `StatusTag` gets a blurred shadow; the other flat status pills don't.
- **Fix**: Add `shadow-none` to the `size: 'status'` compound variant.
- **Decision**: FIXED — `shadow-none` in the status compound

### F6 — Page pill width is fixed at 44px

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/Components/ui/pagination/PaginationItem.vue:11,30
- **Detail**: `size` defaults to `"icon"`, so `size-8.5` (`width: 34px`) survives the merge next to `h-11 min-w-11 px-3`, because a later `h-*` doesn't remove `size-*`. The pill is pinned at 44px and doesn't grow with its label as `min-w-11 px-3` implies. This is not a regression (it used to be `size-9`), and labels up to three digits still fit inside the border.
- **Fix**: Add `w-auto` to the `PaginationItem` class string.
- **Decision**: FIXED — `w-auto` in PaginationItem

### F7 — Pressed meal-tone chips are neither distinct nor demoed

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Components/ui/toggle/index.js:6
- **Detail**: `data-[state=on]:bg-primary` overrides the meal tones, as the plan intended. A pressed Śniadanie, Obiad or Kolacja chip therefore looks like a pressed default chip, but `hover:brightness-95` still applies. The kitchen sink only demos a pressed `default` chip, and CLAUDE.md asks for each variant's states to be demoed.
- **Fix**: Add one pressed meal-tone chip to the "Pigułki filtra" group in `DesignSystem.vue`, then regenerate `section-przyciski.png`.
- **Decision**: FIXED — pressed `breakfast` chip added to Pigułki filtra

### F8 — SheetHeader not aligned with the new close button

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Components/ui/sheet/SheetHeader.vue:11
- **Detail**: The sheet's close button is now the 34px round button. `DialogHeader` got `pr-12 text-left`, but `SheetHeader` is still `text-center sm:text-left` with no right padding, so a long title can run under the X. Only the kitchen sink renders a visible `SheetHeader` today; the app drawer's title is `sr-only`. The plan leaves the sheet panel layout to S-22.
- **Fix**: Extend the S-22 carry-over in `roadmap.md`: "SheetHeader `pr-12 text-left` like DialogHeader".
- **Decision**: FIXED — S-22 carry-over extended in roadmap.md

### F9 — Two small doc gaps

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/design-system.md:276 (§7.3) and the §5.1 `sheet` row
- **Detail**: Phase 4 #3 asked §7.3 to state the total of 56 shots. The per-spec counts add up to 56, but the total is never written. The §5.1 sheet row also leaves out the new 2px inner edge.
- **Fix**: Add "56 zrzutów łącznie" to §7.3 and the 2px inner edge to the §5.1 sheet row.
- **Decision**: FIXED — §7.3 total (56) and §5.1 sheet row updated

## Triage summary (2026-11-17)

User chose "napraw według rekomendacji": all nine findings fixed (F2 via Fix A). 13 baselines regenerated with `--update-snapshots` and reviewed: `section-odznaki`, `section-przyciski`, `focus-visible`, `section-komponenty-aplikacji`, `section-jadlospis`, `input-focus-visible`, `chip-focus-visible`, `overlay-pdf-download-menu` (14 px in the corner behind the menu's rounded edge), `meal-plan-show-desktop`/`-mobile`, `meal-plans-index-desktop`/`-mobile`/`-generate-dialog`. Re-verified after the fixes: build, `lint:tokens`, visual gate 56/56 (without update), full E2E 36/36.
