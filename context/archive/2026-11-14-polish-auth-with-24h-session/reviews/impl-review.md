<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Polish Auth with 24h Session

- **Plan**: context/changes/polish-auth-with-24h-session/plan.md
- **Scope**: Full plan (Phases 1-3 of 3; all automated rows `[x]`, manual rows pending)
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-14
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

Automated criteria re-run on 2026-11-14: `composer test` 349 passed; `route:list --name=verification` empty; `composer phpstan` no errors (baseline is now `ignoreErrors: []`); `composer pint-test` passed; `npm run build` OK; grep for `verification.|MustVerifyEmail|mustVerifyEmail` in app/routes/resources/js empty; Playwright 12/12 on chromium + firefox (run with `MAILPIT_URL=http://localhost:8026`, output dir outside the root-owned `test-results/`).

Success Criteria is WARNING because manual rows 1.7, 1.8, 2.6, 2.7, 3.6 and 3.7 are still `[ ]` while `change.md` says `implemented`. Local `.env` still has `SESSION_LIFETIME=120` and `MAIL_FROM_ADDRESS="hello@example.com"`, so 1.7 cannot pass until it is updated by hand.

## Findings

### F1 — Remember-me test does not prove the session expired

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/Auth/AuthenticationTest.php:119-128
- **Detail**: The test travels 25 h and sends all cookies (session cookie and recaller). If the lifetime were 48 h or more, the session cookie would still authenticate and the test would pass without the recaller being used.
- **Fix**: Send only the recaller cookie (or assert `Auth::viaRemember()` is true) after the travel.
- **Decision**: FIXED via Fix — remember-me test now also asserts `Auth::viaRemember()`

### F2 — Mailpit image is unpinned (`:latest`) in CI and Sail

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: .github/workflows/master.yml:156, docker-compose.yml:77
- **Detail**: `tests/e2e/support/mailpit.ts` depends on the `/api/v1/search` and `/api/v1/message/{id}` response shapes. An upstream change breaks CI with no repo change. `postgres:18-alpine` next to it is pinned. The plan specified `:latest`, so this is a plan weakness, not drift.
- **Fix**: Pin a Mailpit version tag in both files.
- **Decision**: FIXED via Fix — Mailpit pinned to `axllent/mailpit:v1.33.1` in master.yml and docker-compose.yml

