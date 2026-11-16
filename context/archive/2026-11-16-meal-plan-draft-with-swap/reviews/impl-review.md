<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Meal Plan Draft with Recipe Swap

- **Plan**: context/changes/meal-plan-draft-with-swap/plan.md
- **Scope**: Full plan (all phases whose Automated rows are complete; Manual rows are still pending)
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-16
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 6 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Automated verification (re-run during review)

| Check | Result |
|---|---|
| `sail artisan migrate:fresh` → `migrate:rollback --step=1` → `migrate` | PASS |
| `sail composer test` | PASS: 454 passed (1812 assertions) |
| `sail composer phpstan` | PASS: no errors; `phpstan-baseline.neon` untouched |
| `sail composer pint-test` | PASS |
| `sail artisan route:list --name=meal-plans` | PASS: `meal-plans.approve`, `meal-plans.draft` and `meal-plans.meals.update` (`PUT/PATCH meal-plans/{meal_plan}/meals/{meal}`) registered |
| `sail npm run build` | PASS (client + SSR) |
| `sail npm run lint:tokens` + self-test | PASS: 72 files, 0 hits |
| `db:seed --class=E2eMealPlanSeeder` + `pest --filter=E2eMealPlanSeeder` | PASS: 6 passed |
| E2E (`npm run test:e2e`, against `localhost:8091`) | PASS: 36 passed (chromium + firefox) |
| Visual gate (`npm run test:visual`, worktree network) | 50 passed, 1 failed (`design-system.spec.ts:57 › shell nav hover`). The failure passed twice on rerun, so it is flaky; that baseline is not touched by this change. |

Every automated planned change was verified as MATCH. That covers: the race-safety contracts (conditional swap, redraw under `lockForUpdate`, conditional approve and back-to-draft, back-to-draft clearing ingredients in the same transaction), the throttle on store/update only, the limiter moving to approve, the scaling-only job, the migration `down()`, `recipeOptions`, and the owner-scoped `exists` rule. The only additions outside the plan are the Larastan docblocks in `Meal.php`/`MealPlan.php`, which are justified, and some extra race/no-op tests.

## Findings

### F1 — Scaling job overwrites status without checking it

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Jobs/GenerateMealPlanJob.php:78,86
- **Detail**: `handle()` runs `$this->mealPlan->update(['status' => 'processing'])` and later `'done'` without checking the current status. This change adds a new path out of `done`/`error` (back to draft, followed by swaps), so any re-run of an old job would flip an edited draft to `done`. That skips approval and the 5/hour AI limit, and races with swaps. Re-runs come from `queue:retry` on `failed_jobs` or from a future `$tries > 1`. Today this is masked only by `--tries=1` (`docker-compose.yml:32`, CI `queue:work --tries=1`) and by `failed()` guarding on a fresh `pending|processing` status.
- **Fix**: Start `handle()` with a conditional claim, `MealPlan::whereKey($id)->where('status', 'pending')->update(['status' => 'processing'])`, return early (log and finish the log) when it updates 0 rows, and make the final `done` write conditional on `status = 'processing'`.
  - Strength: Uses the same claim pattern as approve, back-to-draft and the old `update`, so the state machine holds whatever the worker's retry setting.
  - Tradeoff: A few lines plus one job test ("a job for a plan no longer pending does nothing").
  - Confidence: HIGH — the pattern already exists in `MealPlanController`.
  - Blind spot: Not checked whether `WithoutOverlapping` with `dontRelease()` interacts with an early return (it should not).
- **Decision**: FIXED. `handle()` claims `pending → processing` with a conditional update and returns early, logging a warning and creating no log row, when it claims nothing. The later writes stay unguarded on purpose: no other action leaves `processing`. Test: "leaves a plan that is no longer pending untouched" (draft, done, error, processing).

