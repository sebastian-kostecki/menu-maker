# Kolacja Carried Over to the Next Day's Śniadanie Implementation Plan

## Overview

Let the user link the Kolacja of day D to the Śniadanie of day D+1 and cook it once (roadmap S-20, MS-05, PRD US-017):
- **In the draft.** The Śniadanie dialog of day D+1 offers "Z kolacji: <Kolacja of day D>". Picking it links the two meals. The Kolacja reads "Też na śniadanie", and the Śniadanie shows the Kolacja dish with "Z kolacji", in the Śniadanie colour.
- **After approval.** The Kolacja's ingredients are scaled for both meals (×2). The Śniadanie has no ingredients of its own. The shopping list contains the dish once, in double quantity. Screen and PDF carry the same two markers.
- **Unlinking.** Swapping or emptying the Kolacja removes the link and empties the Śniadanie. Swapping or emptying the Śniadanie only removes the link.

This is the second pair of the S-19 link mechanism. No schema, endpoint, scaler or shopping-list change is needed. The work is one rule entry, wording that follows the pair, and the fixtures that prove it.

## Current State Analysis

S-19 is done (`context/archive/2026-11-16-meal-plan-repeat-dinner/`):
- **Link data.** `meals.feeds_meal_id` (nullable, unique, `nullOnDelete`) points from the cooked meal to the carried-over meal. The two meals share a `recipe_id`.
- **Pair rules.** `MealLinkRules::PAIRS = [['from' => 'supper', 'to' => 'supper', 'days' => 1]]` (`app/Services/MealLinks/MealLinkRules.php:19-21`). `canFeed()` checks the plan, the pair, a recipe on the source, and no chains (`:26-45`). `fedMealIds()` counts a fed meal only while it shares the cooked meal's recipe (`:55-74`). `servingsFor()` returns 2 for a cooked meal (`:79-82`).
- **JS mirror.** `MEAL_LINK_PAIRS` and `linkSourceFor(meal, weekMeals)` already loop over the pairs, using `pair.to` / `pair.from` (`resources/js/lib/mealLinks.js:2,14-39`).
- **Endpoint.** `PATCH meal-plans/{plan}/meals/{meal}` takes `recipe_id` or `from_meal_id`. It runs one transaction with the plan row locked. `link()` copies the source's `recipe_id` straight to the target, with no category check on that write. `changeRecipe()` keeps the link consistent (`app/Http/Controllers/MealPlanMealController.php:27-106`). The `recipe_id` path validates the recipe against the **meal's** category (`app/Http/Requests/UpdateMealRequest.php:44-49`).
- **Readers are already generic.** The scaler, the shopping list, the PDF recipe pages, `meals_count`, `shortCategories` and the resource all key on `feeds_meal_id` / `fedMealIds()`, not on a category.

**Naming trap.** `supper` is shown as Obiad, and `dinner` as Kolacja (`app/Enums/MealCategory.php`; `resources/js/lib/mealCategories.js:4-8`). S-20 links `dinner` → `breakfast`.

**What is hard-coded to the Obiad pair** (the actual work):
- Grid badges "Na 2 dni" / "Z wczoraj" (`resources/js/Components/MealPlans/MealPlanGrid.vue:43-44`).
- Dialog item "Jak wczoraj: <name>" / "Gotujemy raz na 2 dni" (`RecipeSwapDialog.vue:31,36,39`) and the warning "Zmiana opróżni obiad z …, który jest „z wczoraj”." (`:134`).
- PDF marker "Z wczoraj" / "Na 2 dni" (`app/Services/MealPlanPdf/MealPlanPdfExporter.php:142`) and the recipe-page use label " (na 2 dni)" (`:59`).
- `UpdateMealRequest::LINK_NOT_ALLOWED_MESSAGE` "Tego obiadu nie można połączyć z poprzednim dniem." (`UpdateMealRequest.php:19`).
- The family-less notice "…a dla obiadów na 2 dni — podwójnie." (`app/Services/IngredientScaling/ScalingNotices.php:35`).
- The redraw confirm "…i powtórki obiadów przepadną." (`resources/js/Pages/MealPlans/Show.vue:135`).

