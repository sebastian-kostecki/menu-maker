<script setup lang="ts">
interface Props {
  title: string
  description?: string
}

defineProps<Props>()
</script>

<template>
  <header class="flex min-w-0 flex-col gap-1">
    <nav v-if="$slots.breadcrumbs" aria-label="Ścieżka nawigacji" class="text-sm text-muted-foreground">
      <slot name="breadcrumbs" />
    </nav>
    <div class="flex min-w-0 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
      <div class="flex min-w-0 flex-col gap-1">
        <h1 class="break-words text-2xl font-semibold tracking-tight">{{ title }}</h1>
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
