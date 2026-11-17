<template>
  <div class="overflow-x-auto rounded-lg border border-border bg-card">
    <table class="w-full min-w-160 text-sm" data-testid="meal-plan-grid" :aria-busy="skeleton ? 'true' : undefined">
      <thead>
        <tr class="border-b border-border bg-muted text-left">
          <th scope="col" class="px-4 py-3 font-medium text-muted-foreground">Dzień</th>
          <th v-for="category in MEAL_CATEGORIES" :key="category" scope="col" class="px-4 py-3 font-medium text-muted-foreground">
            {{ mealCategoryLabel(category) }}
          </th>
        </tr>
      </thead>
      <tbody>
        <template v-if="skeleton">
          <tr v-for="index in 7" :key="index" class="border-b border-border last:border-0">
            <td class="px-4 py-4"><Skeleton class="h-4 w-24 motion-reduce:animate-none" /></td>
            <td v-for="category in MEAL_CATEGORIES" :key="category" class="px-4 py-4">
              <Skeleton class="h-4 w-32 motion-reduce:animate-none" />
            </td>
          </tr>
        </template>
        <template v-else>
          <tr v-for="day in days" :key="day.date" class="border-b border-border align-top last:border-0">
            <th scope="row" class="whitespace-nowrap px-4 py-3 text-left font-medium text-foreground">
              <span class="capitalize">{{ day.weekday }}</span>
              <span class="ml-1 text-muted-foreground">{{ day.short }}</span>
            </th>
            <td v-for="category in MEAL_CATEGORIES" :key="category" class="px-4 py-3">
              <div v-if="day.meals[category]?.recipe" class="border-l-4 pl-3" :class="categoryBarClass(category)">
                <div class="font-medium text-foreground">
                  <span v-if="day.meals[category].recipe.deleted">
                    {{ day.meals[category].recipe.name }}
                    <span class="text-xs font-normal text-muted-foreground">(usunięty)</span>
                  </span>
                  <Link
                    v-else
                    :href="route('recipes.show', day.meals[category].recipe.id)"
                    class="rounded-sm hover:text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  >
                    {{ day.meals[category].recipe.name }}
                  </Link>
                  <Badge v-if="day.meals[category].feeds_meal_id && cookedPairFor(category)" variant="muted" class="ml-2 align-middle">{{ cookedPairFor(category)?.cookedLabel }}</Badge>
                  <Badge v-else-if="day.meals[category].from_meal_id && carriedPairFor(category)" variant="muted" class="ml-2 align-middle">{{ carriedPairFor(category)?.carriedLabel }}</Badge>
                </div>
                <div v-if="!day.meals[category].from_meal_id" class="text-xs text-muted-foreground">
                  <span class="tabular-nums">{{ formatCalories(day.meals[category].recipe.calories) }}</span> kcal (cały przepis) · {{ day.meals[category].recipe.servings }} porcji
                </div>
                <Button
                  v-if="editable"
                  type="button"
                  variant="outline"
                  size="sm"
                  class="mt-2"
                  :aria-label="`Zmień przepis: ${day.weekday} – ${mealCategoryLabel(category)}`"
                  @click="emit('swap', day.meals[category])"
                >
                  Zmień
                </Button>
                <Collapsible v-if="!day.meals[category].from_meal_id && day.meals[category].ingredients?.length" class="mt-2 text-xs">
                  <CollapsibleTrigger
                    class="group inline-flex items-center gap-1 rounded-sm text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  >
                    Składniki ({{ day.meals[category].ingredients.length }})
                    <ChevronDown
                      class="size-4 transition-transform group-data-[state=open]:rotate-180 motion-reduce:transition-none"
                      aria-hidden="true"
                    />
                  </CollapsibleTrigger>
                  <CollapsibleContent>
                    <ul class="mt-1 list-disc space-y-0.5 pl-4 text-foreground" data-testid="meal-ingredients">
                      <li v-for="(ingredient, index) in day.meals[category].ingredients" :key="`${ingredient.ingredient_id}-${index}`">
                        {{ ingredient.name }} — {{ ingredient.display }}
                      </li>
                    </ul>
                  </CollapsibleContent>
                </Collapsible>
              </div>
              <div v-else class="border-l-4 pl-3" :class="categoryBarClass(category)">
                <div class="text-muted-foreground">Bez posiłku</div>
                <Button
                  v-if="editable && day.meals[category]"
                  type="button"
                  variant="outline"
                  size="sm"
                  class="mt-2"
                  :aria-label="`Wybierz przepis: ${day.weekday} – ${mealCategoryLabel(category)}`"
                  @click="emit('swap', day.meals[category])"
                >
                  Wybierz
                </Button>
              </div>
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
import { ChevronDown } from 'lucide-vue-next'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/Components/ui/collapsible'
import { Skeleton } from '@/Components/ui/skeleton'
import { MEAL_CATEGORIES, mealCategoryLabel } from '@/lib/mealCategories'
import { cookedPairFor, carriedPairFor } from '@/lib/mealLinks'

interface GridRecipe {
  id: number
  name: string
  calories: number | string
  servings: number
  deleted: boolean
}

interface GridIngredient {
  ingredient_id: number
  name: string
  display: string
}

interface GridMeal {
  id: number
  meal_date: string
  meal_category: string
  recipe?: GridRecipe
  ingredients?: GridIngredient[]
  feeds_meal_id?: number | null
  from_meal_id?: number | null
}

interface Props {
  startDate: string
  meals?: GridMeal[]
  skeleton?: boolean
  editable?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  meals: () => [],
  skeleton: false,
  editable: false,
})

const emit = defineEmits<{ (e: 'swap', meal: GridMeal): void }>()

const CATEGORY_BAR_CLASSES: Record<string, string> = {
  breakfast: 'border-meal-breakfast',
  supper: 'border-meal-supper',
  dinner: 'border-meal-dinner',
}

const categoryBarClass = (category: string): string => CATEGORY_BAR_CLASSES[category] ?? 'border-border'

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
