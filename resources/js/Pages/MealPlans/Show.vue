<template>
  <Head :title="`Jadłospis ${dateRange}`" />

  <AuthenticatedLayout>
    <div class="flex flex-col gap-6">
      <PageHeader :title="formatDateRange(mealPlan.start_date, mealPlan.end_date)">
        <template #breadcrumbs>
          <Link
            :href="route('meal-plans.index')"
            class="inline-flex items-center gap-1.5 rounded-sm text-label text-muted-foreground hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
          >
            <ArrowLeft class="size-4" aria-hidden="true" />
            Jadłospisy
          </Link>
        </template>
        <template #actions>
          <StatusTag :value="mealPlan.status as any" />
          <Button v-if="isDraftWithMeals" variant="outline" @click="showRedrawDialog = true">
            <Shuffle class="size-4" aria-hidden="true" />
            Losuj ponownie
          </Button>
          <Button v-if="isDraftWithMeals" :disabled="approving" @click="handleApprove">
            <Loader2 v-if="approving" class="size-4 animate-spin motion-reduce:animate-none" aria-hidden="true" />
            <Check v-else class="size-4" aria-hidden="true" />
            Zatwierdź
          </Button>
          <Button v-if="canReturnToDraft" variant="ghost" @click="showReturnToDraftDialog = true">
            <Undo2 class="size-4" aria-hidden="true" />
            Wróć do szkicu
          </Button>
          <PdfDownloadMenu
            v-if="mealPlan.links.pdf && mealPlan.links.pdf_with_recipes"
            :links="{ pdf: mealPlan.links.pdf, pdf_with_recipes: mealPlan.links.pdf_with_recipes }"
          />
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
          <Button @click="showReturnToDraftDialog = true">
            <Undo2 class="size-4" aria-hidden="true" />
            Wróć do szkicu
          </Button>
        </div>
      </Alert>

      <Card v-else-if="!mealPlan.meals || mealPlan.meals.length === 0">
        <EmptyState class="py-12" :icon="CalendarDays" title="Brak posiłków" description="Ten jadłospis nie ma jeszcze posiłków.">
          <Button v-if="mealPlan.status === 'draft'" @click="showRedrawDialog = true">
            <Shuffle class="size-4" aria-hidden="true" />
            Losuj ponownie
          </Button>
          <Button v-else-if="mealPlan.status === 'done'" @click="showReturnToDraftDialog = true">
            <Undo2 class="size-4" aria-hidden="true" />
            Wróć do szkicu
          </Button>
        </EmptyState>
      </Card>

      <template v-else>
        <Alert v-if="mealPlan.status === 'draft'" variant="info" role="status">
          <Info aria-hidden="true" />
          <AlertDescription>
            To szkic jadłospisu. Możesz podmienić przepisy; ilości i listę zakupów policzymy po zatwierdzeniu.
          </AlertDescription>
        </Alert>
        <Alert v-if="mealPlan.status === 'draft' && shortCategories?.length" variant="info" role="status" data-testid="short-categories-notice">
          <Info aria-hidden="true" />
          <AlertDescription>
            <p>
              Za mało przepisów, żeby wypełnić cały tydzień:
              {{ shortCategories.map((short) => `${short.label} (${short.recipes} z 7)`).join(', ') }}.
            </p>
            <p>Puste posiłki możesz uzupełnić przepisem z innej pory dnia albo dodać nowy przepis.</p>
            <Link
              :href="route('recipes.create')"
              class="mt-2 inline-block rounded-sm font-medium underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
              Dodaj przepis
            </Link>
          </AlertDescription>
        </Alert>
        <Alert v-if="mealPlan.scaling?.notices.length" variant="warning" role="status" data-testid="scaling-notice">
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
            <MealPlanGrid
              :start-date="mealPlan.start_date"
              :meals="mealPlan.meals"
              :editable="mealPlan.status === 'draft'"
              @swap="openSwap"
            />
          </div>
          <div v-if="shoppingList || mealPlan.status === 'draft'" class="xl:w-75 xl:shrink-0">
            <ShoppingList v-if="shoppingList" :list="shoppingList" />
            <ShoppingList v-else pending />
          </div>
        </div>
      </template>

    </div>

    <ConfirmDialog
      v-model:open="showRedrawDialog"
      title="Losuj ponownie"
      description="Wylosować tydzień od nowa? Twoje zmiany przepisów, powtórki obiadów i kolacje na śniadanie przepadną."
      confirm-label="Losuj ponownie"
      :processing="redrawing"
      @confirm="handleRedraw"
    />

    <ConfirmDialog
      v-model:open="showReturnToDraftDialog"
      title="Wróć do szkicu"
      description="Wrócić do szkicu? Przepisy zostaną, a ilości i listę zakupów policzymy ponownie po zatwierdzeniu."
      confirm-label="Wróć do szkicu"
      :processing="returningToDraft"
      @confirm="handleReturnToDraft"
    />

    <RecipeSwapDialog
      v-if="recipeOptions"
      v-model:open="showSwapDialog"
      :meal-plan-id="mealPlan.id"
      :meal="swapMeal"
      :groups="swapGroups"
      :week-meals="mealPlan.meals ?? []"
    />
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Head, Link, router, usePoll } from '@inertiajs/vue3'
import { AlertCircle, ArrowLeft, CalendarDays, Check, Info, Loader2, Shuffle, TriangleAlert, Undo2 } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert'
import { Button } from '@/Components/ui/button'
import { Card } from '@/Components/ui/card'
import PageHeader from '@/Components/Common/PageHeader.vue'
import EmptyState from '@/Components/Common/EmptyState.vue'
import StatusTag from '@/Components/MealPlans/StatusTag.vue'
import PdfDownloadMenu from '@/Components/MealPlans/PdfDownloadMenu.vue'
import ActionDropdown from '@/Components/MealPlans/ActionDropdown.vue'
import ConfirmDialog from '@/Components/Common/ConfirmDialog.vue'
import MealPlanGrid from '@/Components/MealPlans/MealPlanGrid.vue'
import RecipeSwapDialog from '@/Components/MealPlans/RecipeSwapDialog.vue'
import { formatDateRange } from '@/lib/format'
import { MEAL_CATEGORIES, mealCategoryLabel } from '@/lib/mealCategories'
import type { RecipeOptionGroup, RecipeOptions, ShortCategory } from '@/types/meal-plan'
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
  feeds_meal_id?: number | null
  from_meal_id?: number | null
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
    pdf_with_recipes: string | null
  }
}

