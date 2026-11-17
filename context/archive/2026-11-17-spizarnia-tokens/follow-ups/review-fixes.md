# Review follow-ups: spizarnia-tokens

Source: `reviews/impl-review.md` (2026-11-17)

## F3 — Firefox E2E race in the meal-plan flow (separate change, outside F-05)

- **Where**: `tests/e2e/meal-plan.spec.ts:3` ("user drafts, swaps, approves and reopens a weekly meal plan"), `[firefox]` project only.
- **Symptom**: `expect(getByText('Generujemy jadłospis…')).toBeVisible()` times out after 5 s. The plan reaches `done` through the `fake` driver on the queue before the transient "generating" state is asserted. Chromium passes. Re-run on 2026-11-17: 35 of 36 pass.
- **Why it's not F-05**: the locator is text-based and the token change does not touch it. The implementer recorded that it fails identically on the pre-change tokens (Progress row 3.2).
- **Proposed fix**: make the step tolerate a fast transition: assert that either "Generujemy jadłospis…" or the finished-plan state is visible, then continue from the finished state. Alternatively, hold the job in the test until the generating state has been asserted.
- **Done when**: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e` is 36 of 36 on both projects, and row 3.2 of this plan can be ticked.

## Flaky visual shot `shell nav hover` (found while applying the review fixes)

- **Where**: `tests/e2e/visual/design-system.spec.ts:57` → `shell-nav-hover.png`.
- **Symptom**: with an unchanged baseline it sometimes passes and sometimes fails. Observed on 2026-11-17: in 4 runs against the HEAD baseline it failed twice, including 1 of 2 isolated runs (`-g 'shell nav'`). An `--update-snapshots` run also wrote a different image. The diff is confined to about 18×19 px around the "Jadłospisy" icon at (26,174)–(44,193), at most 7 RGB levels, so `threshold: 0.005` + `maxDiffPixels: 0` rejects it.
- **Not caused by**: the review fixes (grid bars, font check, preconnect, overlay swatch), none of which touches the shell nav. `toHaveScreenshot` already disables CSS transitions.
- **Proposed fix**: find out why the hovered icon renders differently between runs, e.g. by asserting the hovered state before the screenshot (`expect(link).toHaveCSS('background-color', …)`), or by moving the mouse away and back. Then regenerate this one PNG.
- **Done when**: 5 consecutive `npm run test:visual -- -g 'shell nav'` runs pass on one baseline.
