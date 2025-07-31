<template>
  <Head title="Recipes" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Recipes
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900 space-y-6">
            <!-- Toolbar with filters and search -->
            <RecipesToolbar
              v-model="filterState"
              :categories="categories"
              @search="handleSearch"
              @category-change="handleCategoryChange"
              @reset="handleReset"
            />

            <!-- Recipes table -->
            <RecipesTable
              :recipes="recipes.data"
              :sort="sortState"
              @sort-change="handleSortChange"
              @delete-requested="handleDeleteRequested"
            />

            <!-- Pagination -->
            <ServerPagination
              :meta="recipes.meta"
              :links="recipes.links"
              @page-change="handlePageChange"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Delete confirmation modal -->
    <ConfirmDeleteModal
      :open="showDeleteModal"
      :recipe-name="selectedRecipe?.name || ''"
      @confirm="handleDeleteConfirm"
      @cancel="handleDeleteCancel"
    />
  </AuthenticatedLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, reactive, watch, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RecipesToolbar from '@/Components/recipes/RecipesToolbar.vue';
import RecipesTable from '@/Components/recipes/RecipesTable.vue';
import ServerPagination from '@/Components/recipes/ServerPagination.vue';
import ConfirmDeleteModal from '@/Components/recipes/ConfirmDeleteModal.vue';
import { useLocalStorageStateSync } from '@/lib/useLocalStorageStateSync.js';

// Define props interface
const props = defineProps({
  recipes: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({})
  },
  categories: {
    type: Array,
    required: true
  }
});

// Page data
const page = usePage();

// Filter state
const filterState = reactive({
  search: props.filters.search || '',
  category: props.filters.category || null
});

// Sort state
const sortState = reactive({
  field: props.filters.sort || 'created_at',
  direction: props.filters.direction || 'desc'
});

// Delete modal state
const showDeleteModal = ref(false);
const selectedRecipe = ref(null);

// Initialize localStorage sync for filters and sort
const { syncToStorage } = useLocalStorageStateSync();
syncToStorage('mm.recipes.filters', { filterState, sortState });

// Current page state
const currentPage = computed(() => props.recipes.meta.current_page);

// Debounced search handler
let searchTimeout = null;
const handleSearch = (searchValue) => {
  filterState.search = searchValue;

  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }

  searchTimeout = setTimeout(() => {
    updateFilters({ page: 1 });
  }, 300);
};

// Category change handler
const handleCategoryChange = (categoryValue) => {
  filterState.category = categoryValue;
  updateFilters({ page: 1 });
};

// Reset filters handler
const handleReset = () => {
  filterState.search = '';
  filterState.category = null;
  sortState.field = 'created_at';
  sortState.direction = 'desc';
  updateFilters({ page: 1 });
};

// Sort change handler
const handleSortChange = (field) => {
  if (sortState.field === field) {
    sortState.direction = sortState.direction === 'asc' ? 'desc' : 'asc';
  } else {
    sortState.field = field;
    sortState.direction = 'asc';
  }
  updateFilters();
};

// Page change handler
const handlePageChange = (page) => {
  updateFilters({ page });
};

// Delete handlers
const handleDeleteRequested = (recipe) => {
  selectedRecipe.value = recipe;
  showDeleteModal.value = true;
};

const handleDeleteConfirm = () => {
  if (selectedRecipe.value) {
    router.delete(route('recipes.destroy', selectedRecipe.value.id), {
      preserveScroll: true,
      onSuccess: () => {
        showDeleteModal.value = false;
        selectedRecipe.value = null;
      }
    });
  }
};

const handleDeleteCancel = () => {
  showDeleteModal.value = false;
  selectedRecipe.value = null;
};

// Update filters and make API call
const updateFilters = (additional = {}) => {
  const params = {
    search: filterState.search || undefined,
    category: filterState.category || undefined,
    sort: sortState.field,
    direction: sortState.direction,
    page: currentPage.value,
    ...additional
  };

  // Remove undefined values
  Object.keys(params).forEach(key => {
    if (params[key] === undefined) {
      delete params[key];
    }
  });

  router.get(route('recipes.index'), params, {
    preserveState: true,
    preserveScroll: true
  });
};
</script>
