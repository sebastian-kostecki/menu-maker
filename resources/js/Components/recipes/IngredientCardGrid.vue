<template>
  <Card class="flex flex-col gap-4 p-6">
    <h2 class="flex items-center gap-2 text-section-title">
      <ShoppingBasket class="size-5" aria-hidden="true" />
      Składniki
    </h2>

    <EmptyState
      v-if="ingredients.length === 0"
      class="py-6"
      :icon="Package"
      title="Brak składników"
      description="Ten przepis nie ma jeszcze żadnych składników."
      :heading-level="3"
    />

    <div v-else class="grid grid-cols-1 gap-2 sm:grid-cols-2">
      <IngredientCard
        v-for="ingredient in ingredients"
        :key="ingredient.id"
        :ingredient="ingredient"
      />
    </div>
  </Card>
</template>

<script setup>
import { Package, ShoppingBasket } from 'lucide-vue-next';
import { Card } from '@/Components/ui/card';
import EmptyState from '@/Components/Common/EmptyState.vue';
import IngredientCard from './IngredientCard.vue';

defineProps({
  ingredients: {
    type: Array,
    default: () => [],
  },
});
</script>
