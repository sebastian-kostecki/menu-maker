# AI Ingredient Scaling (S-04) Implementation Plan

## Overview

Every generated meal gets its own ingredient quantities, scaled to the family's energy needs. Code computes each person's daily kcal from the NIZP-PZH 2020 norms (age, sex and, for adults, weight and height). It splits that 30/40/30 across Śniadanie/Obiad/Kolacja and multiplies each recipe's quantities by (family kcal for that meal ÷ recipe total kcal).

An AI driver (OpenRouter or the Anthropic API) then rounds the computed amounts to practical kitchen amounts inside code-enforced guardrails. The result is stored per meal. The grid, the shopping list and the PDF all read it.

When there is no family, or the AI fails after its retries, the plan still finishes. The user sees a notice, and an AI failure is reported once to the error log and mail alert.

Roadmap: S-04 (`context/foundation/roadmap.md:150-165`). PRD: US-012, US-004, FR-005, FR-002.

## Current State Analysis

- **Generation.** `GenerateMealPlanJob::handle()` calls `MealPlanGenerator::generate()`, which replaces the plan's 21 `meals` inside one `DB::transaction` (`app/Services/MealPlanGenerator.php:65-106`). It then stores `generation_meta = {generated_at, regenerate, version: 'random-v1'}` (`app/Jobs/GenerateMealPlanJob.php:81-87`).
- **Quantities.** `meals` stores none (`database/migrations/2025_07_31_145701_create_meals_table.php`). Every consumer reads `recipe_ingredients.quantity` as written:
  - `ShoppingListBuilder::forMealPlan` (`app/Services/ShoppingList/ShoppingListBuilder.php:70-112`). Its pure `build(iterable<QuantityLine>)` is the S-05 seam reserved for S-04.
  - `MealPlanPdfExporter::pdfMeal` (`app/Services/MealPlanPdf/MealPlanPdfExporter.php:77-100`).
  - `MealPlanResource` `meals[].recipe.ingredients` (`app/Http/Resources/MealPlanResource.php:60-75`). This one also has a stale `'pcs'` unit fallback at `:71`.
- **UI.** `MealPlanGrid.vue` shows only the recipe name, kcal and servings per cell (`resources/js/Components/MealPlans/MealPlanGrid.vue:28-40`). The Inertia show branch loads only `meals.recipe` (`app/Http/Controllers/MealPlanController.php:96-105`). `Show.vue` reads only `generation_meta.error_message` (`resources/js/Pages/MealPlans/Show.vue:123,150`).
- **Categories.** `MealCategory` stores `supper` and shows it as **Obiad**, and stores `dinner` and shows it as **Kolacja** (`app/Enums/MealCategory.php:8-30`).
- **Recipes.** `recipes.calories` is `decimal(10,2)`, the whole recipe ("kaloryczność całkowita", PRD §3.3, db-plan §1.5). Validation is `min:0` (`app/Http/Requests/StoreRecipeRequest.php:35`, `UpdateRecipeRequest.php:36`), so 0 kcal is accepted. The form label is just "Kaloryczność (kcal)" (`resources/js/Components/recipes/RecipeForm.vue:68`).
- **Family profile.** `family_members` has `first_name`, `birth_date` (NOT NULL), `gender` (male/female) and nothing about body size (`database/migrations/2025_07_31_145634_create_family_members_table.php`). `birth_date` only needs `before:today` (`app/Http/Requests/FamilyMemberRequest.php:28-30`). Nothing outside the CRUD reads family members, and generation runs with zero members. `tests/Unit/FamilyMemberPolicyTest.php` does not exist; CLAUDE.md requires it.
- **No AI plumbing.** There is no client, no `config/services.php` entry, no `.env.example` key and no `Http::fake` anywhere. Guzzle is present transitively, so the `Http` facade works.
- **Queue.**
  - Sail runs `queue:listen --tries=1` with the default `--timeout=60` (`docker-compose.yml:28-30`); CI runs `queue:work --tries=1` (`.github/workflows/master.yml:206`). `retry_after` is 90 (`config/queue.php:42`).
  - The job declares no `$timeout`. A `WithoutOverlapping(...)->dontRelease()->expireAfter(300)` guards it.
  - A job-level retry under `--tries=1` is a failure, so AI retries must happen inside one attempt.
- **Error tracking.**
  - `report($e)` logs at error level, which goes to the daily log and the `mail_alert` channel, deduped per class+file+line for one hour (`app/Logging/MailAlertHandler.php:148-157`).
  - F-01 rules: one failure produces one entry, and no family data goes into log context (`context/archive/2026-11-13-error-tracking-baseline/plan.md:41,48`).
  - `user_id` and `meal_plan_id` are already in Context (`app/Http/Middleware/AddLogContext.php:24`, `GenerateMealPlanJob.php:66`).
- **Tests that pin unscaled amounts:**
  - `tests/e2e/meal-plan.spec.ts:26-39` expects "21 szt.", "2,1 kg" and "1,05 l". The seeder (`database/seeders/E2eMealPlanSeeder.php`) creates no family, random recipe kcal, and the same 100 g / 50 ml / 1 szt on every recipe.
  - `tests/Unit/Services/MealPlanPdfExporterTest.php:52-68` expects quantities "as written".
  - `tests/Unit/Jobs/GenerateMealPlanJobTest.php:37` expects `version === 'random-v1'` and calls `handle(app(MealPlanGenerator::class))` directly.
  - `ShoppingListBuilderTest` (`forMealPlan` cases) and `MealPlanControllerTest:416-458` build plans via the `mealPlanWithRecipes` / `attachIngredient` helpers (`tests/Pest.php:33-65`).

## Desired End State

- **Grid.** On a done plan, every cell has a "Składniki" toggle listing that meal's scaled amounts ("Mąka — 200 g", "Jajka — 2 szt.").
- **List and PDF.** The shopping list and the PDF show the same scaled amounts, summed and formatted by the S-05 rules. A plan's quantities are a snapshot of its generation: a later recipe or family edit changes nothing until "Regeneruj".
- **Scaling rule** (worked example):
  - Family: a woman aged 40 (165 cm, 65 kg) = 2400 kcal/day; a man aged 40 (180 cm, 75 kg) = 3000; a child aged 7 = 1800. Total 7200 kcal/day.
  - Obiad (`supper`, 40%) = 2880 kcal. A 1440-kcal Obiad recipe gets factor 2.0, so 100 g of flour becomes 200 g before AI rounding.
