<template>
  <EmptyState
    v-if="ingredients.length === 0"
    :icon="Package"
    title="Brak składników"
    description="Ten przepis nie ma jeszcze żadnych składników."
    :heading-level="3"
  />

  <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <IngredientCard
      v-for="ingredient in ingredients"
      :key="ingredient.id"
      :ingredient="ingredient"
    />
  </div>
</template>

<script setup>
import { Package } from 'lucide-vue-next';
import EmptyState from '@/Components/Common/EmptyState.vue';
import IngredientCard from './IngredientCard.vue';

defineProps({
  ingredients: {
    type: Array,
    default: () => [],
  },
});
</script>
