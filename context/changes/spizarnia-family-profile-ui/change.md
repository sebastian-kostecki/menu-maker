---
change_id: spizarnia-family-profile-ui
title: Spizarnia family and profile UI
status: impl_reviewed
created: 2026-11-17
updated: 2026-11-18
---

## Notes

<!-- Free-form notes for this change: links, ad-hoc context, decisions that don't belong in research/frame/plan. -->

- Roadmap S-26 (M-4, SP-07). Planned in the worktree `.claude/worktrees/spizarnia-family-profile-ui` on branch `worktree-spizarnia-family-profile-ui` (from `develop` at 5a96cdd). Merge order per roadmap Streams: E (S-26) first, then B (S-23), then C (S-24).
- Unplanned, recorded in impl-review F2 (2026-11-18):
  - Phase 1 (1d5a21c) refreshed `design-system.spec.ts/overlay-confirm-dialog-field.png`. Only 2 pixels changed, on the dialog's left and right edges, by at most 12 RGB levels: anti-aliasing noise from the tile grid shifting the content under the dialog, not a regression.
  - Commit bde0a98 fixes a flake in `tests/e2e/meal-plan.spec.ts`. The spec now waits for the POST `/approve` response and then the shopping list, instead of the short-lived „Generujemy jadłospis…” text. It changes no feature code.
