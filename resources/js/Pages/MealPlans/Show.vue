<template>
  <Head :title="`Jadłospis ${dateRange}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Jadłospis
          </h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ dateRange }}
          </p>
        </div>
        <div class="flex items-center gap-3">
          <StatusTag :value="mealPlan.status as any" />
          <Button v-if="mealPlan.links.pdf" variant="outline" as-child>
            <a :href="mealPlan.links.pdf" target="_blank" rel="noopener">
              <Download class="mr-2 h-4 w-4" />
              Pobierz PDF
            </a>
          </Button>
          <Button v-if="canRegenerate" variant="outline" @click="showRegenerateDialog = true">
            <RefreshCw class="mr-2 h-4 w-4" />
            Regeneruj
          </Button>
          <ActionDropdown :item="mealPlan" />
        </div>
      </div>
    </template>

    <div class="space-y-6">
      <template v-if="isGenerating">
        <p class="text-sm text-gray-600 dark:text-gray-400" role="status">
          Generujemy jadłospis…
        </p>
        <p v-if="isSlow" class="rounded-md border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800">
          Generowanie trwa dłużej niż zwykle. Sprawdź, czy działa worker kolejki, albo odśwież stronę.
        </p>
        <MealPlanGrid :start-date="mealPlan.start_date" skeleton />
      </template>

      <div v-else-if="mealPlan.status === 'error'" class="rounded-lg border border-red-200 bg-red-50 p-6 text-center dark:border-red-900 dark:bg-red-900/20">
        <p class="text-sm text-red-700 dark:text-red-300">
          {{ errorMessage }}
        </p>
        <Button class="mt-4" @click="showRegenerateDialog = true">
          <RefreshCw class="mr-2 h-4 w-4" />
          Regeneruj
        </Button>
      </div>

      <div v-else-if="!mealPlan.meals || mealPlan.meals.length === 0" class="rounded-lg border border-gray-200 bg-white p-6 text-center dark:border-gray-700 dark:bg-gray-800">
        <ChefHat class="mx-auto h-12 w-12 text-gray-400" />
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Ten jadłospis nie ma jeszcze posiłków.
        </p>
        <Button class="mt-4" @click="showRegenerateDialog = true">
          <RefreshCw class="mr-2 h-4 w-4" />
          Regeneruj
        </Button>
      </div>

      <template v-else>
        <div
          v-if="mealPlan.scaling?.notices.length"
          class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-200"
          data-testid="scaling-notice"
        >
          <p v-for="notice in mealPlan.scaling.notices" :key="notice">
            {{ notice }}
          </p>
          <Link
            v-if="mealPlan.scaling.status === 'as_written'"
            :href="route('family-members.index')"
            class="mt-2 inline-block font-medium underline"
          >
            Przejdź do profilu rodziny
          </Link>
        </div>
        <MealPlanGrid :start-date="mealPlan.start_date" :meals="mealPlan.meals" />
        <ShoppingList v-if="shoppingList" :list="shoppingList" />
      </template>

      <div class="flex justify-start">
        <Button variant="outline" as-child>
          <Link :href="route('meal-plans.index')">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Wróć do jadłospisów
          </Link>
        </Button>
      </div>
    </div>

    <ConfirmDialog
      v-if="showRegenerateDialog"
      title="Regeneruj jadłospis"
      description="Obecne posiłki zostaną zastąpione nowym losowaniem przepisów. Czy chcesz kontynuować?"
      confirm-text="Regeneruj"
      @confirm="handleRegenerate"
      @cancel="showRegenerateDialog = false"
    />
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Head, Link, router, usePoll } from '@inertiajs/vue3'
import { ArrowLeft, ChefHat, Download, RefreshCw } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import StatusTag from '@/Components/MealPlans/StatusTag.vue'
import ActionDropdown from '@/Components/MealPlans/ActionDropdown.vue'
import ConfirmDialog from '@/Components/MealPlans/ConfirmDialog.vue'
import MealPlanGrid from '@/Components/MealPlans/MealPlanGrid.vue'
import ShoppingList, { type ShoppingListPayload } from '@/Components/MealPlans/ShoppingList.vue'

interface Recipe {
  id: number
  name: string
  calories: number | string
  servings: number
  deleted: boolean
}

interface MealIngredient {
  ingredient_id: number
  name: string
  display: string
}

interface Meal {
  id: number
  meal_date: string
  meal_category: string
  recipe?: Recipe
  ingredients?: MealIngredient[]
}

interface MealPlan {
  id: number
  start_date: string
  end_date: string
  status: string
  generation_meta?: { error_message?: string } | null
  created_at: string
  updated_at: string
  meals_count: number
  logs_count: number
  meals?: Meal[]
  scaling?: { status: string | null; notices: string[] }
  links: {
    self: string
    pdf: string | null
  }
}

interface Props {
  mealPlan: MealPlan
  shoppingList: ShoppingListPayload | null
}

const props = defineProps<Props>()

// Above the job's own budget (up to ~61 s of AI attempts, killed at 80 s), so a healthy but slow run never looks like a dead worker.
const SLOW_AFTER_MS = 75_000

const showRegenerateDialog = ref(false)
const isSlow = ref(false)

const isGenerating = computed(() => ['pending', 'processing'].includes(props.mealPlan.status))
const canRegenerate = computed(() => ['done', 'error'].includes(props.mealPlan.status))
const errorMessage = computed(
  () => props.mealPlan.generation_meta?.error_message ?? 'Generowanie jadłospisu nie powiodło się.'
)

const formatDate = (isoDate: string): string => {
  const [year, month, day] = isoDate.split('-')

  return `${day}.${month}.${year}`
}

const dateRange = computed(() => `${formatDate(props.mealPlan.start_date)} – ${formatDate(props.mealPlan.end_date)}`)

const { start, stop } = usePoll(3000, { only: ['mealPlan', 'shoppingList'] }, { autoStart: false })

let slowTimer: ReturnType<typeof setTimeout> | null = null

const clearSlowTimer = () => {
  if (slowTimer !== null) {
    clearTimeout(slowTimer)
    slowTimer = null
  }
}

watch(
  isGenerating,
  (generating) => {
    if (generating) {
      start()
      if (slowTimer === null) {
        slowTimer = setTimeout(() => {
          isSlow.value = true
        }, SLOW_AFTER_MS)
      }
    } else {
      stop()
      clearSlowTimer()
      isSlow.value = false
    }
  },
  { immediate: true }
)

onBeforeUnmount(() => {
  stop()
  clearSlowTimer()
})

const handleRegenerate = () => {
  showRegenerateDialog.value = false

  router.put(
    route('meal-plans.update', { meal_plan: props.mealPlan.id }),
    { regenerate: true },
    {
      preserveScroll: true,
      onError: (errors) => {
        toast.error(errors.status ?? errors.recipes ?? errors.regenerate ?? 'Nie udało się zregenerować jadłospisu.')
      },
    }
  )
}
</script>
