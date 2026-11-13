---
change_id: tailwind-4-upgrade
title: Upgrade to Tailwind 4 and refresh shadcn-vue components
status: plan_reviewed
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
