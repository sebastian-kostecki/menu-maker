<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Spiżarnia App Shell

- **Plan**: context/changes/spizarnia-app-shell/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-11-17
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 7 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Success criteria re-run (2026-11-17, in the `menu-maker-laravel.test-1` container against this worktree)

| Check | Result |
|---|---|
| `npm run build` (client + SSR) | PASS. It first failed because `public/build` is owned by root; it passed when rerun as root. |
| `npm run lint:tokens` + `scripts/lint-tokens.test.sh` | PASS (76 files, 0 hits; self-test passes) |
| Grep 1.3 (`AppSidebar` / `Zwiń panel boczny` / `menu-maker:app-shell`) | PASS (no matches) |
| Grep 2.3 (tagline / footer copy, `<h1` in `Pages/Auth`) | PASS (no matches) |
| Pest | PASS, 559 tests. The first run had 15 failures because `storage/logs/laravel.log` is owned by root; it passed when rerun with `LOG_CHANNEL=stderr`. |
| Larastan | PASS. The first run failed because `/tmp/phpstan` was not writable; it passed when rerun as root. |
| Pint | PASS (188 files) |
| Visual gate at HEAD (before the fixes) | PASS, 58/58, run against `php artisan serve` from this worktree on port 8090 (`VISUAL_BASE_URL=http://laravel.test:8090`) |
| Visual gate after the fixes | PASS, 58/58. Exactly 5 PNGs refreshed (4 guest shots + `section-ladowanie-i-rozwijanie`), then a second run without `--update-snapshots` passed 58/58. |
| E2E (chromium + firefox) after the fixes | PASS, 36/36, including password reset through Mailpit. Run in the pinned Playwright image on `menu-maker_sail` against the same server. |

Manual rows 1.8–1.12 and 2.7–2.11 are all still `[ ]` (pending). None is falsely ticked.

## Findings

### F1 — Baseline set exceeded the plan's allowed list, yet 1.7/2.6 are ticked

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: plan.md:296-300, plan.md:448-450, Progress 1.7 / 2.6
- **Detail**:
  - The plan says the changed set must be a subset of the listed baselines and that "any other changed PNG is a regression to fix, not to accept".
  - Phase 1 refreshed 28 baselines, against the 24–25 allowed. The extras are `overlay-dialog`, `section-jadlospis` and `section-komponenty-aplikacji`.
  - Phase 2 refreshed 10, against the 6 allowed. The extras are `focus-visible`, `section-jadlospis`, `section-komponenty-aplikacji`, `section-komunikaty`, `section-paginacja` and `section-przyciski`.
  - A pixel diff shows the extras are benign. Each image keeps its size, and only 59–526 pixels differ, all on anti-aliased curve edges. They changed because the intended height changes in Powłoka and Typografia moved the sections below them (−132px in phase 1, +84/+104px in phase 2).
  - The prediction in the plan was wrong, but Progress 1.7 and 2.6 claim criteria that literally didn't hold, and nothing records the deviation.
- **Fix**: Add a short addendum under Phase 1 change 10 and Phase 2 change 7 listing the extra shots and the cause (a layout shift below a resized kitchen-sink section). Add one sentence to design-system.md §7.3 saying that changing a section's height may refresh the shots of the sections below it.
- **Decision**: FIXED: plan.md addendum (impl-review, 2026-11-17) lists the extra shots and their cause. design-system.md §7 notes that changing a section's height can refresh the shots of the sections below it.

### F2 — Guest panel `<aside>` is a complementary landmark and category blocks are read aloud

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Layouts/GuestLayout.vue:20-41
- **Detail**:
  - The decorative panel is an `<aside>`, so screen readers announce a "complementary" region before the form.
  - The three category blocks are read as "Śniadanie Obiad Kolacja" ahead of the h1.
  - The plan specified `<aside>` (plan.md:388), so this is a plan defect the implementation carried over. The slogan was already made a `<p>` so the page outline stays h1-only, and the same reasoning applies to the blocks.
- **Fix**: Change the panel to a `<div>` (the brand link stays reachable) and add `aria-hidden="true"` to the category-blocks row.
- **Decision**: FIXED: the panel is a `<div>` and the category-blocks row has `aria-hidden="true"`. No E2E spec references the panel. The guest baselines did not change because of this; the F5 spacing change refreshed them.