### F3 — Environment defaults can silently undo the slice outside local dev

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: .env.example:34,48-55, phpunit.xml:31
- **Detail**: (a) Any `.env` created from the old example keeps `SESSION_LIFETIME=120`, which overrides the new 1440 default, so dev/prod stay at 2 h (the plan's Migration Notes mention it, nothing enforces it). (b) `.env.example` now defaults to `MAIL_MAILER=smtp` with host `mailpit`: a deploy built from it before F-02 returns HTTP 500 on `POST /forgot-password` for existing accounts. (c) `phpunit.xml` pins `SESSION_LIFETIME=1440`, so no test covers the config default itself.
- **Fix**: Record in roadmap item F-02 (production deploy) that production must set `MAIL_*` and either unset or set `SESSION_LIFETIME=1440`; optionally drop the phpunit pin so the config default is what gets tested.
  - Strength: The slice deliberately deferred production mail to F-02, so the checklist lives where the work is done.
  - Tradeoff: Relies on a note being read; nothing fails automatically.
  - Confidence: MED — depends on how F-02 is executed.
  - Blind spot: Actual production `.env` content is unknown.
- **Decision**: FIXED (follow-up) — production `MAIL_*` / `SESSION_LIFETIME` notes queued in follow-ups/review-fixes.md for F-02; phpunit.xml pin kept on purpose (a developer `.env` with 120 would otherwise break the boundary tests)

### F4 — Touched `User.php` still lacks `declare(strict_types=1)`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Models/User.php:1
- **Detail**: CLAUDE.md says to add the declaration when touching a PHP file. Phase 1 removed a line from this file, and `ProfileController.php` got it in the same commit.
- **Fix**: Add `declare(strict_types=1);` after the opening tag.
- **Decision**: FIXED via Fix — `declare(strict_types=1);` added to app/Models/User.php

### F5 — Test locale not pinned

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: phpunit.xml
- **Detail**: The Polish assertions in `RegistrationTest` and `PasswordResetTest` rely on the `config/app.php` default (`APP_LOCALE` falls back to `pl`). A developer `.env` with `APP_LOCALE=en` breaks them.
- **Fix**: Add `<env name="APP_LOCALE" value="pl"/>` to phpunit.xml.
- **Decision**: FIXED via Fix — `APP_LOCALE=pl` pinned in phpunit.xml

### F6 — Dead `status` prop left in the profile form

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue:8-12, resources/js/Pages/Profile/Edit.vue
- **Detail**: The `status` prop is still declared and passed but its only consumer (the verification-link-sent message) was removed.
- **Fix**: Remove the prop from the partial and from the `Edit.vue` pass-through (keep `status` in `ProfileController::edit` only if another consumer needs it).
- **Decision**: FIXED via Fix — `status` prop removed from both Vue files, and from `ProfileController::edit`

### F7 — Polish validation attributes leak into non-account forms

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: lang/pl/validation.php (`attributes`, `custom`, `uploaded`), lang/pl.json
- **Detail**: `name => imię` is global, so a recipe-name failure on a rule without a custom message reads "Pole imię …". Other form fields (`first_name`, `birth_date`, `calories`, …) have no Polish label. The stock `custom` placeholder block is dead. `uploaded` reads awkwardly, and the reset-mail line "Jeśli to nie Ty prosiłeś" is gendered.
- **Fix**: Add `attributes()` with `name => nazwa` to the recipe Form Requests, add the missing attribute labels, and use neutral wording in `pl.json`.
- **Decision**: FIXED via Fix — `attributes()` (`name` → `nazwa przepisu`) in Store/UpdateRecipeRequest, dead `custom` block emptied, `uploaded` reworded, reset-mail line made gender-neutral. Missing attribute labels for family-member fields not added (their requests carry their own messages)

### F8 — E2E Mailpit helper is thin on error handling

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/e2e/support/mailpit.ts:9-40, tests/e2e/password-reset.spec.ts, tests/e2e/login.spec.ts
- **Detail**: `fetch` is not wrapped in try/catch, so a stopped Mailpit throws `TypeError: fetch failed` with no hint to start it. `response.ok` is not checked on the message fetch. `findResetPath` repeats the search, and `findResetMessage` is a pure alias. `generateRandomEmail` is copy-pasted in two specs, and `context.close()` is not in a `finally`.
- **Fix**: Catch network errors in the polling loop and put `MAILPIT_URL` in the error. Check `response.ok`. Move `generateRandomEmail` into `support/`.
- **Decision**: FIXED via Fix — helper retries on network errors, checks `response.ok`, names `MAILPIT_URL` in the error; `generateRandomEmail` moved to support/mailpit.ts; context closed in `finally`

## Notes (not findings)

- **Stale specs.** `.ai/api/{meal-plan,family-members,recipe}-implementation-plan.md` still mention `verified`. The plan's "NOT doing" excluded them. `.ai/ui/meal-plans-view-implementation-plan.md:9` is not covered by that exclusion.
- **No registration throttle.** Dropping e-mail verification means no one confirms an address is theirs. `/register` and `/forgot-password` have no route throttle beyond the 60 s broker throttle. This matters once real SMTP is live (F-02).
- **English left on the landing page.** `Welcome.vue` still shows "Log in" and "Register". It is outside the plan's scope ("No translation of non-account screens").
- **Local test output.** `test-results/` is root-owned from an earlier Docker run, so a plain `npm run test:e2e` fails with EACCES on the host. Playwright ran with `--output` elsewhere. Fix with `sudo chown -R $USER test-results`, or remove the directory.
