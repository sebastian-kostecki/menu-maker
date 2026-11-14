# Polish Auth with 24h Session Implementation Plan

## Overview

Bring the account flow in line with US-001, US-002 and US-003 (roadmap S-01):

- An idle session lasts 24 h. "Zapamiętaj mnie" is a deliberate exception, and US-002 is amended to say so.
- The dead e-mail verification code is removed.
- Every account screen, every validation message and the reset e-mail are in Polish.
- The reset e-mail is delivered over SMTP to Mailpit, locally and in CI.
- A Playwright test proves the reset flow end to end.

## Current State Analysis

- **Session:**
  - `config/session.php:35` defaults `lifetime` to `env('SESSION_LIFETIME', 120)`. `.env.example:34` and the local `.env` set `120`. `phpunit.xml` and the CI E2E `.env` do not set it, so they use the config default.
  - The `database` driver expires a session by `last_activity`, which means idle time.
  - `LoginRequest.php:45` passes `$this->boolean('remember')` to `Auth::attempt`. The guard has no `remember` duration, so the recaller cookie lasts about 400 days.
- **Reset link:** `config/auth.php:99` sets `expire => 60`, which already matches US-003's 1 h. No test checks expiry. The throttle is 60 s (`:100`).
- **E-mail verification is dead code:**
  - `App\Models\User` does not implement `MustVerifyEmail` (`User.php:5` is a commented import), so the `verified` middleware (`routes/web.php:25,27`) lets everyone through.
  - Code that still depends on it:
    - routes `verification.notice`, `verification.verify` and `verification.send` (`routes/auth.php:39-48`)
    - three controllers
    - `Pages/Auth/VerifyEmail.vue`
    - `tests/Feature/Auth/EmailVerificationTest.php`
    - `ProfileController.php:22` (`mustVerifyEmail` prop)
    - `Profile/Edit.vue:9,36`
    - `UpdateProfileInformationForm.vue:9,72-76`, which links to `route('verification.send')`
- **Language:** `config/app.php:81` uses locale `pl` and fallback `en`.
  - `Login.vue` and `AuthenticatedLayout.vue` are in Polish.
  - `Register.vue`, `ForgotPassword.vue`, `ResetPassword.vue`, `ConfirmPassword.vue`, `Profile/Edit.vue` and `Profile/Partials/*` are in English.
  - `lang/pl/` has only `auth.php` and a two-rule `validation.php` (`required`, `email`). There is no `passwords.php` and no `lang/pl.json`, so status messages, most validation errors (`unique`, `confirmed`, `min`, `lowercase`, …) and the reset e-mail come out in English.
- **Mail:**
  - `.env.example:48` sets `MAIL_MAILER=log`.
  - `docker-compose.yml` has only `laravel.test`, `queue` and `pgsql`; there is no mail catcher.
  - `phpunit.xml:28` uses `array`.
  - The CI E2E job (`.github/workflows/master.yml:136-220`) runs `php artisan serve` on the runner with `services: postgres`, and its generated `.env` has no `MAIL_*`, so mail goes to `log`.
- **E2E:**
  - `tests/e2e/login.spec.ts` registers with the English labels `Name`, `Email`, `Password`, `Confirm Password` and `Register`.
  - `meal-plan.spec.ts` already uses the Polish login labels.
  - `playwright.config.ts` runs `fullyParallel` on two browsers (chromium, firefox).

## Desired End State

- **Idle session.** A user who logs in without "Zapamiętaj mnie" stays logged in for up to 24 h after their last request. The first request after that redirects to `/login`.
- **Remember me.** With "Zapamiętaj mnie" ticked, the user is re-authenticated by the remember cookie after the session expires. US-002 in `.ai/core/prd.md` now states this exception.
- **No verification.** No verification routes, controllers, page or `verified` middleware remain. `artisan route:list` shows no `verification.*` route.
- **Polish everywhere in the account flow:**
  - Registration, login, forgot password, reset password, confirm password and profile show only Polish text.
  - Every validation error on those forms is in Polish.
  - The reset e-mail is in Polish: subject, lines, button, layout greeting and footer.
