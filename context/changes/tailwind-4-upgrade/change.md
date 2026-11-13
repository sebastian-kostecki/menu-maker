---
change_id: tailwind-4-upgrade
title: Upgrade to Tailwind 4 and refresh shadcn-vue components
status: implemented
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
- Phase 2 decision: ran shadcn-vue@2 add --overwrite for all 15 components; the current CLI emits the redesigned 'new' style (different Button/Input/Dialog looks, no shadows, ring-3) and rewrites imports to new deps (@lucide/vue, 'cn' package), downgrades @vueuse/core to ^13 and bumps reka-ui. Rejected to protect visual parity and the requested lucide-vue-next 1.0 / vueuse 15; reverted the output and kept the components migrated by @tailwindcss/upgrade (already v4 syntax, e.g. data-highlighted, outline-hidden, shadow-xs). Only components.json changed (tailwind.config set to empty string, as required for Tailwind 4).
- Phase 3: lucide-vue-next 1.0.0, @vueuse/core 15.0.0, concurrently 10.0.7 bumped; no code changes needed (all icon imports and reactiveOmit/useVModel still exist; composer dev concurrently flags still valid).
- Phase 4: CLAUDE.md stack line and cursor rules updated (Boost guidelines in repo did not mention Tailwind 3, no refresh needed). Further parity fixes: index.js variant files (button, badge) are not migrated by the upgrade tool, renamed shadow/outline utilities by hand; v4 auto-source scanning now generates classes from ui/*/index.js that v3 never emitted (e.g. avatar h-10 w-10 rounded-full bg-secondary, button lg px-8), so avatar circle and lg button padding now render as designed (accepted). Inline labels inside space-y containers got block leading-6 (RecipeForm, IngredientRow, FamilyMembers forms) to keep spacing.
