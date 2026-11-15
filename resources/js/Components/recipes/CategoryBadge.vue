<template>
  <Badge :variant="variant">{{ label }}</Badge>
</template>

<script setup>
import { computed } from 'vue';
import { Badge } from '@/Components/ui/badge';
import { MEAL_CATEGORIES, mealCategoryLabel } from '@/lib/mealCategories';

const props = defineProps({
  category: {
    type: String,
    default: 'breakfast',
  },
});

const isKnownCategory = computed(() => MEAL_CATEGORIES.includes(props.category));
const variant = computed(() => (isKnownCategory.value ? props.category : 'outline'));
const label = computed(() => (isKnownCategory.value ? mealCategoryLabel(props.category) : props.category));
</script>