- **Reset e-mail delivery.** Locally, a reset request delivers an e-mail to Mailpit (`http://localhost:8025`), and its link resets the password. It stops working after 60 min.
- **CI.** The E2E job runs Mailpit and a Playwright spec that registers, requests a reset, follows the link from Mailpit, sets a new password and logs in with it.

### Key Discoveries:

- `vendor/laravel/framework/src/Illuminate/Auth/AuthManager.php:147-148`: a guard's `remember` config key (minutes) calls `setRememberDuration`. We leave it unset on purpose: remember-me keeps its default lifetime.
- `vendor/.../Auth/Notifications/ResetPassword.php:77-81` uses JSON keys (`Reset your password`, `Reset Password`, `This password reset link will expire in :count minutes.`, …). The mail layout uses `@lang('Hello!')`, `@lang('Whoops!')`, `@lang('Regards,')`, the "If you're having trouble clicking the \":actionText\" button…" line (`Notifications/resources/views/email.blade.php`) and `__('All rights reserved.')` (`Mail/resources/views/html/message.blade.php`). Copy these keys verbatim from vendor; a single changed character makes the key miss.
- `NewPasswordController.php` and `PasswordResetLinkController.php` pass `Password::*` status keys through `__()`/`trans()`, so `lang/pl/passwords.php` (`reset`, `sent`, `throttled`, `token`, `user`) translates them without code changes.
- Sail convention for Mailpit: service `mailpit`, image `axllent/mailpit:latest`, ports `${FORWARD_MAILPIT_PORT:-1025}:1025` and `${FORWARD_MAILPIT_DASHBOARD_PORT:-8025}:8025`, and the app reaches it as `MAIL_HOST=mailpit`.
- The `sessions` table already exists (`database/migrations/0001_01_01_000000_create_users_table.php`).

## What We're NOT Doing

- **No production mail provider or credentials.** Production SMTP is set up with F-02 (`public-deploy-with-queue-worker`, `kostecki-infra`).
- **No limit on remember-me duration.** `auth.guards.web.remember` stays unset (user decision: remember-me overrides the 24 h rule).
- **No migration.** `users.email_verified_at`, `UserFactory::unverified()` and the `email_verified_at = null` reset on e-mail change in `ProfileController::update` stay. They are harmless, and keeping them leaves `ProfileTest` unchanged.
- **No `laravel-lang/lang` or other new dependency.** Translations are written by hand.
- **No change to the "user not found" response** on forgot-password. The current behaviour is only translated, and account enumeration stays out of scope.
- **No queued mail.** The reset notification stays synchronous, as today.
- **No translation of non-account screens** (recipes, meal plans, family members), except that their validation errors become Polish through the full `validation.php`.
- **No edits to historical per-feature plans** under `.ai/api/` that show `verified` in route snippets.

## Implementation Approach

Three phases, each independently green:

1. **Behaviour first:** lifetimes, verification removal, PRD amendment, and the Pest tests for the 24 h boundary, remember-me and link expiry.
2. **Polish text:** lang files and Vue screens, with `login.spec.ts` updated in the same phase so the E2E suite never runs against mismatched labels.
3. **Mail delivery:** Mailpit in Sail and CI, plus the E2E reset spec. It comes last because the spec reads the Polish labels and the Polish e-mail from Phase 2.

## Critical Implementation Details

### Testing session expiry in Pest

`actingAs()` and in-memory state hide expiry.

- `SessionGuard` caches the user.
- `Session\Store` keeps its attributes across test requests (`start()` merges handler data into the existing attributes).
- `ArraySessionHandler` lives inside the cached driver.

So the expiry test must:

