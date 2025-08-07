<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <IngredientCard
      v-for="ingredient in ingredients"
      :key="ingredient.id"
      :ingredient="ingredient"
    />
  </div>

  <!-- Empty state -->
  <div v-if="ingredients.length === 0" class="text-center py-8 text-gray-500">
    <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
    </svg>
    <p class="text-lg font-medium">No ingredients added</p>
    <p class="text-sm">This recipe doesn't have any ingredients yet.</p>
  </div>
</template>

<script setup>
import IngredientCard from './IngredientCard.vue';

defineProps({
  ingredients: {
    type: Array,
    default: () => [],
  },
});
</script>
