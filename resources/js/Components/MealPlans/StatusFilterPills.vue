<template>
  <div role="group" aria-label="Status" class="flex flex-wrap items-center gap-2">
    <Toggle
      size="sm"
      :model-value="isAllPressed"
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
import { computed } from 'vue'
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

// An empty or unknown status (e.g. a stale URL) counts as „Wszystkie”, so one pill is always pressed.
const isAllPressed = computed(() => !props.statuses.some((status) => status.value === props.modelValue))

// Exactly one pill stays pressed: unpressing the active one is ignored.
// Clicking „Wszystkie” while it stands in for an unknown status still clears that status.
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
