<template>
  <Head title="Przepisy" />

  <AuthenticatedLayout>
    <template #header>
      <PageHeader title="Przepisy">
        <template #description>
          Zarządzaj swoją kolekcją przepisów
          <span v-if="recipes.meta.total > 0">
            ({{ recipes.meta.total }} {{ pluralize(recipes.meta.total, { one: 'przepis', few: 'przepisy', many: 'przepisów' }) }})
          </span>
        </template>
      </PageHeader>
    </template>

    <div class="mx-auto flex max-w-6xl flex-col gap-6">
      <EmptyState
        v-if="noRecipes"
        class="py-12"
        :icon="ChefHat"
        title="Brak przepisów"
        description="Dodaj pierwszy przepis, aby zaplanować tydzień."
      >
        <Button as-child>
          <Link :href="route('recipes.create')">
            <Plus class="size-4" aria-hidden="true" />
            Dodaj przepis
          </Link>
        </Button>
      </EmptyState>

      <template v-else>
        <!-- Pasek filtrów i wyszukiwania -->
        <RecipesToolbar
          :model-value="filterState"
          @update:model-value="(value) => Object.assign(filterState, value)"
          :categories="categories"
          @search="handleSearch"
          @category-change="handleCategoryChange"
          @reset="handleReset"
        />

        <EmptyState
          v-if="noResults"
          class="py-12"
          :icon="SearchX"
          title="Brak wyników"
          description="Na tej stronie nie ma przepisów lub żaden nie pasuje do filtrów."
        >
          <Button variant="outline" @click="handleReset">
            Wyczyść filtry
          </Button>
        </EmptyState>

        <template v-else>
          <RecipesTable
            :recipes="recipes.data"
            :sort="sortState"
            @sort-change="handleSortChange"
            @delete-requested="handleDeleteRequested"
          />

          <ServerPagination
            :meta="recipes.meta"
            @page-change="handlePageChange"
          />
        </template>
      </template>
    </div>

    <!-- Delete confirmation dialog -->
    <ConfirmDialog
      v-model:open="showDeleteModal"
      title="Usuń przepis"
      :description="`Czy na pewno chcesz usunąć przepis „${selectedRecipe?.name ?? ''}”? Tej operacji nie można cofnąć.`"
      confirm-label="Usuń"
      variant="destructive"
      :processing="deleting"
      @confirm="handleDeleteConfirm"
    />
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, reactive, computed } from 'vue';
import { ChefHat, Plus, SearchX } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RecipesToolbar from '@/Components/recipes/RecipesToolbar.vue';
import RecipesTable from '@/Components/recipes/RecipesTable.vue';
import ServerPagination from '@/Components/Common/ServerPagination.vue';
import ConfirmDialog from '@/Components/Common/ConfirmDialog.vue';
import PageHeader from '@/Components/Common/PageHeader.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import { Button } from '@/Components/ui/button';
import { pluralize } from '@/lib/format.js';
import { toast } from 'vue-sonner';
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

// Stan pustej listy decydowany na podstawie filtrów zwróconych przez serwer
const noRecipes = computed(() =>
  props.recipes.meta.total === 0 && !(props.filters.search || props.filters.category)
);
const noResults = computed(() => props.recipes.data.length === 0 && !noRecipes.value);

// Bez parametrów w URL serwer wysyła `filters` jako pustą tablicę JSON, a `[].sort` to funkcja
const initialFilters = Array.isArray(props.filters) ? {} : props.filters;

// Filter state
const filterState = reactive({
  search: initialFilters.search || '',
  category: initialFilters.category || null
});

// Sort state
const sortState = reactive({
  field: initialFilters.sort || 'created_at',
  direction: initialFilters.direction || 'desc'
});

// Delete modal state
const showDeleteModal = ref(false);
const selectedRecipe = ref(null);
const deleting = ref(false);

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
const handleSortChange = ({ column, direction }) => {
  sortState.field = column;
  sortState.direction = direction;
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
    deleting.value = true;
    router.delete(route('recipes.destroy', selectedRecipe.value.id), {
      preserveScroll: true,
      onError: () => {
        toast.error('Nie udało się usunąć przepisu.');
      },
      onFinish: () => {
        deleting.value = false;
        // The selection stays set so the dialog text does not blank out while it animates closed;
        // handleDeleteRequested overwrites it on the next open.
        showDeleteModal.value = false;
      }
    });
  }
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
