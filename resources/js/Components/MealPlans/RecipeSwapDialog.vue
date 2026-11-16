<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>Zmień przepis</DialogTitle>
        <DialogDescription>{{ description }}</DialogDescription>
      </DialogHeader>

      <Combobox :open="true" :model-value="undefined" @update:model-value="handleSelect">
        <ComboboxAnchor class="w-full">
          <ComboboxInput aria-label="Szukaj przepisu" placeholder="Szukaj przepisu" />
        </ComboboxAnchor>
        <ComboboxContent position="inline" class="mt-2 max-h-72 overflow-y-auto rounded-md border bg-popover text-popover-foreground">
          <ComboboxViewport class="p-1">
            <ComboboxEmpty class="p-2 text-sm text-muted-foreground">Brak przepisów</ComboboxEmpty>
            <ComboboxItem
              v-for="option in items"
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
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { ComboboxContent, ComboboxViewport } from 'reka-ui'
import { toast } from 'vue-sonner'
import {
  Combobox,
  ComboboxAnchor,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
} from '@/Components/ui/combobox'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/dialog'
import { formatNumber, formatWeekdayShort } from '@/lib/format'
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
}

const props = defineProps<Props>()
const emit = defineEmits<{ (e: 'update:open', value: boolean): void }>()

const saving = ref(false)

const currentRecipeId = computed(() => props.meal?.recipe?.id ?? null)

const description = computed(() => {
  if (!props.meal) {
    return ''
  }

  const [, month, day] = props.meal.meal_date.split('-')

  return `${formatWeekdayShort(props.meal.meal_date)} ${day}.${month} – ${mealCategoryLabel(props.meal.meal_category)}`
})

const items = computed(() =>
  props.options.map((option) => {
    const days = props.weekMeals
      .filter((other) => other.recipe?.id === option.id && other.id !== props.meal?.id)
      .map((other) => formatWeekdayShort(other.meal_date))

    return { ...option, usedOn: [...new Set(days)].join(', ') }
  })
)

const handleSelect = (option: unknown) => {
  const picked = option as RecipeOption | undefined

  if (!picked || !props.meal || saving.value) {
    return
  }

  saving.value = true

  router.patch(
    route('meal-plans.meals.update', { meal_plan: props.mealPlanId, meal: props.meal.id }),
    { recipe_id: picked.id },
    {
      preserveScroll: true,
      only: ['mealPlan'],
      onSuccess: () => {
        emit('update:open', false)
      },
      onError: (errors) => {
        toast.error(errors.recipe_id ?? errors.status ?? 'Nie udało się zmienić przepisu.')
      },
      onFinish: () => {
        saving.value = false
      },
    }
  )
}
</script>