1. Use the `database` session driver (override `session.driver` in the test).
2. Log in through `POST /login`.
3. Capture the session id, and the recaller cookie for the remember-me case.
4. Before each follow-up request, call `travel()`, reset the auth guards (`Auth::forgetGuards()`) and the session drivers (`app('session')->forgetDrivers()`, plus `forgetInstance('session.store')` if bound).
5. Send only the cookies (`withCookie`, which test requests encrypt automatically).

Assert both sides of the boundary: 23 h 59 min after the last request it passes, and 24 h 1 min after it redirects to `login`.

### Reset link in the E2E spec

The link in the e-mail is absolute and built from the request host. Open it by **path** (`/reset-password/<token>?email=…`) relative to Playwright's `baseURL`, so it works for both Sail (`localhost:${APP_PORT}`) and CI (`127.0.0.1:8000`).

Tests run in parallel on two browsers, so each test registers its own random e-mail and searches Mailpit by that recipient (`GET /api/v1/search?query=to:"<email>"`). It must not read "the latest message". Poll briefly, because delivery is synchronous but the API indexes asynchronously.

## Phase 1: Lifetimes and verification removal

### Overview

The 24 h idle session becomes the default everywhere (config, example env, tests, CI). Remember-me stays as a documented exception. All e-mail verification code is removed. The new behaviour and the existing 60-min link expiry are covered by tests.

### Changes Required:

#### 1. Session lifetime

**File**: `config/session.php`, `.env.example`

**Intent**: Make 24 h the idle lifetime in every environment, including those that do not set `SESSION_LIFETIME` (phpunit, CI E2E).

**Contract**: `'lifetime' => (int) env('SESSION_LIFETIME', 1440)`; `.env.example` sets `SESSION_LIFETIME=1440`. `expire_on_close` stays `false`.

#### 2. PRD amendment

**File**: `.ai/core/prd.md`

**Intent**: Record the decision that remember-me overrides the 24 h rule, so the spec and the code agree.

**Contract**: US-002 acceptance criterion 3 reads: `Sesja wygasa po 24 h braku aktywności, chyba że użytkownik zaznaczył „Zapamiętaj mnie”.` Nothing else in the row changes.

#### 3. Remove e-mail verification

**Files**:
- `routes/auth.php`
- `routes/web.php`
- `app/Http/Controllers/Auth/EmailVerificationNotificationController.php` (delete)
- `app/Http/Controllers/Auth/EmailVerificationPromptController.php` (delete)
- `app/Http/Controllers/Auth/VerifyEmailController.php` (delete)
- `resources/js/Pages/Auth/VerifyEmail.vue` (delete)
- `tests/Feature/Auth/EmailVerificationTest.php` (delete; approved by the user during planning)
- `app/Models/User.php`
- `.ai/core/api-plan.md`

**Intent**: Remove the unused verification feature. Its middleware suggests a guarantee the app does not give, and it adds an English page.

**Contract**:
- `routes/auth.php` has no `verification.*` routes, and the `auth` group keeps `password.confirm`, `password.update` and `logout`.
- `routes/web.php:25,27` use `['auth']` only.
- `User.php` drops the commented `MustVerifyEmail` import.
- `.ai/core/api-plan.md:40` route snippet drops `'verified'`.
- `grep -rn "verification\.\|MustVerifyEmail\|mustVerifyEmail" app routes resources/js` returns nothing.

#### 4. Profile cleanup

**Files**: `app/Http/Controllers/ProfileController.php`, `resources/js/Pages/Profile/Edit.vue`, `resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue`

**Intent**: Remove the `mustVerifyEmail` prop and the "unverified e-mail" block that links to the deleted `verification.send` route. Otherwise the profile page throws in Ziggy.

**Contract**:
- `ProfileController::edit` renders `Profile/Edit` with `status` only and drops the `MustVerifyEmail` import.
- Neither Vue file declares or passes `mustVerifyEmail`.
- `ProfileController.php` gets `declare(strict_types=1);`.

#### 5. Tests

