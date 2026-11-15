<template>
  <TableHead :aria-sort="ariaSort">
    <button
      type="button"
      class="group inline-flex w-full items-center justify-start gap-2 rounded-sm text-left font-semibold text-foreground transition-colors hover:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
      @click="handleSort"
    >
      <slot />
      <ChevronUp v-if="isActive && direction === 'asc'" class="size-4 text-foreground" aria-hidden="true" />
      <ChevronDown v-else-if="isActive" class="size-4 text-foreground" aria-hidden="true" />
      <ChevronsUpDown v-else class="size-4 text-muted-foreground" aria-hidden="true" />
    </button>
  </TableHead>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { ChevronUp, ChevronDown, ChevronsUpDown } from 'lucide-vue-next'
import { TableHead } from '@/Components/ui/table'

type SortDirection = 'asc' | 'desc'

const props = defineProps<{
  column: string
  sort: string
  direction: SortDirection
}>()

const emit = defineEmits<{
  (e: 'sort-change', value: { column: string; direction: SortDirection }): void
}>()

const isActive = computed(() => props.column === props.sort)

const ariaSort = computed(() => {
  if (!isActive.value) {
    return undefined
  }

  return props.direction === 'asc' ? 'ascending' : 'descending'
})

const handleSort = () => {
  const nextDirection: SortDirection = isActive.value && props.direction === 'asc' ? 'desc' : 'asc'

  emit('sort-change', { column: props.column, direction: nextDirection })
}
</script>
