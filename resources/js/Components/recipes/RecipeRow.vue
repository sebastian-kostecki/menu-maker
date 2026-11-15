<template>
  <TableRow class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
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
      <CategoryBadge :category="recipe.category" />
    </TableCell>
    <TableCell class="text-gray-600 dark:text-gray-300">
      {{ formatCalories(recipe.calories) }}
    </TableCell>
    <TableCell class="text-gray-600 dark:text-gray-300">
      {{ recipe.servings }}
    </TableCell>
    <TableCell class="text-gray-600 dark:text-gray-300">
      {{ formatDate(recipe.created_at) }}
    </TableCell>
    <TableCell class="text-right">
      <div class="flex items-center justify-end gap-2">
        <Button
          variant="ghost"
          size="sm"
          @click="handleShowRecipe"
          class="h-8 w-8 p-0 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-blue-900/20"
          :aria-label="`View ${recipe.name}`"
        >
          <Eye class="h-4 w-4" />
        </Button>
        <Button
          variant="ghost"
          size="sm"
          @click="handleEditRecipe"
          class="h-8 w-8 p-0 hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-900/20"
          :aria-label="`Edit ${recipe.name}`"
        >
          <Edit class="h-4 w-4" />
        </Button>
        <Button
          variant="ghost"
          size="sm"
          @click="handleDeleteRecipe"
          class="h-8 w-8 p-0 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
          :aria-label="`Usuń ${recipe.name}`"
        >
          <Trash2 class="h-4 w-4" />
        </Button>
      </div>
    </TableCell>
  </TableRow>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { Eye, Edit, Trash2 } from 'lucide-vue-next';
import { TableCell, TableRow } from '@/Components/ui/table';
import { Button } from '@/Components/ui/button';
import CategoryBadge from '@/Components/recipes/CategoryBadge.vue';

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
