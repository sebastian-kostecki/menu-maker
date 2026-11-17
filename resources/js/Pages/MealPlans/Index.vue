<template>
  <Head title="Jadłospisy" />

  <AuthenticatedLayout>
    <div class="mx-auto flex max-w-6xl flex-col gap-6">
      <PageHeader title="Jadłospisy" :description="description">
        <template #actions>
          <GenerateMealPlanDialog />
        </template>
      </PageHeader>

      <div class="flex flex-wrap items-center justify-between gap-3">
        <StatusFilterPills
          :model-value="currentFilters.status || ''"
          :statuses="statuses"
          @update:model-value="handleStatusChange"
        />
        <SortMenu
          :sort="currentFilters.sort ?? 'created_at'"
          :direction="currentFilters.direction ?? 'desc'"
          @sort-change="handleSortChange"
        />
      </div>

      <ul v-if="mealPlans.data.length > 0" aria-label="Jadłospisy" class="flex flex-col gap-3.5">
        <li v-for="item in mealPlans.data" :key="item.id">
          <MealPlanTile :item="item" />
        </li>
      </ul>
      <Card v-else>
        <EmptyState
          v-if="isFiltered"
          class="py-12"
          :icon="CalendarDays"
          title="Brak jadłospisów o wybranym statusie"
        >
          <Button variant="outline" @click="handleClearFilters">
            Wyczyść filtry
          </Button>
        </EmptyState>
        <EmptyState
          v-else
          class="py-12"
          :icon="CalendarDays"
          title="Nie masz jeszcze jadłospisów"
          description="Wygeneruj tydzień 7 dni × 3 posiłki z własnych przepisów."
        >
          <GenerateMealPlanDialog label="Wygeneruj pierwszy jadłospis" />
        </EmptyState>
      </Card>

      <ServerPagination
        :meta="mealPlans.meta"
        @page-change="handlePageChange"
      />
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { CalendarDays } from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'
import { Card } from '@/Components/ui/card'
import EmptyState from '@/Components/Common/EmptyState.vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import GenerateMealPlanDialog from '@/Components/MealPlans/GenerateMealPlanDialog.vue'
import PageHeader from '@/Components/Common/PageHeader.vue'
import { pluralize } from '@/lib/format'
import type { MealPlanStatus } from '@/types/meal-plan'
// Types for MealPlan components and API responses

interface StatusOption {
  value: MealPlanStatus
  label: string
}

interface MealPlanListItem {
  id: number
  start_date: string
  end_date: string
  status: MealPlanStatus
  created_at: string
  updated_at: string
  meals_count: number
  logs_count: number
  links: {
    self: string
    pdf: string | null
    pdf_with_recipes: string | null
  }
}

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

interface PaginationLinks {
  first: string | null
  last: string | null
  prev: string | null
  next: string | null
}

interface MealPlanCollection {
  data: MealPlanListItem[]
  meta: PaginationMeta
  links: PaginationLinks
}

type SortDirection = 'asc' | 'desc'

interface FiltersState {
  status?: MealPlanStatus | ''
  sort?: 'start_date' | 'end_date' | 'status' | 'created_at'
  direction?: SortDirection
  perPage?: number
}

interface MealPlanCollectionProps {
  mealPlans: MealPlanCollection
  filters: {
    filter?: { status?: MealPlanStatus }
    sort?: FiltersState['sort']
    direction?: SortDirection
    perPage?: number | string
  }
  statuses: StatusOption[]
}
import MealPlanTile from '@/Components/MealPlans/MealPlanTile.vue'
import SortMenu from '@/Components/MealPlans/SortMenu.vue'
import StatusFilterPills from '@/Components/MealPlans/StatusFilterPills.vue'
import ServerPagination from '@/Components/Common/ServerPagination.vue'

// Props from Inertia
const props = defineProps<MealPlanCollectionProps>()

// Filter state is derived from the URL (props.filters echoes the query as raw strings); nothing is stored locally.
const currentFilters = computed<FiltersState>(() => ({
  status: props.filters.filter?.status || '',
  sort: props.filters.sort || 'created_at',
  direction: props.filters.direction === 'asc' ? 'asc' : 'desc',
  perPage: Number(props.filters.perPage) || 15
}))

const description = computed(() => {
  const total = props.mealPlans.meta.total
  const base = 'Przeglądaj wygenerowane jadłospisy'

  if (total <= 0) {
    return base
  }

  return `${base} · ${total} ${pluralize(total, { one: 'jadłospis', few: 'jadłospisy', many: 'jadłospisów' })}`
})

// A status filter, or a page beyond the first, with no rows is not the first-run state.
const isFiltered = computed(() =>
  Boolean(props.filters.filter?.status) || props.mealPlans.meta.current_page > 1
)

// Serialise the current filters merged with a change; an empty status is omitted
const buildQuery = (changes: FiltersState = {}): Record<string, any> => {
  const merged = { ...currentFilters.value, ...changes }
  const queryParams: Record<string, any> = {}

  if (merged.status) {
    queryParams['filter[status]'] = merged.status
  }
  if (merged.sort) {
    queryParams.sort = merged.sort
  }
  if (merged.direction) {
    queryParams.direction = merged.direction
  }
  if (merged.perPage) {
    queryParams.perPage = merged.perPage
  }

  return queryParams
}

// Handle filters change (goes back to page 1)
const handleFiltersChange = (newFilters: FiltersState) => {
  router.get(route('meal-plans.index'), buildQuery(newFilters), {
    preserveScroll: true,
    replace: true
  })
}

// Handle a status pill ('' = all)
const handleStatusChange = (status: string) => {
  handleFiltersChange({ status: status as FiltersState['status'] })
}

// Handle page change: current query plus the page
const handlePageChange = (page: number) => {
  router.get(route('meal-plans.index'), { ...buildQuery(), page }, {
    preserveScroll: true,
    replace: true
  })
}

// Handle sorting change
const handleSortChange = ({ column, direction }: { column: string, direction: SortDirection }) => {
  handleFiltersChange({
    sort: column as FiltersState['sort'],
    direction
  })
}

// Reset to the bare list
const handleClearFilters = () => {
  router.get(route('meal-plans.index'), {}, {
    preserveScroll: true,
    replace: true
  })
}
</script>
