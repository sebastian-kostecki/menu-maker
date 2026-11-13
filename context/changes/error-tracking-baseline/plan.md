# Error Reporting Baseline Implementation Plan

## Overview

Make server and browser failures visible without adding any package. Every log entry from a request or a queued job carries `user_id` (and `meal_plan_id` inside meal-plan generation). Production writes daily log files kept for 14 days. Server errors (level `error` and above) send an alert e-mail, at most once per hour for the same error. Unhandled browser errors are logged as `warning` through a throttled endpoint, with no e-mail. Reporting is active only where it is configured (production). Local, test and CI environments stay quiet.

This is roadmap item F-01 (`error-tracking-baseline`). It unlocks S-03 (real weekly meal plan, whose generation runs in a queue where failures never reach the user), S-04 (AI calls) and F-02 (public deploy).

## Current State Analysis

- `bootstrap/app.php:23-25`: `withExceptions` is empty. Nothing beyond Laravel's default reporting is configured.
- `config/logging.php:55-58`: the `stack` channel reads `LOG_STACK`, and `.env.example:18-21` sets `LOG_STACK=single` and `LOG_LEVEL=debug`. The `daily` channel already exists with `days => env('LOG_DAILY_DAYS', 14)` (`config/logging.php:68-74`).
- `composer.json`: no error-tracking package. `vendor/monolog` (v3) ships `DeduplicationHandler`, `SymfonyMailerHandler` and `WhatFailureGroupHandler`.
- `app/Jobs/GenerateMealPlanJob.php:74-94`: the job catches an exception, writes its own `Log::error` with `meal_plan_id` and a trace string, then rethrows. The queue worker then reports the same exception again through the exception handler. `failed()` (`:124-141`) logs a third time. One failure therefore produces 2–3 error entries, which would mean 2–3 alert e-mails. No entry carries `user_id`.
- `vendor/laravel/framework/src/Illuminate/Log/LogManager.php:149-150`: Laravel pushes the `Context` log processor onto every channel, custom ones included. `Context` data added during a request is also carried into jobs that request dispatches.
- Frontend: `resources/js/app.js` has no global error handler. Seven `console.error` calls handle form and validation results, for example `resources/js/Components/MealPlans/ActionDropdown.vue:155,167`. Uncaught errors in Vue components and unhandled promise rejections are lost.
- `.env.example:45` has `MAIL_MAILER=log`. The production mail provider is decided in roadmap S-01. `phpunit.xml:27` sets `MAIL_MAILER=array` and `QUEUE_CONNECTION=sync`.
- The earlier design note "Sentry in production" (`.ai/core/api-plan.md:130`, `.ai/api/meal-plan-implementation-plan.md:114`) is superseded by this plan: the user chose logs + mail.

## Desired End State

- In production (`LOG_STACK=daily,mail_alert`, `LOG_ALERT_MAIL_TO` set), an unhandled exception in a web request or a queued job writes one entry to `storage/logs/laravel-YYYY-MM-DD.log`. The entry carries `user_id`, and `meal_plan_id` inside generation. The recipient gets one e-mail. Repeats of the same error within an hour add log entries but no further e-mails.
- An uncaught error in the browser reaches `POST /client-errors`. It is logged at `warning` level with the page URL and `user_id` when signed in, and sends no e-mail.
- With `LOG_ALERT_MAIL_TO` unset, the `mail_alert` channel is a no-op. If mail sending fails, the original log entry is still written and the request or job is not disturbed.
- Verify with `vendor/bin/sail composer test`, PHPStan, Pint and `npm run build`, then the manual checks in Progress.

### Key Discoveries:

- Context processor applies to every channel: `LogManager.php:149-150`.
- `daily` retention is already 14 days by default: `config/logging.php:72`.
- Job double-logging: `GenerateMealPlanJob.php:87-91` and `:126-129`.
- Throttle middleware pattern in the repo: `routes/auth.php:43,47` (`throttle:6,1`).
- Test environment: `phpunit.xml:21-32` (`MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`).

## What We're NOT Doing

- No error-tracking service or SDK (no Sentry, Nightwatch or GlitchTip) and no new composer/npm package.
- No performance tracing, metrics or dashboards.
- No e-mail for browser errors. They are log-only at `warning`.
- No change to the seven existing `console.error` sites, which handle validation and form results, not crashes.
- No personal data in log context beyond `user_id`: no e-mail, IP, request bodies or family-member data.
- No production mail provider choice (roadmap S-01) and no production `.env` or server setup (roadmap F-02, `kostecki-infra`).
- No rewrite of the meal-plan generation logic. The job stays a placeholder (roadmap S-03). Only its logging and context change.