### F2 — Approve rate limit is check-then-hit, not atomic

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Http/Requests/ApproveMealPlanRequest.php:43,70
- **Detail**: `RateLimiter::tooManyAttempts($key, 5)` runs in `withValidator()`, and `RateLimiter::hit()` runs later in `passedValidation()`. Parallel approvals of different drafts can all pass the check before any of them hits, and drafts are cheap (30/min). This is now the only cost guard on the AI path. Separately, an approve that loses the claim race in the controller has already consumed a hit. The pattern was carried over from the old store request.
- **Fix A ⭐ Recommended**: Hit first and compare: `if (RateLimiter::hit($key, 3600) > 5) throw …` (atomic increment on the cache store), keeping `availableIn` for the message.
  - Strength: Closes the parallel bypass with a one-line change in one place.
  - Tradeoff: Refused approvals (wrong status, empty draft) also count towards the 5. Rare in practice.
  - Confidence: HIGH — Laravel's `hit()` returns the incremented count.
  - Blind spot: Not checked which cache store the app uses (the database store increments atomically; the array store in tests is fine).
- **Fix B**: Accept as a pre-existing pattern and note it in the plan.
  - Strength: No code change. The app is single-family, and abuse needs deliberate parallel requests.
  - Tradeoff: The guard on the paid AI path stays bypassable.
  - Confidence: MED — the risk is low, but cost is the reason the limiter exists.
  - Blind spot: Real AI cost per scaling run not measured.
- **Decision**: FIXED via Fix A. `withValidator()` calls `RateLimiter::hit($key, 3600) > 5` and `passedValidation()` was removed. Test: "a refused approval still counts towards the hourly limit".

### F3 — Visual baselines changed beyond the plan's list

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/e2e/visual/meal-plan-show.spec.ts-snapshots/meal-plan-show-mobile.png, tests/e2e/visual/design-system.spec.ts-snapshots/overlay-user-menu.png
- **Detail**: Plan §4 said the done-plan shots "differ only in the header button". On mobile, "Wróć do szkicu" is wider than "Regeneruj", so the header wraps to a second row together with the ⋯ menu. The PNG grows from 1562 to 1566 px and the whole page below shifts (diff bbox y 169–1544). `overlay-user-menu.png` is not in the list: its 24 px of change are the anti-aliased corner showing through the kitchen-sink StatusTag row, which shifted when "Szkic" was added. `meal-plan-show-desktop.png` differs only in the header box, as planned.
- **Fix**: Look at `meal-plan-show-mobile.png` (manual row 4.6). If the wrapped header is acceptable, record both deviations in the plan as accepted; otherwise shorten the mobile label.
- **Decision**: ACCEPTED. The mobile PNG was reviewed: the header actions wrap cleanly onto a second row with nothing overlapping. Recorded in the plan's "Review Addendum". Manual row 4.6 stays with the human.

### F4 — Manual checklist still pending before merge

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/meal-plan-draft-with-swap/plan.md:685,706-711,725
- **Detail**: Manual rows 1.6, 3.4–3.9 and 4.6 are all `[ ]`; none was falsely ticked. Phase 3's checks are the user-facing ones: phone-width dialog, keyboard operability, reload persistence, re-approval restoring quantities. E2E covers 3.4, 3.5 (without search), 3.7 and 3.8, but not 3.6 (redraw), 3.9 (mobile/keyboard) or the 6th-approval limit message. Also, the visual gate's `shell nav hover` shot failed once and passed on two reruns, which points to hover-timing flakiness unrelated to this change.
- **Fix**: Walk through 3.4–3.9 and 4.6 on Sail (`localhost:8091`) and tick them before merging into `develop`.
- **Decision**: ACCEPTED. The user accepted the manual checks 1.6, 3.4–3.9 and 4.6 on 2026-11-16, and the rows are ticked in the plan's Progress.

### F5 — Swap dialog stays open after a "not a draft" error

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/Components/MealPlans/RecipeSwapDialog.vue:113-119
- **Detail**: If the plan was approved in another tab, the swap returns 422 `status`. The partial reload (`only: ['mealPlan']`) turns the grid read-only, but `recipeOptions` is not reloaded, so `v-if="recipeOptions"` keeps the dialog mounted and open. The user can keep picking and keep getting the same toast.
- **Fix**: In `onError`, emit `update:open` false when `errors.status` is present.
- **Decision**: FIXED.