### F3 — `Avatar variant="sticker"` has no kitchen-sink demo

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Pages/Dev/DesignSystem.vue:666, resources/js/Components/ui/avatar/index.js
- **Detail**:
  - CLAUDE.md says "a new primitive variant … gets a demo, with its states, in the kitchen sink".
  - The avatar demo still shows only the default look. The sticker appears only inside `UserMenu` in "Powłoka", and its hover and focus states have no shot.
- **Fix**: Add `<Avatar variant="sticker">` next to the existing avatar demo (which refreshes `section-*` shots deliberately).
- **Decision**: FIXED: `<Avatar variant="sticker">` was added next to the default avatar in "Ładowanie i rozwijanie". `section-ladowanie-i-rozwijanie.png` was refreshed and reviewed. The §5.1 avatar row mentions the demo.

### F4 — Drawer close-on-navigate isn't actually proven by the E2E test

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/e2e/app-shell.spec.ts (drawer test)
- **Detail**:
  - The pages don't use persistent layouts, so `AuthenticatedLayout` remounts on every visit.
  - The `expect(dialog).toBeHidden()` after navigation would therefore pass even without the `@navigate` / `@start` handlers that set `mobileNavOpen = false`.
  - The behaviour works today but has no regression guard.
- **Fix**: Accept this as is, because the behaviour is covered by the remount. Alternatively, add a check that the dialog is hidden while the request is held, using `page.route` to delay it.
- **Decision**: ACCEPTED: the remount covers close-on-navigate. No extra test was added (recommended option).

### F5 — Guest header spacing: 12px instead of the planned 8px

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/Layouts/GuestLayout.vue:44
- **Detail**: The plan put the description directly in the `gap-5` column with `-mt-3`, which leaves 8px under the h1. The code wraps the h1 and description in `flex flex-col gap-3`, which leaves 12px. That is 4px more than the mockup.
- **Fix**: Change the wrapper to `gap-2`, or record 12px as the deliberate value. Either way, the `guest-*` baselines refresh.
- **Decision**: FIXED: the wrapper is `gap-2`, as the plan intended. guest-login, guest-login-mobile, guest-register and guest-forgot-password were refreshed and reviewed.

### F6 — `AppBrand` overrides the `text-section-title` role weight

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Components/Common/AppBrand.vue:28
- **Detail**: `text-section-title font-extrabold` raises the role's built-in 700 to 800. The plan asked for it, and the mockup wordmark is 800, but §10.3 says each role is a single class that carries its own weight.
- **Fix**: Note the wordmark as an explicit exception in §10.3, or in the `AppBrand` row of §5.2.
- **Decision**: FIXED: §10.3 notes the wordmark exception: `text-section-title font-extrabold`, 800 as in the mockup.

### F7 — New Common components use untyped `defineProps`

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Components/Common/AppBrand.vue, MainNav.vue, TextLink.vue
- **Detail**:
  - The new components use plain `<script setup>` with runtime props.
  - `PageHeader`, `EmptyState`, `FormField` and `ConfirmDialog` use `lang="ts"` with typed `Props`.
  - The folder is already mixed (`UserMenu` and the deleted `AppSidebar` were plain JS), so this isn't a break of an established rule.
- **Fix**: Leave as is, or convert the three components to `lang="ts"` with union types for `tone` and `orientation`.
- **Decision**: ACCEPTED: left as plain JS (recommended option). The Common/ folder is already mixed.

### F8 — Register password fields are about 222px wide side by side

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Pages/Auth/Register.vue:61
- **Detail**:
  - The `sm:grid-cols-2` layout sits inside the `max-w-116` (464px) column, so Polish validation messages under each field will wrap heavily.
  - The layout follows the plan and the mockup. No baseline shows the error state.
  - The children of the new grid were also not re-indented.
- **Fix**: Check the layout with validation errors during manual step 2.10, and keep it only if it reads well.
- **Decision**: ACCEPTED: deferred to manual step 2.10. The plan.md addendum adds a check of Register with errors on both password fields.

### F9 — Uncommitted roadmap edit still says S-22 `in-progress`

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/roadmap.md:47, :124 (working tree, uncommitted)
- **Detail**: The working tree has an uncommitted change that moves S-22 from `proposed` to `in-progress`, although `change.md` says `implemented`. It isn't part of either phase commit.
- **Fix**: Commit the roadmap edit, or set it to `done` when archiving.
- **Decision**: FIXED: the roadmap edit (S-22 `in-progress`) is committed with the review fixes. It is set to `done` when the change is archived.
