---
date: 2026-11-13T12:00:00+01:00
researcher: Claude (unattended)
git_commit: 37a01cb
branch: develop
repository: menu-maker
topic: "What does it take to move Pest 4->5, pest-plugin-laravel 4->5, PHPUnit 12->13?"
tags: [research, pest, phpunit, composer]
status: complete
last_updated: 2026-11-13
last_updated_by: Claude (unattended)
---

# Research: Pest 5 / PHPUnit 13 upgrade

## Research Question

Which packages must move together, are there breaking changes for this repo's tests, and does Composer resolve?

## Summary

- `composer require --dev pestphp/pest:^5.0 pestphp/pest-plugin-laravel:^5.0 phpunit/phpunit:^13.0 --with-all-dependencies --dry-run` (run in Sail, PHP 8.5.12) resolves: pest 5.3.5, pest-plugin-laravel 5.0.1, phpunit 13.4.5 (the dry-run result; 13.4.6 was reported available by the caller), plus transitive moves of pest-plugin 5.0.0, pest-plugin-arch 5.0.0, pest-plugin-mutate 5.0.2, pest-plugin-profanity 5.0.1, paratest 7.28.0, php-code-coverage 14.5.2 and the sebastian/* set. Two new packages (sebastian/git-state, sebastian/file-filter); three removed (theseer/tokenizer, composer/xdebug-handler, composer/pcre).
- Current constraint blocker: pest 4.7.8 requires phpunit ^12.5.33 and conflicts with newer, so all three must move in one composer command.
- `nunomaduro/collision` stays at ^8.6 (pest 5 resolution does not require a bump in the dry-run; no change listed).
- Official upgrade guide (pestphp.com/docs/upgrade-guide): Pest 5 needs PHP >= 8.4 (Sail runs 8.5.12; host PHP 8.4.1 is not to be used anyway), built on PHPUnit 13, no Pest API removals listed; all `pestphp/pest-plugin-*` go `^4.0` -> `^5.0`.
- Repo-side impact (checked paths: `composer.json`, `phpunit.xml`, `tests/Pest.php`, `tests/`): tests are Pest-style with `uses(TestCase::class)->in('Feature','Unit')`; grep of `tests/` for `createMock|getMockBuilder|@test|@dataProvider|expectsOutput` hits only `tests/Feature/RecipeControllerTest.php` (the `->with(` dataset usage, a Pest API). `phpunit.xml` uses `<source><include>` (already the PHPUnit 10+ format) and no deprecated attributes.
- Direct constraints in `composer.json:29-31` are pest ^4.0, pest-plugin-laravel ^4.0, phpunit ^12.0; `mockery` ^1.6 and `collision` ^8.6 are untouched.

## Code References

- `composer.json:27-31` - require-dev constraints to change
- `phpunit.xml` - schema ref `vendor/phpunit/phpunit/phpunit.xsd`, PostgreSQL `testing` DB env
- `tests/Pest.php` - only `uses(TestCase::class)`

## Historical Context

- `context/archive/2026-11-13-laravel-13-upgrade`, `postgres-migration` - baseline: 129 tests pass on PostgreSQL.

## Open Questions

- PHPUnit 13 may emit new deprecations/warnings at runtime (not detectable statically); verify by running the suite and checking for skipped/risky/warning counts.
- Boost guideline text mentions Pest 4 ("pest/v4 rules"); a `boost:update`/install refresh may be needed after the upgrade (check `boost.json` and CLAUDE.md).
