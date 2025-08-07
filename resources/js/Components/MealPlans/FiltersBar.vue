<template>
  <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between p-4 bg-gray-50 rounded-lg border">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
      <!-- Status Filter -->
      <div class="flex items-center gap-2">
        <label for="status-filter" class="text-sm font-medium text-gray-700 whitespace-nowrap">
          Status:
        </label>
        <StatusFilter
          id="status-filter"
          :model-value="value.status || ''"
          :options="statuses"
          @update:model-value="handleStatusChange"
        />
      </div>

      <!-- Per Page Select -->
      <div class="flex items-center gap-2">
        <label for="per-page-select" class="text-sm font-medium text-gray-700 whitespace-nowrap">
          Per page:
        </label>
        <PerPageSelect
          id="per-page-select"
          :model-value="value.perPage || 15"
          @update:model-value="handlePerPageChange"
        />
      </div>
    </div>

    <!-- Results Counter -->
    <div class="text-sm text-gray-600">
      <span v-if="resultsCount > 0">
        Showing {{ resultsCount }} {{ resultsCount === 1 ? 'result' : 'results' }}
      </span>
      <span v-else>
        No results found
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
// Local type definitions
type MealPlanStatus = 'pending' | 'processing' | 'done' | 'error'

interface StatusOption {
  value: MealPlanStatus
  label: string
}

interface FiltersState {
  status?: MealPlanStatus | ''
  sort?: 'start_date' | 'end_date' | 'status' | 'created_at'
  direction?: 'asc' | 'desc'
  perPage?: number
}
import StatusFilter from '@/Components/MealPlans/StatusFilter.vue'
import PerPageSelect from '@/Components/MealPlans/PerPageSelect.vue'

interface Props {
  value: FiltersState
  statuses: StatusOption[]
  resultsCount?: number
}

interface Emits {
  (e: 'update:value', value: FiltersState): void
}

const props = withDefaults(defineProps<Props>(), {
  resultsCount: 0
})

const emit = defineEmits<Emits>()

// Handle status filter change
const handleStatusChange = (status: string) => {
  emit('update:value', {
    ...props.value,
    status: status || undefined
  })
}

// Handle per page change
const handlePerPageChange = (perPage: number) => {
  emit('update:value', {
    ...props.value,
    perPage
  })
}
</script>
