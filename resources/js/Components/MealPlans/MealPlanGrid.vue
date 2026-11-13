<template>
  <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
    <table class="w-full min-w-[40rem] text-sm" data-testid="meal-plan-grid">
      <thead>
        <tr class="border-b border-gray-200 bg-gray-50 text-left dark:border-gray-700 dark:bg-gray-900/40">
          <th scope="col" class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Dzień</th>
          <th v-for="category in MEAL_CATEGORIES" :key="category" scope="col" class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">
            {{ mealCategoryLabel(category) }}
          </th>
        </tr>
      </thead>
      <tbody>
        <template v-if="skeleton">
          <tr v-for="index in 7" :key="index" class="animate-pulse border-b border-gray-100 last:border-0 dark:border-gray-700">
            <td class="px-4 py-4"><div class="h-4 w-24 rounded bg-gray-200 dark:bg-gray-700" /></td>
            <td v-for="category in MEAL_CATEGORIES" :key="category" class="px-4 py-4">
              <div class="h-4 w-32 rounded bg-gray-200 dark:bg-gray-700" />
            </td>
          </tr>
        </template>
        <template v-else>
          <tr v-for="day in days" :key="day.date" class="border-b border-gray-100 align-top last:border-0 dark:border-gray-700">
            <th scope="row" class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-900 dark:text-gray-100">
              <span class="capitalize">{{ day.weekday }}</span>
              <span class="ml-1 text-gray-500 dark:text-gray-400">{{ day.short }}</span>
            </th>
            <td v-for="category in MEAL_CATEGORIES" :key="category" class="px-4 py-3">
              <template v-if="day.meals[category]?.recipe">
                <div class="font-medium text-gray-900 dark:text-gray-100">
                  <span v-if="day.meals[category].recipe.deleted">
                    {{ day.meals[category].recipe.name }}
                    <span class="text-xs font-normal text-gray-500">(usunięty)</span>
                  </span>
                  <Link v-else :href="route('recipes.show', day.meals[category].recipe.id)" class="hover:text-blue-600 hover:underline">
                    {{ day.meals[category].recipe.name }}
                  </Link>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                  {{ formatCalories(day.meals[category].recipe.calories) }} kcal · {{ day.meals[category].recipe.servings }} porcji
                </div>
              </template>
              <span v-else class="text-gray-400">—</span>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { MEAL_CATEGORIES, mealCategoryLabel } from '@/lib/mealCategories'

interface GridRecipe {
  id: number
  name: string
  calories: number | string
  servings: number
  deleted: boolean
}

interface GridMeal {
  id: number
  meal_date: string
  meal_category: string
  recipe?: GridRecipe
}

interface Props {
  startDate: string
  meals?: GridMeal[]
  skeleton?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  meals: () => [],
  skeleton: false,
})

const formatCalories = (calories: number | string): string => Number(calories).toFixed(0)

const addDays = (isoDate: string, offset: number): Date => {
  const [year, month, day] = isoDate.split('-').map(Number)

  return new Date(year, month - 1, day + offset)
}

const toIso = (date: Date): string => {
  const pad = (value: number): string => String(value).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

const days = computed(() =>
  Array.from({ length: 7 }, (_, offset) => {
    const date = addDays(props.startDate, offset)
    const iso = toIso(date)
    const meals: Record<string, GridMeal | undefined> = {}
    props.meals
      .filter((meal) => meal.meal_date === iso)
      .forEach((meal) => {
        meals[meal.meal_category] = meal
      })

    return {
      date: iso,
      weekday: date.toLocaleDateString('pl-PL', { weekday: 'long' }),
      short: date.toLocaleDateString('pl-PL', { day: '2-digit', month: '2-digit' }),
      meals,
    }
  })
)
</script>
