---
change_id: pest-5-upgrade
title: Upgrade to Pest 5 and PHPUnit 13
status: impl_reviewed
created: 2026-11-13
updated: 2026-11-13
archived_at: null
---

## Notes

Stage 4. pestphp/pest 4 -> 5, pest-plugin-laravel 4 -> 5, phpunit 12 -> 13.
- Unattended run: complexity LOW (3 constraint bumps, resolves cleanly in dry-run); question budget 0, plan structure auto-approved with recommended 2-phase split.
- Phase 2: CLAUDE.md Boost block carries no Pest/PHPUnit versions, so no boost:install refresh was needed (hand-written content untouched).
- composer.json/lock only changed by the single require; collision stays 8.9.6; phpunit resolved to 13.4.5 (13.4.6 not yet picked up by composer).
- Archive: warnings were 2 pending Manual rows (1.4, 2.5); unattended, chose Continue archiving. Agent observed no deprecation notices in test output and no CLAUDE.md change.