**Files**: `tests/Feature/Auth/AuthenticationTest.php`, `tests/Feature/Auth/PasswordResetTest.php`

**Intent**: Prove the behaviours this slice exists for. None of them is tested today.

**Contract** (new `it(...)` cases):
- `AuthenticationTest`:
  - The session survives 23 h 59 min idle and is rejected after 24 h 1 min idle (redirect to `login`).
  - Each request resets the idle clock: a request at 20 h and another 20 h later both stay authenticated.
  - With `remember => true`, a request after 25 h idle is authenticated by the recaller cookie.
- `PasswordResetTest`:
  - The token works 59 min after it is issued.
  - The token fails with a `passwords.token` error and the password unchanged 61 min after it is issued.

Follow the "Testing session expiry in Pest" note under Critical Implementation Details.

### Success Criteria:

#### Automated Verification:

- Auth tests pass: `vendor/bin/sail pest tests/Feature/Auth tests/Feature/ProfileTest.php`
- Full suite passes: `vendor/bin/sail composer test`
- No verification routes remain: `vendor/bin/sail artisan route:list --name=verification` lists no routes
- Larastan passes without new baseline entries: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`
- Client and SSR bundles build: `vendor/bin/sail npm run build`

#### Manual Verification:

- After setting `SESSION_LIFETIME=1440` in local `.env`, logging in and opening `/profile` works, and the page shows no "unverified e-mail" block
- A login with "Zapamiętaj mnie" ticked sets a `remember_web_*` cookie in the browser

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Polish account screens and messages

### Overview

All account screens, framework status messages, validation errors and the reset e-mail are in Polish. The existing E2E registration test moves to the Polish labels.

### Changes Required:

#### 1. Validation messages

**File**: `lang/pl/validation.php`

**Intent**: Replace the two-rule file with a complete Polish translation of Laravel's stock `validation.php`, so no rule falls back to English on any form.

**Contract**:
- It contains every top-level key of the framework's English `validation.php`, including nested `between`/`gt`/`gte`/`lt`/`lte`/`max`/`min`/`size` arrays and `custom`.
- `attributes` maps at least `name` → `imię`, `email` → `adres e-mail`, `password` → `hasło`, `password_confirmation` → `potwierdzenie hasła` and `current_password` → `obecne hasło`.
- The existing `required` and `email` wording stays as is.

#### 2. Password broker status messages

**File**: `lang/pl/passwords.php` (new)

**Intent**: Translate reset status and error messages shown on the forgot/reset/login screens.

**Contract**: Keys `reset`, `sent`, `throttled`, `token` and `user`, in Polish. `throttled` keeps no placeholder, like the stock file.

#### 3. JSON strings for the reset e-mail and mail layout

**File**: `lang/pl.json` (new)

**Intent**: Translate the `ResetPassword` notification and the default mail layout.

**Contract**: A JSON object whose keys are the exact English strings from `ResetPassword.php:77-81`, `Notifications/resources/views/email.blade.php` (`Hello!`, `Whoops!`, `Regards,`, the "trouble clicking" line with `:actionText`) and `Mail/resources/views/html/message.blade.php` (`All rights reserved.`). The `:count` and `:actionText` placeholders must survive in the values.

#### 4. Polish Vue screens

**Files**:
- `resources/js/Pages/Auth/Register.vue`
- `resources/js/Pages/Auth/ForgotPassword.vue`
- `resources/js/Pages/Auth/ResetPassword.vue`
- `resources/js/Pages/Auth/ConfirmPassword.vue`
- `resources/js/Pages/Profile/Edit.vue`
- `resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue`
- `resources/js/Pages/Profile/Partials/UpdatePasswordForm.vue`
- `resources/js/Pages/Profile/Partials/DeleteUserForm.vue`

**Intent**: Translate every visible string: `<Head title>`, labels, buttons, links, descriptions, "Saved." flashes and modal text. Follow the wording already used in `Login.vue` (`Adres e-mail`, `Hasło`).

**Contract**: These labels are fixed because Phase 3's E2E and the updated `login.spec.ts` depend on them:

| Screen | Field / element | Label |
| --- | --- | --- |
| Register | name | `Imię` |
| Register | email | `Adres e-mail` |
| Register | password | `Hasło` |
| Register | password confirmation | `Potwierdź hasło` |
| Register | submit button | `Zarejestruj się` |
| ForgotPassword | email | `Adres e-mail` |
| ForgotPassword | submit button | `Wyślij link do resetu hasła` |
| ResetPassword | email | `Adres e-mail` |
| ResetPassword | password | `Nowe hasło` |
| ResetPassword | password confirmation | `Potwierdź hasło` |
| ResetPassword | submit button | `Zresetuj hasło` |

No English string literals remain in these files. Markup and logic do not change.

#### 5. E2E registration labels

**File**: `tests/e2e/login.spec.ts`

**Intent**: Keep the existing registration and login E2E tests green against the Polish register screen.

**Contract**: Selectors use the Register labels from the table above (`getByLabel('Hasło', { exact: true })` for the password field). The assertions do not change.

#### 6. Tests

**Files**: `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/Auth/PasswordResetTest.php`

**Intent**: Prove that the user sees Polish errors and gets a Polish e-mail. Both depend on exact translation keys, which break silently.

**Contract** (new `it(...)` cases):
- `RegistrationTest`:
  - Registering with an existing e-mail returns the Polish `unique` message for `adres e-mail`.
  - Mismatched passwords return the Polish `confirmed` message.
  - A 3-character password returns the Polish `min.string` message.
  - Assert exact strings.
- `PasswordResetTest`:
  - The rendered `ResetPassword` mail (`$notification->toMail($user)`) has the Polish subject and contains the Polish button text and the expiry line with `60`.
  - Requesting a link for an unknown e-mail returns the Polish `passwords.user` message.

### Success Criteria:

#### Automated Verification:

- Auth tests pass: `vendor/bin/sail pest tests/Feature/Auth`
- Full suite passes: `vendor/bin/sail composer test`
- No English UI literals remain in the eight Vue files (reviewed diff; `grep -nE "Email|Password|Register|Save|Profile|Delete Account" <files>` returns no visible-text hits)
- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Existing E2E suite passes against Sail: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e` (after `E2eMealPlanSeeder`)