interface Props {
  mealPlan: MealPlan
  shoppingList: ShoppingListPayload | null
  recipeOptions?: RecipeOptions | null
  shortCategories?: ShortCategory[] | null
}

const props = defineProps<Props>()

// Above the job's own budget (up to ~61 s of AI attempts, killed at 80 s), so a healthy but slow run never looks like a dead worker.
const SLOW_AFTER_MS = 75_000

const showRedrawDialog = ref(false)
const showReturnToDraftDialog = ref(false)
const showSwapDialog = ref(false)
const swapMeal = ref<Meal | null>(null)
const redrawing = ref(false)
const returningToDraft = ref(false)
const approving = ref(false)
const isSlow = ref(false)

const isGenerating = computed(() => ['pending', 'processing'].includes(props.mealPlan.status))
const hasMeals = computed(() => (props.mealPlan.meals?.length ?? 0) > 0)
const isDraftWithMeals = computed(() => props.mealPlan.status === 'draft' && hasMeals.value)
const canReturnToDraft = computed(() => props.mealPlan.status === 'done' && hasMeals.value)
const swapGroups = computed<RecipeOptionGroup[]>(() => {
  const slotCategory = swapMeal.value?.meal_category as string | undefined
  const ordered = slotCategory
    ? [slotCategory, ...MEAL_CATEGORIES.filter((category) => category !== slotCategory)]
    : MEAL_CATEGORIES

  return ordered
    .map((category) => ({
      category,
      label: mealCategoryLabel(category),
      options: props.recipeOptions?.[category as keyof RecipeOptions] ?? [],
    }))
    .filter((group) => group.options.length > 0)
})
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

const openSwap = (meal: Meal) => {
  swapMeal.value = meal
  showSwapDialog.value = true
}

const handleApprove = () => {
  approving.value = true

  router.post(
    route('meal-plans.approve', { meal_plan: props.mealPlan.id }),
    {},
    {
      preserveScroll: true,
      onError: (errors) => {
        toast.error(errors.rate_limit ?? errors.status ?? errors.meals ?? 'Nie udało się zatwierdzić jadłospisu.')
      },
      onFinish: () => {
        approving.value = false
      },
    }
  )
}

const handleRedraw = () => {
  redrawing.value = true

  router.put(
    route('meal-plans.update', { meal_plan: props.mealPlan.id }),
    { regenerate: true },
    {
      preserveScroll: true,
      onError: (errors) => {
        toast.error(errors.status ?? errors.recipes ?? errors.regenerate ?? 'Nie udało się wylosować jadłospisu od nowa.')
      },
      onFinish: () => {
        redrawing.value = false
        showRedrawDialog.value = false
      },
    }
  )
}

const handleReturnToDraft = () => {
  returningToDraft.value = true

  router.post(
    route('meal-plans.draft', { meal_plan: props.mealPlan.id }),
    {},
    {
      preserveScroll: true,
      onError: (errors) => {
        toast.error(errors.status ?? 'Nie udało się wrócić do szkicu.')
      },
      onFinish: () => {
        returningToDraft.value = false
        showReturnToDraftDialog.value = false
      },
    }
  )
}
</script>
