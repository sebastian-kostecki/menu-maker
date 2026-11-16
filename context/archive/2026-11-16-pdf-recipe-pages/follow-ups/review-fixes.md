# Review follow-ups: pdf-recipe-pages

Source: `reviews/impl-review.md` (2026-11-16).

## Before merge

- [ ] **F7.** Run E2E (`tests/e2e/meal-plan.spec.ts`, after re-seeding `E2eMealPlanSeeder`) and `npm run test:visual` against a Sail stack that serves this branch. The review could not, because the running stack mounts the main checkout.
- [ ] **F7.** Complete the pending manual Progress rows 1.5, 2.6, 3.6, 3.7 and 3.8 in `plan.md`.

## Later (separate change)

- [ ] **F6.** Replace the local `MealPlanListItem` declarations in `ActionDropdown.vue`, `MealPlanRow.vue`, `MealPlanTable.vue` and `Pages/MealPlans/Index.vue` with an import from `@/types/meal-plan`. This duplication existed before this change.
