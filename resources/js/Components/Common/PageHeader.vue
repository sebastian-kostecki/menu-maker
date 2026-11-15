<script setup lang="ts">
interface Props {
  title: string
  description?: string
  /** Pages keep the default h1; only the kitchen sink passes "h2", so it keeps a single h1. */
  as?: 'h1' | 'h2'
}

withDefaults(defineProps<Props>(), {
  description: undefined,
  as: 'h1',
})
</script>

<template>
  <header class="flex min-w-0 flex-col gap-1">
    <nav v-if="$slots.breadcrumbs" aria-label="Ścieżka nawigacji" class="text-sm text-muted-foreground">
      <slot name="breadcrumbs" />
    </nav>
    <div class="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
      <div class="flex min-w-0 flex-col gap-1">
        <component :is="as" class="break-words text-2xl font-semibold tracking-tight">{{ title }}</component>
        <div v-if="$slots.description || description" class="text-sm text-muted-foreground">
          <slot name="description">{{ description }}</slot>
        </div>
      </div>
      <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
        <slot name="actions" />
      </div>
    </div>
  </header>
</template>
