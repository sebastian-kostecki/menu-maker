<template>
  <div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <h1 class="text-3xl font-bold text-gray-900">{{ name }}</h1>
      <CategoryBadge :category="category" />
    </div>
  </div>
</template>

<script setup>
import CategoryBadge from './CategoryBadge.vue';

defineProps({
  name: {
    type: String,
    default: '',
  },
  category: {
    type: String,
    default: 'breakfast',
  },
});
</script>
