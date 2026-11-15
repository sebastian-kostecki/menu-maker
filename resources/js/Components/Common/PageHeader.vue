<script setup lang="ts">
interface Props {
  title: string
  description?: string
  as?: 'h1' | 'h2'
}

withDefaults(defineProps<Props>(), {
  description: undefined,
  as: 'h1',
})
</script>

<template>
  <header class="flex flex-col gap-2">
    <div v-if="$slots.breadcrumbs">
      <slot name="breadcrumbs" />
    </div>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div class="min-w-0 space-y-1">
        <component :is="as" class="text-2xl font-semibold tracking-tight">{{ title }}</component>
        <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
      </div>
      <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
        <slot name="actions" />
      </div>
    </div>
  </header>
</template>
