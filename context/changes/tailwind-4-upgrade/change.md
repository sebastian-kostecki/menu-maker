---
change_id: tailwind-4-upgrade
title: Upgrade to Tailwind 4 and refresh shadcn-vue components
status: implementing
created: 2026-11-13
updated: 2026-11-13
archived_at: null
---

## Notes

Stage 6. tailwindcss 3 -> 4 (config moves from tailwind.config.js to CSS), regenerate shadcn-vue components in resources/js/Components/ui, lucide-vue-next 1.0, @vueuse/core 15, concurrently 10. Needs a visual review of all screens.

Unattended-run decisions (made on the user's behalf, recommended options):
- Research done locally (no subagents); baseline screenshots in screenshots/before via screenshots/capture.mjs (not part of the test suite).
- Baseline defect noted, out of scope: /recipes/{id} (Show.vue) renders blank with TypeError on recipe.data.name.
- 10x-plan: complexity assessment and phase breakdown auto-approved (recommended options); 4 phases; kept HSL variables (no oklch conversion) for visual parity.
- 10x-plan-review: quick review by main agent, both warnings fixed (E2E in phase 1, app.css diff after CLI add).
- Phase 1 visual review: 28 screenshots compared (PIL pixel diff + Read). Intended/accepted differences: avatar circle+size now render (v3 did not scan ui/*/index.js, v4 auto-scans), red palette slightly more vivid (v4 oklch palette), header icon x-shift (same cause). Fixed: space-y vs inline label spacing (RecipeForm labels block leading-6), DeleteUserForm trailing gap (flex gap), upgrade tool wrongly renamed variant strings 'outline' -> 'outline-solid' (reverted). Dark-mode large diffs in some pairs are mid-transition capture flakes.
