# Error Reporting Baseline — Plan Brief

> Full plan: `context/changes/error-tracking-baseline/plan.md`

## What & Why

Server and browser failures in Menu Maker should become visible before users report them. Roadmap F-01 sits in front of the first real meal-plan generation (S-03). Generation runs in a queue, where a failure never reaches the user, so the "quality" goal requires these errors to be seen first.

## Starting Point

Today only Laravel's default single log file exists, with no error tracking and no alerting. The generation job logs one failure 2–3 times without `user_id`. The Vue app has no global error handler, so uncaught browser errors vanish.

## Desired End State

In production, an unhandled exception from a request or a queued job lands once in a daily log (kept 14 days) with `user_id`, and with `meal_plan_id` during generation. It also e-mails the configured recipient, at most once per hour for the same error. Uncaught browser errors are logged as warnings with the page URL and no e-mail. Local, test and CI environments send nothing.

## Key Decisions Made

| Decision            | Choice                                                   | Why (1 sentence)                                                                          |
| ------------------- | -------------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| Tool                | Logs + mail, no service, no new package                  | User choice; replaces the earlier "Sentry in production" note in `.ai/core/api-plan.md`.  |
| Scope               | Backend (requests, queue) + global Vue handler           | Catches UI failures that now die in the console; the 7 `console.error` sites stay as they are. |
| Personal data       | Only `user_id` and `meal_plan_id`                        | Enough to find the record; no e-mail, IP, bodies or family data leave the app.            |
| Environments        | Production only (`LOG_STACK`, `LOG_ALERT_MAIL_TO`); no tracing | No noise from dev, tests or CI.                                                      |
| Mail threshold      | Server `error`+ mails; browser errors are `warning`, log-only | The browser endpoint is public and noisy, so it must not fill the inbox.             |
| Retention / dedup   | Daily logs 14 days; same error mailed once per hour      | Fits a small Mikrus disk and stops an error loop from flooding the inbox.                 |
| Mail failure        | Swallowed; the daily log entry is still written          | Reporting must never break the request or the job.                                        |

## Scope

**In scope:**
- `user_id`/`meal_plan_id` in Laravel Context.
- One error entry per job failure.
- Daily-log documentation.
- `mail_alert` log channel with one-hour deduplication.
- Throttled `POST /client-errors` endpoint.
- Global Vue/window error reporter with a 5-per-page cap.
- Tests for each.

**Out of scope:**
- Sentry, Nightwatch or GlitchTip.
- Metrics and tracing.
- Mail for browser errors.
- Changes to existing `console.error` sites.
- Choosing the production mail provider (S-01).
- Server setup (F-02, `kostecki-infra`).
- Real generation logic (S-03).

## Architecture / Approach

Everything rides on Laravel logging:
- A web middleware and the job put identifiers into `Context`, which Laravel attaches to every log entry and carries into queued jobs.
- Production's `LOG_STACK=daily,mail_alert` writes files and feeds a custom channel built from stock Monolog handlers: a mailer on Laravel's transport, a one-hour deduplication handler, and a failure-swallowing group.
- The browser reports through a small validated endpoint at `warning` level, below the mail threshold.

## Phases at a Glance

| Phase                      | What it delivers                                                   | Key risk                                                       |
| -------------------------- | ------------------------------------------------------------------ | -------------------------------------------------------------- |
| 1. Context and daily logs  | Identifiers on every entry; one error entry per job failure        | Removing job logs must not drop the status update on failure   |
| 2. Mail alert channel      | Error+ e-mails with one-hour dedup, off when no recipient is set   | A mail failure during reporting must not surface as a new error |
| 3. Browser errors          | Throttled endpoint plus global Vue reporter, warning-only          | Reporter recursion or flooding from a render loop              |

**Prerequisites:** Sail containers running.

**Estimated effort:** 1–2 sessions across 3 small phases.

## Open Risks & Assumptions

- Real alert delivery depends on a production mailer (S-01). Until then, alerts only appear in the log.
- Deduplication keys on level + message, so errors whose messages embed ids are treated as distinct and can each send a mail.
- The production `.env` values (`LOG_STACK`, `LOG_ALERT_MAIL_TO`) must be set by the deploy in F-02.

## Success Criteria (Summary)

- A forced server error in production-like config yields one log entry with `user_id` and one e-mail. A repeat within the hour yields no second e-mail.
- A failed generation job yields one error entry carrying `meal_plan_id`, and the plan still flips to `error`.
- A browser error yields one `warning` entry with the URL, without breaking the page or sending mail.
