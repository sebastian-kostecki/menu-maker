<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Error Reporting Baseline

- **Plan**: context/changes/error-tracking-baseline/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-13
- **Verdict**: REJECTED
- **Findings**: 1 critical, 5 warnings, 3 observations

Automated checks re-run during this review (Sail): Pest 148 passed (527 assertions), Larastan no errors (baseline untouched), Pint passed, `npm run build` (client + SSR) built. Manual items 1.4, 2.4, 3.5, 3.6 are unchecked in Progress (pending, not falsely ticked).

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | FAIL |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Findings

### F1 — Alert mail is not sent from queue workers; buffer grows unbounded

- **Severity**: ❌ CRITICAL
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Safety & Quality
- **Location**: app/Logging/CreateMailAlertLogger.php:86-93
- **Detail**: `DeduplicationHandler` extends `BufferHandler` (bufferLimit 0, level Debug). It only passes records on in `flush()`, reached via a shutdown function registered on first record (`BufferHandler.php:68`) or an explicit `close()`/`reset()`. Nothing in `Illuminate/Log` or `Illuminate/Queue` calls `reset()`/`close()` in the `queue:work` loop (grep verified). In a daemon worker, the job-failure alert (the stated reason for this feature, plan overview and S-03) is mailed only when the worker exits (max-time, deploy, restart), or never on kill/OOM. Every record at every level (including `info` lines) also accumulates in memory for the worker's life. php-fpm requests are fine (flush at shutdown, after the response). The plan itself prescribed this handler chain, so this is a plan flaw, not only an implementation slip. Tests miss it because they call `->getLogger()->close()` by hand.
- **Fix A ⭐ Recommended**: Replace the buffering chain with a small non-buffering handler (a `MailHandler`/`AbstractProcessingHandler` subclass at level `error`) that dedups per record with `Cache::add($key, 1, 3600)` and sends immediately, inside a `WhatFailureGroupHandler`.
  - Strength: Sends at the moment of the error in web and worker alike; no buffering, so no memory growth; the key is written only when `Cache::add` wins, and can be made to follow a successful send (also fixes the dedup-before-send problem); needs no file store. Project has no Redis, but the `database`/`file` cache store works.
  - Tradeoff: Drops stock `DeduplicationHandler` (plan said "built from stock Monolog handlers"); a small custom handler plus test rewrite.
  - Confidence: HIGH — failure mode verified in vendor source.
  - Blind spot: Cache driver choice in production (F-02) and its atomicity under concurrent workers.
- **Fix B**: Keep `DeduplicationHandler`, set `bufferLimit` with `flushOnOverflow`, add a `Level::Error` filter in front of it, and call `Log::channel('mail_alert')->getLogger()->reset()` from `Queue::after`, `Queue::failing` and `Looping` listeners.
  - Strength: Keeps the plan's stock-handler design and the file dedup store.
  - Tradeoff: Wiring in a service provider; flush timing depends on queue events firing (not on timeouts/kill); dedup-before-send and unwritable-store problems remain.
  - Confidence: MEDIUM — depends on which queue events fire for each failure type.
  - Blind spot: Behaviour with `QUEUE_CONNECTION` other than the one in production.
- **Decision**: FIXED via Fix A — new non-buffering `App\Logging\MailAlertHandler` (immediate send, `file` cache store for dedup so a DB outage cannot disable alerts, key written after a successful send, 60 s backoff after a failed send); `DeduplicationHandler` removed

### F2 — Channel build can recurse with MAIL_MAILER=log and silently disables alerting on bad config

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Logging/CreateMailAlertLogger.php:44-51
- **Detail**: The factory calls `Mail::mailer()->getSymfonyTransport()` at channel build time. With `MAIL_MAILER=log` (the `.env.example` default and the setup used by manual check 2.4), `MailManager::createLogTransport` (`MailManager.php:466-477`) resolves the default log channel (`stack`), which is still being built and not yet cached in `LogManager`, so it re-enters `mail_alert`. By code reading this looks like unbounded recursion, ending in a fatal error that `LogManager::get`'s try/catch cannot catch. Not executed: inferred. Separately, address construction, `->to(trim($recipient))` and `Mail::mailer()` run outside `WhatFailureGroupHandler`: an invalid or comma-separated `LOG_ALERT_MAIL_TO`, or empty `mail.from.address`, makes `LogManager` fall back to the emergency logger (likely duplicate lines in a stack) and alerts are silently off.
- **Fix**: Build the `Email` and resolve the transport lazily at send time (a transport wrapper whose `send()` calls `Mail::mailer()->getSymfonyTransport()`), inside the failure-swallowing group; accept comma-separated recipients and validate them; confirm with `MAIL_MAILER=log LOG_STACK=daily,mail_alert LOG_ALERT_MAIL_TO=… ` under Sail before relying on manual check 2.4.
  - Strength: Removes the build-time recursion and all build-time failure modes in one change.
  - Tradeoff: Slightly more code than the current inline construction.
  - Confidence: MED — recursion is inferred from code, not reproduced.
  - Blind spot: Not run; reproduce first.