## Implementation Approach

Lean entirely on Laravel's logging pipeline:
- **Phase 1** puts identifiers into `Context` once, in a web middleware and in the job, so every log line and every reported exception carries them. It also removes the job's duplicate logging, so one failure means one error entry.
- **Phase 2** adds a custom `mail_alert` log channel. It is built from stock Monolog handlers: a mailer handler using Laravel's configured Symfony transport, wrapped in a one-hour deduplication handler and a failure-swallowing group. Production enables it by listing it in `LOG_STACK`.
- **Phase 3** adds a small, throttled, validated endpoint that writes browser errors at `warning` level, which is below the mail threshold. The Vue app reports to it through a global handler with a per-page cap.

## Critical Implementation Details

- **Reporting must never fail the request.** `stack` has `ignore_exceptions => false` (`config/logging.php:58`). An SMTP failure inside the mail handler would therefore surface while an exception is already being reported. Wrap the mail handler so that its failures are swallowed (Monolog `WhatFailureGroupHandler`). The `daily` handler in the same stack still writes the entry.
- **No log→mail→log loop.** With `MAIL_MAILER=log`, the alert e-mail is itself written to the log at `debug` level. That is below the `error` threshold, so it does not trigger another alert. Keep the channel level at `error` to preserve this.
- **Deduplication key.** `DeduplicationHandler` keys on level + message. The dedup store file belongs under `storage/logs/` so it survives between requests and workers. The window is 3600 s.
- **Browser reporter must not recurse.** A failure while posting a report must be swallowed silently and must not call the reporter again. Cap reports per page load (5) so a render loop cannot flood the endpoint.

## Phase 1: Context and daily logs

### Overview

Every log entry from a request or job carries `user_id`, and generation entries also carry `meal_plan_id`. One failed generation yields exactly one error entry. Production logging is documented as daily files kept for 14 days.

### Changes Required:

#### 1. Request context middleware

**File**: `app/Http/Middleware/AddLogContext.php` (new), registered in `bootstrap/app.php`

**Intent**: For each web request, put the authenticated user's id into Laravel `Context` (hidden values excluded), so all log entries and jobs dispatched from that request carry it.

**Contract**: Appended to the `web` middleware group after the session and auth middleware. Adds `user_id` (int) only when a user is authenticated, and adds nothing for guests. Starts with `declare(strict_types=1);`.

#### 2. Generation job context and single error entry

**File**: `app/Jobs/GenerateMealPlanJob.php`

**Intent**: Add `meal_plan_id` to `Context` at the start of `handle()` and of `failed()`. Remove the job's own `Log::error` calls in the `catch` block and in `failed()`, so the worker's report of the rethrown exception is the single error entry. Keep the status and `generation_meta` updates and the `info` lines unchanged.

**Contract**: `handle()` still rethrows. `failed(\Throwable)` still sets status `error` when the plan is `processing`. The `Log::error` call sites at `:87` and `:126` are removed.

#### 3. Environment documentation

**File**: `.env.example`

**Intent**: Document the production logging setup next to the existing `LOG_*` keys without changing local defaults.

**Contract**: Keep `LOG_STACK=single`. Add commented guidance: production uses `LOG_STACK=daily` (Phase 2 extends it to `daily,mail_alert`), `LOG_LEVEL=info` and `LOG_DAILY_DAYS=14`.

#### 4. Tests

**File**: `tests/Feature/AddLogContextTest.php` (new), `tests/Unit/Jobs/GenerateMealPlanJobTest.php`

**Intent**: Prove that `user_id` is in `Context` for authenticated requests and absent for guests. Prove that a job failure sets `meal_plan_id` in `Context` and writes no error entry of its own: assert with `Log` spying that `error` is not called by the job, while the status update still happens.

**Contract**: Existing job tests keep passing. The new assertions use `Context::get()`/`Context::has()`.

### Success Criteria:

#### Automated Verification:

