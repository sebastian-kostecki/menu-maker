# Tailwind 4 Upgrade — Plan Brief

> Full plan: `context/changes/tailwind-4-upgrade/plan.md`
> Research: `context/changes/tailwind-4-upgrade/research.md`

## What & Why

Upgrade to Tailwind 4 with the Vite plugin and CSS-first config, refresh shadcn-vue components, and bump lucide-vue-next, @vueuse/core and concurrently, keeping the UI visually identical (light and dark).

## Starting Point

Tailwind 3 with `tailwind.config.js`, PostCSS, HSL CSS variables, 15 shadcn-vue components, class-based dark mode.

## Desired End State

Tailwind 4.x, no JS/PostCSS config, builds and tests green, screenshots match the baseline.

## Key Decisions Made

| Decision | Choice | Why | Source |
| --- | --- | --- | --- |
| Integration | `@tailwindcss/vite`, drop postcss/autoprefixer | Official recommendation for Vite | Research |
| Colours | Keep HSL variables via `@theme inline` | Visual parity, no palette change | Plan |
| Animations | `tw-animate-css` | shadcn-vue v4 standard | Research |
| Dark | `@custom-variant dark (&:is(.dark *))` | Keep class-based dark | Plan |
| Migration tool | `@tailwindcss/upgrade` then hand review | Handles renames safely | Plan |

## Scope

**In scope:** Tailwind, shadcn components, three package bumps, docs.
**Out of scope:** recipe Show page bug, dark toggle, redesign.

## Architecture / Approach

Upgrade tool, review diff, CLI refresh of components, bump packages, compare screenshots after each risky phase.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Core migration | Tailwind 4 + CSS config | Visual drift from default changes |
| 2. shadcn refresh | v4 components | Overwriting local edits |
| 3. Package bumps | lucide 1, vueuse 15, concurrently 10 | Renamed icons |
| 4. Docs and verify | Docs, final gates | Missed regressions |

**Prerequisites:** Sail running. **Estimated effort:** ~1 session, 4 phases.

## Open Risks & Assumptions

- Upgrade tool needs Node 20+ (Sail has 22).

## Success Criteria (Summary)

- All gates green; screenshots match baseline.
