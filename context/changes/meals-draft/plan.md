# Drag-and-drop meals in the draft, any recipe in any slot — Implementation Plan

## Overview

In a draft meal plan the user can drag a meal with the mouse onto another cell of the 7 × 3 grid: dropping on a filled cell exchanges the two recipes, dropping on an empty cell moves the recipe there and empties the source. Any of the user's recipes may sit in any slot (a Śniadanie recipe at Kolacja and vice versa), through the drag and through the „Zmień” dialog alike.

## Current State Analysis

- The draft grid is inline markup in `resources/js/Components/MealPlans/MealPlanGrid.vue:3-111` (a `<table>`, days as rows, `MEAL_CATEGORIES` as columns, cells keyed by `meal.meal_category`, `:197-215`). There is no drag-and-drop code or library anywhere in `resources/js`.
- Every draft edit is one `PATCH meal-plans/{meal_plan}/meals/{meal}` (`routes/web.php:30-32`, `MealPlanMealController::update`, `app/Http/Controllers/MealPlanMealController.php:27-60`), sent with Inertia `router.patch` from `RecipeSwapDialog.vue:193-214` (`only: ['mealPlan','shortCategories']`, errors → `vue-sonner` toast). Nothing changes two meals in one request.
- The same-category rule is deliberate (PRD FR 5 `.ai/core/prd.md:22`, US-014 `:65`; S-17 brief "keeps the kcal-based scaling factor meaningful") and enforced only in `UpdateMealRequest.php:48-51` (`->where('category', $meal->meal_category)`).
- A meal row is a fixed slot (`meal_date` × `meal_category`); there is **no** unique constraint on it (`database/migrations/2025_07_31_145701_create_meals_table.php:23`, plain index). The recipe's own category lives on `recipes.category`; `MealPlanResource.php:66` already exposes `recipe.category`.
- Downstream code keys off the slot: kcal share 30/40/30 (`ScalingCalculator.php:18`, called with `$meal->meal_category` in `MealIngredientScaler.php:76`), grid and PDF day-row colour, link pairs (`MealLinkRules::PAIRS`, `app/Services/MealLinks/MealLinkRules.php:20-23`), the swap-dialog option list (`Show.vue:252-256`). The PDF recipe page already uses `recipe.category`.
- Links: `feeds_meal_id` on the cooked meal (unique). A linked pair always shares one `recipe_id` (`link()` copies it; `changeRecipe()` drops the link whenever the carried meal's recipe changes). `changeRecipe()` (`MealPlanMealController.php:89-107`) empties the carried meal when the cooked meal's recipe changes and only unlinks when the carried meal changes.
- `recipeOptions` (`MealPlanController.php:159-181`) already sends the owner's recipes for all three categories, keyed by category; `Show.vue` passes only the slot's list to the dialog.
- Tests that encode the old rule: `tests/Feature/MealPlanMealControllerTest.php:73` ('swap is refused for a recipe of another category'), `:447` ('a carried-over Śniadanie cannot be given the Kolacja recipe through recipe_id'), `tests/Unit/MealRequestValidationTest.php:122` (`test_rejects_other_category_deleted_and_foreign_recipes`).
- E2E `tests/e2e/meal-plan.spec.ts` is one long flow on `e2e-planner-<project>@example.com` with positional `tbody tr`/`td` locators; the visual gate has draft baselines `meal-plan-draft-desktop/mobile.png` and `meal-plan-draft-swap-dialog.png` built from the `E2eMealPlanSeeder` draft fixture (`database/seeders/E2eMealPlanSeeder.php:214-221`, `attachPinnedMeals` `:234-261`).

## Desired End State

- On a draft, on a device with a fine pointer, every filled cell is draggable (grab cursor, grip icon). Dropping it on another cell of the same plan calls `POST meal-plans/{meal_plan}/meals/{meal}/move` with `target_meal_id`; after the partial reload the two cells show exchanged recipes (or source empty / target filled), and the result survives a refresh.
- Moving a cooked meal empties the meal it fed (existing rule), and a toast names the emptied meal. Dropping a meal onto its own linked partner, onto itself, or onto a cell with the same recipe changes nothing.
- The „Zmień” / „Wybierz” dialog lists the slot's own category first, then the other two categories in labelled groups; any own, non-deleted recipe can be picked for any slot.
- A cell whose recipe category differs from its column (and which is not a carried-over meal) shows a `Badge variant="tag"` with the recipe's category label; the cell keeps the column colour.
- Ingredients still scale by the slot's kcal share; the PDF is unchanged.
- PRD FR 5 / US-014 describe the new rules plus a new US for drag-and-drop; `design-system.md` describes the drag states and the category tag.

Verify: Pest suite, Larastan, Pint, build, `lint:tokens`, the extended `meal-plan.spec.ts` in Chromium and Firefox, and the visual gate with deliberately updated baselines.

### Key Discoveries:

- `MealPlanMealController.php:29-48` — lock the plan row with `lockForUpdate()`, re-check `status === 'draft'`, re-read meals inside the transaction; the move endpoint copies this pattern.
- `ApproveMealPlanRequest.php:21-24` — non-resource actions authorize in the Form Request with `Gate::allows('update', …)`; `MealPlanController::approve` / `backToDraft` are the precedent for extra actions on a resource controller.
- `MealLinkRules::matchesPair` reads the slot (`meal_category`), never `recipe.category`, so links keep working when a cross-category recipe sits in a slot.
- `@vueuse/core` ^15 is installed (`useMediaQuery` available); `ui/combobox` ships `ComboboxGroup` (`resources/js/Components/ui/combobox/ComboboxGroup.vue`).
- Kitchen sink renders an editable grid and the swap dialog in demo mode (`resources/js/Pages/Dev/DesignSystem.vue:1160-1181`), covered by `tests/e2e/visual/design-system.spec.ts`.

## What We're NOT Doing

- No touch or long-press dragging and no keyboard drag; touch and keyboard users reach the same result through the „Zmień” dialog (WCAG 2.5.7 alternative). No „Przenieś…” action.
- No DnD library (native HTML5 DnD only; no dependency change).
- No change to scaling: the slot decides the kcal share (`ScalingCalculator` and its tests stay as they are).
- No copy-on-drop, no drag between plans, no drag outside `draft`.
- No change to the PDF (day rows keep the slot colour; recipe pages already show the recipe's category) and no category marker added there.
- No change to `MealLinkRules`, to which slot pairs can be linked, or to the `shortCategories` logic (only its notice copy changes, Phase 2).
- No new DB constraint or migration (the move never changes a row's `meal_date` / `meal_category`).
- No edits to archived change folders or to the stale `.ai/core/api-plan.md`.

## Implementation Approach

A drop is a recipe exchange between two fixed meal rows, done server-side in one transaction by applying the existing `changeRecipe()` semantics to both rows. This keeps one link rule for the dialog and the drag, needs no schema change, and cannot put two meals in one cell. The category restriction is removed from validation, so both edit paths share one rule: any own, non-deleted recipe. The frontend adds native HTML5 DnD to the existing grid cells (fine pointers only), emits a `move` event to `Show.vue`, which posts with the same Inertia partial-reload pattern as the dialog.

## Critical Implementation Details

- **No-op before side effects.** The move must return early when `meal === target`, or when both rows have the same `recipe_id` (this covers every linked pair, since a pair always shares a recipe). Otherwise `changeRecipe()` on a carried meal would drop its link even though nothing moved.
- **Drag sources inside the cell.** The recipe `<Link>` must get `draggable="false"`, otherwise the browser drags the URL instead of the cell. Firefox starts an HTML5 drag only after `dataTransfer.setData(...)` is called in `dragstart`. `dragover` must call `preventDefault()` only on valid targets, so invalid cells show the no-drop cursor.
- **Carried meals and the category tag.** A carried Śniadanie from Kolacja already says „Z kolacji” (US-017), so the category tag is suppressed when `from_meal_id` is set.

## Phase 1: Backend — any category and a move endpoint

### Overview

Allow any own recipe in any slot and add the transactional two-meal move.

### Changes Required:

#### 1. Relax the recipe rule

**File**: `app/Http/Requests/UpdateMealRequest.php`

**Intent**: Drop the category condition so the dialog can put any own, non-deleted recipe in any slot; reword the message.

**Contract**: `recipe_id` exists rule keeps `user_id = plan owner` and `deleted_at IS NULL`, loses `->where('category', $meal->meal_category)`. `INVALID_RECIPE_MESSAGE` becomes `'Wybierz jeden ze swoich przepisów.'`.

#### 2. Move request

**File**: `app/Http/Requests/MoveMealRequest.php` (new, `declare(strict_types=1);`, via `vendor/bin/sail artisan make:request MoveMealRequest --no-interaction`)

**Intent**: Authorize and validate a drop: the target must be another meal of the same plan, and the plan must be a draft.

**Contract**: `authorize()` → `Gate::allows('update', $this->route('meal'))`. Rules: `target_meal_id` → `required`, `integer`, `Rule::exists('meals', 'id')->where('meal_plan_id', $meal->meal_plan_id)`. `after()` adds `status` → `UpdateMealRequest::NOT_DRAFT_MESSAGE` when the plan is not a draft. Message constant `TARGET_INVALID_MESSAGE = 'Posiłek można przenieść tylko w obrębie tego jadłospisu.'` for `target_meal_id.exists`/`required`.

#### 3. Move action and route

**File**: `app/Http/Controllers/MealPlanMealController.php`, `routes/web.php`

**Intent**: Exchange the recipes of the dragged meal and the target in one transaction, reusing `changeRecipe()` on both rows so links behave exactly as in the dialog.

**Contract**:
- Route: `Route::post('/meal-plans/{meal_plan}/meals/{meal}/move', [MealPlanMealController::class, 'move'])->scopeBindings()->name('meal-plans.meals.move');`
- `move(MoveMealRequest $request, MealPlan $mealPlan, Meal $meal): JsonResponse|RedirectResponse`. Inside `DB::transaction`: lock the plan (`lockForUpdate`), refuse a non-draft with `ValidationException` on `status`, re-read both meals scoped to the plan, return early on the no-op cases (see Critical Implementation Details), then `changeRecipe($source, $targetRecipeId)` and `changeRecipe($target, $sourceRecipeId)`. Non-JSON → `back()`; JSON → `{ message: 'Meal moved.', data: MealPlanResource }` like `update()`.
- Recipes being moved are not re-validated (a soft-deleted recipe already in the plan may move with its meal).

#### 4. Tests

**Files**: `tests/Feature/MealPlanMealControllerTest.php`, `tests/Unit/MealRequestValidationTest.php`, `tests/Unit/MoveMealRequestTest.php` (new)

**Intent**: Flip the old category tests to the new outcome and cover the move.

**Contract**:
- `:73` becomes 'owner can put a recipe of another category in a draft meal'; `:447` becomes 'a carried-over Śniadanie given the Kolacja recipe through recipe_id is cooked fresh' (link dropped, recipe kept); `test_rejects_other_category_deleted_and_foreign_recipes` becomes `test_accepts_other_category_but_rejects_deleted_and_foreign_recipes`.
- New feature tests for `move`: swap two filled meals of different categories; move into an empty meal (source becomes empty); moving a cooked meal empties its carried meal and drops the link; swapping two cooked meals (two „Na 2 dni” Obiady) empties and unlinks both carried meals; moving a carried meal unlinks it and keeps the cooked meal intact; drop onto own linked partner / onto itself / same recipe is a no-op (links intact); refused for a meal of another plan (404 via scoped binding) and for a `target_meal_id` of another plan (422); refused outside a draft (422 `status`); other user → 403; JSON response shape.
- `MoveMealRequestTest`: required / integer / same-plan target, draft-only.

### Success Criteria:

#### Automated Verification:

- Meal controller and request tests pass: `vendor/bin/sail pest --filter='MealPlanMealController|MealRequestValidation|MoveMealRequest'`
- Full Pest suite passes: `vendor/bin/sail composer test`
- Larastan passes without baseline additions: `vendor/bin/sail composer phpstan`
- Pint passes: `vendor/bin/sail composer pint-test`
- Route is registered: `vendor/bin/sail artisan route:list --name=meal-plans.meals.move`

---

## Phase 2: Swap dialog with all categories

### Overview

The „Zmień” / „Wybierz” dialog offers every own recipe, the slot's category first.

### Changes Required:

#### 1. Pass all groups from the page

**File**: `resources/js/Pages/MealPlans/Show.vue`

**Intent**: Replace the single-category `swapOptions` with ordered groups: the slot's category first, then the remaining categories in `MEAL_CATEGORIES` order, skipping empty groups.

**Contract**: `swapGroups: { category: string; label: string; options: RecipeOption[] }[]` passed as a new `groups` prop (replacing `options`) to `RecipeSwapDialog`. Add the type to `resources/js/types/meal-plan.ts`. In the same file, change the copy of the `short-categories-notice` second paragraph (`Show.vue:92`) to `Puste posiłki możesz uzupełnić przepisem z innej pory dnia albo dodać nowy przepis.` Keep the `shortCategories` logic as it is: the draw still works per category.

#### 2. Grouped list

**File**: `resources/js/Components/MealPlans/RecipeSwapDialog.vue`

**Intent**: Render each group under a heading with `ComboboxGroup` (+ a label element), keep search across all groups (hide groups with no match), keep „Nie robimy”, the link option, „obecny” and „jest już” behaviour unchanged.

**Contract**: Prop `groups` replaces `options`; the "Brak przepisów" text shows only when no group has a match. Group headings use existing text tokens (`text-meta`, `text-muted-foreground`).

#### 3. Kitchen sink

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: Feed the demo dialog grouped options so the kitchen sink shows the grouped list.

**Contract**: Demo data shape matches `groups`.

### Success Criteria:

#### Automated Verification:

- Client and SSR build passes: `vendor/bin/sail npm run build`
- Token lint passes: `vendor/bin/sail npm run lint:tokens`

#### Manual Verification:

- On a draft, „Zmień” on a Kolacja cell lists Kolacja recipes first, then Śniadanie and Obiad under headings; picking a Śniadanie recipe saves and survives a refresh.
- Typing a search term filters across all groups and Enter picks the top match.

---

## Phase 3: Grid drag-and-drop and the category tag

### Overview

Native HTML5 DnD on grid cells for fine pointers, the move request from the page, and the cross-category tag.

### Changes Required:

#### 1. Draggable cells and drop targets

**File**: `resources/js/Components/MealPlans/MealPlanGrid.vue`

**Intent**: When `editable` and `(pointer: fine)` (`useMediaQuery` from `@vueuse/core`) and not `moving`, make filled cells draggable and every cell with a meal row a drop target; emit `move(source, target)` on drop. Keep the `<table>` structure and existing aria-labels so positional E2E locators keep working.

**Contract**:
- New prop `moving?: boolean` (disables dragging; grid gets `aria-busy` while true). New emit `(e: 'move', source: GridMeal, target: GridMeal)`.
- Filled cell: `draggable="true"`, `dragstart` sets `dataTransfer.effectAllowed = 'move'` and `setData('text/plain', String(meal.id))`, tracks the dragged meal in local state; `dragend` clears it. Recipe `<Link draggable="false">`.
- Target: `dragover`/`dragenter` call `preventDefault()` only when a drag is active and the target differs from the source; `drop` emits `move`.
- States (token utilities only): draggable cell `cursor-grab active:cursor-grabbing select-none` and a `GripVertical` icon (`aria-hidden`); dragged source `opacity-50`; hovered valid target `outline-2 outline-dashed outline-offset-2 outline-ring`; transitions `transition-opacity motion-reduce:transition-none`.
- `GridRecipe` gains `category: string`. When `recipe.category !== meal_category` and `!from_meal_id`, render `<Badge variant="tag">{{ mealCategoryLabel(recipe.category) }}</Badge>` next to the link badges.

#### 2. Page wiring

**File**: `resources/js/Pages/MealPlans/Show.vue`

**Intent**: Handle `move` with `router.post(route('meal-plans.meals.move', { meal_plan, meal: source.id }), { target_meal_id: target.id }, { preserveScroll: true, only: ['mealPlan', 'shortCategories'] })`, a `moving` flag, error toasts like the dialog (`errors.target_meal_id ?? errors.status ?? 'Nie udało się przenieść posiłku.'`), and an info toast on success when a cooked meal (source or target with `feeds_meal_id`, recipes differ) emptied its carried meal.

**Contract**: The info toast names every emptied meal. If source and target are both cooked meals (e.g. two „Na 2 dni” Obiady), both carried meals are emptied. Collect the carried meal (`feeds_meal_id`) of each cooked meal among source and target, then join their `{weekday} – {kategoria}` labels with `, ` in one toast: `Wyczyszczono {labels}, bo korzystały z przeniesionych posiłków.` For a single meal: `Wyczyszczono {weekday} – {kategoria}, bo korzystał z przeniesionego posiłku.` Format weekday/date with `lib/format.js`. Skip the request entirely for the client-detectable no-ops (same meal, same recipe). `Meal.recipe` type gains `category`.

#### 3. Kitchen sink and design system

**Files**: `resources/js/Pages/Dev/DesignSystem.vue`, `context/foundation/design-system.md`

**Intent**: Demo the editable grid with a cross-category meal and a local `@move` handler that swaps the demo meals (no request); document the drag states and the category tag in the grid-cell section (§ near `:242`).

**Contract**: Demo `planMeals` include one cross-category meal; doc lists default / draggable / dragging / drop-target / moving states and the tag rule (hidden on carried meals).

### Success Criteria:

#### Automated Verification:

- Client and SSR build passes: `vendor/bin/sail npm run build`
- Token lint passes: `vendor/bin/sail npm run lint:tokens`
- Full Pest suite still passes: `vendor/bin/sail composer test`

#### Manual Verification:

- In Chrome and Firefox, dragging Monday Śniadanie onto Monday Kolacja exchanges them; the moved recipes show the category tag; a refresh keeps the result.
- Dragging onto an empty cell moves the recipe and leaves „Bez posiłku” with „Wybierz” at the source.
- Dragging a „Na 2 dni” Obiad to another day empties its „Z wczoraj” meal and shows the info toast; dragging it onto its own „Z wczoraj” partner changes nothing.
- Grabbing the recipe name drags the cell, not the link; a plain click on the name still opens the recipe.
- With touch emulation (coarse pointer) cells are not draggable and the grid still scrolls sideways.
- On an approved plan nothing is draggable.

---

## Phase 4: E2E, fixtures, visual baselines and PRD

### Overview

Cover the drag in the browser, show a cross-category meal in the visual draft, update baselines deliberately, and bring the PRD in line.

### Changes Required:

#### 1. Draft fixture

**Files**: `database/seeders/E2eMealPlanSeeder.php`, `tests/Unit/Seeders/E2eMealPlanSeederTest.php`

**Intent**: In the visual draft, exchange Thursday's Śniadanie and Kolacja recipes so the screenshot shows the category tag in two cells.

**Contract**: `attachPinnedMeals` gains `array $swaps = []` (`[categoryA, offset, categoryB]` triples, applied before links); the draft fixture passes `[[MealCategory::Breakfast->value, 3, MealCategory::Dinner->value]]`; the seeder test asserts the swapped recipe categories.

#### 2. E2E drag steps

**File**: `tests/e2e/meal-plan.spec.ts`

**Intent**: Extend the existing draft flow (same user, no parallel conflict): drag a filled cell onto another filled cell with `locator.dragTo`, assert exchanged names and the category tag, reload and assert again; drag onto an empty cell; pick a recipe of another category through „Zmień” (group heading visible).

**Contract**:
- Wait for the `POST …/move` response before asserting; keep positional locators valid.
- Append the new steps at the end of the flow, after the Kolacja unlink step (the plan is then a draft with Tuesday's Śniadanie empty and 19 filled cells). Nothing after that point checks scaled amounts, shopping totals or link cells. Earlier steps check „Mąka — 200 g / 400 g” and shopping totals, which hold only because every slot scales by exactly 2.0 (`E2eMealPlanSeeder.php:396-412`). They also build links on Mon/Tue Obiad and Mon Kolacja → Tue Śniadanie.
- Drag swap: Friday Śniadanie ↔ Friday Kolacja (`tbody tr` nth(4), `td` nth(0) and nth(2)). Both are 810 kcal at a 30% share, so the drag cannot change any scale factor.
- Drag into empty: a filled Friday cell onto Tuesday's empty Śniadanie (`tbody tr` nth(1), `td` nth(0)); the filled-cell count stays 19.
- Cross-category „Zmień” pick: on a Śniadanie or Kolacja cell, pick a recipe from the other of those two categories (never Obiad), from under its group heading.

#### 3. Visual baselines

**Files**: `tests/e2e/__screenshots__/visual/meal-plan-show.spec.ts/meal-plan-draft-*.png`, design-system baselines under `tests/e2e/__screenshots__/visual/design-system.spec.ts/`

**Intent**: Regenerate only after reviewing the change: draft desktop/mobile (grip icons, tags), swap dialog (groups), kitchen-sink grid and dialog.

**Contract**: `vendor/bin/sail npm run build` (no `public/hot`), `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`, then `npm run test:visual -- --update-snapshots`; review every changed PNG in the diff; the done-plan baselines must not change.

#### 4. PRD

**File**: `.ai/core/prd.md`

**Intent**: FR 5 says any own recipe in any meal, kcal share by time of day; US-014 criterion 1 lists all recipes with the meal's category first; add US-018 „Przeciąganie posiłków” (drag swaps two meals or moves into an empty one, works with the mouse in the draft, moving a cooked meal empties the dependent meal, persists after refresh).

**Contract**: Sections FR 5 (`:22`) and the US table (`:65` onwards).

### Success Criteria:

#### Automated Verification:

- Seeder test passes: `vendor/bin/sail pest --filter=E2eMealPlanSeeder`
- E2E passes in Chromium and Firefox: `vendor/bin/sail npm run build && vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- Visual gate passes after the deliberate update: `npm run test:visual`
- Full Pest suite passes: `vendor/bin/sail composer test`

#### Manual Verification:

- Every changed PNG reviewed: only draft grid, swap dialog and kitchen-sink grid/dialog differ, done-plan screenshots unchanged.
- PRD FR 5, US-014 and US-018 read consistently with the shipped behaviour.

---

## Testing Strategy

### Unit Tests:

- `MoveMealRequest`: required, integer, target in the same plan, draft-only.
- `UpdateMealRequest`: other category accepted; deleted and foreign recipes still rejected.
- Seeder: the visual draft has the swapped Thursday meals.

### Integration Tests:

- Feature tests for `move` (swap, move to empty, link side effects, no-op cases, cross-plan, non-draft, authorization, JSON shape) and the flipped category tests in `MealPlanMealControllerTest`.
- Playwright: drag swap, drag into empty, cross-category pick in the dialog, persistence after reload, in Chromium and Firefox.

### Manual Testing Steps:

1. Open a draft, drag a breakfast onto a dinner; check the tag and refresh.
2. Drag a cooked „Na 2 dni” Obiad to another day; check the toast and the emptied „Z wczoraj” meal.
3. Approve the draft; check that the moved breakfast at Kolacja is scaled (amounts present) and the shopping list builds.
4. Toggle touch emulation; check cells are not draggable and „Zmień” still offers all categories.

## Performance Considerations

One request per drop with a row lock on the plan; two `changeRecipe()` calls touch at most four meal rows. The partial reload reuses the existing `only: ['mealPlan','shortCategories']` payload.

## Migration Notes

None: no schema change. Existing drafts and done plans are unaffected; cross-category meals can only appear through the new paths.

## References

- Archived same-category decision: `context/archive/2026-11-16-meal-plan-draft-with-swap/plan-brief.md:28`, `plan.md:71`
- Archived carry-over (only cross-category path so far): `context/archive/2026-11-17-dinner-to-breakfast-carryover/plan.md:76,85`
- Transaction + lock pattern: `app/Http/Controllers/MealPlanMealController.php:29-48`
- Link side effects: `app/Http/Controllers/MealPlanMealController.php:89-107`, `app/Services/MealLinks/MealLinkRules.php:20-79`
- Kcal share by slot: `app/Services/IngredientScaling/ScalingCalculator.php:18`, `MealIngredientScaler.php:76`
- Grid: `resources/js/Components/MealPlans/MealPlanGrid.vue`; dialog: `resources/js/Components/MealPlans/RecipeSwapDialog.vue:193-214`
- Design system grid cell: `context/foundation/design-system.md:242`, states `:416-420`
- Worktree gate rule: `context/foundation/lessons.md` („Point E2E and visual runs at the worktree's own Sail stack”)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Backend — any category and a move endpoint

#### Automated

- [x] 1.1 Meal controller and request tests pass — 7e07230
- [x] 1.2 Full Pest suite passes — 7e07230
- [x] 1.3 Larastan passes without baseline additions — 7e07230
- [x] 1.4 Pint passes — 7e07230
- [x] 1.5 Route is registered — 7e07230

### Phase 2: Swap dialog with all categories

#### Automated

- [x] 2.1 Client and SSR build passes — f39077d
- [x] 2.2 Token lint passes — f39077d

#### Manual

- [ ] 2.3 Kolacja dialog lists own category first, then the others; a Śniadanie pick persists
- [ ] 2.4 Search filters across all groups and Enter picks the top match

### Phase 3: Grid drag-and-drop and the category tag

#### Automated

- [x] 3.1 Client and SSR build passes
- [x] 3.2 Token lint passes
- [x] 3.3 Full Pest suite still passes

#### Manual

- [ ] 3.4 Drag swap in Chrome and Firefox with category tags, persists after refresh
- [ ] 3.5 Drag onto an empty cell moves the recipe and empties the source
- [ ] 3.6 Moving a cooked Obiad empties its carried meal with a toast; drop on own partner is a no-op
- [ ] 3.7 Grabbing the recipe name drags the cell; clicking still opens the recipe
- [ ] 3.8 Coarse pointer: no dragging, sideways scroll intact
- [ ] 3.9 Approved plan: nothing draggable

### Phase 4: E2E, fixtures, visual baselines and PRD

#### Automated

- [ ] 4.1 Seeder test passes
- [ ] 4.2 E2E passes in Chromium and Firefox
- [ ] 4.3 Visual gate passes after the deliberate update
- [ ] 4.4 Full Pest suite passes

#### Manual

- [ ] 4.5 Every changed PNG reviewed; done-plan screenshots unchanged
- [ ] 4.6 PRD FR 5, US-014 and US-018 consistent with shipped behaviour
