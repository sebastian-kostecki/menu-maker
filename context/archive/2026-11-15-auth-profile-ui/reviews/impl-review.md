<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Auth & Profile UI Implementation Plan

- **Plan**: context/changes/auth-profile-ui/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-15
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

### Automated verification (re-run during review)

| Command | Result |
|---|---|
| `vendor/bin/sail npm run build` | PASS |
| `npm run lint:tokens` | PASS — 25 files, 0 hits |
| Breeze import grep (`Pages/Auth`; `Pages` + `Layouts`) | PASS — no output |
| `vendor/bin/sail composer test` | PASS — 368 passed (1365 assertions) |
| Full E2E (`npm run test:e2e`, chromium + firefox) | PASS — 30 passed, including 4 × 2 in `profile.spec.ts` |
| Visual gate (`npm run test:visual`), run 1 | FAIL — 32 passed, 1 failed (`shell nav hover`) |
| `shell nav` tests alone, run 2 and run 3 | PASS — 2/2 each time |
| Visual gate, run 4 | PASS — 33 passed |

### Re-verification after triage fixes (2026-11-15)

| Command | Result |
|---|---|
| `vendor/bin/sail npm run build` | PASS |
| `npm run lint:tokens` | PASS — 25 files, 0 hits |
| Full E2E | PASS — 32 passed (30 + the new autofocus test × 2 browsers) |
| Visual gate, 2 runs | 33/33 PASS, then 32 passed + 1 failed (`shell nav hover` again; see F2) |

Accepted minor drift (no finding):
- `DeleteUserForm.vue:100-108` uses `:open` + `@update:open="handleOpenChange"` instead of `v-model:open`. The behaviour is the same, and the split is what lets closing reset the form.
- The kitchen-sink default `FormField` demo binds only `:id`. It has no error or description, so nothing is lost.

## Findings

### F1 — Autofocus lost after Inertia client-side navigation

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/ui/input/Input.vue:24 (call sites: Pages/Auth/Login.vue:49, Register.vue:34, ForgotPassword.vue:45, ResetPassword.vue:45, ConfirmPassword.vue:36, Profile/Partials/UpdateProfileInformationForm.vue:43)
- **Detail**:
  - Breeze `TextInput.vue:11-14` (at 72ab6b0) focused itself in `onMounted` when it had `autofocus`.
  - `ui/input` only passes the native attribute through, and the browser applies native autofocus only on the first document load.
  - After an Inertia `<Link>` visit (Login → „Nie pamiętasz hasła?”, Register → „Masz już konto?”), no field gets focus.
  - The plan said autofocus is kept (plan.md:209) but did not notice that Breeze focused the field in code.
  - No E2E spec checks focus on arrival.
- **Fix A ⭐ Recommended**: Restore the Breeze behaviour in `ui/input/Input.vue`: `onMounted` focuses the element when `$attrs.autofocus` is present. No `defineExpose`.
  - Strength: one place, the same mechanism Breeze had, and every current call site is fixed. No other view uses `autofocus` (grep).
  - Tradeoff: one more local edit to a shadcn primitive. Add it to the D-9 note in Migration Notes next to the `aria-invalid` classes.
  - Confidence: HIGH — Breeze ran the same logic on the same pages without issues.
  - Blind spot: SSR. `onMounted` does not run on the server, so hydration is unaffected, but this was not tested.
- **Fix B**: Focus the field from each page with a template ref and `onMounted(() => ref.value.$el.focus())`.
  - Strength: the primitive stays untouched.
  - Tradeoff: 6 copies of the same code, and each new form has to remember it.
  - Confidence: MED — the code is simple, but it is spread across pages.
  - Blind spot: none significant.
- **Decision**: FIXED (Fix A). `ui/input` runs `onMounted` → `focus()` when `autofocus` is present. The new E2E test `login.spec.ts` › „autofocuses the first field after a client-side visit” failed on the old build and passes on the fixed one. The D-9 note in the plan is updated.

### F2 — Unplanned baseline changes in phase 1 and a flaky hover shot

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: tests/e2e/__screenshots__/visual/design-system.spec.ts/{section-powloka,section-karta-i-tabela,shell-nav-hover,shell-nav-focus-visible}.png (commit 8347100)
- **Detail**:
  - plan.md:158-164 says any changed PNG beyond the two sections, the new overlay and corner-only overlay diffs is a regression.
  - Commit 8347100 also changed four other baselines. Measured with a pixel diff:
    - `section-powloka`: 331 px, maximum channel difference 28;
    - `section-karta-i-tabela`: 45 px, maximum difference 2;
    - `shell-nav-hover`: 167 px, maximum difference 7;
    - `shell-nav-focus-visible`: 175 px, maximum difference 23.
  - `overlay-user-menu` changed only in its corner columns, which the corner rule allows.
  - These look like sub-pixel shifts, because `section-formularze` grew from 408 to 580 px. The commit and the plan do not explain them.
  - Manual check 1.5 is still unchecked.
  - In this review, the first full visual run failed on `shell nav hover`. The next three runs passed (the test twice alone, then the full gate).
