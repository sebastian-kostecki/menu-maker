<template>
  <Head title="Jadłospisy" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Jadłospisy
          </h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Przeglądaj i zarządzaj wygenerowanymi jadłospisami
            <span v-if="mealPlans.meta.total > 0" class="ml-2">
              ({{ mealPlans.meta.total }} {{ mealPlans.meta.total === 1 ? 'jadłospis' : mealPlans.meta.total < 5 ? 'jadłospisy' : 'jadłospisów' }})
            </span>
          </p>
        </div>
        <div class="flex items-center gap-2">
          <GenerateMealPlanDialog />
        </div>
      </div>
    </template>

    <div class="mx-auto max-w-7xl">
      <div class="overflow-hidden bg-white shadow-xs sm:rounded-lg dark:bg-gray-800">
        <div class="p-6">
          <div class="space-y-6">
            <!-- Filters Bar -->
            <FiltersBar
              v-model:value="localFilters"
              :statuses="statuses"
              :results-count="mealPlans.meta.total"
              @update:value="handleFiltersChange"
            />

            <!-- Meal Plan Table -->
            <MealPlanTable
              :items="mealPlans.data"
              :sort="localFilters.sort"
              :direction="localFilters.direction"
              @sort-change="handleSortChange"
            />

            <!-- Pagination -->
            <ServerPagination
              :meta="mealPlans.meta"
              @page-change="handlePageChange"
            />
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import GenerateMealPlanDialog from '@/Components/MealPlans/GenerateMealPlanDialog.vue'
// Types for MealPlan components and API responses
type MealPlanStatus = 'pending' | 'processing' | 'done' | 'error'

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
    perPage?: number
  }
  statuses: StatusOption[]
}
import FiltersBar from '@/Components/MealPlans/FiltersBar.vue'
import MealPlanTable from '@/Components/MealPlans/MealPlanTable.vue'
import ServerPagination from '@/Components/Common/ServerPagination.vue'

// Props from Inertia
const props = defineProps<MealPlanCollectionProps>()

// Local filters state synchronized with query params
const localFilters = ref<FiltersState>({
  status: props.filters.filter?.status || '',
  sort: props.filters.sort || 'created_at',
  direction: props.filters.direction || 'desc',
  perPage: props.filters.perPage || 15
})

// Build query params from the local filters
const buildQuery = (): Record<string, any> => {
  const queryParams: Record<string, any> = {}

  if (localFilters.value.status) {
    queryParams['filter[status]'] = localFilters.value.status
  }

  if (localFilters.value.sort) {
    queryParams.sort = localFilters.value.sort
  }

  if (localFilters.value.direction) {
    queryParams.direction = localFilters.value.direction
  }

  if (localFilters.value.perPage) {
    queryParams.perPage = localFilters.value.perPage
  }

  return queryParams
}

// Handle filters change (goes back to page 1)
const handleFiltersChange = (newFilters: FiltersState) => {
  localFilters.value = { ...localFilters.value, ...newFilters }

  router.get(route('meal-plans.index'), buildQuery(), {
    preserveScroll: true,
    replace: true
  })
}

// Handle page change: continue the list the server returned (props.filters echoes the URL),
// not localFilters, which may hold stale values restored from localStorage.
const handlePageChange = (page: number) => {
  const query: Record<string, any> = { page }

  if (props.filters.filter?.status) {
    query['filter[status]'] = props.filters.filter.status
  }
  if (props.filters.sort) {
    query.sort = props.filters.sort
  }
  if (props.filters.direction) {
    query.direction = props.filters.direction
  }
  if (props.filters.perPage) {
    query.perPage = props.filters.perPage
  }

  router.get(route('meal-plans.index'), query, {
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

// Save filters to localStorage
watch(localFilters, (newFilters) => {
  try {
    localStorage.setItem('mm.mealPlans.filters', JSON.stringify(newFilters))
  } catch (error) {
    console.warn('Failed to save filters to localStorage:', error)
  }
}, { deep: true })

// Load filters from localStorage on mount
const loadFiltersFromStorage = () => {
  try {
    const savedFilters = localStorage.getItem('mm.mealPlans.filters')
    if (savedFilters) {
      const parsed = JSON.parse(savedFilters)
      // Only use saved filters if not overridden by URL params
      if (!props.filters.filter?.status && parsed.status) {
        localFilters.value.status = parsed.status
      }
      if (!props.filters.perPage && parsed.perPage) {
        localFilters.value.perPage = parsed.perPage
      }
    }
  } catch (error) {
    console.warn('Failed to load filters from localStorage:', error)
  }
}

// Load saved filters on component mount
loadFiltersFromStorage()
</script>
