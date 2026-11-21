# Review follow-ups: meal-plan-improvemnt

Source: `reviews/impl-review.md` (2026-11-21).

- [ ] **F4: manual checks (human).** Run plan.md "Manual Testing Steps" 1–4, then tick Progress 1.5, 2.5 and 2.6. For 2.6, the reviewer found that the baseline grew from 192×76 to 192×108, the top 64 rows are pixel-identical, and the only addition is the "Sam jadłospis" row. During 1.5, also check that "Bez posiłku" now renders muted (F5).
- [ ] **F7: shared `links` type (code, optional).** `ActionDropdown.vue` and `Pages/MealPlans/Index.vue` should import `MealPlanListItem` from `@/types/meal-plan`, and `Show.vue` should reuse an exported `MealPlanLinks` type instead of its local copy.
- [ ] **F9: duplicate meals per slot (watch only).** The menu keeps the first meal per (date, category). Revisit only if duplicates appear, or if a unique constraint on `meals (meal_plan_id, meal_date, meal_category)` is added.
