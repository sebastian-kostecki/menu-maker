---
change_id: postgres-migration
title: Replace MySQL with PostgreSQL and drop Redis
status: impl_reviewed
created: 2026-11-13
updated: 2026-11-13
archived_at: null
---

## Notes

Stage 2. Decisions (2026-11-13): PostgreSQL replaces MySQL in Sail; backend tests run on PostgreSQL (not SQLite :memory:), CI gets a postgres service; Redis is removed (unused: cache/queue/session use the database). Known risk: case-sensitive LIKE in app/Models/Recipe.php:62. Port clash with household-treasurer (80, 5432) — set APP_PORT/FORWARD_DB_PORT.


## Notes

- Unattended run: plan-review applied non-interactively; F1 (pgsql env contract) fixed in plan.md.
- Unattended: archived with 4 manual Progress rows pending (1.6, 2.6, 3.3, 4.5) - recommended path for manual-only warnings.
- Unattended: legacy Docker volumes menu-maker_sail-mysql / menu-maker_sail-redis not removed (sandbox denied); remove manually.
- Unattended: FORWARD_DB_PORT=5433 set in local .env (port 5432 busy).
