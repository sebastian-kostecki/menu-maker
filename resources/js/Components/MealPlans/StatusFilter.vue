<template>
  <Select :model-value="modelValue || 'all'" @update:model-value="handleChange">
    <SelectTrigger class="w-40">
      <SelectValue placeholder="All statuses" />
    </SelectTrigger>
    <SelectContent>
      <SelectItem value="all">
        All statuses
      </SelectItem>
      <SelectItem
        v-for="option in options"
        :key="option.value"
        :value="option.value"
      >
        {{ option.label }}
      </SelectItem>
    </SelectContent>
  </Select>
</template>

<script setup lang="ts">
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select'
// Local type definition
interface StatusOption {
  value: string
  label: string
}

interface Props {
  modelValue?: string
  options: StatusOption[]
}

interface Emits {
  (e: 'update:modelValue', value: string): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// Validate and emit status change
const handleChange = (value: string | undefined) => {
  // Convert "all" to empty string for backend
  const validValue = value === 'all' ? '' : (value || '')
  
  // Validate against allowed options or empty string
  if (validValue === '' || props.options.some(option => option.value === validValue)) {
    emit('update:modelValue', validValue)
  }
}
</script>
