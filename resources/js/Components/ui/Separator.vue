<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '@/lib/utils'

export interface SeparatorProps {
  orientation?: 'horizontal' | 'vertical'
  decorative?: boolean
  class?: string
}

const props = withDefaults(defineProps<SeparatorProps>(), {
  orientation: 'horizontal',
  decorative: true
})

const separatorClasses = computed(() => cn(
  'shrink-0 bg-border',
  {
    'h-px w-full': props.orientation === 'horizontal',
    'h-full w-px': props.orientation === 'vertical'
  },
  props.class
))
</script>

<template>
  <div
    :class="separatorClasses"
    :role="decorative ? 'none' : 'separator'"
    :aria-orientation="orientation"
  />
</template>