**Colour is free.** The grid stripe and the PDF category cell are already coloured by the cell's own category (`MealPlanGrid.vue:28`, `resources/views/pdf/meal-plan.blade.php:75`). A breakfast that holds a Kolacja recipe is therefore shown in the Śniadanie colour, as US-017 requires.

## Desired End State

- **Pair.** `PAIRS` holds two entries: `supper → supper, +1` and `dinner → breakfast, +1`. Each pair carries its own labels (table below). All other pairs stay refused: supper → breakfast, dinner → dinner, breakfast → anything, dinner → breakfast on the same day or two days later.
- **Draft UI.**
  - The Śniadanie dialog of day D+1 shows the fixed item "Z kolacji: <name>" ("Gotujemy raz, zostaje na śniadanie") below "Nie robimy". It appears only while the search box is empty, the Kolacja of day D has a recipe, and the Śniadanie feeds nothing (always true). It also appears on an empty Śniadanie.
  - Picking the item saves at once. The Kolacja cell gets a "Też na śniadanie" badge. The Śniadanie cell shows the Kolacja dish link with a "Z kolacji" badge and the Śniadanie stripe colour, with no kcal line and no "Składniki".
  - On a linked Śniadanie, "obecny" marks the "Z kolacji" item.
  - The dialog of a linked Kolacja warns: "Zmiana opróżni śniadanie z <czw. 02.07>, które jest „z kolacji”."
  - The Obiad pair looks exactly as it does today.
- **Unlinking.** Same rules as S-19, from either side:
  - Changing the Kolacja (swap or "Nie robimy") empties the Śniadanie.
  - Changing the Śniadanie only drops the link.
  - Picking the Kolacja's current recipe keeps the link.
  - "Losuj ponownie" drops all links; "Wróć do szkicu" keeps them.
  - The Śniadanie cannot get the Kolacja recipe through `recipe_id`: the request's category rule refuses it with 422, so the S-19 "cook it fresh" path does not apply to this pair.
- **After approval.**
  - The Kolacja is scaled ×2 on its single-meal factor, which is exact because breakfast and dinner have the same 0.30 kcal share.
  - The Śniadanie has no `meal_ingredients` rows and is not listed among recipes without ingredients.
  - The shopping list holds the dish once, in double quantity.
  - The PDF table prints "Też na śniadanie" on the Kolacja row and "Z kolacji" on the Śniadanie row. The Śniadanie row has the Śniadanie category colour and an empty ingredients cell.
  - The Kolacja's recipe page lists the pair as one use, "<Dzień dd.mm> (też na śniadanie)".
- **Generic copy.**
  - `LINK_NOT_ALLOWED_MESSAGE` = "Tego posiłku nie można połączyć z poprzednim dniem."
  - The family-less notice = "Dodaj członków rodziny, aby przeliczyć składniki. Ilości są podane jak w przepisach, a dla dań gotowanych raz na dwa posiłki — podwójnie."
  - The redraw confirm = "Wylosować tydzień od nowa? Twoje zmiany przepisów, powtórki obiadów i kolacje na śniadanie przepadną."
- **Verification.** Pest, Larastan, Pint, the build, `lint:tokens`, E2E and the visual gate are green. The E2E flow links Tuesday's Śniadanie to Monday's Kolacja and approves. It then sees "Mąka — 400 g" on Monday's Kolacja, no "Składniki" on Tuesday's Śniadanie, and totals of 40 szt. / 4 kg / 2 l. Finally it swaps Monday's Kolacja and sees Tuesday's Śniadanie become "Bez posiłku".

