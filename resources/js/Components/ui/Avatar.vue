<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '@/lib/utils'

export interface AvatarProps {
  size?: 'sm' | 'md' | 'lg'
  class?: string
}

const props = withDefaults(defineProps<AvatarProps>(), {
  size: 'md'
})

const avatarClasses = computed(() => cn(
  'relative flex shrink-0 overflow-hidden rounded-full',
  {
    'h-8 w-8': props.size === 'sm',
    'h-10 w-10': props.size === 'md',
    'h-12 w-12': props.size === 'lg'
  },
  props.class
))
</script>

<template>
  <span :class="avatarClasses">
    <slot />
  </span>
</template>
