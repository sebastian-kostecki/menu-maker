---
change_id: laravel-13-upgrade
title: Upgrade to Laravel 13 with Boost 2 and Tinker 3
status: implemented
created: 2026-11-13
updated: 2026-11-13
archived_at: null
---

## Notes

Stage 3. laravel/framework 12 -> 13, laravel/tinker 2 -> 3, laravel/boost 1 -> 2 (then refresh Boost guidelines in CLAUDE.md, still showing PHP 8.4 / Pest 3), sanctum. Read the official upgrade guide via Boost search-docs first.

Unattended decisions (user: "follow the recommendations"):
- Complexity LOW, no interview questions; research + official guide settled all decisions.
- Plan approved automatically with 3 phases (bump, Boost guidelines, final gate).
- Pest/PHPUnit/Inertia/Vite/Tailwind stay out of scope; session serialization untouched.
- Plan review: verdict SOUND, one low observation (verify Boost 2 command name) applied.