- **Fix**: Look at the four PNG diffs as part of 1.5, and add a short addendum to the plan that explains them. If `shell-nav-hover` fails again in CI, stabilise its hover setup instead of re-baselining.
  - Strength: records the baseline decision before the change is archived, and keeps the visual gate trustworthy.
  - Tradeoff: about 10 minutes of manual review. Stabilising the hover test is a separate small change.
  - Confidence: MED — 1 failure in 4 runs is a weak signal of flakiness.
  - Blind spot: not checked whether `shell-nav-hover` was flaky before this change (baseline from a015ca5).
- **Decision**: FIXED (plan addendum). The four baselines are documented as accepted sub-pixel shifts, and they still have to be looked at under manual check 1.5. `shell nav hover` failed in 2 of 6 local runs. Its diff is confined to the hovered „Jadłospisy” icon (a transition caught mid-way), so stabilising it is recorded as a follow-up for its own change.

### F3 — Profile E2E locates cards by a CSS class

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/e2e/profile.spec.ts:25, :40
- **Detail**:
  - `page.locator('div.rounded-xl')` ties the test to the `ui/card` radius class.
  - A token or radius change breaks the profile spec.
  - Every other spec selects by role, label or text.
- **Fix**: Scope each card by role. Either give each partial's `Card` `role="region"` + `aria-labelledby` (its `CardTitle` id) and use `getByRole('region', { name: … })`, or filter by its heading with `page.locator('div', { has: page.getByRole('heading', { name: … }) }).last()`.
- **Decision**: FIXED. Each profile `Card` has `role="region"` and `aria-labelledby` pointing at its `CardTitle` id (`profile-information-title`, `update-password-title`, `delete-account-title`). `profile.spec.ts` uses `getByRole('region', { name })`, and the label check after the reload is scoped to the card.

### F4 — Every manual verification step is still pending

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/auth-profile-ui/plan.md:441-480 (Progress 1.5–1.7, 2.7–2.10, 3.8–3.11)
- **Detail**:
  - The phases ran unattended, so the „pause for manual confirmation” gates did not happen. None of the 11 Manual rows is checked.
  - These are pending, not faked: no row is marked done without evidence.
  - Separately, `context/foundation/roadmap.md` has an uncommitted edit that sets S-13 to `in-progress`.
- **Fix**: Run the manual checklist (especially 2.8 remember-me across a browser restart, 3.10 keyboard flow in the delete dialog, and 3.9 at 390 px) before merging. Then set S-13's roadmap status and commit it.
- **Decision**: ACCEPTED. The manual checklist and the roadmap status stay with the user; they cannot be done in an unattended run.

### F5 — Profile visual shot skips the font assertion after its own navigation

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/e2e/visual/account.spec.ts:24-28
- **Detail**:
  - After `logIn()`, which checks Figtree on `/dashboard`, the test runs `page.goto('/profile')`. That is a full new document, and it is screenshotted without `expectFigtreeLoaded`.
  - Every other visual shot asserts the font on the document it captures.
- **Fix**: Add `await expectFigtreeLoaded(page)` after `page.goto('/profile')`.
- **Decision**: FIXED

### F6 — Delete-account E2E does not assert refocus on a wrong password

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/e2e/profile.spec.ts:68-90
- **Detail**:
  - plan.md:75 says „a wrong password keeps the dialog open, shows the error under the field and refocuses the field”.
  - The spec checks the error and that the dialog stays open, but not the focus.
  - The code does it (`DeleteUserForm.vue:64`), but nothing guards it.
- **Fix**: After the error assertion, add `await expect(dialog.getByLabel('Hasło', { exact: true })).toBeFocused()`.
- **Decision**: FIXED

### F7 — Inconsistent script style and a duplicated link class string

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Pages/Profile/Edit.vue:7-8 and Partials/*.vue; resources/js/Pages/Auth/Login.vue:80, Register.vue:91
- **Detail**:
  - The `Pages/Auth/*.vue` files were converted to the no-semicolon style (like `Dashboard.vue` and `FormField.vue`). The Profile files kept semicolons, and `Edit.vue` has a stray blank line.
  - The token text-link class string is copied in Login and Register.
- **Fix**: Align the Profile scripts with the Auth style. Leave the link string until S-15, or extract it if a third copy appears.
- **Decision**: FIXED. The Profile scripts use the no-semicolon style and the stray blank line is gone. The link class string is left as recommended.
