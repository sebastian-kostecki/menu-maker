<template>
  <Select :model-value="modelValue.toString()" @update:model-value="handleChange">
    <SelectTrigger class="w-20">
      <SelectValue />
    </SelectTrigger>
    <SelectContent>
      <SelectItem
        v-for="option in perPageOptions"
        :key="option"
        :value="option.toString()"
      >
        {{ option }}
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

interface Props {
  modelValue: number
}

interface Emits {
  (e: 'update:modelValue', value: number): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// Available per page options
const perPageOptions = [5, 10, 15, 25, 50, 100]

// Handle per page change with validation
const handleChange = (value: string) => {
  const numericValue = parseInt(value, 10)

  // Validate range
  if (perPageOptions.includes(numericValue)) {
    emit('update:modelValue', numericValue)
  }
}
</script>
