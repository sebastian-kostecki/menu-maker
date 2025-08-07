<template>
  <Head title="Recipes" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Recipes
          </h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Manage your recipe collection
            <span v-if="recipes.meta.total > 0" class="ml-2">
              ({{ recipes.meta.total }} {{ recipes.meta.total === 1 ? 'recipe' : 'recipes' }})
            </span>
          </p>
        </div>
      </div>
    </template>

    <div class="mx-auto max-w-7xl">
      <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
        <div class="p-6">
          <div class="space-y-6">
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

            <!-- Empty state when no recipes -->
            <div v-if="recipes.data.length === 0" class="text-center py-12">
              <div class="mx-auto h-12 w-12 text-gray-400">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
              </div>
              <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                No recipes found
              </h3>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Get started by creating your first recipe.
              </p>
            </div>

            <!-- Pagination -->
            <ServerPagination
              v-if="recipes.data.length > 0"
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
