<template>
  <th
    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-50 transition-colors"
    @click="handleClick"
    @keydown.enter="handleClick"
    @keydown.space.prevent="handleClick"
    tabindex="0"
    role="button"
    :aria-sort="ariaSortValue"
  >
    <div class="flex items-center gap-2 select-none">
      <span>{{ label }}</span>
      <div class="flex flex-col">
        <svg
          :class="[
            'w-3 h-3 transition-colors',
            isActiveAsc ? 'text-gray-900' : 'text-gray-400'
          ]"
          fill="currentColor"
          viewBox="0 0 20 20"
          aria-hidden="true"
        >
          <path
            fill-rule="evenodd"
            d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"
            clip-rule="evenodd"
          />
        </svg>
        <svg
          :class="[
            'w-3 h-3 transition-colors -mt-1',
            isActiveDesc ? 'text-gray-900' : 'text-gray-400'
          ]"
          fill="currentColor"
          viewBox="0 0 20 20"
          aria-hidden="true"
        >
          <path
            fill-rule="evenodd"
            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
            clip-rule="evenodd"
          />
        </svg>
      </div>
    </div>
  </th>
</template>

<script setup lang="ts">
import { computed } from 'vue'
// Local type definition
type SortDirection = 'asc' | 'desc'

interface Props {
  field: string
  activeField?: string
  direction?: SortDirection
  label: string
}

interface Emits {
  (e: 'change', value: { field: string, direction: SortDirection }): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// Check if this field is currently active
const isActive = computed(() => props.activeField === props.field)
const isActiveAsc = computed(() => isActive.value && props.direction === 'asc')
const isActiveDesc = computed(() => isActive.value && props.direction === 'desc')

// ARIA sort value for accessibility
const ariaSortValue = computed(() => {
  if (!isActive.value) return 'none'
  return props.direction === 'asc' ? 'ascending' : 'descending'
})

// Handle click to toggle sort direction
const handleClick = () => {
  let newDirection: SortDirection = 'asc'

  if (isActive.value) {
    // If already active, toggle direction
    newDirection = props.direction === 'asc' ? 'desc' : 'asc'
  }

  emit('change', {
    field: props.field,
    direction: newDirection
  })
}
</script>
