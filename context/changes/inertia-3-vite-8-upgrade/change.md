---
change_id: inertia-3-vite-8-upgrade
title: Upgrade to Inertia 3, laravel-vite-plugin 3 and Vite 8
status: impl_reviewed
created: 2026-11-13
updated: 2026-11-13
archived_at: null
---

## Notes

Stage 5. inertia-laravel 2 -> 3 and @inertiajs/vue3 2 -> 3 together, laravel-vite-plugin 2 -> 3, vite 7 -> 8. Highest frontend risk: SSR, Ziggy, forms. Verify with E2E.

Auto decisions (unattended): complexity MEDIUM accepted; 0 interview questions (research settled all); phases approved as proposed; @inertiajs/vite plugin not adopted (minimal upgrade).

Plan review: SOUND, 1 warning (SSR fallback) addressed; check Sail node version in phase 2.
Impl review: APPROVED (1 observation: config pages.paths case, import.meta.dirname, x-inertia::head adaptations accepted).
