<template>
  <button
    type="button"
    class="group inline-flex items-center justify-start w-full text-left font-semibold text-gray-900 dark:text-gray-100 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none focus:underline transition-colors"
    @click="handleSort"
  >
    <slot />
    <span class="ml-2 flex-none rounded text-gray-400 dark:text-gray-500">
      <ChevronUp
        v-if="currentSort.field === field && currentSort.direction === 'asc'"
        class="h-4 w-4 text-gray-900 dark:text-gray-100"
        aria-hidden="true"
      />
      <ChevronDown
        v-else-if="currentSort.field === field && currentSort.direction === 'desc'"
        class="h-4 w-4 text-gray-900 dark:text-gray-100"
        aria-hidden="true"
      />
      <ChevronsUpDown
        v-else
        class="h-4 w-4 group-hover:text-gray-500 dark:group-hover:text-gray-400"
        aria-hidden="true"
      />
    </span>
  </button>
</template>

<script setup lang="ts">
import { ChevronUp, ChevronDown, ChevronsUpDown } from 'lucide-vue-next'

// Local type definitions
type SortDirection = 'asc' | 'desc'

interface SortState {
  field: string
  direction: SortDirection
}

interface Props {
  field: string
  currentSort: SortState
}

interface Emits {
  (e: 'sort-change', value: { field: string, direction: SortDirection }): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// Handle sort click
const handleSort = () => {
  let newDirection: SortDirection = 'asc'

  if (props.currentSort.field === props.field) {
    // If already sorting by this field, toggle direction
    newDirection = props.currentSort.direction === 'asc' ? 'desc' : 'asc'
  }

  emit('sort-change', {
    field: props.field,
    direction: newDirection
  })
}
</script>
