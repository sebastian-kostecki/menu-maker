<template>
  <Badge :variant="getBadgeVariant(category)" :class="getBadgeClass(category)">
    {{ getCategoryLabel(category) }}
  </Badge>
</template>

<script setup>
import { Badge } from '@/Components/ui/badge';

defineProps({
  category: {
    type: String,
    default: 'breakfast',
  },
});

function getCategoryLabel(category) {
  const labels = {
    breakfast: 'Breakfast',
    supper: 'Supper',
    dinner: 'Dinner'
  };
  return labels[category];
}

function getBadgeVariant(category) {
  const variants = {
    breakfast: 'default',
    supper: 'secondary',
    dinner: 'outline'
  };
  return variants[category];
}

function getBadgeClass(category) {
  const classes = {
    breakfast: 'bg-yellow-100 text-yellow-800 border-yellow-200 hover:bg-yellow-200',
    supper: 'bg-blue-100 text-blue-800 border-blue-200 hover:bg-blue-200',
    dinner: 'bg-green-100 text-green-800 border-green-200 hover:bg-green-200'
  };
  return classes[category];
}
</script>
