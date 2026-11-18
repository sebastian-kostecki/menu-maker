// Mirror of App\Enums\MealCategory (display order and Polish labels); keep the two in sync.
export const MEAL_CATEGORIES = ['breakfast', 'supper', 'dinner']

export const MEAL_CATEGORY_LABELS = {
  breakfast: 'Śniadanie',
  supper: 'Obiad',
  dinner: 'Kolacja',
}

// Complete class strings per category, so Tailwind finds them literally in the source.
export const MEAL_CATEGORY_FILL_CLASSES = {
  breakfast: 'bg-meal-breakfast text-meal-breakfast-foreground',
  supper: 'bg-meal-supper text-meal-supper-foreground',
  dinner: 'bg-meal-dinner text-meal-dinner-foreground',
}

export function mealCategoryLabel(category) {
  return MEAL_CATEGORY_LABELS[category] ?? category
}
