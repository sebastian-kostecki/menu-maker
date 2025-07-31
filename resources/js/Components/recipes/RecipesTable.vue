<template>
  <div class="rounded-md border">
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead class="w-[300px]">
            <SortableHeader
              field="name"
              :current-sort="sort"
              @sort-change="$emit('sort-change', $event)"
            >
              Name
            </SortableHeader>
          </TableHead>
          <TableHead class="w-[120px]">
            <SortableHeader
              field="category"
              :current-sort="sort"
              @sort-change="$emit('sort-change', $event)"
            >
              Category
            </SortableHeader>
          </TableHead>
          <TableHead class="w-[100px]">
            <SortableHeader
              field="calories"
              :current-sort="sort"
              @sort-change="$emit('sort-change', $event)"
            >
              Calories
            </SortableHeader>
          </TableHead>
          <TableHead class="w-[100px]">Servings</TableHead>
          <TableHead class="w-[120px]">
            <SortableHeader
              field="created_at"
              :current-sort="sort"
              @sort-change="$emit('sort-change', $event)"
            >
              Created
            </SortableHeader>
          </TableHead>
          <TableHead class="w-[80px]">Actions</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <RecipeRow
          v-for="recipe in recipes"
          :key="recipe.id"
          :recipe="recipe"
          @delete-requested="$emit('delete-requested', recipe)"
        />
        <TableRow v-if="recipes.length === 0">
          <TableCell :colspan="6" class="text-center py-8 text-gray-500">
            No recipes found
          </TableCell>
        </TableRow>
      </TableBody>
    </Table>
  </div>
</template>

<script setup>
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import SortableHeader from './SortableHeader.vue';
import RecipeRow from './RecipeRow.vue';

// Props
defineProps({
  recipes: {
    type: Array,
    required: true
  },
  sort: {
    type: Object,
    required: true,
    validator: (sort) => {
      return typeof sort === 'object' &&
             'field' in sort &&
             'direction' in sort;
    }
  }
});

// Emits
defineEmits(['sort-change', 'delete-requested']);
</script>
