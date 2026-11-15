<template>
  <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
    <div class="flex flex-col gap-2">
      <Label for="status-filter">Status</Label>
      <StatusFilter
        id="status-filter"
        :model-value="value.status || ''"
        :options="statuses"
        @update:model-value="handleStatusChange"
      />
    </div>

    <div class="flex flex-col gap-2">
      <Label for="per-page-select">Na stronie</Label>
      <PerPageSelect
        id="per-page-select"
        :model-value="value.perPage || 15"
        @update:model-value="handlePerPageChange"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
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
import { Label } from '@/Components/ui/label'
import StatusFilter from '@/Components/MealPlans/StatusFilter.vue'
import PerPageSelect from '@/Components/MealPlans/PerPageSelect.vue'

interface Props {
  value: FiltersState
  statuses: StatusOption[]
}

interface Emits {
  (e: 'update:value', value: FiltersState): void
}

const props = defineProps<Props>()

const emit = defineEmits<Emits>()

// Handle status filter change
const handleStatusChange = (status: string) => {
  emit('update:value', {
    ...props.value,
    status: (status || '') as FiltersState['status']
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
