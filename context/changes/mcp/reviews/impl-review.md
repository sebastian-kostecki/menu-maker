<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Recipes over MCP (add-recipe-tool)

- **Plan**: context/changes/mcp/plan.md
- **Scope**: Full plan (commits 9116296..1314c6a); all Manual rows still pending
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-28
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 7 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Success criteria

All automated criteria pass: composer show, build, full suite (667), phpstan, pint, lint:tokens,
migrations on `testing`, config/route caching, tools/list, E2E (36), PRD grep. The first full-suite run
had 10 failures (deadlock, missing tables) because a parallel session used the same test database;
the rerun passed. The rerun used the Symfony patch versions that session installed (composer.lock is
modified and uncommitted; not part of this change). Manual rows 1.8-1.10, 2.10-2.12, 3.3-3.5 are pending.

## Findings

### F1 — Anonymous requests can forge and flood mcp.log

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Middleware/LogMcpCall.php:41, routes/ai.php:30
- **Detail**: `method` and `params.name` are logged unbounded and with line breaks kept, also for 401s, which `throttle:mcp` never counts. A newline in `method` writes a forged log line; large bodies can fill the disk.
- **Fix**: Strip control characters, limit both values to 100 characters, and add a per-IP throttle before auth.
- **Decision**: FIXED

### F2 — GET /oauth/device answers 500 to anyone

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Providers/AppServiceProvider.php (register)
- **Detail**: Passport's device grant is on by default and no view is bound. Confirmed locally: 500 and a BindingResolutionException in the log, which reaches mail_alert in production.
- **Fix**: `Passport::$deviceCodeGrantEnabled = false;` in `register()`, with a 404 test.
- **Decision**: FIXED

### F3 — OAuth endpoints share one per-IP throttle counter

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: routes/ai.php:27
- **Detail**: Unprefixed `throttle:10,1` shares its bucket with /oauth/token and /client-errors. Other hits from one IP (claude.ai egress) can make discovery and registration answer 429.
- **Fix**: `throttle:10,1,mcp-oauth`, as routes/web.php already prefixes its throttle.
- **Decision**: FIXED

### F4 — Deploy succeeds without Passport keys

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: scripts/lib/production-deploy.sh:90
- **Detail**: The required-env list lacks PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY and the health check only hits /up, so a deploy without them passes and /mcp and /oauth/* answer 500.
- **Fix**: Add both keys to `production_assert_env_ready` (and to its tests, if any).
- **Decision**: FIXED

### F5 — Password change or reset does not revoke MCP tokens

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/Auth/NewPasswordController.php, PasswordController.php
- **Detail**: Refresh tokens last 30 days and renew on each use; nothing revokes them and there is no connected-apps screen. A stolen token keeps write access after a password reset. The plan did not ask for revocation (plan gap).
- **Fix A ⭐ Recommended**: Revoke the user's access and refresh tokens on password change and reset.
  - Strength: Small change; password reset becomes the kill switch.
  - Tradeoff: The connector must sign in again after every password change.
  - Confidence: HIGH — Passport tokens are revocable per user.
  - Blind spot: No per-app revocation.
- **Fix B**: Add a connected-apps list with revoke on the profile page.
  - Strength: Visible, targeted control.
  - Tradeoff: New UI, routes, tests and visual baselines.
  - Confidence: MEDIUM — needs its own plan.
  - Blind spot: Design not explored.
- **Decision**: FIXED via Fix A

### F6 — No upper bound on servings, ingredients or instructions

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Http/Requests/Concerns/ValidatesRecipeFields.php:21, ValidatesRecipeIngredients.php:39
- **Detail**: servings=3000000000 passes validation and overflows the integer column (internal error, SQL with recipe text sent to the log and mail_alert). Ingredient count and instructions length are unbounded on an automatable path. UpdateRecipeRequest keeps its own copy of the rules, so limits added to the trait would miss updates.
- **Fix A ⭐ Recommended**: Add max limits (servings 1000, ingredients 100, instructions 20000) to the shared rules and tool schema, and use the trait in UpdateRecipeRequest.
  - Strength: Form and MCP stay identical, which was the plan's aim.
  - Tradeoff: Changes web form behaviour ("No change to the web form" concerned duplicates only).
  - Confidence: HIGH.
  - Blind spot: Existing recipes above the limits were not checked.
- **Fix B**: Add the limits only in StructuredRecipeImporter, as for calories.
  - Strength: Web form untouched.
  - Tradeoff: Form and MCP drift further; the form keeps the servings overflow.
  - Confidence: HIGH.
  - Blind spot: None significant.
- **Decision**: FIXED via Fix A

### F7 — Two tests would not catch a regression

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/Mcp/AddRecipeToolTest.php:121, tests/Unit/Services/StructuredRecipeImporterTest.php:30
- **Detail**: The 1e12 case only expects the word "calories", which a raw SQL overflow error also contains with debug on. The rounding assertions also hold without the importer's rounding, because PostgreSQL and the cast round. (Found by reading, not by break-check.) An unknown numeric MCP_LOCAL_USER id is also untested.
- **Fix**: Assert the validation message text, test rounding where the database cannot mask it, and add the unknown-id case.
- **Decision**: FIXED

### F8 — Stale OAuth data and unused published config

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: bootstrap/app.php (schedule), config/mcp.php:86
- **Detail**: passport:purge is not scheduled; open registration accumulates clients. config/mcp.php keeps vendor example `clients` (github, linear, an npx playwright command) that nothing uses.
- **Fix**: Schedule passport:purge daily and set `'clients' => []`.
- **Decision**: PENDING

### F9 — Consent page does not show the redirect host

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Pages/Auth/OAuthAuthorize.vue:19
- **Detail**: The client chooses its own name, so any client can call itself "Claude". The redirect allowlist limits the damage.
- **Fix**: Show the redirect host under the client name.
- **Decision**: PENDING

### F10 — Duplicate-name check is not atomic

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Services/RecipeImport/StructuredRecipeImporter.php:44
- **Detail**: Two parallel calls with the same name can both insert. Read from code, not reproduced.
- **Fix**: Take a per-user advisory lock inside the import transaction around the check and insert.
- **Decision**: PENDING

## Rollout note

Check that the Traefik security headers (another repo) have no CSP `form-action 'self'`, which would
block the consent form's redirect to claude.ai. Not verified.

## Triage (2026-11-28)

F1-F7 fixed (F5 and F6 via Fix A); F8-F10 left open. After the fixes: full suite 678, phpstan, pint,
deploy-script tests, config/route caching and E2E (36) pass; GET /oauth/device answers 404. E2E first
failed 4 password tests because the development database lacked the oauth_* tables; passed after
`artisan migrate`. No separate break-check was run on the new tests. Existing recipes above the new
limits (1000 servings, 100 ingredients, 20 000 characters) were not looked for. Not committed.
