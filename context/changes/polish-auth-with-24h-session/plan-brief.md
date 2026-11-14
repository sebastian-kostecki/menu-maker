# Polish Auth with 24h Session — Plan Brief

> Full plan: `context/changes/polish-auth-with-24h-session/plan.md`

## What & Why

Roadmap slice S-01 closes the gap between the current Breeze-style account flow and US-001, US-002 and US-003. The user should be able to register, log in and reset their password on Polish screens. The session should expire after 24 h of inactivity, and the reset link (valid 1 h) should actually arrive by e-mail. Today several of these things are true only on paper.

## Starting Point

Today the app has:
- Registration with auto-login, login and a 60-min reset link.
- A 120-min session. The "Zapamiętaj mnie" cookie lasts about 400 days.
- English Register, ForgotPassword, ResetPassword, ConfirmPassword and Profile screens. Only `Login.vue` is in Polish.
- A two-rule `lang/pl/validation.php`, and no `passwords.php` or `pl.json`, so most errors and the reset e-mail are in English.
- `MAIL_MAILER=log`, with no mail catcher in Sail or CI.
- Verification routes, controllers and a page, plus a `verified` middleware that does nothing, because `User` does not implement `MustVerifyEmail`.

## Desired End State

- **Session:** without "Zapamiętaj mnie" it lasts 24 h from the last request. With it, the remember cookie logs the user back in, and US-002 now states this exception.
- **Verification:** the code is gone.
- **Polish:** every account screen, validation error and the reset e-mail are in Polish.
- **Mail:** a reset request delivers a Polish e-mail to Mailpit locally and in CI, and a Playwright test follows that e-mail's link to a new password and a successful login.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Remember me vs 24 h | Keep the checkbox; it overrides the 24 h rule; amend US-002 criterion 3 | User chose opt-in convenience; the PRD is updated so spec and code agree. |
| E-mail verification | Remove routes, controllers, page, test and the `verified` middleware; keep the column | It is dead code that implies a guarantee the app doesn't give and adds an English page; no migration needed. |
| Mail delivery | Mailpit in Sail and CI over SMTP; production SMTP deferred to F-02 | Proves delivery end to end without a new account or package; F-02 owns production env. |
| Translation scope | All account screens plus hand-written full `validation.php`, `passwords.php`, `pl.json` | No English left in the account flow; the full validation file also fixes English errors on other forms. |
| Reset proof | Playwright spec reads the link from the Mailpit API, in CI too | `Notification::fake()` cannot prove the e-mail actually arrives. |
| Session lifetime source | Change the `config/session.php` default to 1440, not only `.env.example` | phpunit and the CI E2E `.env` don't set `SESSION_LIFETIME`. |

## Scope

**In scope:**
- 24 h idle session.
- Pest boundary tests for the session, remember-me and the reset link.
- Verification removal.
- Profile cleanup.
- US-002 amendment.
- Polish lang files and eight Vue files.
- Polish labels in `login.spec.ts`.
- Mailpit in Sail and CI.
- New `password-reset.spec.ts`.
- `CLAUDE.md` E2E note.

**Out of scope:**
- Production mail provider (F-02).
- Limiting remember-me duration.
- Dropping `email_verified_at`.
- `laravel-lang` package.
- Account-enumeration change.
- Queued mail.
- Translating non-account screens.

## Architecture / Approach

No new classes. Behaviour comes from framework config (`session.lifetime`, the existing 60-min password broker) and translation files that the existing controllers already pass through `__()`. Mail goes through Laravel's SMTP transport to a Mailpit container. The E2E helper searches Mailpit's HTTP API by recipient, so tests stay safe in parallel, and opens the reset link by path relative to `baseURL`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Lifetimes and verification removal | 24 h default, US-002 amended, verification gone, Pest boundary tests | Session-expiry tests passing falsely because cached guard or session state hides expiry |
| 2. Polish account screens and messages | Full PL validation, passwords and JSON; eight Vue files; `login.spec.ts` on PL labels | JSON keys must match vendor strings exactly or the e-mail silently stays English |
| 3. Mail delivery through Mailpit | Mailpit in Sail and CI, SMTP env, E2E reset spec, docs | Parallel E2E on two browsers reading the wrong message; the absolute link host differing from `baseURL` |

**Prerequisites:** Sail running (`vendor/bin/sail up -d`), `AI_SCALING_DRIVER=fake` for E2E; local `.env` updated by hand (`SESSION_LIFETIME=1440`, Mailpit `MAIL_*`).
**Estimated effort:** ~1–2 sessions across 3 phases.

## Open Risks & Assumptions

- Production reset delivery remains unproven until F-02 configures real SMTP and sender DNS.
- Remember-me users never hit the 24 h limit. This is intentional and recorded in the PRD.
- Deleting `EmailVerificationTest.php` was approved during planning. Re-adding verification later means restoring it.

## Success Criteria (Summary)

- A user who is inactive for more than 24 h without "Zapamiętaj mnie" has to log in again, and one with it does not.
- No English text appears anywhere in registration, login, password reset, confirm password or profile, including errors and the reset e-mail.
- In CI, a test receives the reset e-mail in Mailpit, follows its link, sets a new password and logs in with it.
