<template>
  <Card class="scroll-pl-32 overflow-x-auto p-4">
    <table
      class="w-full min-w-160 border-separate border-spacing-x-0 border-spacing-y-2 text-sm"
      data-testid="meal-plan-grid"
      :aria-busy="skeleton || moving ? 'true' : undefined"
    >
      <thead>
        <tr class="text-left">
          <th scope="col" class="sticky left-0 z-10 w-28 bg-card pr-2"><span class="sr-only">Dzień</span></th>
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
            <td class="sticky left-0 z-10 w-28 bg-card pr-2"><Skeleton class="h-4 w-20 motion-reduce:animate-none" /></td>
            <td v-for="category in MEAL_CATEGORIES" :key="category" class="pl-2">
              <Skeleton class="h-18 rounded-md motion-reduce:animate-none" />
            </td>
          </tr>
        </template>
        <template v-else>
          <tr v-for="day in days" :key="day.date" class="align-top">
            <th scope="row" class="sticky left-0 z-10 w-28 whitespace-nowrap bg-card pr-2 text-left align-top">
              <span class="block font-extrabold capitalize text-foreground">{{ day.weekday }}</span>
              <span class="block text-meta font-normal text-muted-foreground">{{ day.short }}</span>
            </th>
            <td v-for="category in MEAL_CATEGORIES" :key="category" class="pl-2">
              <div
                v-if="day.meals[category]?.recipe"
                class="relative flex min-h-18 flex-col items-start gap-1 rounded-md p-2.5 transition-opacity motion-reduce:transition-none"
                :class="[categoryFillClass(category), cellDragClass(day.meals[category])]"
                :draggable="canDrag ? 'true' : undefined"
                @dragstart="onDragStart($event, day.meals[category])"
                @dragend="onDragEnd"
                @dragenter="onDragOver($event, day.meals[category])"
                @dragover="onDragOver($event, day.meals[category])"
                @dragleave="onDragLeave($event, day.meals[category])"
                @drop="onDrop($event, day.meals[category])"
              >
                <GripVertical v-if="canDrag" class="absolute right-1 top-1 size-4 opacity-60" aria-hidden="true" />
                <Badge v-if="day.meals[category].feeds_meal_id && cookedPairFor(category)" variant="tag">{{ cookedPairFor(category)?.cookedLabel }}</Badge>
                <Badge v-else-if="day.meals[category].from_meal_id && carriedPairFor(category)" variant="tag">{{ carriedPairFor(category)?.carriedLabel }}</Badge>
                <Badge v-if="showsCategoryTag(day.meals[category], category)" variant="tag">
                  {{ mealCategoryLabel(day.meals[category].recipe.category) }}
                </Badge>
                <div class="font-semibold">
                  <span v-if="day.meals[category].recipe.deleted">
                    {{ day.meals[category].recipe.name }}
                    <span class="text-xs font-normal">(usunięty)</span>
                  </span>
                  <Link
                    v-else
                    :href="route('recipes.show', day.meals[category].recipe.id)"
                    draggable="false"
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
                :class="cellDragClass(day.meals[category])"
                @dragenter="onDragOver($event, day.meals[category])"
                @dragover="onDragOver($event, day.meals[category])"
                @dragleave="onDragLeave($event, day.meals[category])"
                @drop="onDrop($event, day.meals[category])"
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
import { computed, ref } from 'vue'
import { useMediaQuery } from '@vueuse/core'
import { Link } from '@inertiajs/vue3'
import { ChevronDown, GripVertical, Moon, Plus, RefreshCw, Soup, Sun } from 'lucide-vue-next'
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
  category: string
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
  moving?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  meals: () => [],
  skeleton: false,
  editable: false,
  moving: false,
})

const emit = defineEmits<{
  (e: 'swap', meal: GridMeal): void
  (e: 'move', source: GridMeal, target: GridMeal): void
}>()

const hasFinePointer = useMediaQuery('(pointer: fine)')
const canDrag = computed(() => props.editable && !props.skeleton && !props.moving && hasFinePointer.value)
const draggedMeal = ref<GridMeal | null>(null)
const hoveredMealId = ref<number | null>(null)

/**
 * A drop is valid only where it would change something: not the dragged meal itself and not a meal with the same recipe (which includes its own linked partner).
 */
const isValidTarget = (target?: GridMeal): target is GridMeal =>
  canDrag.value &&
  draggedMeal.value !== null &&
  target !== undefined &&
  target.id !== draggedMeal.value.id &&
  target.recipe?.id !== draggedMeal.value.recipe?.id

/**
 * The recipe-category tag marks a recipe from another time of day, unless the carried-over link already explains it (a Kolacja recipe „Z kolacji” at Śniadanie).
 */
const showsCategoryTag = (meal: GridMeal | undefined, category: string): boolean => {
  if (!meal?.recipe || meal.recipe.category === category) {
    return false
  }

  return !(meal.from_meal_id && meal.recipe.category === carriedPairFor(category)?.from)
}

const onDragStart = (event: DragEvent, meal?: GridMeal): void => {
  if (!canDrag.value || !meal || !event.dataTransfer) {
    event.preventDefault()

    return
  }

  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', String(meal.id))
  draggedMeal.value = meal
}

const onDragEnd = (): void => {
  draggedMeal.value = null
  hoveredMealId.value = null
}

const onDragOver = (event: DragEvent, target?: GridMeal): void => {
  if (!isValidTarget(target)) {
    return
  }

  event.preventDefault()
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = 'move'
  }
  hoveredMealId.value = target.id
}

const onDragLeave = (event: DragEvent, target?: GridMeal): void => {
  const cell = event.currentTarget as Node | null

  // Moving onto a child of the same cell (badge, link, button) is not leaving it.
  if (cell && event.relatedTarget instanceof Node && cell.contains(event.relatedTarget)) {
    return
  }

  if (target && hoveredMealId.value === target.id) {
    hoveredMealId.value = null
  }
}

const onDrop = (event: DragEvent, target?: GridMeal): void => {
  const source = draggedMeal.value

  if (!isValidTarget(target) || source === null) {
    return
  }

  event.preventDefault()
  onDragEnd()
  emit('move', source, target)
}

const cellDragClass = (meal?: GridMeal): string[] => {
  if (!meal) {
    return []
  }

  const classes: string[] = []

  if (canDrag.value && meal.recipe) {
    classes.push('cursor-grab select-none pr-6 active:cursor-grabbing')
  }

  if (draggedMeal.value?.id === meal.id) {
    classes.push('opacity-50')
  }

  if (hoveredMealId.value === meal.id) {
    classes.push('outline-2 outline-dashed outline-offset-2 outline-ring')
  }

  return classes
}

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