- Pest suite passes: `vendor/bin/sail composer test`
- Larastan passes without baseline changes: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`

#### Manual Verification:

- With `LOG_STACK=daily` locally, signing in and triggering a forced exception (temporary `abort(500)` or a tinker-dispatched failing job) writes one entry to `storage/logs/laravel-<today>.log` that contains `user_id` and, for the job, `meal_plan_id`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Mail alert channel

### Overview

A `mail_alert` log channel e-mails entries at level `error` and above to `LOG_ALERT_MAIL_TO`, at most once per hour per identical error. It never breaks the request when mail fails, and does nothing when no recipient is configured.

### Changes Required:

#### 1. Channel factory

**File**: `app/Logging/CreateMailAlertLogger.php` (new)

**Intent**: Build the Monolog logger for the `mail_alert` channel. The chain is: a mailer handler sending through Laravel's configured mail transport (so the mailer follows `MAIL_*`, including `log` and `array`), wrapped in a deduplication handler (3600 s, store under `storage/logs/`), wrapped in a failure-swallowing group. With no recipient configured, return a logger with a no-op handler.

**Contract**: Invokable `__invoke(array $config): Monolog\Logger`. Config keys: `to` (string|null), `level` (default `error`), `dedup_seconds` (default 3600). From address and name come from `mail.from`. Subject: `[<APP_NAME> <APP_ENV>] <level>: <message>` truncated to a sane length. The e-mail body is the formatted record, including context and the exception trace. Starts with `declare(strict_types=1);`. Larastan level 5 clean.

#### 2. Channel registration

**File**: `config/logging.php`

**Intent**: Register the `mail_alert` custom channel so production can enable it through `LOG_STACK`.

**Contract**: The `mail_alert` entry has `driver => custom`, `via => CreateMailAlertLogger::class`, `to => env('LOG_ALERT_MAIL_TO')`, `level => 'error'` and `dedup_seconds => 3600`.

#### 3. Environment documentation

**File**: `.env.example`

**Intent**: Document `LOG_ALERT_MAIL_TO` (empty by default) and the production stack `LOG_STACK=daily,mail_alert`.

**Contract**: The new key `LOG_ALERT_MAIL_TO=` stays empty in the example and the local default stays `single`.

#### 4. Tests

**File**: `tests/Unit/Logging/CreateMailAlertLoggerTest.php` (new)

**Intent**: Use the `array` mailer (`phpunit.xml`) to prove five behaviours:
- an `error` entry sends exactly one message to the configured recipient, and that message contains the `Context` `user_id`;
- a `warning` entry sends nothing;
- the same error twice within the window sends one message;
- no recipient means no message and no exception;
- a throwing transport does not propagate out of `Log::error`.

**Contract**: Each test uses its own temporary dedup store path. Each test resolves the channel through `Log::channel('mail_alert')` with config overridden per test.

### Success Criteria:

#### Automated Verification:

- Pest suite passes: `vendor/bin/sail composer test`
- Larastan passes without baseline changes: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`

#### Manual Verification:

- Locally with `LOG_STACK=daily,mail_alert`, `LOG_ALERT_MAIL_TO=you@example.com` and `MAIL_MAILER=log`, one forced server error produces one alert e-mail (visible as a rendered message in the log). A second identical error within the hour produces no second e-mail.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Browser errors

### Overview

Uncaught errors and unhandled promise rejections in the Vue app are posted to a throttled endpoint and logged at `warning` level, with no e-mail.

### Changes Required:

#### 1. Endpoint

**File**: `app/Http/Controllers/ClientErrorController.php` (new, invokable), `app/Http/Requests/StoreClientErrorRequest.php` (new), `routes/web.php`

**Intent**: Accept a browser error report from guests and signed-in users. Validate and length-limit it, then write it with `Log::warning` together with the page URL. `user_id` comes from `Context` (Phase 1). Respond with 204. This is a single action, not a resource controller, so no policy applies.

**Contract**:
- Route: `POST /client-errors`, named `client-errors.store`, in the `web` group (CSRF via the existing axios XSRF cookie), with `throttle:10,1`.
- Request fields: `message` (required string ≤ 1000), `stack` (nullable string ≤ 5000), `url` (required string ≤ 2000), `source` (required, in `vue`, `window`, `promise`).
- Response: 204 with no body. Both new PHP files start with `declare(strict_types=1);`.

#### 2. Global browser reporter

**File**: `resources/js/lib/reportClientError.js` (new), `resources/js/app.js`

**Intent**: Register `app.config.errorHandler` and a `window` `unhandledrejection` listener (plus `error`) that post to `route('client-errors.store')`. The reporter caps at 5 reports per page load, swallows its own failures and still logs the error to the console so development is unchanged. Client-only: `resources/js/ssr.js` is not touched.

