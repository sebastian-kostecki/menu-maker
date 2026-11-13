---
change_id: postgres-migration
title: Replace MySQL with PostgreSQL and drop Redis
status: new
created: 2026-11-13
updated: 2026-11-13
archived_at: null
---

## Notes

Stage 2. Decisions (2026-11-13): PostgreSQL replaces MySQL in Sail; backend tests run on PostgreSQL (not SQLite :memory:), CI gets a postgres service; Redis is removed (unused: cache/queue/session use the database). Known risk: case-sensitive LIKE in app/Models/Recipe.php:62. Port clash with household-treasurer (80, 5432) — set APP_PORT/FORWARD_DB_PORT.
