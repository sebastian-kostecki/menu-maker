<template>
  <Card class="overflow-x-auto p-4">
    <table
      class="w-full min-w-160 border-separate border-spacing-x-0 border-spacing-y-2 text-sm"
      data-testid="meal-plan-grid"
      :aria-busy="skeleton ? 'true' : undefined"
    >
      <thead>
        <tr class="text-left">
          <th scope="col" class="sticky left-0 z-10 bg-card pr-2"><span class="sr-only">Dzień</span></th>
          <th v-for="category in MEAL_CATEGORIES" :key="category" scope="col" class="pl-2 text-meta font-bold text-foreground">
            <span class="inline-flex items-center gap-1.5">
              <component :is="CATEGORY_ICONS[category]" class="size-4" aria-hidden="true" />
              {{ mealCategoryLabel(category) }}
            </span>
          </th>
        </tr>
      </thead>
      <tbody>
        <template v-if="skeleton">
          <tr v-for="index in 7" :key="index">
            <td class="sticky left-0 z-10 bg-card pr-2"><Skeleton class="h-4 w-20 motion-reduce:animate-none" /></td>
            <td v-for="category in MEAL_CATEGORIES" :key="category" class="pl-2">
              <Skeleton class="h-18 rounded-md motion-reduce:animate-none" />
            </td>
          </tr>
        </template>
        <template v-else>
          <tr v-for="day in days" :key="day.date" class="align-top">
            <th scope="row" class="sticky left-0 z-10 whitespace-nowrap bg-card pr-2 text-left align-top">
              <span class="block font-extrabold capitalize text-foreground">{{ day.weekday }}</span>
              <span class="block text-meta font-normal text-muted-foreground">{{ day.short }}</span>
            </th>
            <td v-for="category in MEAL_CATEGORIES" :key="category" class="pl-2">
              <div
                v-if="day.meals[category]?.recipe"
                class="flex min-h-18 flex-col items-start gap-1 rounded-md p-2.5"
                :class="categoryFillClass(category)"
              >
                <Badge v-if="day.meals[category].feeds_meal_id && cookedPairFor(category)" variant="tag">{{ cookedPairFor(category)?.cookedLabel }}</Badge>
                <Badge v-else-if="day.meals[category].from_meal_id && carriedPairFor(category)" variant="tag">{{ carriedPairFor(category)?.carriedLabel }}</Badge>
                <div class="font-semibold">
                  <span v-if="day.meals[category].recipe.deleted">
                    {{ day.meals[category].recipe.name }}
                    <span class="text-xs font-normal">(usunięty)</span>
                  </span>
                  <Link
                    v-else
                    :href="route('recipes.show', day.meals[category].recipe.id)"
                    class="rounded-sm hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                  >
                    {{ day.meals[category].recipe.name }}
                  </Link>
                </div>
                <div v-if="!day.meals[category].from_meal_id" class="text-xs font-medium opacity-85">
                  <span class="tabular-nums">{{ formatCalories(day.meals[category].recipe.calories) }}</span> kcal (cały przepis) · {{ day.meals[category].recipe.servings }} {{ servingsLabel(day.meals[category].recipe.servings) }}
                </div>
                <Button
                  v-if="editable"
                  type="button"
                  variant="outline"
                  size="xs"
                  class="mt-1"
                  :aria-label="`Zmień przepis: ${day.weekday} – ${mealCategoryLabel(category)}`"
                  @click="emit('swap', day.meals[category])"
                >
                  <RefreshCw aria-hidden="true" />
                  Zmień
                </Button>
                <Collapsible v-if="!day.meals[category].from_meal_id && day.meals[category].ingredients?.length" class="text-xs">
                  <CollapsibleTrigger
                    class="group inline-flex items-center gap-1 rounded-sm font-semibold underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                  >
                    Składniki ({{ day.meals[category].ingredients.length }})
                    <ChevronDown
                      class="size-4 transition-transform group-data-[state=open]:rotate-180 motion-reduce:transition-none"
                      aria-hidden="true"
                    />
                  </CollapsibleTrigger>
                  <CollapsibleContent>
                    <ul class="mt-1 list-disc space-y-0.5 pl-4" data-testid="meal-ingredients">
                      <li v-for="(ingredient, index) in day.meals[category].ingredients" :key="`${ingredient.ingredient_id}-${index}`">
                        {{ ingredient.name }} — {{ ingredient.display }}
                      </li>
                    </ul>
                  </CollapsibleContent>
                </Collapsible>
              </div>
              <div
                v-else
                class="flex min-h-18 flex-col items-start gap-1 rounded-md border-2 border-dashed border-muted-foreground bg-card p-2.5 text-muted-foreground"
              >
                <div>Bez posiłku</div>
                <Button
                  v-if="editable && day.meals[category]"
                  type="button"
                  variant="outline"
                  size="xs"
                  class="mt-1"
                  :aria-label="`Wybierz przepis: ${day.weekday} – ${mealCategoryLabel(category)}`"
                  @click="emit('swap', day.meals[category])"
                >
                  <Plus aria-hidden="true" />
                  Wybierz
                </Button>
              </div>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </Card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ChevronDown, Moon, Plus, RefreshCw, Soup, Sun } from 'lucide-vue-next'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card } from '@/Components/ui/card'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/Components/ui/collapsible'
import { Skeleton } from '@/Components/ui/skeleton'
import { pluralize } from '@/lib/format'
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

const CATEGORY_FILL_CLASSES: Record<string, string> = {
  breakfast: 'bg-meal-breakfast text-meal-breakfast-foreground',
  supper: 'bg-meal-supper text-meal-supper-foreground',
  dinner: 'bg-meal-dinner text-meal-dinner-foreground',
}

const CATEGORY_ICONS: Record<string, unknown> = {
  breakfast: Sun,
  supper: Soup,
  dinner: Moon,
}

const categoryFillClass = (category: string): string => CATEGORY_FILL_CLASSES[category] ?? 'bg-muted text-foreground'

const servingsLabel = (servings: number): string => pluralize(servings, { one: 'porcja', few: 'porcje', many: 'porcji' })

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
