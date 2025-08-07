<template>
  <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
    <div class="flex flex-col sm:flex-row gap-4 items-center flex-1">
      <!-- Search Input -->
      <div class="w-full sm:w-auto min-w-[300px]">
        <SearchInput
          v-model="searchValue"
          placeholder="Search recipes..."
          @update:model-value="handleSearchInput"
          @keydown.enter="handleSearchEnter"
        />
      </div>

      <!-- Category Filter Dropdown -->
      <div class="w-full sm:w-auto min-w-[200px]">
        <CategoryFilterDropdown
          v-model="categoryValue"
          :options="categoriesWithAll"
          @update:model-value="handleCategoryChange"
        />
      </div>

      <!-- Reset Filters Button -->
      <ResetFiltersButton
        :disabled="!hasActiveFilters"
        @click="handleReset"
      />
    </div>

    <!-- Create Recipe Button -->
    <div class="w-full sm:w-auto">
      <CreateRecipeButton />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import SearchInput from './SearchInput.vue';
import CategoryFilterDropdown from './CategoryFilterDropdown.vue';
import ResetFiltersButton from './ResetFiltersButton.vue';
import CreateRecipeButton from './CreateRecipeButton.vue';

// Props
const props = defineProps({
  modelValue: {
    type: Object,
    default: () => ({})
  },
  categories: {
    type: Array,
    required: true
  }
});

// Emits
const emit = defineEmits(['update:modelValue', 'search', 'category-change', 'reset']);

// Local state
const searchValue = ref(props.modelValue.search || '');
const categoryValue = ref(props.modelValue.category || 'all');

// Computed
const categoriesWithAll = computed(() => [
  { value: 'all', label: 'All Categories' },
  ...props.categories
]);

const hasActiveFilters = computed(() => {
  return searchValue.value !== '' || (categoryValue.value !== '' && categoryValue.value !== 'all');
});

// Watch for changes from parent
watch(() => props.modelValue, (newValue) => {
  searchValue.value = newValue.search || '';
  categoryValue.value = newValue.category || 'all';
}, { deep: true });

// Event handlers
const handleSearchInput = (value) => {
  searchValue.value = value;
  updateModelValue();
  emit('search', value);
};

const handleSearchEnter = () => {
  emit('search', searchValue.value);
};

const handleCategoryChange = (value) => {
  categoryValue.value = value;
  updateModelValue();
  emit('category-change', value);
};

const handleReset = () => {
  searchValue.value = '';
  categoryValue.value = 'all';
  updateModelValue();
  emit('reset');
};

// Update model value
const updateModelValue = () => {
  emit('update:modelValue', {
    search: searchValue.value,
    category: categoryValue.value
  });
};
</script>
