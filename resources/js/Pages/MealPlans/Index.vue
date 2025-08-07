<template>
  <Head title="Meal Plans" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Meal Plans
          </h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            View and manage your generated meal plans
            <span v-if="mealPlans.meta.total > 0" class="ml-2">
              ({{ mealPlans.meta.total }} {{ mealPlans.meta.total === 1 ? 'plan' : 'plans' }})
            </span>
          </p>
        </div>
      </div>
    </template>

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
        :links="mealPlans.links"
        :meta="mealPlans.meta"
      />
    </div>
  </AuthenticatedLayout>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
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
    'filter.status'?: MealPlanStatus
    sort?: FiltersState['sort']
    direction?: SortDirection
    perPage?: number
  }
  statuses: StatusOption[]
}
import FiltersBar from '@/Components/MealPlans/FiltersBar.vue'
import MealPlanTable from '@/Components/MealPlans/MealPlanTable.vue'
import ServerPagination from '@/Components/MealPlans/ServerPagination.vue'

// Props from Inertia
const props = defineProps<MealPlanCollectionProps>()

// Local filters state synchronized with query params
const localFilters = ref<FiltersState>({
  status: props.filters['filter.status'] || '',
  sort: props.filters.sort || 'created_at',
  direction: props.filters.direction || 'desc',
  perPage: props.filters.perPage || 15
})

// Handle filters change
const handleFiltersChange = (newFilters: FiltersState) => {
  localFilters.value = { ...localFilters.value, ...newFilters }

  // Convert filters to query params format
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

  // Navigate with new filters
  router.get(route('meal-plans.index'), queryParams, {
    preserveScroll: true,
    replace: true
  })
}

// Handle sorting change
const handleSortChange = ({ field, direction }: { field: string, direction: SortDirection }) => {
  handleFiltersChange({
    sort: field as FiltersState['sort'],
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
      if (!props.filters['filter.status'] && parsed.status) {
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