**Contract**: `reportClientError({ message, stack, url, source })` returns nothing and never throws. Uses the existing `window.axios` (`resources/js/bootstrap.js`).

#### 3. Tests

**File**: `tests/Feature/ClientErrorControllerTest.php` (new)

**Intent**: Prove that:
- a valid report returns 204 and writes one `warning` (spy on `Log`) with message and URL;
- a signed-in report carries `user_id` in `Context`;
- a guest report is accepted;
- invalid or oversized fields return 422;
- the 11th request within a minute returns 429;
- a report sends no alert e-mail (`Mail` / `array` mailer stays empty with the `mail_alert` channel active).

**Contract**: One file for the controller, per repo convention.

### Success Criteria:

#### Automated Verification:

- Pest suite passes: `vendor/bin/sail composer test`
- Larastan passes without baseline changes: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`
- Client and SSR bundles build: `vendor/bin/sail npm run build`

#### Manual Verification:

- In the browser, throwing a temporary error from a component writes one `warning` entry in the log with the page URL and your `user_id`. The page keeps working, and no alert e-mail is sent.
- Forcing a render loop that throws repeatedly results in at most 5 reports from one page load

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding.

---

## Testing Strategy

### Unit Tests:

- Mail alert channel: threshold, one-hour dedup, no recipient, failing transport (Phase 2).
- Generation job: `meal_plan_id` in Context and no duplicate error entry (Phase 1).

### Integration Tests:

- Feature: `user_id` in Context for authenticated requests (Phase 1); client-error endpoint validation, throttle, warning-only logging, no e-mail (Phase 3).

### Manual Testing Steps:

1. Set `LOG_STACK=daily,mail_alert`, `LOG_ALERT_MAIL_TO=<your address>` and `MAIL_MAILER=log` in local `.env`, then restart the containers.
2. Force a server error while signed in. Expect one log entry with `user_id` and one alert message in the log.
3. Repeat it within the hour. Expect a second log entry and no second alert.
4. Throw an error in a Vue component. Expect one `warning` entry with the URL and no alert.

## Performance Considerations

The alert e-mail is sent synchronously in the reporting path, but at most once per hour per error, thanks to deduplication. Browser reports are throttled per client (10 per minute) and capped at 5 per page load.

## Migration Notes

There are no data migrations. Deployment (F-02) must set `LOG_STACK=daily,mail_alert`, `LOG_LEVEL=info` and `LOG_ALERT_MAIL_TO`. Until a real mailer exists (S-01), alerts are only written to the log.

## References

- Roadmap item: `context/foundation/roadmap.md` (F-01 `error-tracking-baseline`)
- Superseded design note: `.ai/core/api-plan.md:130`
- Job logging today: `app/Jobs/GenerateMealPlanJob.php:74-141`
- Context processor wiring: `vendor/laravel/framework/src/Illuminate/Log/LogManager.php:140-150`
- Throttle pattern: `routes/auth.php:43,47`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Context and daily logs

#### Automated

- [x] 1.1 Pest suite passes: `vendor/bin/sail composer test` — ed37e29
- [x] 1.2 Larastan passes without baseline changes: `vendor/bin/sail composer phpstan` — ed37e29
- [x] 1.3 Pint passes: `vendor/bin/sail composer pint-test` — ed37e29

#### Manual

- [ ] 1.4 Forced server error and failed job each write one daily-log entry with user_id (and meal_plan_id for the job)

### Phase 2: Mail alert channel

#### Automated

- [x] 2.1 Pest suite passes: `vendor/bin/sail composer test` — 77cbb29
- [x] 2.2 Larastan passes without baseline changes: `vendor/bin/sail composer phpstan` — 77cbb29
- [x] 2.3 Pint passes: `vendor/bin/sail composer pint-test` — 77cbb29

#### Manual

- [ ] 2.4 One forced server error sends one alert; an identical repeat within the hour sends none

### Phase 3: Browser errors

#### Automated

- [x] 3.1 Pest suite passes: `vendor/bin/sail composer test`
- [x] 3.2 Larastan passes without baseline changes: `vendor/bin/sail composer phpstan`
- [x] 3.3 Pint passes: `vendor/bin/sail composer pint-test`
- [x] 3.4 Client and SSR bundles build: `vendor/bin/sail npm run build`

#### Manual

- [ ] 3.5 Component error writes one warning entry with URL and user_id, page keeps working, no alert sent
- [ ] 3.6 A throwing render loop produces at most 5 reports per page load
