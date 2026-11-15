<template>
  <Head :title="`Jadłospis ${dateRange}`" />

  <AuthenticatedLayout>
    <div class="flex flex-col gap-6">
      <PageHeader title="Jadłospis" :description="dateRange">
        <template #actions>
          <StatusTag :value="mealPlan.status as any" />
          <Button v-if="mealPlan.links.pdf" variant="outline" as-child>
            <a :href="mealPlan.links.pdf" target="_blank" rel="noopener">
              <FileDown class="size-4" aria-hidden="true" />
              Pobierz PDF
            </a>
          </Button>
          <Button v-if="canRegenerate" variant="outline" @click="showRegenerateDialog = true">
            <RefreshCw class="size-4" aria-hidden="true" />
            Regeneruj
          </Button>
          <ActionDropdown :item="mealPlan" />
        </template>
      </PageHeader>

      <template v-if="isGenerating">
        <p class="flex items-center gap-2 text-sm text-muted-foreground" role="status">
          <Loader2 class="size-4 animate-spin motion-reduce:animate-none" aria-hidden="true" />
          Generujemy jadłospis…
        </p>
        <Alert v-if="isSlow" variant="warning">
          <TriangleAlert aria-hidden="true" />
          <AlertDescription>
            Generowanie trwa dłużej niż zwykle. Sprawdź, czy działa worker kolejki, albo odśwież stronę.
          </AlertDescription>
        </Alert>
        <MealPlanGrid :start-date="mealPlan.start_date" skeleton />
      </template>

      <Alert v-else-if="mealPlan.status === 'error'" variant="destructive">
        <AlertCircle aria-hidden="true" />
        <AlertTitle>Generowanie nie powiodło się</AlertTitle>
        <AlertDescription>{{ errorMessage }}</AlertDescription>
        <div class="col-start-2 mt-3">
          <Button @click="showRegenerateDialog = true">
            <RefreshCw class="size-4" aria-hidden="true" />
            Regeneruj
          </Button>
        </div>
      </Alert>

      <EmptyState
        v-else-if="!mealPlan.meals || mealPlan.meals.length === 0"
        :icon="CalendarDays"
        title="Brak posiłków"
        description="Ten jadłospis nie ma jeszcze posiłków."
      >
        <template #action>
          <Button @click="showRegenerateDialog = true">
            <RefreshCw class="size-4" aria-hidden="true" />
            Regeneruj
          </Button>
        </template>
      </EmptyState>

      <template v-else>
        <Alert v-if="mealPlan.scaling?.notices.length" variant="warning" data-testid="scaling-notice">
          <TriangleAlert aria-hidden="true" />
          <AlertDescription>
            <p v-for="notice in mealPlan.scaling.notices" :key="notice">
              {{ notice }}
            </p>
            <Link
              v-if="mealPlan.scaling.status === 'as_written'"
              :href="route('family-members.index')"
              class="mt-2 inline-block rounded-sm font-medium underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
              Przejdź do profilu rodziny
            </Link>
          </AlertDescription>
        </Alert>
        <div class="flex flex-col gap-6 xl:flex-row xl:items-start">
          <div class="min-w-0 xl:flex-1">
            <MealPlanGrid :start-date="mealPlan.start_date" :meals="mealPlan.meals" />
          </div>
          <div v-if="shoppingList" class="xl:w-72 xl:shrink-0">
            <ShoppingList :list="shoppingList" />
          </div>
        </div>
      </template>

      <div class="flex justify-start">
        <Button variant="outline" as-child>
          <Link :href="route('meal-plans.index')">
            <ArrowLeft class="size-4" aria-hidden="true" />
            Wróć do jadłospisów
          </Link>
        </Button>
      </div>
    </div>

    <ConfirmDialog
      v-model:open="showRegenerateDialog"
      title="Regeneruj jadłospis"
      description="Obecne posiłki zostaną zastąpione nowym losowaniem przepisów. Czy chcesz kontynuować?"
      confirm-label="Regeneruj"
      :processing="regenerating"
      @confirm="handleRegenerate"
    />
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Head, Link, router, usePoll } from '@inertiajs/vue3'
import { AlertCircle, ArrowLeft, CalendarDays, FileDown, Loader2, RefreshCw, TriangleAlert } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert'
import { Button } from '@/Components/ui/button'
import PageHeader from '@/Components/Common/PageHeader.vue'
import EmptyState from '@/Components/Common/EmptyState.vue'
import StatusTag from '@/Components/MealPlans/StatusTag.vue'
import ActionDropdown from '@/Components/MealPlans/ActionDropdown.vue'
import ConfirmDialog from '@/Components/Common/ConfirmDialog.vue'
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
const regenerating = ref(false)
const isSlow = ref(false)

const isGenerating = computed(() => ['pending', 'processing'].includes(props.mealPlan.status))
const canRegenerate = computed(() => props.mealPlan.status === 'done' && (props.mealPlan.meals?.length ?? 0) > 0)
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
  regenerating.value = true

  router.put(
    route('meal-plans.update', { meal_plan: props.mealPlan.id }),
    { regenerate: true },
    {
      preserveScroll: true,
      onError: (errors) => {
        toast.error(errors.status ?? errors.recipes ?? errors.regenerate ?? 'Nie udało się zregenerować jadłospisu.')
      },
      onFinish: () => {
        regenerating.value = false
        showRegenerateDialog.value = false
      },
    }
  )
}
</script>