#### Manual Verification:

- Registration, forgot-password, reset-password, confirm-password and profile screens show only Polish text, including validation errors after an empty or invalid submit
- The reset e-mail written to the log (still `MAIL_MAILER=log` at this point) is in Polish, including greeting, button and footer

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Mail delivery through Mailpit

### Overview

The reset e-mail is delivered over SMTP to Mailpit in Sail and in CI. A Playwright spec proves the whole reset flow through the real inbox.

### Changes Required:

#### 1. Mailpit in Sail

**File**: `docker-compose.yml`

**Intent**: Give the local stack a mail catcher that starts with `sail up -d`.

**Contract**:
- Add service `mailpit` (`axllent/mailpit:latest`) with ports `${FORWARD_MAILPIT_PORT:-1025}:1025` and `${FORWARD_MAILPIT_DASHBOARD_PORT:-8025}:8025`, on network `sail`.
- `laravel.test.depends_on` gains `mailpit` (`condition: service_started`).

#### 2. Example environment

**File**: `.env.example`

**Intent**: Make SMTP to Mailpit the documented local default, with a real From address.

**Contract**: `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025`, `MAIL_FROM_ADDRESS="no-reply@menu-maker.local"`. A comment says production SMTP credentials come with F-02.

#### 3. CI E2E job

**File**: `.github/workflows/master.yml`

**Intent**: Let the E2E job send and read real e-mail.

