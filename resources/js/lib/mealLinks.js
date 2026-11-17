// Mirror of App\Services\MealLinks\MealLinkRules::PAIRS; keep the two in sync.
export const MEAL_LINK_PAIRS = [{ from: 'supper', to: 'supper', days: 1 }]

const shiftIsoDate = (isoDate, offset) => {
  const [year, month, day] = isoDate.split('-').map(Number)
  const date = new Date(Date.UTC(year, month - 1, day + offset))

  return date.toISOString().slice(0, 10)
}

/**
 * The meal that may feed `meal` ("Jak wczoraj"), under the same conditions as MealLinkRules::canFeed(), or null.
 */
export function linkSourceFor(meal, weekMeals) {
  if (!meal || meal.feeds_meal_id) {
    return null
  }

  for (const pair of MEAL_LINK_PAIRS) {
    if (pair.to !== meal.meal_category) {
      continue
    }

    const sourceDate = shiftIsoDate(meal.meal_date, -pair.days)
    const source = weekMeals.find((other) => other.meal_category === pair.from && other.meal_date === sourceDate && other.id !== meal.id)

    if (!source || !source.recipe || source.from_meal_id) {
      continue
    }

    if (!source.feeds_meal_id || source.feeds_meal_id === meal.id) {
      return source
    }
  }

  return null
}
