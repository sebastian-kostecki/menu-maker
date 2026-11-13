export const MEAL_CATEGORIES = ['breakfast', 'supper', 'dinner']

export const MEAL_CATEGORY_LABELS = {
  breakfast: 'Śniadanie',
  supper: 'Obiad',
  dinner: 'Kolacja',
}

export function mealCategoryLabel(category) {
  return MEAL_CATEGORY_LABELS[category] ?? category
}
