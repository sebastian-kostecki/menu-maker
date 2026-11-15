<script setup lang="ts">
import type { Component } from 'vue'

interface Props {
  icon: Component
  title: string
  description?: string
}

withDefaults(defineProps<Props>(), {
  description: undefined,
})
</script>

<template>
  <div class="flex flex-col items-center gap-3 rounded-lg border bg-card p-8 text-center">
    <component :is="icon" class="size-10 text-muted-foreground" aria-hidden="true" />
    <p class="text-lg font-semibold">{{ title }}</p>
    <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
    <div v-if="$slots.action" class="mt-2">
      <slot name="action" />
    </div>
  </div>
</template>
