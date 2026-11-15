<script setup>
defineProps({
  title: { type: String, required: true },
  description: { type: String, required: false, default: undefined },
  // Pages keep the default h1; only the kitchen sink passes "h2", so it keeps a single h1.
  as: { type: String, required: false, default: 'h1', validator: (value) => ['h1', 'h2'].includes(value) },
})
</script>

<template>
  <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div class="flex flex-col gap-2">
      <slot name="breadcrumbs" />
      <component :is="as" class="text-2xl font-semibold tracking-tight">{{ title }}</component>
      <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
    </div>
    <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2">
      <slot name="actions" />
    </div>
  </header>
</template>
