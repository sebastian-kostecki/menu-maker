# Inertia 3 / Vite 8 Upgrade — Plan Brief

> Full plan: `context/changes/inertia-3-vite-8-upgrade/plan.md`
> Research: `context/changes/inertia-3-vite-8-upgrade/research.md`

## What & Why

Upgrade inertia-laravel and @inertiajs/vue3 to v3, laravel-vite-plugin to 3 and Vite to 8 together (they must move as a set) so the stack stays supported, with no behaviour change.

## Starting Point

Inertia 2, Vite 7, standard SSR entry with Ziggy; only `<title inertia>` is a known breaking item.

## Desired End State

All packages upgraded, full gate and E2E green, SSR verified to render, Boost guidelines refreshed.

## Key Decisions Made

| Decision | Choice | Why | Source |
| --- | --- | --- | --- |
| Optional @inertiajs/vite plugin | Not adopted | Keeps change minimal | Plan (auto) |
| Tailwind | Untouched | Separate change | Brief |
| Config | Publish config/inertia.php | v3 restructured it | Research |
| SSR verification | Manual start-ssr + curl | SSR not in Sail/CI | Plan (auto) |

## Scope

**In scope:** four package bumps, Blade attribute, config, Boost guideline refresh.
**Out of scope:** Tailwind, new v3 features, SSR infra.

## Architecture / Approach

Server first, then JS toolchain, then E2E/SSR verification.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Server adapter | inertia-laravel 3 green on Pest | Composer resolution with Boost 2 |
| 2. JS toolchain | vue3 3, plugin 3, Vite 8 builds | SSR bundle under Vite 8 |
| 3. Verification | E2E, SSR render, Boost docs | SSR silently falling back |

**Prerequisites:** Sail up. **Estimated effort:** ~1 session.

## Open Risks & Assumptions

- Sail Node version must be >= 20.19.

## Success Criteria (Summary)

- All gates green, SSR output confirmed.