### F6 — Row menu's confirm copy differs from the Show page

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/Components/MealPlans/ActionDropdown.vue:55-60,158
- **Detail**: The plan asked for the "same confirm text and POST as Show".
  - ActionDropdown: title "Wrócić do szkicu?", description "Przepisy zostaną, a ilości…".
  - Show (`Show.vue:129-130`): title "Wróć do szkicu", description "Wrócić do szkicu? Przepisy zostaną…".
  - The dropdown also adds a success toast, "Jadłospis wrócił do szkicu.", that Show does not have.
- **Fix**: Pick one title/description pair (the dropdown's question-as-title reads better) and use it in both places.
- **Decision**: FIXED differently. The dropdown now uses Show's copy (title "Wróć do szkicu", the question in the description), because both files title their other dialogs with the action ("Usuń jadłospis", "Losuj ponownie"). The dropdown keeps its success toast, since it has no page change to show the result.

### F7 — Swap dialog bypasses the ui/combobox wrapper and formats dates by hand

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Components/MealPlans/RecipeSwapDialog.vue:43,84-86
- **Detail**: The component imports `ComboboxContent` and `ComboboxViewport` straight from `reka-ui` (probably to get `position="inline"`), while the plan and CLAUDE.md say to build on `Components/ui/combobox`. It also builds `${day}.${month}` by hand instead of using a `lib/format.js` helper.
- **Fix**: Add a `dd.mm` helper next to `formatWeekdayShort` in `lib/format.js`. Either expose an inline variant of `ComboboxList` in `ui/combobox`, or leave a comment explaining why the raw reka-ui parts are used.
- **Decision**: FIXED. Added `formatDayMonth` to `lib/format.js`. The raw reka-ui import is kept with a comment: `ComboboxList` always portals out of the dialog's focus trap and is sized as a popper.

### F8 — Swap's draft guard doesn't lock the plan row

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/MealPlanMealController.php:29-35
- **Detail**: The swap is one `UPDATE meals … WHERE EXISTS (meal_plans … status='draft')`, as the plan specified. Under READ COMMITTED the subquery does not lock `meal_plans`, so a swap that started a moment before an approval commits can still land after the plan becomes `pending`. Harm is negligible: the job reads meals later. Separately, a swap racing a redraw hits a deleted meal row, updates 0 rows and shows the misleading "tylko w szkicu" message.
- **Fix**: Accept and note it in the plan. Strict ordering would need `lockForUpdate()` on the plan inside a transaction, as `update()` does.
- **Decision**: ACCEPTED. Recorded in the plan's "Review Addendum".

### F9 — Small test-coverage gaps

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Unit/MealRequestValidationTest.php
- **Detail**: The Testing Strategy (plan:626) lists "swap: … draft only" as a Form Request unit test. The unit test builds the validator from `rules()`/`messages()` only and never exercises `after()`; draft-only is covered only by feature tests (`MealPlanMealControllerTest.php:116,127`). No test covers the `throttle:30,1,meal-plan-draw` route middleware, in particular that it does not share the `client-errors.store` bucket.
- **Fix**: Add a unit case for the `after()` status check and one feature test asserting a 429 on the 31st store or update within a minute.
- **Decision**: FIXED. The unit validator now runs `after()`, and a new case covers "refuses a swap outside a draft". A new feature test checks that store and redraw share the 30/min throttle and that client error reports are unaffected.

### F10 — Kitchen-sink swap demo sends a real PATCH

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/Pages/Dev/DesignSystem.vue:873-886
- **Detail**: The demo mounts the real `RecipeSwapDialog`. Picking an option fires `PATCH meal-plans/1/meals/{id}` (local/testing only), which normally ends in a 404/403 toast but can modify a real meal if the developer owns plan 1 and it is a draft.
- **Fix**: Give the demo a `demo` prop or an `@select` handler that skips the request, or pass a non-existent plan id.
- **Decision**: FIXED. `RecipeSwapDialog` has a `demo` prop (picking only closes the dialog), and the kitchen sink uses it.
