<template>
  <div role="group" :aria-label="ariaLabel" class="flex flex-wrap items-center gap-2">
    <Toggle
      size="sm"
      :model-value="isAllPressed"
      @update:model-value="(pressed) => select('', pressed)"
    >
      {{ allLabel }}
      <span v-if="allCount !== undefined">{{ formatNumber(allCount) }}</span>
    </Toggle>
    <Toggle
      v-for="option in options"
      :key="option.value"
      size="sm"
      :variant="option.variant"
      :model-value="modelValue === option.value"
      @update:model-value="(pressed) => select(option.value, pressed)"
    >
      {{ option.label }}
      <span v-if="option.count !== undefined">{{ formatNumber(option.count) }}</span>
    </Toggle>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Toggle } from '@/Components/ui/toggle'
import { formatNumber } from '@/lib/format'

interface FilterOption {
  value: string
  label: string
  variant?: 'default' | 'breakfast' | 'supper' | 'dinner'
  count?: number
}

interface Props {
  modelValue: string
  options: FilterOption[]
  ariaLabel: string
  allLabel?: string
  allCount?: number
}

interface Emits {
  (e: 'update:modelValue', value: string): void
}

const props = withDefaults(defineProps<Props>(), {
  allLabel: 'Wszystkie',
  allCount: undefined,
})
const emit = defineEmits<Emits>()

// An empty or unknown value (e.g. a stale URL) counts as „Wszystkie”, so one pill is always pressed.
const isAllPressed = computed(() => !props.options.some((option) => option.value === props.modelValue))

// Exactly one pill stays pressed: unpressing the active one is ignored.
// Clicking „Wszystkie” while it stands in for an unknown value still clears that value.
const select = (value: string, pressed: boolean | null | undefined) => {
  if (value === props.modelValue) {
    return
  }

  if (!pressed && !(value === '' && isAllPressed.value)) {
    return
  }

  emit('update:modelValue', value)
}
</script>
