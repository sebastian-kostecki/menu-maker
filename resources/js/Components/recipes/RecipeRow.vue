<template>
  <TableRow class="transition-colors hover:bg-muted/50">
    <TableCell class="font-medium">
      <Link
        :href="route('recipes.show', recipe.id)"
        class="rounded-sm hover:text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
      >{{ recipe.name }}</Link>
    </TableCell>
    <TableCell>
      <CategoryBadge :category="recipe.category" />
    </TableCell>
    <TableCell class="text-right tabular-nums text-muted-foreground">
      {{ formatNumber(recipe.calories, { maximumFractionDigits: 0 }) }} kcal
    </TableCell>
    <TableCell class="text-right tabular-nums text-muted-foreground">
      {{ recipe.servings }}
    </TableCell>
    <TableCell class="text-muted-foreground">
      {{ formatDate(recipe.created_at) }}
    </TableCell>
    <TableCell class="text-right">
      <div class="flex items-center justify-end gap-1">
        <Button variant="ghost" size="icon" as-child>
          <Link :href="route('recipes.show', recipe.id)" :aria-label="`Pokaż ${recipe.name}`">
            <Eye class="size-4" aria-hidden="true" />
          </Link>
        </Button>
        <Button variant="ghost" size="icon" as-child>
          <Link :href="route('recipes.edit', recipe.id)" :aria-label="`Edytuj ${recipe.name}`">
            <Pencil class="size-4" aria-hidden="true" />
          </Link>
        </Button>
        <Button
          variant="ghost"
          size="icon"
          class="hover:bg-destructive/10 hover:text-destructive"
          :aria-label="`Usuń ${recipe.name}`"
          @click="handleDeleteRecipe"
        >
          <Trash2 class="size-4" aria-hidden="true" />
        </Button>
      </div>
    </TableCell>
  </TableRow>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { Eye, Pencil, Trash2 } from 'lucide-vue-next';
import { TableCell, TableRow } from '@/Components/ui/table';
import { Button } from '@/Components/ui/button';
import CategoryBadge from '@/Components/recipes/CategoryBadge.vue';
import { formatDate, formatNumber } from '@/lib/format.js';

// Props
defineProps({
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

// Event handlers
const handleDeleteRecipe = () => {
  emit('delete-requested');
};
</script>
