<template>
  <TableRow class="hover:bg-gray-50">
    <TableCell class="font-medium">
      <button
        type="button"
        class="text-left hover:text-blue-600 hover:underline transition-colors"
        @click="handleShowRecipe"
      >
        {{ recipe.name }}
      </button>
    </TableCell>
    <TableCell>
      <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium capitalize" :class="categoryClasses">
        {{ recipe.category }}
      </span>
    </TableCell>
    <TableCell>
      {{ formatCalories(recipe.calories) }}
    </TableCell>
    <TableCell>
      {{ recipe.servings }}
    </TableCell>
    <TableCell>
      {{ formatDate(recipe.created_at) }}
    </TableCell>
    <TableCell>
      <RowActionDropdown
        :recipe-id="recipe.id"
        @show="handleShowRecipe"
        @edit="handleEditRecipe"
        @delete="handleDeleteRecipe"
      />
    </TableCell>
  </TableRow>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { TableCell, TableRow } from '@/Components/ui/table';
import RowActionDropdown from './RowActionDropdown.vue';

// Props
const props = defineProps({
  recipe: {
    type: Object,
    required: true,
    validator: (recipe) => {
      return typeof recipe === 'object' &&
             'id' in recipe &&
             'name' in recipe &&
             'category' in recipe &&
             'calories' in recipe &&
             'servings' in recipe &&
             'created_at' in recipe;
    }
  }
});

// Emits
const emit = defineEmits(['delete-requested']);

// Computed
const categoryClasses = computed(() => {
  const baseClasses = 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium capitalize';

  switch (props.recipe.category) {
    case 'breakfast':
      return 'bg-yellow-100 text-yellow-800';
    case 'dinner':
      return 'bg-blue-100 text-blue-800';
    case 'supper':
      return 'bg-purple-100 text-purple-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
});

// Methods
const formatCalories = (calories) => {
  return parseFloat(calories).toFixed(0) + ' kcal';
};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};

// Event handlers
const handleShowRecipe = () => {
  router.visit(route('recipes.show', props.recipe.id));
};

const handleEditRecipe = () => {
  router.visit(route('recipes.edit', props.recipe.id));
};

const handleDeleteRecipe = () => {
  emit('delete-requested');
};
</script>