- **Decision**: FIXED — transport resolved lazily at send time; recipients parsed as a comma-separated list, invalid entries dropped; test for `MAIL_MAILER=log` with `mail_alert` in the default stack

### F3 — Dedup key is weak and alert body/subject can carry PII

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Logging/CreateMailAlertLogger.php:78-91, config/database.php (no `mask_bindings_in_exception_messages`)
- **Detail**: Monolog dedups on level + first line of message. `QueryException` messages embed the SQL with bindings substituted (`Str::replaceArray`) because the connection does not set `mask_bindings_in_exception_messages`. Effects: during a DB outage with database sessions the message differs per request (session id), so dedup never matches and each distinct message is mailed with no global cap; user e-mails, names and password hashes from insert/update queries can reach the subject (150 chars) and the body. In the other direction, different exceptions sharing a generic message (e.g. "Call to a member function x() on null") are merged for an hour. The body also includes stack traces (`getTraceAsString` args are included unless `exception_ignore_args=On`; production php.ini unverified).
- **Fix**: Set `'mask_bindings_in_exception_messages' => true` on the pgsql connection; dedup on exception class + file:line (from `$record->context['exception']`) falling back to level + message; add a global cap (e.g. max N mails/hour); confirm `exception_ignore_args=On` for production in F-02.
  - Strength: Closes the PII path at the source and makes the "once per hour per error" promise true.
  - Tradeoff: Needs the custom key logic (natural if F1 Fix A is chosen).
  - Confidence: HIGH — `QueryException` behaviour read from vendor; config grep empty.
  - Blind spot: Production php.ini and the DB driver actually used on Mikrus.
- **Decision**: FIXED — dedup key is exception class + file:line (fallback level + first line), global cap `max_per_window` (20/h), `mask_bindings_in_exception_messages` on pgsql, subject sanitised. Still open for F-02: confirm `exception_ignore_args=On` in production php.ini

### F4 — Log forging through the client-error endpoint

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/ClientErrorController.php:17-21, app/Http/Requests/StoreClientErrorRequest.php
- **Detail**: Unauthenticated guests can send `message`, `url`, `stack` with raw newlines. The message is concatenated into the log message (`'Client error: '.$message`), and Laravel's `LineFormatter` runs with inline line breaks allowed, so an attacker can write fake `[timestamp] production.CRITICAL: …` lines into `storage/logs/laravel-*.log`. Context strings starting with `{` are also unescaped back into real newlines by `LineFormatter`. Mail is unaffected (warning is below the alert threshold), but log readers and any future log parsing are misled.
- **Fix**: Keep user text out of the message (constant `'Client error'`) and strip control characters (`/[\x00-\x1F\x7F]+/`) from `message`, `url`, `source`, and collapse `stack` newlines to a visible marker, in the controller or the form request (`prepareForValidation`).
  - Strength: Small, local change; keeps the endpoint log-only as the plan intends.
  - Tradeoff: Stack traces become single-line; add a test for a payload with newlines.
  - Confidence: HIGH — formatter settings read from `LogManager.php:524-526` per the review.
  - Blind spot: Exact `LineFormatter` unescape behaviour for `stack` not reproduced.
- **Decision**: FIXED — constant message `Client error`, user text only in context with control characters removed and stack line breaks collapsed to ` | `

### F5 — Browser report logs the full URL (query + hash) and has large abuse headroom

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/app.js:27,38,47, app/Http/Requests/StoreClientErrorRequest.php, routes/web.php:46-48
- **Detail**: `url` is `window.location.href`. `routes/auth.php` has `reset-password/{token}` and Breeze links carry `?email=…`, so an error on that page logs the reset token and the user's e-mail, breaking the plan's "no personal data beyond user_id" rule. Search/filter query strings leak too. Separately, one IP can write about 10 × 8 KB per minute (roughly 115 MB/day, kept 14 days); the throttle keys on user id when signed in, and there is no `trustProxies` configuration, so behind a reverse proxy every guest shares one bucket (or, with a wildcard-trusted proxy, `X-Forwarded-For` bypasses it).
- **Fix**: Send `location.pathname` from JS and strip `?` and `#` server-side too; lower the limits (message 500, stack 2000); note `trustProxies` as a requirement for F-02.
  - Strength: Removes the PII leak with a few lines; limits disk growth.
  - Tradeoff: Query context is lost when debugging an error.
  - Confidence: HIGH — route and URL source verified.
  - Blind spot: Proxy setup on Mikrus is in `kostecki-infra`, not this repo.
- **Decision**: FIXED — JS sends `location.pathname`, server strips `?` and `#`; limits lowered to message 500 / stack 2000 / url 1000. Still open for F-02: configure `trustProxies` on the production host

