<template>
  <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow duration-200">
    <div class="flex items-center justify-between">
      <h3 class="font-medium text-gray-900 text-lg">{{ ingredient.name }}</h3>
      <Badge variant="secondary" class="ml-2 font-mono text-sm">
        {{ formatQuantity(ingredient.quantity) }} {{ ingredient.unit.code }}
      </Badge>
    </div>
  </div>
</template>

<script setup>
import { Badge } from '@/Components/ui/badge';

defineProps({
  ingredient: {
    type: Object,
    required: true,
  },
});

function formatQuantity(quantity) {
  // Format quantity to remove unnecessary decimals
  return quantity % 1 === 0 ? quantity.toString() : quantity.toFixed(2).replace(/\.?0+$/, '');
}
</script>