**Contract**:
- The `e2e-tests` job gains `services.mailpit` (`axllent/mailpit:latest`, ports `1025:1025` and `8025:8025`).
- The generated `.env` gains `MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025` and `MAIL_FROM_ADDRESS="no-reply@menu-maker.local"`.
- The `Run E2E tests` step sets `MAILPIT_URL=http://127.0.0.1:8025`.

#### 4. Mailpit helper and reset spec

**Files**: `tests/e2e/support/mailpit.ts` (new), `tests/e2e/password-reset.spec.ts` (new)

**Intent**: An E2E proof that the link actually reaches the inbox and resets the password, per the slice outcome.

**Contract**:
- `mailpit.ts` exports `findResetPath(email: string): Promise<string>`.
  - It uses `process.env.MAILPIT_URL ?? 'http://localhost:8025'` and polls `GET /api/v1/search?query=to:"<email>"` until a message appears (up to about 10 s).
  - It reads the message body (`GET /api/v1/message/<ID>`) and returns the `/reset-password/...` path with its query string.
- The spec:
  1. Registers a random `e2e-*@example.com` user through the Polish register form.
  2. Logs out in a fresh context.
  3. Submits `Wyślij link do resetu hasła`.
  4. Asserts the Polish `passwords.sent` status.
  5. Opens the returned path.
  6. Sets a new password with `Nowe hasło` / `Potwierdź hasło` / `Zresetuj hasło`.
  7. Lands on `/login` with the Polish `passwords.reset` status.
  8. Logs in with the new password and reaches `/dashboard`.
- The spec also asserts the e-mail subject is the Polish one from `lang/pl.json`.

See the "Reset link in the E2E spec" note under Critical Implementation Details.

#### 5. Developer docs

**File**: `CLAUDE.md`

**Intent**: Keep the E2E instructions accurate now that mail is part of the run.

**Contract**: The E2E bullet in `## Commands` says:
- `.env` must use `MAIL_MAILER=smtp` with `MAIL_HOST=mailpit`.
- Mailpit (started by `sail up -d`, UI on `FORWARD_MAILPIT_DASHBOARD_PORT`, default 8025) must be running.
- Set `MAILPIT_URL` if the dashboard port is not 8025.

### Success Criteria:

#### Automated Verification:

- Mailpit container is running after `vendor/bin/sail up -d`: `vendor/bin/sail ps` lists `mailpit`
- Full Pest suite still passes: `vendor/bin/sail composer test`
- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Whole E2E suite, including the new reset spec on both browsers, passes against Sail: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder` then `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- CI workflow is valid YAML and the `e2e-tests` job defines the `mailpit` service: `grep -n "mailpit" .github/workflows/master.yml`

#### Manual Verification:

- With local `.env` switched to Mailpit, requesting a reset in the browser delivers a Polish e-mail visible at `http://localhost:8025`, and its button opens the reset screen
- The E2E job passes in CI on the pull request into `main`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- None new. All behaviour here goes through HTTP and the framework's session and password brokers, so feature tests are the right level.

### Integration Tests:

- **Pest feature tests:**
  - idle-session boundary (23:59 passes, 24:01 is logged out, and activity resets the clock)
  - remember-me re-authentication after expiry
  - reset-token boundary (59 min passes, 61 min fails)
  - Polish `unique`/`confirmed`/`min` messages
  - Polish reset e-mail content
  - Polish `passwords.user` error
- **Playwright:**
  - `login.spec.ts` on Polish labels
  - new `password-reset.spec.ts` through Mailpit, on chromium and firefox

### Manual Testing Steps:

1. Set `SESSION_LIFETIME=1440` and the Mailpit `MAIL_*` values in local `.env`, run `vendor/bin/sail up -d`, and open `http://localhost:${APP_PORT}/register`. Every text should be Polish. Submit an empty form and check the errors are Polish.
2. Register. You should land on the dashboard. Open `/profile` and check there is no unverified-e-mail block and every text is Polish.
3. Log out, open "Nie pamiętasz hasła?", submit your e-mail, open Mailpit at `http://localhost:8025`, and check the Polish e-mail. Click the button, set a new password, and log in with it.
4. Log in with "Zapamiętaj mnie" ticked, delete the session cookie (`laravel_session` while `APP_NAME=Laravel`) in devtools, and reload. You should still be logged in. Repeat without the checkbox: you should be redirected to `/login`.

