<template>
  <Badge :variant="badgeVariant" :class="badgeClasses">
    {{ statusLabel }}
  </Badge>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Badge } from '@/Components/ui/badge'
// Local type definition
type MealPlanStatus = 'pending' | 'processing' | 'done' | 'error'

interface Props {
  value: MealPlanStatus
}

const props = defineProps<Props>()

// Status label mapping
const statusLabels: Record<MealPlanStatus, string> = {
  pending: 'Pending',
  processing: 'Processing',
  done: 'Done',
  error: 'Error'
}

// Badge variant based on status
const badgeVariant = computed(() => {
  switch (props.value) {
    case 'pending':
      return 'secondary'
    case 'processing':
      return 'default'
    case 'done':
      return 'default'
    case 'error':
      return 'destructive'
    default:
      return 'secondary'
  }
})

// Additional classes for custom colors
const badgeClasses = computed(() => {
  switch (props.value) {
    case 'pending':
      return 'bg-gray-100 text-gray-800 hover:bg-gray-200'
    case 'processing':
      return 'bg-blue-100 text-blue-800 hover:bg-blue-200'
    case 'done':
      return 'bg-green-100 text-green-800 hover:bg-green-200'
    case 'error':
      return 'bg-red-100 text-red-800 hover:bg-red-200'
    default:
      return ''
  }
})

// Status label
const statusLabel = computed(() => {
  return statusLabels[props.value] || props.value
})
</script>
