# Review follow-ups: meal-plan-show-ui

Source: `reviews/impl-review.md` (2026-11-15).

- [ ] **F7 — Roadmap S-09 status.** When the change is merged/archived, set S-09 to `done` in `context/foundation/roadmap.md` (table row `:49` and item `- **Status:**` `:149`). It stays `in-progress` until then on purpose.
- [ ] **F8 (residual) — Energy band drift.** The visual family's birth dates are `now()->subYears(40)` while the plan date is fixed at 2026-06-15, so the 31–50 band (and the ×2.0 amounts) holds until about 2036. Pin the visual user's birth dates relative to `VISUAL_PLAN_START` if that ever matters.
- [ ] **Flaky `shell nav hover` under `--update-snapshots`.** During a full parallel re-record (2026-11-15), `design-system.spec.ts › shell nav hover` captured a 15-pixel variant, and later runs then failed against it. Develop's baseline passes 3/3 in isolation and 2/2 in full runs, so it was kept. If it recurs, wait for the hover transition to finish (or disable transitions) before the screenshot.