**Labels per pair** (one source on each side; the Obiad values are today's strings, unchanged):

| Key | `supper → supper` | `dinner → breakfast` | Used by |
|---|---|---|---|
| cooked badge / PDF marker | Na 2 dni | Też na śniadanie | grid, PDF table |
| carried badge / PDF marker | Z wczoraj | Z kolacji | grid, PDF table |
| recipe-page use suffix | na 2 dni | też na śniadanie | PDF recipe page |
| dialog item prefix | Jak wczoraj | Z kolacji | dialog (JS only) |
| dialog item hint | Gotujemy raz na 2 dni | Gotujemy raz, zostaje na śniadanie | dialog (JS only) |
| warning | Zmiana opróżni obiad z {dzień}, który jest „z wczoraj”. | Zmiana opróżni śniadanie z {dzień}, które jest „z kolacji”. | dialog (JS only) |

### Key Discoveries:

- **×2 is exact for this pair.** `ScalingCalculator::MEAL_SHARES` gives breakfast and dinner 0.30 each (`app/Services/IngredientScaling/ScalingCalculator.php:18`). So "Kolacja factor × 2" equals "Kolacja share + Śniadanie share", and the roadmap decision ("ilości liczone tak samo") needs no scaler change. A test pins the equality so that a later change to the shares fails loudly.
- **A category finds its pair without the partner meal.** Each category is the `from` of at most one pair (supper, dinner) and the `to` of at most one (supper, breakfast). A cooked meal finds its labels by `from = its category`, and a carried-over meal by `to = its category`. A unit test enforces this uniqueness, so a third pair cannot silently make the lookup ambiguous.
- **`canFeed()` needs no new branch.** The breakfast never feeds, and the dinner is never fed. Both chain checks and `matchesPair()` (`MealLinkRules.php:84-95`) already work for a cross-category pair.
- **Cross-category `recipe_id` is safe.** `link()` writes the Kolacja's `recipe_id` to the breakfast directly (`MealPlanMealController.php:76`). The `recipe_id` path still validates against the meal's own category, so no request can put a Kolacja recipe on a breakfast except through the link.
- **E2E totals.** The S-19 block ends in a draft with Tuesday's Obiad empty and 20 filled meals (`tests/e2e/meal-plan.spec.ts:196-209`). With Kolacja ×2 and Śniadanie ×0 the plan still has 20 units, so approval shows `totalsWithEmptyMeal` (40 szt. / 4 kg / 2 l, `:101-105`). The visible proof is Mąka 400 g on the Kolacja versus 200 g elsewhere (seeder pins ×2 per meal, `database/seeders/E2eMealPlanSeeder.php:341-376`).
- **Seeder link shape is same-category only.** `attachPinnedMeals(..., array $links)` takes `[category, fromOffset]` and always targets the same category at `fromOffset + 1` (`E2eMealPlanSeeder.php:192-223`). It must name the target category. Monday Kolacja → Tuesday Śniadanie avoids the pinned empty Wednesday breakfast, which the link loop would otherwise overwrite.

## What We're NOT Doing

- Setting the link from the Kolacja dialog, or from both dialogs. The link is set only from the Śniadanie of day D+1, as in S-19.
- Other pairs (Kolacja → Kolacja, Obiad → Śniadanie, Kolacja → Obiad, same-day leftovers) and chains.
- Links across plans: Monday's Śniadanie never offers "Z kolacji", and Sunday's Kolacja feeds nothing.
- A "cook it fresh" path on a carried-over Śniadanie with the Kolacja recipe. That recipe is not in the Śniadanie list, and the request refuses it.
- Changes to the migration, the endpoint body, the transaction, `MealIngredientScaler`, `ShoppingListBuilder`, `MealPlanResource`, `GenerateMealPlanJob`, `ScalingCalculator` or the kcal shares.
- Changing the "jest już" marks. In a Kolacja dialog, the Kolacja recipe that also feeds Tuesday's breakfast reads "jest już: wt.", which is accurate.
- A new visual baseline for the Śniadanie dialog.
- Letting the draw create links (roadmap "Parked").
- Consolidating the local meal type declarations into `types/meal-plan.ts`.

## Implementation Approach

Extend the S-19 mechanism by data, not by branches:
- **The pair and its server labels** live in `MealLinkRules::PAIRS`. The PDF reads its markers and use suffix from there.
- **The pair and its client labels** live in `MEAL_LINK_PAIRS`. The grid and the dialog read their badges, item, hint and warning from there.
- **Copy shared by every pair** (error message, notice, redraw confirm) becomes generic.

Each side gets two small lookups by category (cooked labels by `from`, carried labels by `to`).

## Environment (prerequisite for every phase)

Every `sail`, E2E and visual command must run against this worktree's code. Sail serves the checkout it was started from, and `scripts/visual-test.sh` mounts the checkout it runs from. A fresh worktree has no `.env`, `vendor/` or `node_modules/`.

1. Copy `.env` from the main checkout.
   - Give it its own `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT` and `FORWARD_MAILPIT_DASHBOARD_PORT`, and set `MAILPIT_URL` to match.
   - Keep `AI_SCALING_DRIVER=fake`, `MAIL_MAILER=smtp` and `MAIL_HOST=mailpit`.
2. Install `vendor/` through the Sail Composer container, not host PHP.
3. Run `vendor/bin/sail up -d`, `vendor/bin/sail npm ci` and `vendor/bin/sail artisan migrate`.

Run the visual gate with `VISUAL_DOCKER_NETWORK=<worktree-dir>_sail VISUAL_BASE_URL=http://laravel.test npm run test:visual`.

## Phase 1: The Pair and Its Wording (Backend)

### Overview

Add `dinner → breakfast` with per-pair labels, print the labels in the PDF, and make the shared copy generic. Feature and unit tests cover linking, unlinking, refusals and ×2 for the new pair.

### Changes Required:

#### 1. Pair rules

**File**: `app/Services/MealLinks/MealLinkRules.php`

**Intent**: The second pair becomes allowed, and each pair names the words that describe it, so readers stop hard-coding the Obiad pair.

**Contract**:
- `PAIRS` entries gain `cooked_label`, `carried_label` and `use_suffix`. The PHPDoc shape is updated accordingly.
  - `['from' => 'supper', 'to' => 'supper', 'days' => 1, 'cooked_label' => 'Na 2 dni', 'carried_label' => 'Z wczoraj', 'use_suffix' => 'na 2 dni']`
  - `['from' => 'dinner', 'to' => 'breakfast', 'days' => 1, 'cooked_label' => 'Też na śniadanie', 'carried_label' => 'Z kolacji', 'use_suffix' => 'też na śniadanie']`
- New `cookedPairFor(string $category): ?array` (the pair whose `from` is the category) and `carriedPairFor(string $category): ?array` (the pair whose `to` is the category).
- `canFeed()`, `fedMealIds()` and `servingsFor()` are unchanged.
- The class docblock names both pairs and keeps the "keep in sync with `resources/js/lib/mealLinks.js`" note.

#### 2. Generic refusal message

**File**: `app/Http/Requests/UpdateMealRequest.php`

**Intent**: The refusal fits both pairs.

**Contract**: `LINK_NOT_ALLOWED_MESSAGE = 'Tego posiłku nie można połączyć z poprzednim dniem.'` No rule changes.

#### 3. Generic family-less notice

**File**: `app/Services/IngredientScaling/ScalingNotices.php`

**Intent**: The "doubled" clause covers a Kolacja carried to breakfast too.

**Contract**: The linked-plan variant of the `STATUS_AS_WRITTEN` notice becomes "Dodaj członków rodziny, aby przeliczyć składniki. Ilości są podane jak w przepisach, a dla dań gotowanych raz na dwa posiłki — podwójnie." `hasLinkedMeals()` is unchanged.

#### 4. PDF markers and recipe-page label from the pair

**File**: `app/Services/MealPlanPdf/MealPlanPdfExporter.php`

**Intent**: The printed plan says "Też na śniadanie" / "Z kolacji" for the new pair, and the Kolacja's recipe page names the use correctly.

**Contract**:
- `pdfMeal()` sets the marker from `carriedPairFor($meal->meal_category)['carried_label']` for a carried-over meal and from `cookedPairFor(...)['cooked_label']` for a cooked meal. If no pair is found, use `null`, though a valid link never hits that case.
- The recipe-page use label becomes `$dayLabel.' ('.cookedPairFor(...)['use_suffix'].')'`.
- `PdfMeal`, the template and the category colouring are unchanged: the carried-over Śniadanie already gets `categoryValue = breakfast` and the empty ingredients cell (`meal-plan.blade.php:75-80`).

#### 5. Tests

**Files**:
- `tests/Unit/Services/MealLinkRulesTest.php`
- `tests/Feature/MealPlanMealControllerTest.php`
- `tests/Unit/Services/MealIngredientScalerTest.php`
- `tests/Unit/Services/ShoppingListBuilderTest.php`
- `tests/Unit/Services/MealPlanPdfExporterTest.php`
- `tests/Unit/Services/ScalingNoticesTest.php`
- `tests/Unit/Jobs/GenerateMealPlanJobTest.php`

**Intent**: Pin the new pair, its refusals, every link and unlink path across categories, the ×2 scaling, and the printed labels. Existing S-19 tests keep passing. Their assertions reference `LINK_NOT_ALLOWED_MESSAGE` by constant, except the notice string, which is updated.

**Contract**:

*Rules:*
- `dinner on day D can feed breakfast on day D+1`
- `canFeed refuses supper to breakfast, dinner to dinner, breakfast to dinner, and dinner to breakfast on the same day or two days later`
- `each category is the source of at most one pair and the target of at most one`
- `names the cooked and carried labels of each pair by category`

*Endpoint:*
- `owner links Tuesday's Śniadanie to Monday's Kolacja`: the breakfast's `recipe_id` is the Kolacja's (dinner-category) recipe; `feeds_meal_id` / `from_meal_id` are in the JSON
- `linking fills an empty Śniadanie`
- `swapping or emptying the Kolacja empties the carried-over Śniadanie and drops the link`
- `swapping or emptying the carried-over Śniadanie drops the link and keeps the Kolacja`
- `a carried-over Śniadanie cannot be given the Kolacja recipe through recipe_id` (422, `INVALID_RECIPE_MESSAGE`, link kept)
- `an Obiad link and a Kolacja link coexist on the same days`

*Scaler:*
- `a Kolacja feeding the next Śniadanie gets twice the single-meal amounts and the Śniadanie gets no rows` (two-adult family: Mąka 100 g → 400 g)
- `breakfast and dinner have the same kcal share` (guards the ×2 rule)

*Shopping list:* `a Kolacja carried to breakfast adds the dish once in double quantity and lists no recipe without ingredients`

*PDF:*
- `a Śniadanie carried over from Kolacja prints Z kolacji in the breakfast category with an empty ingredients cell`
- `a Kolacja feeding breakfast prints Też na śniadanie with its doubled lines`
- `a Kolacja carried to breakfast is one use on the recipe page labelled też na śniadanie`

*Notices:* the existing `ScalingNoticesTest.php:34` test gets the new string.

*Job:* `a plan with a Kolacja carried to breakfast ends done with rows only on the Kolacja`

### Success Criteria:

#### Automated Verification:

- Pest suite passes: `vendor/bin/sail composer test`
- Larastan passes with no new baseline entries: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human before proceeding to the next phase.

---

## Phase 2: Badges, Dialog Item and Kitchen Sink (UI)

### Overview

The grid and the dialog read their texts from the pair. The Śniadanie dialog offers "Z kolacji", the redraw copy names both kinds of links, and the kitchen sink demos the new pair.

### Changes Required:

#### 1. Pair list with labels on the frontend

**File**: `resources/js/lib/mealLinks.js`

**Intent**: The client knows the same pairs as the server, plus the dialog-only labels.

**Contract**:
- `MEAL_LINK_PAIRS` mirrors `PAIRS` (both entries, same `cooked_label` / `carried_label`). Add `offerLabel`, `offerHint` and `feedsWarning(dateLabel)` per the labels table.
- New exports `cookedPairFor(category)` and `carriedPairFor(category)`, matching the PHP lookups.
- `linkSourceFor()` is unchanged. It already returns the Kolacja of day D for a breakfast.

#### 2. Grid badges per pair

**File**: `resources/js/Components/MealPlans/MealPlanGrid.vue`

**Intent**: Both cells of either pair say what happens, in text.

**Contract**: The cooked badge text is `cookedPairFor(category).cooked_label`, and the carried badge text is `carriedPairFor(category).carried_label`. Everything else is unchanged: `Badge variant="muted"`, the hidden kcal line and "Składniki" on the carried-over meal, the stripe by cell category, and the single "Zmień" button.

#### 3. Dialog item and warning per pair

**File**: `resources/js/Components/MealPlans/RecipeSwapDialog.vue`

**Intent**: The Śniadanie of day D+1 offers "Z kolacji", and a linked Kolacja warns that a change empties the next breakfast.

**Contract**:
- The link item's label, `text-value` and hint come from `carriedPairFor(meal.meal_category)`: "<offerLabel>: <source recipe name>" and `offerHint`. Its visibility, the "obecny" rule and the `{ from_meal_id }` body are unchanged.
- `feedsWarning` uses `cookedPairFor(meal.meal_category).feedsWarning(<formatWeekdayShort> <formatDayMonth> of the fed meal)`.
- The Obiad item and warning render exactly today's text.

#### 4. Redraw copy

**File**: `resources/js/Pages/MealPlans/Show.vue`

**Intent**: The redraw confirm names everything that is lost.

**Contract**: `description` = "Wylosować tydzień od nowa? Twoje zmiany przepisów, powtórki obiadów i kolacje na śniadanie przepadną."

#### 5. Kitchen sink

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: The new cell and dialog states have demos (CLAUDE.md verification rule).

**Contract**:
- In `planMeals` (`:213-242`), link Friday's Kolacja (id 15) to Saturday's Śniadanie (id 16), next to the existing id 11 → 14 link (`:227`). Id 16 shows dish 15's recipe, no ingredients and `from_meal_id: 15`, and id 15 gets `feeds_meal_id: 16`.
- Replace the single ternary with a small map, so both links read clearly.
- The editable grid caption (`:890`) also names the Friday "Też na śniadanie" / Saturday "Z kolacji" pair. Saturday's dialog shows "Z kolacji … obecny", and Friday's Kolacja dialog shows the warning.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token lint passes: `vendor/bin/sail npm run lint:tokens`
- Pest suite passes: `vendor/bin/sail composer test`

#### Manual Verification:

- In a draft, Tuesday's Śniadanie "Zmień" → "Z kolacji: <Monday's Kolacja>" shows "Też na śniadanie" on Monday's Kolacja and "Z kolacji" on Tuesday's Śniadanie in the Śniadanie colour, and the link persists after reload
- Monday's Kolacja dialog shows the breakfast warning, and swapping Monday's Kolacja turns Tuesday's Śniadanie into "Bez posiłku"
- Swapping Tuesday's Śniadanie keeps Monday's Kolacja and removes both badges
- "Z kolacji" is absent on Monday's Śniadanie, on Obiad and Kolacja cells, and when the previous Kolacja is empty; it is offered on an empty Śniadanie
- After approval, Monday's Kolacja shows doubled "Składniki", Tuesday's Śniadanie has none, and the PDF (with and without recipes) shows both markers, the Śniadanie colour and one recipe-page use labelled "(też na śniadanie)"
- The Obiad pair still reads "Jak wczoraj", "Na 2 dni" and "Z wczoraj"; the badges and the new item work at phone width and with the keyboard

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: E2E and Visual Gate

### Overview

The browser test drives link, approve and unlink for the new pair. The seeded visual draft pins a Kolacja → Śniadanie link, and the baselines are updated deliberately.

### Changes Required:

#### 1. E2E flow

**File**: `tests/e2e/meal-plan.spec.ts`

**Intent**: Prove the cross-category link, persistence, ×2 on a Kolacja, and unlinking in a real browser against the queue worker.

**Contract**: Add these steps after the S-19 block (`:196-209`), which leaves a draft with Tuesday's Obiad empty, no links and 20 recipe cells:
1. **Link.** Read Monday's Kolacja name (`tbody tr` first, `td` nth(2)). Open "Zmień" on Tuesday's Śniadanie (`/^Zmień przepis: .* – Śniadanie$/`, nth(1)) and pick the `/Z kolacji/` option. Expect:
   - Tuesday's Śniadanie link text equals Monday's Kolacja name;
   - `getByText('Z kolacji', { exact: true })` on Tuesday and `getByText('Też na śniadanie', { exact: true })` on Monday;
   - `tbody td a` stays at 20.
2. **Reload.** Expect the link to persist.
3. **Approve.**
   - Expect done with `totalsWithEmptyMeal` (see Key Discoveries).
   - Monday's Kolacja "Składniki" contain "Mąka — 400 g".
   - Tuesday's Śniadanie has no "Składniki" button.
4. **Back to draft and unlink.** Swap Monday's Kolacja to an option filtered with `hasText: 'jest już', hasNotText: 'obecny'`. Expect:
   - Tuesday's Śniadanie reads "Bez posiłku" with "Wybierz";
   - "Z kolacji" and "Też na śniadanie" counts are 0;
   - `tbody td a` is 19.

The S-19 assertions stay as they are. Their page-wide 0 counts for "Z wczoraj" and "Na 2 dni" are unaffected because the new labels differ.

#### 2. Seeder: a cross-category pinned link

**Files**: `database/seeders/E2eMealPlanSeeder.php`, `tests/Unit/Seeders/E2eMealPlanSeederTest.php`

**Intent**: The visual draft shows the new badges deterministically.

**Contract**:
- `attachPinnedMeals`'s `$links` becomes `list<array{0: string, 1: int, 2: string}>`, i.e. `[fromCategory, fromOffset, toCategory]`. The target is `toCategory` at `fromOffset + 1`.
- The draft call (`:182`) becomes `[[Supper, 0, Supper], [Dinner, 0, Breakfast]]`, so Monday's Kolacja "Kanapki z pastą jajeczną" feeds Tuesday's Śniadanie (replacing "Owsianka z jabłkiem i cynamonem"). The empty Wednesday breakfast and the done plan of 2026-06-15 are unchanged.
- Seeder test:
  - `:66` unique recipe names go from 19 to 18;
  - `:78` `feeds_meal_id` count goes from 1 to 2;
  - 21 meals with 20 carrying a recipe stay as they are;
  - new test `links Monday dinner of the visual draft to Tuesday breakfast with the same recipe`.

#### 3. Visual specs

**File**: `tests/e2e/visual/meal-plan-show.spec.ts`

**Intent**: The draft spec waits for the new states.

**Contract**:
- `openSeededDraft` (`:60-78`) keeps 20 `tbody td a` and also waits for `getByText('Z kolacji', { exact: true })` and `getByText('Też na śniadanie', { exact: true })`.
- The comment at `:58-59` also mentions that Tuesday's Śniadanie now holds Monday's Kolacja.
- The swap-dialog shot (Tuesday's Obiad) is unchanged.

#### 4. Baseline updates

**Intent**: Regenerate only the baselines this change is expected to alter, and review every changed PNG.

**Contract**:
- Run `npm run test:visual -- --update-snapshots`, then review each PNG in the diff.
- Expected to change:
  - `meal-plan-draft-desktop` and `meal-plan-draft-mobile`: the two new badges, and Tuesday's Śniadanie reading "Kanapki z pastą jajeczną" (mobile only where it is in frame);
  - `section-jadlospis`: the Friday/Saturday pair in both kitchen-sink grids;
  - `meal-plan-draft-swap-dialog` only if the grid behind the dialog overlay is visible in the shot.
- Every other baseline must stay unchanged, including `meal-plans-index-*` (the draft still counts 20 meals) and `meal-plan-show-*`. Record any other difference in the review instead of accepting it silently.

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Seeder runs and its test passes: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder` and `vendor/bin/sail pest --filter=E2eMealPlanSeeder`
- E2E passes: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- Visual gate passes after the deliberate update: `npm run test:visual`
- Full Pest suite passes: `vendor/bin/sail composer test`

#### Manual Verification:

- Every changed PNG in the diff was reviewed, and only the baselines listed in §4 changed

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful.

---

## Testing Strategy

### Unit Tests:

- **Pair rules:**
  - the new pair is allowed;
  - every neighbouring combination is refused (supper → breakfast, dinner → dinner, breakfast → dinner, wrong day distance);
  - each category is the source of at most one pair and the target of at most one;
  - the label lookups return the right pair.
- **Scaler:** Kolacja ×2 and Śniadanie without rows, plus the equal-share guard.
- **Shopping list:** the pair counted once in double quantity, with no "without ingredients" entry.
- **PDF exporter:** both markers, the breakfast category on the carried-over row, an empty ingredients cell, and one recipe-page use labelled "(też na śniadanie)".
- **Notices:** the generic doubled clause.
- **Job:** rows only on the Kolacja.
- **Seeder:** the cross-category pinned link.

### Integration Tests:

- Feature tests cover:
  - linking (also onto an empty Śniadanie);
  - unlinking from either side;
  - the category rule refusing the Kolacja recipe on a breakfast;
  - coexistence with an Obiad link.
- E2E: … (S-19 block) → link Tuesday's Śniadanie to Monday's Kolacja → reload → approve (Mąka 400 g on Monday's Kolacja, no Składniki on Tuesday's Śniadanie, totals 40 szt. / 4 kg / 2 l) → back to draft → swap Monday's Kolacja → Tuesday's Śniadanie reads "Bez posiłku".

### Manual Testing Steps:

1. Generate a draft. On Tuesday's Śniadanie pick "Z kolacji". Both badges appear, and the Śniadanie keeps its colour.
2. Check Monday's Śniadanie, an Obiad and a Kolacja: none offers "Z kolacji". Empty Monday's Kolacja: Tuesday's Śniadanie no longer offers it.
3. Open Monday's Kolacja. The warning names Tuesday's breakfast. Swap it. Tuesday's Śniadanie becomes "Bez posiłku".
4. Link again, also link Tuesday's Obiad "Jak wczoraj", and approve. Both pairs show their own badges, the Kolacja's Składniki are doubled, and the PDF shows all four markers and one recipe-page use per pair.
5. "Wróć do szkicu" keeps both links. "Losuj ponownie" removes them, as its confirm says.

## Performance Considerations

No change: the label lookups scan a two-entry constant, and every reader still works on already-loaded meals.

## Migration Notes

None. No schema change, and plans linked under S-19 read the same labels as before.

## References

- Roadmap item: `context/foundation/roadmap.md` (S-20, MS-05; Unknowns settled 2026-11-16); PRD US-017 (`.ai/core/prd.md:68`)
- Prior change: `context/archive/2026-11-16-meal-plan-repeat-dinner/plan.md` (link mechanism) and `reviews/impl-review.md` (F1 "cook it fresh", F3 `fedMealIds` recipe check)
- Pair rules: `app/Services/MealLinks/MealLinkRules.php:19-95`; JS mirror: `resources/js/lib/mealLinks.js:2-39`
- Swap endpoint: `app/Http/Controllers/MealPlanMealController.php:27-106`; request: `app/Http/Requests/UpdateMealRequest.php:19,44-49`
- Kcal shares: `app/Services/IngredientScaling/ScalingCalculator.php:18`
- Hard-coded labels: `MealPlanGrid.vue:43-44`, `RecipeSwapDialog.vue:31-39,134`, `MealPlanPdfExporter.php:59,142`, `ScalingNotices.php:35`, `Show.vue:135`
- Seeder links: `database/seeders/E2eMealPlanSeeder.php:182,192-223`; E2E S-19 block: `tests/e2e/meal-plan.spec.ts:163-209`; visual draft: `tests/e2e/visual/meal-plan-show.spec.ts:58-78`
- Kitchen-sink fixture: `resources/js/Pages/Dev/DesignSystem.vue:213-242,890`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: The Pair and Its Wording (Backend)

#### Automated

- [ ] 1.1 Pest suite passes
- [ ] 1.2 Larastan passes with no new baseline entries
- [ ] 1.3 Pint passes

### Phase 2: Badges, Dialog Item and Kitchen Sink (UI)

#### Automated

- [ ] 2.1 Client and SSR bundles build
- [ ] 2.2 Token lint passes
- [ ] 2.3 Pest suite passes

#### Manual

- [ ] 2.4 Z kolacji links Tuesday's Śniadanie to Monday's Kolacja with both badges in the Śniadanie colour, persisting after reload
- [ ] 2.5 Monday's Kolacja dialog warns and swapping it empties Tuesday's Śniadanie
- [ ] 2.6 Swapping Tuesday's Śniadanie keeps Monday's Kolacja and removes both badges
- [ ] 2.7 Z kolacji is absent on Monday, non-Śniadanie cells and after an empty Kolacja, and offered on an empty Śniadanie
- [ ] 2.8 After approval the doubled Kolacja, shopping list and PDF markers, colour and recipe-page label are correct
- [ ] 2.9 The Obiad pair is unchanged and the new badges and item work at phone width and with the keyboard

### Phase 3: E2E and Visual Gate

#### Automated

- [ ] 3.1 Build passes
- [ ] 3.2 Seeder runs and its test passes
- [ ] 3.3 E2E passes
- [ ] 3.4 Visual gate passes after the deliberate update
- [ ] 3.5 Full Pest suite passes

#### Manual

- [ ] 3.6 Every changed PNG was reviewed and only the listed baselines changed
