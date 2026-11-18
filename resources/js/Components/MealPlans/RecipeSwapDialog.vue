<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>{{ meal && !meal.recipe ? 'Wybierz przepis' : 'Zmień przepis' }}</DialogTitle>
        <DialogDescription>{{ description }}</DialogDescription>
        <p v-if="feedsWarning" class="text-sm text-muted-foreground">{{ feedsWarning }}</p>
      </DialogHeader>

      <Combobox :open="true" :model-value="undefined" ignore-filter @update:model-value="handleSelect">
        <ComboboxAnchor class="w-full">
          <ComboboxInput v-model="searchTerm" aria-label="Szukaj przepisu" placeholder="Szukaj przepisu" />
        </ComboboxAnchor>
        <ComboboxContent position="inline" class="mt-2 max-h-72 overflow-y-auto rounded-md border bg-popover text-popover-foreground">
          <ComboboxViewport class="p-1">
            <ComboboxItem
              v-if="!isSearching"
              :value="EMPTY_OPTION"
              text-value="Nie robimy (pusty posiłek)"
              :disabled="saving"
              :class="[ITEM_CLASS, isEmptyCurrent ? currentClass : '']"
            >
              <span class="flex w-full items-center justify-between gap-2">
                <span :class="['font-medium', isEmptyCurrent ? '' : 'italic text-muted-foreground']">Nie robimy (pusty posiłek)</span>
                <span v-if="isEmptyCurrent" class="text-xs opacity-85">obecny</span>
              </span>
            </ComboboxItem>
            <ComboboxItem
              v-if="!isSearching && linkSource && linkPair"
              :value="{ linkFrom: linkSource }"
              :text-value="`${linkPair.offerLabel}: ${linkSource.recipe?.name}`"
              :disabled="saving"
              :class="[ITEM_CLASS, isLinkCurrent ? currentClass : '']"
            >
              <span class="flex w-full items-center justify-between gap-2">
                <span class="font-medium">{{ linkPair.offerLabel }}: {{ linkSource.recipe?.name }}</span>
                <span v-if="isLinkCurrent" class="text-xs opacity-85">obecny</span>
              </span>
              <span :class="['text-xs', isLinkCurrent ? 'opacity-85' : 'text-muted-foreground']">{{ linkPair.offerHint }}</span>
            </ComboboxItem>
            <p v-if="!hasMatches" class="p-2 text-sm text-muted-foreground">Brak przepisów</p>
            <ComboboxGroup v-for="group in filteredGroups" :key="group.category" :data-testid="`swap-group-${group.category}`">
              <ComboboxLabel class="px-2 py-1.5 text-meta text-muted-foreground">{{ group.label }}</ComboboxLabel>
              <ComboboxItem
              v-for="option in group.options"
              :key="option.id"
              :value="option"
              :text-value="option.name"
              :disabled="saving"
              :class="[ITEM_CLASS, isCurrentRecipe(option.id) ? currentClass : '']"
            >
              <span class="flex w-full items-center justify-between gap-2">
                <span class="font-medium">{{ option.name }}</span>
                <span v-if="isCurrentRecipe(option.id)" class="text-xs opacity-85">obecny</span>
              </span>
              <span :class="['text-xs', isCurrentRecipe(option.id) ? 'opacity-85' : 'text-muted-foreground']">
                <span v-if="option.calories !== null" class="tabular-nums">{{ formatNumber(option.calories, { maximumFractionDigits: 0 }) }} kcal</span>
                <span v-if="option.usedOn"> · jest już: {{ option.usedOn }}</span>
              </span>
            </ComboboxItem>
            </ComboboxGroup>
          </ComboboxViewport>
        </ComboboxContent>
      </Combobox>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
// ui/combobox's ComboboxList always portals out and is sized as a popper; inside a Dialog the list must stay
// inline (position="inline") within the dialog's focus trap, so the reka-ui parts are used directly here.
import { ComboboxContent, ComboboxLabel, ComboboxViewport } from 'reka-ui'
import { toast } from 'vue-sonner'
import {
  Combobox,
  ComboboxAnchor,
  ComboboxGroup,
  ComboboxInput,
  ComboboxItem,
} from '@/Components/ui/combobox'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/dialog'
import { formatDayMonth, formatNumber, formatWeekdayShort } from '@/lib/format'
import { mealCategoryLabel } from '@/lib/mealCategories'
import { carriedPairFor, cookedPairFor, linkSourceFor } from '@/lib/mealLinks'
import type { RecipeOption, RecipeOptionGroup } from '@/types/meal-plan'

interface SwapMeal {
  id: number
  meal_date: string
  meal_category: string
  recipe?: { id: number; name: string }
  feeds_meal_id?: number | null
  from_meal_id?: number | null
}

interface Props {
  open: boolean
  mealPlanId: number
  meal: SwapMeal | null
  groups: RecipeOptionGroup[]
  weekMeals: SwapMeal[]
  /** Kitchen-sink mode: picking a recipe only closes the dialog and sends no request. */
  demo?: boolean
}

