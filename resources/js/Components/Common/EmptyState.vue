<script setup lang="ts">
import type { Component } from 'vue'

interface Props {
  icon?: Component
  title: string
  description?: string
  headingLevel?: 2 | 3
}

withDefaults(defineProps<Props>(), {
  headingLevel: 2,
})
</script>

<template>
  <div class="flex flex-col items-center gap-2 py-12 text-center">
    <component :is="icon" v-if="icon" class="size-10 text-muted-foreground" aria-hidden="true" />
    <component :is="`h${headingLevel}`" class="text-lg font-semibold">{{ title }}</component>
    <p v-if="description" class="max-w-md text-sm text-muted-foreground">{{ description }}</p>
    <div v-if="$slots.action" class="mt-2">
      <slot name="action" />
    </div>
  </div>
</template>
