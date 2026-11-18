<script setup>
import { computed } from 'vue'
import { Badge } from '@/Components/ui/badge'
import { Card } from '@/Components/ui/card'
import CategoryBadge from '@/Components/recipes/CategoryBadge.vue'
import { formatDateRange, formatDayMonth, formatWeekdayShort } from '@/lib/format'
import { carriedPairFor, cookedPairFor } from '@/lib/mealLinks'
import { MEAL_CATEGORIES, mealCategoryLabel } from '@/lib/mealCategories'
import { todayYmd, weekDays } from '@/lib/mealWeek'

const CATEGORY_FILL_CLASSES = {
  breakfast: 'bg-meal-breakfast text-meal-breakfast-foreground',
  supper: 'bg-meal-supper text-meal-supper-foreground',
  dinner: 'bg-meal-dinner text-meal-dinner-foreground',
}

const props = defineProps({
  startDate: { type: String, required: true },
  endDate: { type: String, required: true },
  meals: { type: Array, required: true },
  today: { type: String, default: null },
})

const title = computed(() => {
  const today = props.today ?? todayYmd()

  return props.startDate <= today && today <= props.endDate
    ? 'Ten tydzień'
    : `Tydzień ${formatDateRange(props.startDate, props.endDate)}`
})

const capitalize = (text) => text.charAt(0).toUpperCase() + text.slice(1)

const days = computed(() =>
  weekDays(props.startDate).map((date) => ({
    date,
    label: capitalize(formatWeekdayShort(date)),
    short: formatDayMonth(date),
    meals: Object.fromEntries(
      MEAL_CATEGORIES.map((category) => [
        category,
        props.meals.find((meal) => meal.meal_date === date && meal.meal_category === category) ?? null,
      ]),
    ),
  })),
)

const tagFor = (meal, category) => {
  if (meal.feeds_meal_id) {
    return cookedPairFor(category)?.cookedLabel ?? null
  }

  if (meal.from_meal_id) {
    return carriedPairFor(category)?.carriedLabel ?? null
  }

  return null
}
</script>

<template>
  <Card class="flex flex-col gap-4 p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="text-section-title">{{ title }}</h2>
      <div class="flex flex-wrap items-center gap-2">
        <CategoryBadge v-for="category in MEAL_CATEGORIES" :key="category" :category="category" />
      </div>
    </div>
    <ol class="grid gap-4 lg:grid-cols-7 lg:gap-2">
      <li v-for="day in days" :key="day.date" class="grid grid-cols-3 gap-1.5 lg:grid-cols-1">
        <p class="col-span-3 text-meta font-bold lg:col-span-1">
          {{ day.label }}
          <span class="font-medium text-muted-foreground">{{ day.short }}</span>
        </p>
        <template v-for="category in MEAL_CATEGORIES" :key="category">
          <div
            v-if="day.meals[category]?.recipe"
            class="flex min-h-13 flex-col items-start gap-1 rounded-md px-2.5 py-2 text-meta"
            :class="CATEGORY_FILL_CLASSES[category]"
          >
            <Badge v-if="tagFor(day.meals[category], category)" variant="tag">{{ tagFor(day.meals[category], category) }}</Badge>
            <span>
              <span class="sr-only">{{ mealCategoryLabel(category) }}: </span>{{ day.meals[category].recipe.name }}<template v-if="day.meals[category].recipe.deleted"> (usunięty)</template>
            </span>
          </div>
          <div
            v-else
            class="min-h-13 rounded-md border-2 border-dashed border-muted-foreground px-2.5 py-2 text-meta text-muted-foreground"
          >
            <span class="sr-only">{{ mealCategoryLabel(category) }}: </span>Bez posiłku
          </div>
        </template>
      </li>
    </ol>
  </Card>
</template>