### F6 — Timeout / max-attempts job failures are no longer logged or alerted

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: app/Jobs/GenerateMealPlanJob.php:121-136
- **Detail**: The plan removed the job's own `Log::error` calls so the worker's report of the rethrown exception is the single entry. That holds for exceptions thrown out of `handle()`. For timeouts and max-attempts failures `Worker::failJob()` only calls `$job->fail($e)` (verified, `Worker.php:866-869`), which invokes `failed()` without calling the exception handler's `report`. `failed()` now only updates status, so these failures leave no log entry and no alert. The job has no `$timeout`/`$tries` and still contains `sleep(2)` (placeholder). Plan flaw, not an implementation slip.
- **Fix**: In `failed()`, `report($exception)` (or `Log::error`) only when `$exception` is a `MaxAttemptsExceededException` or `TimeoutExceededException`, so the common path still yields exactly one entry.
  - Strength: Restores observability for the cases the worker does not report, without reintroducing double logging.
  - Tradeoff: Slightly more logic in `failed()`; add a test per exception type.
  - Confidence: MED — `Worker.php` max-attempts path read in part; the timeout signal path not traced in full.
  - Blind spot: Behaviour with a future `$tries` setting.
- **Decision**: FIXED — `failed()` calls `report()` for `MaxAttemptsExceededException` (covers `TimeoutExceededException`, its subclass) only

### F7 — Touched files without `strict_types`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: bootstrap/app.php, config/logging.php
- **Detail**: CLAUDE.md says to add `declare(strict_types=1);` when touching a file. Both were modified by this change and lack it. (`routes/web.php` and `HandleInertiaRequests.php` also lack it; routes/web.php was touched in Phase 3.) New files all have it.
- **Fix**: Add `declare(strict_types=1);` to `bootstrap/app.php`, `config/logging.php`, `routes/web.php`.
- **Decision**: FIXED — `declare(strict_types=1);` added to bootstrap/app.php, config/logging.php, routes/web.php

### F8 — Test gaps and one weak assertion

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Unit/Logging/CreateMailAlertLoggerTest.php:103, tests/Unit/Jobs/GenerateMealPlanJobTest.php:56-72
- **Detail**: The "throwing transport" test ends with `expect(true)->toBeTrue()` and does not assert the transport was invoked (a `NullHandler` path would also pass). The job tests cover `failed()` only, not the `handle()` catch path (`Context::add('meal_plan_id')` and no `Log::error`). Nothing covers `MAIL_MAILER=log` with `mail_alert` in a stack (F2), worker flushing (F1), subject/body sanitisation, or newline payloads (F4).
- **Fix**: Count `doSend` calls in the throwing-transport test; add a `handle()` failure test; add regression tests alongside whichever of F1-F5 get fixed.
- **Decision**: FIXED — throwing-transport test now counts attempts; added `handle()` failure test, `report()` tests, newline/URL/cap/subject/recipient/recursion tests

### F9 — Manual checks pending while change.md says `implemented`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/error-tracking-baseline/plan.md (Progress 1.4, 2.4, 3.5, 3.6)
- **Detail**: All four manual items are unchecked (correctly, not falsely ticked), but `change.md` was set to `implemented` at epilogue. Manual 2.4 uses `MAIL_MAILER=log` with `LOG_STACK=daily,mail_alert`, which is the exact setup at risk in F2, and manual items would not have surfaced F1 (queue worker) unless a real worker is run. 3.6 should also cover that ResizeObserver / "Script error." noise can consume the 5-report cap before a real error.
- **Fix**: Run the four manual checks after F1/F2 are resolved, including one with a real `queue:work`, and tick them in Progress before merge.
- **Decision**: ACCEPTED — user accepted the review on 2026-11-13; manual checks 1.4, 2.4, 3.5, 3.6 remain to be run by a human (see Manual verification below)

## Triage (2026-11-13)

User chose "fix the errors yourself according to your recommendations". F1-F8 fixed, F9 accepted (manual checks still to be run by the human).

Verification after the fixes (Sail): Pest 161 passed (552 assertions), Larastan no errors (baseline untouched), Pint passed, `npm run build` built client and SSR.

### Manual verification still required

- 1.4: with `LOG_STACK=daily`, a forced exception while signed in writes one entry with `user_id`. Do the same with a failing job (`meal_plan_id`).
- 2.4: with `LOG_STACK=daily,mail_alert`, `LOG_ALERT_MAIL_TO=you@example.com`, `MAIL_MAILER=log`, one forced error gives one alert and an identical repeat gives none. Also run it once from a real `sail artisan queue:work` with a failing job: the alert must arrive while the worker keeps running (this was the F1 failure mode).
- 3.5, 3.6: browser checks as in the plan. The logged `url` is now the path only.

### Carried to F-02 (deploy)

- `trustProxies` for the production host (throttle keys on client IP).
- `exception_ignore_args=On` in the production php.ini.
- `.env`: `LOG_STACK=daily,mail_alert`, `LOG_LEVEL=info`, `LOG_ALERT_MAIL_TO`, and the `file` cache store must be writable (`storage/framework/cache`).
