<template>
  <div class="overflow-x-auto rounded-lg border bg-card">
    <Table>
      <TableHeader>
        <TableRow class="bg-muted">
          <SortableHeader
            class="w-75"
            column="name"
            :sort="sort.field"
            :direction="sort.direction"
            @sort-change="$emit('sort-change', $event)"
          >
            Nazwa
          </SortableHeader>
          <SortableHeader
            class="w-30"
            column="category"
            :sort="sort.field"
            :direction="sort.direction"
            @sort-change="$emit('sort-change', $event)"
          >
            Kategoria
          </SortableHeader>
          <SortableHeader
            class="w-25 text-right"
            column="calories"
            :sort="sort.field"
            :direction="sort.direction"
            @sort-change="$emit('sort-change', $event)"
          >
            Kalorie
          </SortableHeader>
          <TableHead class="w-25 text-right font-semibold text-foreground">Porcje</TableHead>
          <SortableHeader
            class="w-30"
            column="created_at"
            :sort="sort.field"
            :direction="sort.direction"
            @sort-change="$emit('sort-change', $event)"
          >
            Dodano
          </SortableHeader>
          <TableHead class="w-30 text-right font-semibold text-foreground">Akcje</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        <RecipeRow
          v-for="recipe in recipes"
          :key="recipe.id"
          :recipe="recipe"
          @delete-requested="$emit('delete-requested', recipe)"
        />
      </TableBody>
    </Table>
  </div>
</template>

<script setup>
import {
  Table,
  TableBody,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import SortableHeader from '@/Components/Common/SortableHeader.vue';
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