const props = defineProps<Props>()
const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>()

const EMPTY_OPTION = { empty: true } as const

const saving = ref(false)
const searchTerm = ref('')

watch(
  () => props.open,
  () => {
    searchTerm.value = ''
  }
)

const currentRecipeId = computed(() => props.meal?.recipe?.id ?? null)

const ITEM_CLASS = 'flex-col items-start gap-0.5 rounded-md px-3.5 py-2.5'

// Full class names (no interpolation) so Tailwind sees them; the data-highlighted pair keeps the meal fill over the item's accent highlight.
const CURRENT_CLASSES: Record<string, string> = {
  breakfast:
    'bg-meal-breakfast text-meal-breakfast-foreground data-highlighted:bg-meal-breakfast data-highlighted:text-meal-breakfast-foreground data-highlighted:brightness-95',
  supper:
    'bg-meal-supper text-meal-supper-foreground data-highlighted:bg-meal-supper data-highlighted:text-meal-supper-foreground data-highlighted:brightness-95',
  dinner:
    'bg-meal-dinner text-meal-dinner-foreground data-highlighted:bg-meal-dinner data-highlighted:text-meal-dinner-foreground data-highlighted:brightness-95',
}

const currentClass = computed(() => CURRENT_CLASSES[props.meal?.meal_category ?? ''] ?? '')
const isEmptyCurrent = computed(() => Boolean(props.meal && !props.meal.recipe))
const isLinkCurrent = computed(() => Boolean(linkSource.value && props.meal?.from_meal_id === linkSource.value.id))
const isCurrentRecipe = (recipeId: number): boolean => recipeId === currentRecipeId.value && !props.meal?.from_meal_id

const description = computed(() => {
  if (!props.meal) {
    return ''
  }

  return `${formatWeekdayShort(props.meal.meal_date)} ${formatDayMonth(props.meal.meal_date)} – ${mealCategoryLabel(props.meal.meal_category)}`
})

const linkSource = computed(() => linkSourceFor(props.meal, props.weekMeals) as SwapMeal | null)

const linkPair = computed(() => (props.meal ? carriedPairFor(props.meal.meal_category) : null))

const feedsWarning = computed(() => {
  const fed = props.meal?.feeds_meal_id ? props.weekMeals.find((other) => other.id === props.meal?.feeds_meal_id) : null

  const pair = props.meal ? cookedPairFor(props.meal.meal_category) : null

  return fed && pair ? pair.feedsWarning(`${formatWeekdayShort(fed.meal_date)} ${formatDayMonth(fed.meal_date)}`) : ''
})

const groupsWithUsage = computed(() =>
  props.groups.map((group) => ({
    ...group,
    options: group.options.map((option) => {
      const days = props.weekMeals
        .filter((other) => other.recipe?.id === option.id && other.id !== props.meal?.id)
        .map((other) => formatWeekdayShort(other.meal_date))

      return { ...option, usedOn: [...new Set(days)].join(', ') }
    }),
  }))
)

// reka-ui highlights the first item on every keystroke, so while the user searches the "Nie robimy" row stays hidden:
// otherwise Enter after typing a name would empty the meal instead of picking the top match.
const isSearching = computed(() => searchTerm.value.trim() !== '')

const filteredGroups = computed(() => {
  const term = searchTerm.value.trim().toLocaleLowerCase('pl')

  if (!term) {
    return groupsWithUsage.value
  }

  return groupsWithUsage.value
    .map((group) => ({ ...group, options: group.options.filter((option) => option.name.toLocaleLowerCase('pl').includes(term)) }))
    .filter((group) => group.options.length > 0)
})

const hasMatches = computed(() => filteredGroups.value.length > 0)

const handleSelect = (option: unknown) => {
  const picked = option as RecipeOption | typeof EMPTY_OPTION | { linkFrom: SwapMeal } | undefined

  if (!picked || !props.meal || saving.value) {
    return
  }

  if (props.demo) {
    emit('update:open', false)

    return
  }

  saving.value = true

  router.patch(
    route('meal-plans.meals.update', { meal_plan: props.mealPlanId, meal: props.meal.id }),
    'linkFrom' in picked ? { from_meal_id: picked.linkFrom.id } : { recipe_id: 'empty' in picked ? null : picked.id },
    {
      preserveScroll: true,
      only: ['mealPlan', 'shortCategories'],
      onSuccess: () => {
        emit('update:open', false)
      },
      onError: (errors) => {
        toast.error(errors.recipe_id ?? errors.from_meal_id ?? errors.status ?? 'Nie udało się zmienić przepisu.')

        // The plan is no longer a draft (e.g. approved in another tab): nothing left to pick here.
        if (errors.status) {
          emit('update:open', false)
        }
      },
      onFinish: () => {
        saving.value = false
      },
    }
  )
}
</script>
