<template>
  <div role="group" aria-label="Status" class="flex flex-wrap items-center gap-2">
    <Toggle
      size="sm"
      :model-value="!modelValue"
      @update:model-value="(pressed) => select('', pressed)"
    >
      Wszystkie
    </Toggle>
    <Toggle
      v-for="status in statuses"
      :key="status.value"
      size="sm"
      :model-value="modelValue === status.value"
      @update:model-value="(pressed) => select(status.value, pressed)"
    >
      {{ status.label }}
    </Toggle>
  </div>
</template>

<script setup lang="ts">
import { Toggle } from '@/Components/ui/toggle'

interface StatusOption {
  value: string
  label: string
}

interface Props {
  modelValue: string
  statuses: StatusOption[]
}

interface Emits {
  (e: 'update:modelValue', value: string): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// Exactly one pill stays pressed: unpressing the active one is ignored.
const select = (value: string, pressed: boolean | null | undefined) => {
  if (!pressed || value === props.modelValue) {
    return
  }

  emit('update:modelValue', value)
}
</script>