- **Notices** sit above the grid and at the top of the PDF, for these states:
  - no family members: amounts as written, plus a link to the family profile;
  - AI failed: computed amounts without AI rounding;
  - recipes with 0 kcal: not scaled, named;
  - adults without weight or height: reference row used, named;
  - a plan generated before this change: suggest regenerating.
- **Family profile.** Weight (kg) and height (cm) can be entered, and are required for members aged 19+.
- **Configuration.** `AI_SCALING_DRIVER` picks `openrouter`, `anthropic` or `fake`. CI and E2E use `fake`; production uses a real driver with its key.
- **Verification.** Pest, Larastan, Pint and the build are green. E2E asserts the deterministic scaled totals ("42 szt.", "4,2 kg", "2,1 l") after generating and after regenerating.

### Key Discoveries:

- **The list seam.** `ShoppingListBuilder::build()` already takes any `iterable<QuantityLine>` with decimal-string quantities (`app/Services/ShoppingList/QuantityLine.php:13-19`). Only the line source changes.
- **Category mapping.** The 30/40/30 split maps to `breakfast` 0.30, `supper` (Obiad) 0.40 and `dinner` (Kolacja) 0.30. Do not map it by enum name.
- **Time budget.** A job-level `public int $timeout` overrides the worker's `--timeout` only under `queue:work` (CI; `Worker::timeoutForJob`). Sail's `queue:listen` also wraps each child in a Symfony Process killed after the listener's own `--timeout` (default 60, `Listener.php:122-128`), so Sail's queue command must get `--timeout=85`. 80 s stays below `retry_after` 90. Three AI attempts of ≤20 s each fit inside that.
- **Placement.** The AI call must sit outside `MealPlanGenerator`'s transaction, after `generate()` returns. `meals` cascade-delete, so regeneration wipes the old `meal_ingredients` rows automatically.
- **No new UI dependency.** There is no Collapsible in `resources/js/Components/ui/`. A native `<details>`/`<summary>` avoids adding a shadcn component.
- **CI env.** CI writes `.env` inline in a heredoc (`.github/workflows/master.yml:186-200`). `AI_SCALING_DRIVER=fake` goes there.
- **PHP SDK.** The official Anthropic package is `anthropic-ai/sdk` (`use Anthropic\Client;`). Top-level named args are camelCase (`maxTokens`, `outputConfig`). Structured output uses `outputConfig: ['format' => ...]`, and effort goes inside `outputConfig`.
  - On `claude-opus-5-5`, thinking is always on and the default effort is `medium`, so set `effort: 'low'` explicitly for this rounding task.
  - Never send `thinking: disabled` or `budget_tokens`; both return 400.
  - Copy the SDK call shapes from the SDK's documented examples; do not guess them.

## What We're NOT Doing

- **Subscriptions.** No Claude Pro/Max subscription login, and no `claude -p` in the worker. A deployed app needs a Console API key.
- **Norms.** We use the 2020 norms, not the newer Normy żywienia 2024. There are no pregnancy or breastfeeding additions and no per-person activity level: everyone uses the "umiarkowana" (moderate) values.
- **AI scope.** The AI may not change units, switch unit groups or add or remove ingredients. Only the quantity changes.
- **Live data.** No live recalculation from recipe or family edits; the snapshot holds until regeneration. No backfill of scaled values for old plans; they become `as_written` rows.
- **Editing.** No editing of scaled amounts (PRD §4).
- **Other scope cuts:**
  - no per-meal partial AI success (one batch call; invalid items fall back individually, but there is one success/failure state per plan);
  - no streaming;
  - no AI on any request path.
