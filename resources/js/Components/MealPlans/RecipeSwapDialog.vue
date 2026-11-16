<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>{{ meal && !meal.recipe ? 'Wybierz przepis' : 'Zmień przepis' }}</DialogTitle>
        <DialogDescription>{{ description }}</DialogDescription>
      </DialogHeader>

      <Combobox v-model:search-term="searchTerm" :open="true" :model-value="undefined" ignore-filter @update:model-value="handleSelect">
        <ComboboxAnchor class="w-full">
          <ComboboxInput aria-label="Szukaj przepisu" placeholder="Szukaj przepisu" />
        </ComboboxAnchor>
        <ComboboxContent position="inline" class="mt-2 max-h-72 overflow-y-auto rounded-md border bg-popover text-popover-foreground">
          <ComboboxViewport class="p-1">
            <ComboboxItem :value="EMPTY_OPTION" text-value="Nie robimy (pusty posiłek)" :disabled="saving" class="flex-col items-start gap-0.5">
              <span class="flex w-full items-center justify-between gap-2">
                <span class="font-medium">Nie robimy (pusty posiłek)</span>
                <span v-if="meal && !meal.recipe" class="text-xs text-muted-foreground">obecny</span>
              </span>
            </ComboboxItem>
            <p v-if="!filteredItems.length" class="p-2 text-sm text-muted-foreground">Brak przepisów</p>
            <ComboboxItem
              v-for="option in filteredItems"
              :key="option.id"
              :value="option"
              :text-value="option.name"
              :disabled="saving"
              class="flex-col items-start gap-0.5"
            >
              <span class="flex w-full items-center justify-between gap-2">
                <span class="font-medium">{{ option.name }}</span>
                <span v-if="option.id === currentRecipeId" class="text-xs text-muted-foreground">obecny</span>
              </span>
              <span class="text-xs text-muted-foreground">
                <span v-if="option.calories !== null" class="tabular-nums">{{ formatNumber(option.calories, { maximumFractionDigits: 0 }) }} kcal</span>
                <span v-if="option.usedOn"> · jest już: {{ option.usedOn }}</span>
              </span>
            </ComboboxItem>
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
import { ComboboxContent, ComboboxViewport } from 'reka-ui'
import { toast } from 'vue-sonner'
import {
  Combobox,
  ComboboxAnchor,
  ComboboxInput,
  ComboboxItem,
} from '@/Components/ui/combobox'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/dialog'
import { formatDayMonth, formatNumber, formatWeekdayShort } from '@/lib/format'
import { mealCategoryLabel } from '@/lib/mealCategories'
import type { RecipeOption } from '@/types/meal-plan'

interface SwapMeal {
  id: number
  meal_date: string
  meal_category: string
  recipe?: { id: number; name: string }
}

interface Props {
  open: boolean
  mealPlanId: number
  meal: SwapMeal | null
  options: RecipeOption[]
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

const description = computed(() => {
  if (!props.meal) {
    return ''
  }

  return `${formatWeekdayShort(props.meal.meal_date)} ${formatDayMonth(props.meal.meal_date)} – ${mealCategoryLabel(props.meal.meal_category)}`
})

const items = computed(() =>
  props.options.map((option) => {
    const days = props.weekMeals
      .filter((other) => other.recipe?.id === option.id && other.id !== props.meal?.id)
      .map((other) => formatWeekdayShort(other.meal_date))

    return { ...option, usedOn: [...new Set(days)].join(', ') }
  })
)

const filteredItems = computed(() => {
  const term = searchTerm.value.trim().toLocaleLowerCase('pl')

  return term ? items.value.filter((option) => option.name.toLocaleLowerCase('pl').includes(term)) : items.value
})

const handleSelect = (option: unknown) => {
  const picked = option as RecipeOption | typeof EMPTY_OPTION | undefined

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
    { recipe_id: 'empty' in picked ? null : picked.id },
    {
      preserveScroll: true,
      only: ['mealPlan'],
      onSuccess: () => {
        emit('update:open', false)
      },
      onError: (errors) => {
        toast.error(errors.recipe_id ?? errors.status ?? 'Nie udało się zmienić przepisu.')

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
