<script setup>
defineProps({
  title: { type: String, required: true },
  description: { type: String, required: false, default: undefined },
})
</script>

<template>
  <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div class="flex flex-col gap-2">
      <slot name="breadcrumbs" />
      <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
      <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
    </div>
    <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
      <slot name="actions" />
    </div>
  </header>
</template>
