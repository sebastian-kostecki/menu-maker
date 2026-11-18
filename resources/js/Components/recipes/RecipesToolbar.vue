<template>
  <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
      <SearchInput
        :model-value="search"
        placeholder="Szukaj przepisów…"
        @update:model-value="(value) => emit('search', value)"
      />
      <FilterPills
        :model-value="category"
        aria-label="Kategoria"
        :options="categoryOptions"
        :all-count="categoryCounts.all"
        @update:model-value="(value) => emit('category-change', value)"
      />
    </div>

    <SortMenu
      :options="sortOptions"
      :sort="sort"
      :direction="direction"
      @sort-change="(value) => emit('sort-change', value)"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import SearchInput from './SearchInput.vue';
import FilterPills from '@/Components/Common/FilterPills.vue';
import SortMenu from '@/Components/Common/SortMenu.vue';

const sortOptions = [
  { value: 'created_at:desc', label: 'Najnowsze', column: 'created_at', direction: 'desc' },
  { value: 'created_at:asc', label: 'Najstarsze', column: 'created_at', direction: 'asc' },
  { value: 'name:asc', label: 'Nazwa A–Z', column: 'name', direction: 'asc' },
  { value: 'name:desc', label: 'Nazwa Z–A', column: 'name', direction: 'desc' },
  { value: 'calories:asc', label: 'Najmniej kcal', column: 'calories', direction: 'asc' },
  { value: 'calories:desc', label: 'Najwięcej kcal', column: 'calories', direction: 'desc' },
];

const props = defineProps({
  search: {
    type: String,
    default: ''
  },
  category: {
    type: String,
    default: ''
  },
  sort: {
    type: String,
    default: 'created_at'
  },
  direction: {
    type: String,
    default: 'desc'
  },
  categories: {
    type: Array,
    required: true
  },
  categoryCounts: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['search', 'category-change', 'sort-change']);

const categoryOptions = computed(() =>
  props.categories.map((category) => ({
    value: category.value,
    label: category.label,
    variant: category.value,
    count: props.categoryCounts[category.value] ?? 0
  }))
);
</script>
