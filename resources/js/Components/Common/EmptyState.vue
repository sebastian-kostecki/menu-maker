<script setup>
defineProps({
  icon: { type: null, required: false, default: undefined },
  title: { type: String, required: true },
  description: { type: String, required: false, default: undefined },
})
</script>

<template>
  <div class="flex flex-col items-center gap-2 text-center">
    <component :is="icon" v-if="icon" class="size-10 text-muted-foreground" aria-hidden="true" />
    <h2 class="text-lg font-semibold">{{ title }}</h2>
    <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
    <div v-if="$slots.default" class="mt-2">
      <slot />
    </div>
  </div>
</template>