## Performance Considerations

None. The reset mail stays synchronous on the request path, as today. Mailpit SMTP adds a few milliseconds locally.

## Migration Notes

- No database migration.
- Existing local `.env` files keep `SESSION_LIFETIME=120` and `MAIL_MAILER=log` until updated by hand. The config default changes to 1440, but an explicit `.env` value wins.
- Existing sessions keep working. Their lifetime is evaluated against the new value on the next request.

## References

- Roadmap item: `context/foundation/roadmap.md` (S-01, `polish-auth-with-24h-session`)
- Requirements: `.ai/core/prd.md` (US-001, US-002, US-003)
- Mail provider deferral: `context/archive/2026-11-13-error-tracking-baseline/plan.md:42`
- Login wording to follow: `resources/js/Pages/Auth/Login.vue`
- Remember duration hook: `vendor/laravel/framework/src/Illuminate/Auth/AuthManager.php:147`
- Reset e-mail keys: `vendor/laravel/framework/src/Illuminate/Auth/Notifications/ResetPassword.php:77-81`
- E2E pattern: `tests/e2e/login.spec.ts`, `playwright.config.ts`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Lifetimes and verification removal

#### Automated

- [x] 1.1 Auth tests pass: `vendor/bin/sail pest tests/Feature/Auth tests/Feature/ProfileTest.php` — 5e68f11
- [x] 1.2 Full suite passes: `vendor/bin/sail composer test` — 5e68f11
- [x] 1.3 No verification routes remain: `vendor/bin/sail artisan route:list --name=verification` lists no routes — 5e68f11
- [x] 1.4 Larastan passes without new baseline entries: `vendor/bin/sail composer phpstan` — 5e68f11
- [x] 1.5 Pint passes: `vendor/bin/sail composer pint-test` — 5e68f11
- [x] 1.6 Client and SSR bundles build: `vendor/bin/sail npm run build` — 5e68f11

#### Manual

- [ ] 1.7 After setting `SESSION_LIFETIME=1440` in local `.env`, logging in and opening `/profile` works, and the page shows no "unverified e-mail" block
- [ ] 1.8 A login with "Zapamiętaj mnie" ticked sets a `remember_web_*` cookie in the browser

### Phase 2: Polish account screens and messages

#### Automated

- [x] 2.1 Auth tests pass: `vendor/bin/sail pest tests/Feature/Auth`
- [x] 2.2 Full suite passes: `vendor/bin/sail composer test`
- [x] 2.3 No English UI literals remain in the eight Vue files
- [x] 2.4 Client and SSR bundles build: `vendor/bin/sail npm run build`
- [x] 2.5 Existing E2E suite passes against Sail

#### Manual

- [ ] 2.6 Registration, forgot-password, reset-password, confirm-password and profile screens show only Polish text, including validation errors
- [ ] 2.7 The reset e-mail written to the log is in Polish, including greeting, button and footer

### Phase 3: Mail delivery through Mailpit

#### Automated

- [ ] 3.1 Mailpit container is running after `vendor/bin/sail up -d`
- [ ] 3.2 Full Pest suite still passes: `vendor/bin/sail composer test`
- [ ] 3.3 Client and SSR bundles build: `vendor/bin/sail npm run build`
- [ ] 3.4 Whole E2E suite, including the new reset spec on both browsers, passes against Sail
- [ ] 3.5 CI workflow defines the `mailpit` service in the `e2e-tests` job

#### Manual

- [ ] 3.6 With local `.env` switched to Mailpit, a browser reset request delivers a Polish e-mail to Mailpit and its button opens the reset screen
- [ ] 3.7 The E2E job passes in CI on the pull request into `main`
