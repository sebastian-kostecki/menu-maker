// Mirror of App\Services\MealLinks\MealLinkRules::PAIRS; keep the two in sync.
export const MEAL_LINK_PAIRS = [
  {
    from: 'supper',
    to: 'supper',
    days: 1,
    cookedLabel: 'Na 2 dni',
    carriedLabel: 'Z wczoraj',
    offerLabel: 'Jak wczoraj',
    offerHint: 'Gotujemy raz na 2 dni',
    feedsWarning: (dateLabel) => `Zmiana opróżni obiad z ${dateLabel}, który jest „z wczoraj”.`,
  },
  {
    from: 'dinner',
    to: 'breakfast',
    days: 1,
    cookedLabel: 'Też na śniadanie',
    carriedLabel: 'Z kolacji',
    offerLabel: 'Z kolacji',
    offerHint: 'Gotujemy raz, zostaje na śniadanie',
    feedsWarning: (dateLabel) => `Zmiana opróżni śniadanie z ${dateLabel}, które jest „z kolacji”.`,
  },
]

/**
 * The pair whose cooked meal has this category (labels of the meal that feeds the next day), or null.
 */
export const cookedPairFor = (category) => MEAL_LINK_PAIRS.find((pair) => pair.from === category) ?? null

/**
 * The pair whose carried-over meal has this category (labels of the meal that is fed), or null.
 */
export const carriedPairFor = (category) => MEAL_LINK_PAIRS.find((pair) => pair.to === category) ?? null

const shiftIsoDate = (isoDate, offset) => {
  const [year, month, day] = isoDate.split('-').map(Number)
  const date = new Date(Date.UTC(year, month - 1, day + offset))

  return date.toISOString().slice(0, 10)
}

/**
 * The meal that may feed `meal` (the "Jak wczoraj" / "Z kolacji" dialog item), under the same conditions as MealLinkRules::canFeed(), or null.
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