- **Docs.** The PRD is not edited (FR-002's new body fields are recorded in `.ai/core/db-plan.md` instead). The pre-existing English messages in `FamilyMemberRequest` stay unchanged; only the new rules get Polish messages.

## Implementation Approach

1. A pure, bcmath-based core (`app/Services/IngredientScaling/`) turns family members and a plan's meals into computed quantities.
2. A `MealIngredientScaler` step in the job persists those quantities as `meal_ingredients` snapshot rows.
3. An `IngredientRefiner` interface lets a driver propose rounded quantities. A `RefinementGuard` accepts each proposal or reverts it to the computed value.
4. The three consumers switch from recipe quantities to `meal_ingredients`.
5. One `ScalingNotices` class turns `generation_meta.scaling` into Polish strings for both the resource and the PDF.

Phases go bottom-up (rules, then storage and consumers, then AI, then UI, then E2E). The app works and stays green after each phase.

## Critical Implementation Details

- **Timing & lifecycle:** `scale()` runs after `generate()` commits and before `status = done`. The AI attempts happen with no DB transaction open, and the final `meal_ingredients` insert runs in its own short transaction. A plan is only ever `done` with its rows present: the UI polls on status, so writing rows after `done` would flash an empty list.
- **Performance constraints:**
  - per attempt: an HTTP timeout of 20 s (OpenRouter: the `Http` timeout; Anthropic: a Guzzle transport timeout, since the SDK does not enforce its own, with SDK `maxRetries` 0, so our loop owns retries);
  - at most 3 attempts with a 500 ms pause between them;
  - the job's `$timeout = 80`.
  
  A configuration error (missing key or model) is not retried.
- **State sequencing:** the plan notices and the PDF must not show family personal data in logs. `generation_meta.scaling` may store first names, because it is user data shown back to the same owner. Never put first names in `report()`/Context or in the AI payload. The AI payload holds only ingredient names, unit codes and quantities.

## Phase 1: Scaling rules and family body data

### Overview

Pure services that compute each member's daily kcal and a meal's scaling factor, plus the weight/height fields on the family profile that adult kcal depends on. Also: the recipe calories `gt:0` rule, the label change, and the missing `FamilyMemberPolicyTest`.

### Changes Required:

#### 1. Family member body data

**File**: `database/migrations/<timestamp>_add_body_measurements_to_family_members_table.php` (new), `app/Models/FamilyMember.php`, `database/factories/FamilyMemberFactory.php`

**Intent**: Store optional weight and height so adult energy needs follow the norms' "normal weight for height" rule. While touching the model and factory, add `declare(strict_types=1);` to them.

**Contract**:
- Columns: `weight_kg decimal(5,1) null` and `height_cm decimal(5,1) null`. Add both to fillable, with `decimal:1` casts.
- Factory states:
  - `adult()`: age 25–60, with weight and height inside normal BMI;
  - `child()`: age 2–15, with no body data.
- The default definition must stay valid under the new validation, so an adult default gets body data.

#### 2. Family member validation and forms

**File**: `app/Http/Requests/FamilyMemberRequest.php`, `resources/js/Pages/FamilyMembers/{Create,Edit,InlineEditDialog,FamilyMemberTable}.vue`, `app/Http/Controllers/FamilyMemberController.php` (only if it whitelists fields)

**Intent**: Accept `weight_kg` (30–300) and `height_cm` (100–250), and require both when the member's age at today, from the submitted `birth_date`, is 19 or more. The forms show both fields with client-side checks that mirror the server. The table shows them ("—" when empty).

**Contract**:
- Rules: `nullable|numeric|between:30,300` and `nullable|numeric|between:100,250`, plus a `Rule::requiredIf` driven by the age.
- Polish messages: "Podaj wagę (wymagana od 19 lat).", "Waga musi wynosić od 30 do 300 kg.", "Podaj wzrost (wymagany od 19 lat).", "Wzrost musi wynosić od 100 do 250 cm."

#### 3. Energy norms table

**File**: `app/Services/IngredientScaling/EnergyNorms.php` (new)

**Intent**: Hard-code the NIZP-PZH 2020 energy needs (chapter "Energia", Tables 2–4, `https://ncez.pzh.gov.pl/wp-content/uploads/2021/03/normy_zywienia_2020web.pdf`) at the "umiarkowana" (moderate) activity level. Return a person's daily kcal as a decimal string. Rows marked *derived* are an app extension: a least-squares line through each age band's four source rows, extended every 5 kg up to 100 kg and rounded to 50 kcal. The docblock must say so.

**Contract**:
- Signature: `dailyKcal(string $gender, CarbonInterface $birthDate, CarbonInterface $onDate, ?string $weightKg, ?string $heightCm): string`.
- Age is completed years (months below 1 year) at `$onDate`. A birth date after `$onDate` counts as 0 months.
- **Children and adolescents** (Table 2; the weight and height arguments are ignored):

| Age | Both / boys | Girls |
| --- | --- | --- |
| 0–6 months | 550 | 550 |
| 7–11 months | 700 | 700 |
| 1–3 | 1000 | 1000 |
| 4–6 | 1400 | 1400 |
| 7–9 | 1800 | 1800 |
| 10–12 | 2350 | 2100 |
| 13–15 | 3000 | 2450 |
| 16–18 | 3400 | 2500 |

- **Adults 19+** (Tables 3–4, PAL 1.75; columns are kg, * = derived):

| Men | 55 | 65 | 75 | 85 | 90* | 95* | 100* |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 19–30 | 2650 | 2900 | 3200 | 3450 | 3600 | 3750 | 3850 |
| 31–50 | 2600 | 2850 | 3000 | 3250 | 3350 | 3450 | 3550 |
| 51–65 | 2450 | 2650 | 2850 | 3050 | 3150 | 3250 | 3350 |
| 66–75 | 2150 | 2350 | 2550 | 2750 | 2850 | 2950 | 3050 |
| >75 | 2050 | 2250 | 2450 | 2650 | 2750 | 2850 | 2950 |

| Women | 45 | 55 | 65 | 75 | 80* | 85* | 90* | 95* | 100* |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 19–30 | 2000 | 2300 | 2550 | 2800 | 2950 | 3050 | 3200 | 3350 | 3450 |
| 31–50 | 2100 | 2250 | 2400 | 2550 | 2600 | 2700 | 2750 | 2850 | 2900 |
| 51–65 | 2000 | 2200 | 2300 | 2450 | 2550 | 2600 | 2650 | 2750 | 2800 |
| 66–75 | 1850 | 2050 | 2200 | 2350 | 2450 | 2500 | 2600 | 2700 | 2750 |
| >75 | 1750 | 1950 | 2100 | 2250 | 2350 | 2400 | 2500 | 2600 | 2650 |

- **Adult weight rule:**
  1. Clamp the actual weight to the normal-BMI range for the height (h in metres): `[18.5·h², 24.9·h²]`.
  2. Linearly interpolate kcal between neighbouring weight rows. Below the first row or above the last, use that row.
  3. An adult missing weight or height uses the reference row (men 75 kg, women 65 kg), and the caller is told: return a small value object, or expose `isReference`, so the scaler can list the member in the notice.

#### 4. Scaling calculator

**File**: `app/Services/IngredientScaling/ScalingCalculator.php` (new)

**Intent**: Turn the family's daily kcal and a meal into a factor and computed quantities, with bcmath at scale 12 and one half-up rounding to 2 decimals at output.

**Contract**:
- `MEAL_SHARES = ['breakfast' => '0.30', 'supper' => '0.40', 'dinner' => '0.30']`.
- `factor(string $familyDailyKcal, string $category, string $recipeCalories): ?string` returns `null` when the recipe calories are ≤ 0.
- `scale(string $quantity, string $factor): string` returns a 2-decimal string.

#### 5. Recipe calories rule and label

**File**: `app/Http/Requests/StoreRecipeRequest.php`, `app/Http/Requests/UpdateRecipeRequest.php`, `resources/js/Components/recipes/RecipeForm.vue`

**Intent**: Block new 0-kcal recipes, and make it explicit that the value is the whole recipe.

**Contract**:
- `calories` becomes `gt:0`, with the message "Kaloryczność musi być większa od zera." (replaces `calories.min`).
- Label: "Kaloryczność całkowita (kcal) *".

#### 6. Tests

**File**: `tests/Unit/FamilyMemberPolicyTest.php` (new), `tests/Unit/Services/EnergyNormsTest.php` (new), `tests/Unit/Services/ScalingCalculatorTest.php` (new), `tests/Unit/RecipeRequestValidationTest.php`, `tests/Feature/FamilyMemberTest.php`

**Intent**: Cover the four policy abilities for owner versus other user, and every rule boundary.

**Contract**:
- **Norms:**
  - infant at 6 versus 7 months (550/700);
  - age 18 versus 19 on the plan date;
  - band edges 30/31, 50/51, 65/66, 75/76;
  - a man at 180 cm, 75 kg = 3000 in the 31–50 band;
  - interpolation between 75 and 85 kg rows (80 kg, 180 cm man aged 40 = 3125);
  - BMI clamp (an overweight 165 cm woman is clamped to 24.9·1.65² ≈ 67.8 kg);
  - below the first row and above 100 kg;
  - missing body data giving the reference row plus the flag.
- **Calculator:**
  - the factor for the worked example (2880 / 1440 = 2);
  - `null` at 0 kcal;
  - rounding at the 2-decimal boundary.
- **Family requests:**
  - an adult without weight is rejected;
  - a child without weight is accepted;
  - range boundaries 30/300 and 100/250.
- **Recipes:** 0 kcal is rejected, 0.01 is accepted.

### Success Criteria:

#### Automated Verification:

- Migration applies cleanly: `vendor/bin/sail artisan migrate`
- New and updated unit tests pass: `vendor/bin/sail pest tests/Unit/FamilyMemberPolicyTest.php tests/Unit/Services/EnergyNormsTest.php tests/Unit/Services/ScalingCalculatorTest.php tests/Unit/RecipeRequestValidationTest.php`
- Family member feature tests pass: `vendor/bin/sail pest tests/Feature/FamilyMemberTest.php`
- Larastan and Pint are clean: `vendor/bin/sail composer phpstan` and `vendor/bin/sail composer pint-test`
- Client and SSR bundles build: `vendor/bin/sail npm run build`

#### Manual Verification:

- The child and adult values in `EnergyNorms` match Tables 2–4 in the NIZP-PZH 2020 PDF, checked visually
- Adding and editing a family member shows weight and height, requires them for an adult, and shows Polish errors

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Per-meal quantities

### Overview

Persist a computed snapshot per meal and make every consumer read it. After this phase plans are scaled by code (no AI yet). Old plans keep their amounts through an `as_written` backfill.

### Changes Required:

#### 1. `meal_ingredients` storage

**File**: `database/migrations/<timestamp>_create_meal_ingredients_table.php` (new), `app/Models/MealIngredient.php` (new), `app/Models/Meal.php`

**Intent**: One row per (meal, ingredient), keeping the recipe amount, the code-computed amount, the final amount and where the final amount came from.

**Contract**:
- Columns:
  - `id`;
  - `meal_id` FK `cascadeOnDelete`;
  - `ingredient_id` FK;
  - `unit_id` FK;
  - `recipe_quantity decimal(10,2)`;
  - `computed_quantity decimal(12,2)`;
  - `quantity decimal(12,2)`;
  - `source string(20)` (`ai` | `computed` | `as_written`, as string constants on the model);
  - timestamps;
  - `unique(meal_id, ingredient_id)`.
- Model: `decimal:2` casts and `ingredient()` / `unit()` relations. `Meal::ingredients(): HasMany` ordered by `id`.
- This is not a resource controller, so it needs no policy. Access goes only through an owner-authorized `MealPlan`.

#### 2. Backfill existing plans

**File**: the same migration (or a separate data migration right after it)

**Intent**: Give every existing meal `as_written` rows copied from its recipe's current `recipe_ingredients` (soft-deleted recipes included), so consumers have a single source.

**Contract**: An `INSERT … SELECT` from `meals` joined to `recipe_ingredients`, with all three quantities equal to `recipe_ingredients.quantity` and `source = 'as_written'`. `down()` drops the table.

#### 3. Scaler step in the job

**File**: `app/Services/IngredientScaling/MealIngredientScaler.php` (new), `app/Services/IngredientScaling/ScalingOutcome.php` (new), `app/Jobs/GenerateMealPlanJob.php`

**Intent**: After `generate()`, compute every meal's quantities from the owner's family members and write the rows. Record the outcome in `generation_meta.scaling`, bump the version and give the job its own timeout.

**Contract**:
- `scale(MealPlan $mealPlan): ScalingOutcome`.
  - It loads `meals.recipe.recipeIngredients.{ingredient,unit}` and the owner's family members via `where('user_id', $mealPlan->user_id)`.
  - Family daily kcal is the sum of `EnergyNorms::dailyKcal(..., $mealPlan->start_date, ...)`.
- Per meal:
  - no members: every row is `as_written` (factor 1);
  - recipe calories ≤ 0: the meal's rows are `as_written` and the recipe name is collected;
  - otherwise: `computed`.
- `ScalingOutcome` contents:
  - `status` (`computed` | `as_written`; Phase 3 adds `refined`; `as_written` only when the family is empty);
  - `unscaledRecipes`: unique recipe names, Polish-sorted;
  - `membersWithoutBody`: first names of adults on the reference row;
  - `toArray()`.
- Job:
  - `handle(MealPlanGenerator $generator, MealIngredientScaler $scaler)`;
  - `public int $timeout = 80;`;
  - `generation_meta` on success: `{generated_at, regenerate, version: 'scaled-v1', scaling: outcome->toArray()}`;
  - the existing error branches are unchanged.

#### 4. Consumers read the snapshot

**File**: `app/Services/ShoppingList/ShoppingListBuilder.php`, `app/Services/MealPlanPdf/MealPlanPdfExporter.php`, `app/Http/Resources/MealPlanResource.php`, `app/Http/Controllers/MealPlanController.php`

**Intent**: Build list lines, PDF ingredient lines and resource ingredients from `meal.ingredients` instead of `recipe.recipeIngredients`. Rows with quantity 0 are skipped everywhere.

**Contract**:
- **`forMealPlan`:**
  - eager-loads `meals.recipe` and `meals.ingredients.{ingredient,unit}`;
  - `QuantityLine.quantity` = `meal_ingredients.quantity`;
  - `recipesWithoutIngredients` = meals with a recipe and no rows;
  - the docblock no longer says "as written".
- **`pdfMeal`:** lines come from `meal->ingredients`, formatted the same way (`name — QuantityFormatter`).
- **`MealPlanResource`:**
  - `meals[].ingredients` (meal level, when loaded) = `[{ingredient_id, name, quantity, unit: {code}, display, source}]`, where `display` = `QuantityFormatter` output;
  - recipe-level `ingredients` is removed from this resource;
  - the stale `'pcs'` fallback goes.
- **`MealPlanController::show`:** both branches eager-load `meals.ingredients.{ingredient,unit}`. Update the comment at `:96-97`.

#### 5. Tests

**File**: `tests/Unit/Services/MealIngredientScalerTest.php` (new), `tests/Unit/Jobs/GenerateMealPlanJobTest.php`, `tests/Unit/Services/ShoppingListBuilderTest.php`, `tests/Unit/Services/MealPlanPdfExporterTest.php`, `tests/Feature/MealPlanControllerTest.php`, `tests/Pest.php`

**Intent**: Prove the scaling end to end in Pest, and re-anchor the existing quantity tests on the snapshot.

**Contract**:
- **Scaler cases:**
  - the worked-example family gives factor 2;
  - no family gives `as_written` everywhere and status `as_written`;
  - a 0-kcal recipe is listed and kept as written;
  - an adult without body data appears in `membersWithoutBody`;
  - another user's family members are ignored;
  - regeneration replaces the rows (cascade).
- **Job:** asserts `version === 'scaled-v1'` plus the `scaling` keys.
- **Helper:** `tests/Pest.php` gains a helper that writes `meal_ingredients` for a plan built by `mealPlanWithRecipes` (or runs the scaler with no family), so the existing list/PDF/controller assertions keep their meaning.
- **Backfill:** a migration test (or a scaler test against the backfill SQL) shows that an old-style plan reads its recipe amounts.

### Success Criteria:

#### Automated Verification:

- Migrations, including the backfill, apply and roll back cleanly: `vendor/bin/sail artisan migrate` then `vendor/bin/sail artisan migrate:rollback --step=1` then `vendor/bin/sail artisan migrate`
- Scaler, job, list, PDF and controller tests pass: `vendor/bin/sail pest tests/Unit/Services tests/Unit/Jobs tests/Feature/MealPlanControllerTest.php`
- Full suite passes: `vendor/bin/sail composer test`
- Larastan and Pint are clean: `vendor/bin/sail composer phpstan` and `vendor/bin/sail composer pint-test`

#### Manual Verification:

- With a family set up, generating a plan in the browser changes the shopping-list totals from the recipe-as-written amounts to the scaled ones, and an existing older plan still shows its old list

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: AI refinement

### Overview

Let an AI driver round the computed amounts to practical ones, inside code-enforced guardrails, with bounded retries. A single reported fallback covers the case where it fails.

### Changes Required:

#### 1. Refiner contract and guard

**File**: `app/Services/IngredientScaling/IngredientRefiner.php` (new interface), `app/Services/IngredientScaling/RefinementItem.php` (new), `app/Services/IngredientScaling/RefinementGuard.php` (new), `app/Services/IngredientScaling/RefinementFailedException.php` (new), `app/Services/IngredientScaling/AiRefinementFailedException.php` (new)

**Intent**: Define one call per plan. Validate the response structurally (a failed check means the attempt failed) and per item (a failed check reverts that item to its computed value).

**Contract**:
- `refine(list<RefinementItem> $items): array<string, string>` maps item key to proposed quantity. It throws `RefinementFailedException` on HTTP, timeout, non-JSON, a missing or unknown key, a duplicate or a non-numeric value. Configuration errors use a subclass flagged non-retryable.
- `RefinementItem` (one per distinct `(ingredient_id, unit code, computed quantity)` across the plan, not one per row, to keep the AI output small):
  - `key` = `"i{ingredientId}-{unitCode}-{computedQuantity}"`;
  - `name`;
  - `unitCode`;
  - `computedQuantity` (string).
- Guard, per item:
  - **`szt`:** the proposal must be a whole number in `{floor(c), ceil(c)}`; 0 is allowed only when `c < 0.5`.
  - **Other units:** `|q − c| ≤ 0.25·c` and `q ≥ 0`.
  - A valid proposal becomes `source = ai`; an invalid one keeps `computed`.
- `AiRefinementFailedException` wraps the last cause. Its message must not contain family data.

#### 2. Drivers

**File**: `app/Services/IngredientScaling/Drivers/OpenRouterIngredientRefiner.php`, `Drivers/AnthropicIngredientRefiner.php`, `Drivers/FakeIngredientRefiner.php` (all new), `app/Providers/AppServiceProvider.php`, `config/services.php`, `.env.example`, `composer.json` / `composer.lock`, `phpunit.xml`, `docker-compose.yml`

**Intent**: Three interchangeable drivers, bound by `config('services.ingredient_refiner.driver')`. Both real drivers send the same system prompt and a JSON schema `{items: [{key, quantity}]}`, carry only ingredient names, units and quantities, and make exactly one HTTP call per `refine()`. The system prompt says: round to practical kitchen amounts, keep the unit, whole pieces, stay within ±25%.

**Contract**:
- **Config** (`config/services.php`):
  - `ingredient_refiner.driver` = `env('AI_SCALING_DRIVER', 'openrouter')`;
  - `openrouter` = `{key: OPENROUTER_API_KEY, model: OPENROUTER_MODEL, base_url: 'https://openrouter.ai/api/v1'}`;
  - `anthropic` = `{key: ANTHROPIC_API_KEY, model: env('ANTHROPIC_MODEL', 'claude-opus-5-5'), effort: env('ANTHROPIC_EFFORT', 'low')}`;
  - a shared `timeout` of 20 s.
- **`.env.example`:** document all of these keys, with `OPENROUTER_MODEL` empty for the operator to choose.
- **OpenRouter:**
  - `Http::withToken()->timeout(20)->post('{base_url}/chat/completions', …)`;
  - `response_format` = `{type: 'json_schema', json_schema: {name, strict: true, schema}}`;
  - parse `choices[0].message.content` as JSON.
- **Anthropic:**
  - `composer require "anthropic-ai/sdk"` (approved in planning; needs network);
  - wrap the SDK call in a thin, container-bound gateway class so the driver is unit-testable without network;
  - set `maxRetries` to 0 (`RequestOptions::with(maxRetries: 0)`). The SDK does not enforce `RequestOptions::timeout` itself (only the transport does), so the gateway passes the SDK a Guzzle client with `timeout` and `connect_timeout` = `services.ingredient_refiner.timeout` as its transporter/HTTP client (check the exact constructor option after `composer require`);
  - structured output via `outputConfig` `format`, effort `low`, no `thinking` parameter;
  - check `stop_reason`: a refusal or `max_tokens` means `RefinementFailedException`.
- **Fake (deterministic):**
  - `szt`: half-up round, minimum 1;
  - `g`/`ml`: half-up to an integer;
  - `kg`/`l`: unchanged.
- **`phpunit.xml`:** set `AI_SCALING_DRIVER=fake`.
- **`docker-compose.yml`:** the `queue` service command becomes `php artisan queue:listen --tries=1 --timeout=85` (85 < `retry_after` 90), so the listener's process timeout does not kill the job before its own 80 s.

#### 3. Retry loop and fallback in the scaler

**File**: `app/Services/IngredientScaling/MealIngredientScaler.php`, `ScalingOutcome.php`

**Intent**: Insert the refinement between computing and writing.

**Contract**:
- When there are `computed` rows, call `refine()` up to 3 times with a 500 ms pause between attempts. A non-retryable config error stops immediately.
- **Dedupe:** group the `computed` rows by `(ingredient_id, unit code, computed_quantity)` into one `RefinementItem` each, and keep a map from item key to its rows.
- **Success:** run the guard per item, then apply the result (the proposal, or the computed value when rejected) to every row of that item; status becomes `refined`.
- **Final failure:** `report(new AiRefinementFailedException(previous: $last))` exactly once. Rows stay `computed`, status `computed`, and the job still ends `done`.
- `as_written` rows are never sent to the AI.
- A family with no `computed` rows (every drawn recipe has 0 kcal) makes no AI call and gets status `refined`, so the "AI failed" notice never shows without a failed call.

#### 4. Tests

**File**: `tests/Unit/Services/RefinementGuardTest.php`, `tests/Unit/Services/OpenRouterIngredientRefinerTest.php`, `tests/Unit/Services/AnthropicIngredientRefinerTest.php`, `tests/Unit/Services/MealIngredientScalerTest.php` (all new except the last)

**Intent**: Cover each guard boundary, each driver's request and parsing, and the retry and fallback contract.

**Contract**:
- **Guard:**
  - `szt` 2.37 accepts 2 or 3 and rejects 2.5 and 4;
  - 0.3 accepts 0;
  - 100 g accepts 125 and rejects 126.
- **OpenRouter:** `Http::preventStrayRequests()` plus `Http::fake` for success, malformed JSON, a missing key, a 500 and a timeout. Assert that the request carries the model, the schema, and no family data.
- **Anthropic:** the mocked gateway returns success, refusal and `max_tokens`. A gateway test asserts that the client it builds has `maxRetries` 0 and a 20 s transport timeout.
- **Scaler:**
  - fails twice, then succeeds → `refined`, nothing reported (`Exceptions::fake()`);
  - fails three times → `computed` plus exactly one `AiRefinementFailedException` reported;
  - config error → one report, no retries;
  - an out-of-bounds item → that row `computed`, the others `ai`;
  - two meals with the same ingredient, unit and computed quantity → one `RefinementItem`, and its answer lands on both rows;
  - no family → the refiner is never called;
  - a family with only 0-kcal recipes drawn → the refiner is never called and the status is `refined`.

### Success Criteria:

#### Automated Verification:

- Composer installs the new dependency: `vendor/bin/sail composer install`
- Guard, driver and scaler tests pass: `vendor/bin/sail pest tests/Unit/Services`
- Full suite passes with no stray HTTP: `vendor/bin/sail composer test`
- Larastan and Pint are clean: `vendor/bin/sail composer phpstan` and `vendor/bin/sail composer pint-test`

#### Manual Verification:

- With a real `OPENROUTER_API_KEY` and model and `AI_SCALING_DRIVER=openrouter`, a generation ends `done` with `source = ai` rows, practical amounts and a total time under 1 minute
- With a real `ANTHROPIC_API_KEY` and `AI_SCALING_DRIVER=anthropic`, the same result
- With an invalid key, the plan ends `done` with computed amounts, and exactly one error entry appears in the log (and in the mail alert when configured)
- One real call per driver on a full 21-meal payload is timed, and the duration and item count are recorded in `change.md` Notes; if an attempt takes over 15 s, revisit the per-attempt timeout or the default model before merging

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: UI and notices

### Overview

Show the scaled amounts per meal in the grid and explain any degradation, on screen and in the PDF.

### Changes Required:

#### 1. Notices from generation meta

**File**: `app/Services/IngredientScaling/ScalingNotices.php` (new), `app/Http/Resources/MealPlanResource.php`, `app/Services/MealPlanPdf/MealPlanDocument.php`, `app/Services/MealPlanPdf/MealPlanPdfExporter.php`, `resources/views/pdf/meal-plan.blade.php`

**Intent**: One place turns `generation_meta.scaling` into Polish sentences. The resource exposes them; the PDF prints them under the title.

**Contract**:
- `ScalingNotices::for(MealPlan): array{status: ?string, notices: list<string>}`. It returns notices only for `done` plans, in this order:
  - **`as_written`:** "Dodaj członków rodziny, aby przeliczyć składniki. Ilości są podane jak w przepisach."
  - **`computed`:** "Nie udało się dopracować ilości przez AI — pokazujemy ilości przeliczone automatycznie."
  - **`unscaledRecipes`:** "Przepisy bez kaloryczności nie zostały przeliczone: {names}."
  - **`membersWithoutBody`:** "Uzupełnij wagę i wzrost, aby dokładniej przeliczyć porcje: {names}."
  - **`version` missing or `random-v1`:** "Ten jadłospis powstał przed przeliczaniem składników — zregeneruj go, aby dopasować ilości do rodziny."
- `MealPlanResource` gains `scaling: {status, notices}`.
- `MealPlanDocument` gains `notices: list<string>`.

#### 2. Plan view

**File**: `resources/js/Pages/MealPlans/Show.vue`, `resources/js/Components/MealPlans/MealPlanGrid.vue`

**Intent**: An amber notice box above the grid lists `scaling.notices`. When the status is `as_written`, it links to the family profile. Each grid cell gets a native `<details>` with "Składniki (n)" listing the `display` strings. The kcal line reads "{kcal} kcal (cały przepis) · {n} porcji".

**Contract**:
- `data-testid="scaling-notice"` on the box and `data-testid="meal-ingredients"` on each list.
- The link goes to `route('family-members.index')`.
- TS interfaces gain `ingredients` on the meal and `scaling` on the plan.
- `usePoll(..., only: ['mealPlan','shoppingList'])` is unchanged; the meal ingredients travel inside `mealPlan`.

#### 3. Tests

**File**: `tests/Unit/Services/ScalingNoticesTest.php` (new), `tests/Feature/MealPlanControllerTest.php`, `tests/Unit/Services/MealPlanPdfExporterTest.php`

**Intent**: Cover each notice branch and its ordering. Show that the Inertia `mealPlan.meals.*.ingredients` and `mealPlan.scaling` props carry the scaled `display` strings, and that the PDF text contains a scaled line and the notice.

**Contract**: Polish strings asserted verbatim; the PDF is checked through the existing `pdfText` helper.

### Success Criteria:

#### Automated Verification:

- Notice, controller and PDF tests pass: `vendor/bin/sail pest tests/Unit/Services/ScalingNoticesTest.php tests/Feature/MealPlanControllerTest.php tests/Unit/Services/MealPlanPdfExporterTest.php`
- Full suite passes: `vendor/bin/sail composer test`
- Larastan and Pint are clean: `vendor/bin/sail composer phpstan` and `vendor/bin/sail composer pint-test`
- Client and SSR bundles build: `vendor/bin/sail npm run build`

#### Manual Verification:

- Expanding "Składniki" in a cell shows that meal's scaled amounts; the layout holds on mobile width and in dark mode
- Each notice state (no family, AI failure, 0-kcal recipe, adult without body data, old plan) shows the right text, and the family link works
- The PDF shows the notice under the title and the scaled amounts in the table, with correct Polish characters

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 5: E2E and docs

### Overview

Make the browser path deterministic with the fake driver and a pinned family, prove the scaled amounts reach the grid, the list and the PDF, and bring the specs and agent docs in line.

### Changes Required:

#### 1. Seeder and environments

**File**: `database/seeders/E2eMealPlanSeeder.php`, `.github/workflows/master.yml`, `CLAUDE.md`

**Intent**: Every planner user gets the same two-adult family and fixed recipe kcal, so any 21-meal draw scales by exactly 2.0. CI and the documented local E2E command run with `AI_SCALING_DRIVER=fake`.

**Contract**:
- **Family:** replace the planner's members each run with:
  - a man aged 40 (`now()->subYears(40)`), 180 cm, 75 kg = 3000 kcal;
  - a woman aged 40, 165 cm, 65 kg = 2400 kcal.
  
  Total 5400/day: 1620 for breakfast and Kolacja, 2160 for Obiad.
- **Recipe kcal:** pin `calories` at 810 for breakfast, 1080 for Obiad (`supper`) and 810 for Kolacja (`dinner`). This gives factor 2.0, so each meal is 200 g Mąka, 100 ml Mleko and 2 szt. Jajka, and the weekly totals are "42 szt.", "4,2 kg" and "2,1 l".
- **CI:** add `AI_SCALING_DRIVER=fake` to the E2E job's `.env` heredoc.
- **CLAUDE.md:**
  - the E2E section notes that `.env` must contain `AI_SCALING_DRIVER=fake`;
  - the stale Hard Rule saying `GenerateMealPlanJob` is a placeholder is replaced with one line on the real pipeline (generator → scaler → refiner driver).

#### 2. E2E spec

**File**: `tests/e2e/meal-plan.spec.ts`

**Intent**: Assert the scaled shopping totals and one expanded cell after generating and after regenerating, and assert that no scaling notice shows with the fake driver.

**Contract**:
- The expected list becomes `['Jajka','42 szt.'], ['Mąka','4,2 kg'], ['Mleko','2,1 l']`.
- Open the first `<details>` and expect "Mąka — 200 g".
- Expect `scaling-notice` to be hidden.

#### 3. Spec docs

**File**: `.ai/core/db-plan.md`, `.ai/core/api-plan.md`, `.ai/core/test-plan.md`, `.ai/core/tech-stack.md`

**Intent**: Record the decisions where future changes will look.

**Contract**:
- **`db-plan.md`:** `meal_ingredients`, the `family_members.weight_kg`/`height_cm` fields (an FR-002 extension) and the `generation_meta.scaling` shape.
- **`api-plan.md`:** the `IngredientScalingService` row becomes the real classes; the show payload gains `meals[].ingredients` and `scaling`.
- **`test-plan.md`:** scaling, AI and fake-driver scenarios.
- **`tech-stack.md`:** OpenRouter plus the Anthropic API (`anthropic-ai/sdk`), the `AI_SCALING_DRIVER` switch, and a note that a Claude subscription cannot back the deployed app.

### Success Criteria:

#### Automated Verification:

- Build and seed succeed: `vendor/bin/sail npm run build` and `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`
- E2E passes in both browsers with the fake driver: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- Full suite passes: `vendor/bin/sail composer test`
- Larastan and Pint are clean: `vendor/bin/sail composer phpstan` and `vendor/bin/sail composer pint-test`

#### Manual Verification:

- The CI run on the PR into `main` is green, E2E job included
- The updated `.ai/core` docs and the CLAUDE.md lines match the shipped behaviour

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- **`EnergyNorms`:** every age and weight boundary, the BMI clamp, derived rows and the reference-row fallback.
- **`ScalingCalculator`:** factor, 0 kcal, rounding.
- **`RefinementGuard`:** `szt` floor/ceil/0 and the ±25% edge.
- **Drivers:** request shape, parsing and failure classes (`Http::fake`, a mocked Anthropic gateway).
- **`MealIngredientScaler`:** status branches, retries and a single report.
- **`ScalingNotices`:** each sentence and its order.
- **Policy and requests:** `FamilyMemberPolicy`, the `FamilyMemberRequest` and recipe calories rules.

### Integration Tests:

- **Job:** generate end to end with the fake driver: rows written, `generation_meta.scaling` set, `version: 'scaled-v1'`.
- **Controller:** Inertia props carry meal ingredients and notices; the JSON branch is structurally intact; the shopping list and PDF use the snapshot.
- **E2E:** generate and regenerate, with deterministic totals and an expanded cell.

### Manual Testing Steps:

1. Add a two-adult family plus a 7-year-old, generate, and check the Obiad amounts against the worked example (factor = 0.4 × daily total ÷ recipe kcal).
2. Delete all family members and regenerate: amounts as written, plus the notice with its link.
3. Set an invalid AI key and regenerate: `done` with computed amounts, the AI notice, and one error log entry.
4. Use real OpenRouter and Anthropic keys: practical amounts (whole eggs, rounded grams) in under a minute.
5. Download the PDF and compare its amounts and notice with the screen.

## Performance Considerations

- **AI budget.** The worst case is about 3 × 20 s plus 1 s of pauses, on top of under 1 s of DB work. That fits the 80 s job timeout and the PRD's under-1-minute average, given that success normally comes on the first attempt. Prefer `effort: low` and a compact payload: at most 21 × 3–8 rows, deduplicated to one item per distinct (ingredient, unit, computed quantity). The first-attempt latency is unmeasured until step 3.8; thinking is always on for `claude-opus-5-5`.
- **Poll payload.** The show poll (every 3 s while generating) now includes meal ingredients. While generating, `meals` belongs to the previous draw or is empty, so the payload stays small. A done plan stops polling.

## Migration Notes

- Two additive migrations: `family_members` body columns (nullable) and `meal_ingredients` with an `as_written` backfill. No data is lost. Rollback drops the new table and columns.
- Existing plans keep showing recipe amounts and get the "zregeneruj" notice. Existing adults without body data use the reference row and are named in the notice until edited.
- Deployment (F-02) needs `AI_SCALING_DRIVER` plus either `OPENROUTER_API_KEY` and `OPENROUTER_MODEL`, or `ANTHROPIC_API_KEY`. Without a key, plans still finish, with computed amounts and an hourly error mail. The production worker must allow the job's 80 s: `queue:work` honours the job `$timeout`, while a `queue:listen` worker needs `--timeout` between 80 and `retry_after` (90).

## References

- Roadmap S-04: `context/foundation/roadmap.md:150-165`
- NIZP-PZH 2020 norms, chapter "Energia" (Tables 2–4): `https://ncez.pzh.gov.pl/wp-content/uploads/2021/03/normy_zywienia_2020web.pdf`
- S-05 seam and arithmetic rules: `context/archive/2026-11-14-shopping-list/plan-brief.md`
- S-06 PDF exporter: `context/changes/meal-plan-pdf-export/plan-brief.md`
- F-01 error-reporting rules: `context/archive/2026-11-13-error-tracking-baseline/plan.md`
- Job: `app/Jobs/GenerateMealPlanJob.php:64-124`; generator: `app/Services/MealPlanGenerator.php:65-106`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Scaling rules and family body data

#### Automated

- [x] 1.1 Migration applies cleanly: `vendor/bin/sail artisan migrate` — ead4dd5
- [x] 1.2 New and updated unit tests pass: `vendor/bin/sail pest tests/Unit/FamilyMemberPolicyTest.php tests/Unit/Services/EnergyNormsTest.php tests/Unit/Services/ScalingCalculatorTest.php tests/Unit/RecipeRequestValidationTest.php` — ead4dd5
- [x] 1.3 Family member feature tests pass: `vendor/bin/sail pest tests/Feature/FamilyMemberTest.php` — ead4dd5
- [x] 1.4 Larastan and Pint are clean: `vendor/bin/sail composer phpstan` and `vendor/bin/sail composer pint-test` — ead4dd5
- [x] 1.5 Client and SSR bundles build: `vendor/bin/sail npm run build` — ead4dd5

#### Manual

- [ ] 1.6 The child and adult values in `EnergyNorms` match Tables 2–4 in the NIZP-PZH 2020 PDF, checked visually
- [ ] 1.7 Adding and editing a family member shows weight and height, requires them for an adult, and shows Polish errors

### Phase 2: Per-meal quantities

#### Automated

- [x] 2.1 Migrations, including the backfill, apply and roll back cleanly: `vendor/bin/sail artisan migrate` then `vendor/bin/sail artisan migrate:rollback --step=1` then `vendor/bin/sail artisan migrate` — a9744ca
- [x] 2.2 Scaler, job, list, PDF and controller tests pass: `vendor/bin/sail pest tests/Unit/Services tests/Unit/Jobs tests/Feature/MealPlanControllerTest.php` — a9744ca
- [x] 2.3 Full suite passes: `vendor/bin/sail composer test` — a9744ca
- [x] 2.4 Larastan and Pint are clean: `vendor/bin/sail composer phpstan` and `vendor/bin/sail composer pint-test` — a9744ca

#### Manual

- [ ] 2.5 With a family set up, generating a plan in the browser changes the shopping-list totals from the recipe-as-written amounts to the scaled ones, and an existing older plan still shows its old list

### Phase 3: AI refinement

#### Automated

- [x] 3.1 Composer installs the new dependency: `vendor/bin/sail composer install` — 57a069c
- [x] 3.2 Guard, driver and scaler tests pass: `vendor/bin/sail pest tests/Unit/Services` — 57a069c
- [x] 3.3 Full suite passes with no stray HTTP: `vendor/bin/sail composer test` — 57a069c
- [x] 3.4 Larastan and Pint are clean: `vendor/bin/sail composer phpstan` and `vendor/bin/sail composer pint-test` — 57a069c

#### Manual

- [ ] 3.5 With a real `OPENROUTER_API_KEY` and model and `AI_SCALING_DRIVER=openrouter`, a generation ends `done` with `source = ai` rows, practical amounts and a total time under 1 minute
- [ ] 3.6 With a real `ANTHROPIC_API_KEY` and `AI_SCALING_DRIVER=anthropic`, the same result
- [ ] 3.7 With an invalid key, the plan ends `done` with computed amounts, and exactly one error entry appears in the log (and in the mail alert when configured)
- [ ] 3.8 One real call per driver on a full 21-meal payload is timed, and the duration and item count are recorded in `change.md` Notes; if an attempt takes over 15 s, revisit the per-attempt timeout or the default model before merging

### Phase 4: UI and notices

#### Automated

- [x] 4.1 Notice, controller and PDF tests pass: `vendor/bin/sail pest tests/Unit/Services/ScalingNoticesTest.php tests/Feature/MealPlanControllerTest.php tests/Unit/Services/MealPlanPdfExporterTest.php` — 4d9cf1a
- [x] 4.2 Full suite passes: `vendor/bin/sail composer test` — 4d9cf1a
- [x] 4.3 Larastan and Pint are clean: `vendor/bin/sail composer phpstan` and `vendor/bin/sail composer pint-test` — 4d9cf1a
- [x] 4.4 Client and SSR bundles build: `vendor/bin/sail npm run build` — 4d9cf1a

#### Manual

- [ ] 4.5 Expanding "Składniki" in a cell shows that meal's scaled amounts; the layout holds on mobile width and in dark mode
- [ ] 4.6 Each notice state (no family, AI failure, 0-kcal recipe, adult without body data, old plan) shows the right text, and the family link works
- [ ] 4.7 The PDF shows the notice under the title and the scaled amounts in the table, with correct Polish characters

### Phase 5: E2E and docs

#### Automated

- [x] 5.1 Build and seed succeed: `vendor/bin/sail npm run build` and `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder` — 1d57cda
- [x] 5.2 E2E passes in both browsers with the fake driver: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e` — 1d57cda
- [x] 5.3 Full suite passes: `vendor/bin/sail composer test` — 1d57cda
- [x] 5.4 Larastan and Pint are clean: `vendor/bin/sail composer phpstan` and `vendor/bin/sail composer pint-test` — 1d57cda

#### Manual

- [ ] 5.5 The CI run on the PR into `main` is green, E2E job included
- [ ] 5.6 The updated `.ai/core` docs and the CLAUDE.md lines match the shipped behaviour
