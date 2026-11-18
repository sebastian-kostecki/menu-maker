import { Moon, Soup, Sun } from 'lucide-vue-next'

// Mirror of App\Enums\MealCategory (display order and Polish labels); keep the two in sync.
export const MEAL_CATEGORIES = ['breakfast', 'supper', 'dinner']

export const MEAL_CATEGORY_LABELS = {
  breakfast: 'Śniadanie',
  supper: 'Obiad',
  dinner: 'Kolacja',
}

export const MEAL_CATEGORY_ICONS = {
  breakfast: Sun,
  supper: Soup,
  dinner: Moon,
}

// Static full strings so Tailwind sees them; callers fall back to 'bg-muted text-foreground' for an unknown category.
export const MEAL_CATEGORY_FILL_CLASSES = {
  breakfast: 'bg-meal-breakfast text-meal-breakfast-foreground',
  supper: 'bg-meal-supper text-meal-supper-foreground',
  dinner: 'bg-meal-dinner text-meal-dinner-foreground',
}

export function mealCategoryLabel(category) {
  return MEAL_CATEGORY_LABELS[category] ?? category
}
