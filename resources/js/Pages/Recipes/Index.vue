<template>
  <Head title="Przepisy" />

  <AuthenticatedLayout>
    <div class="mx-auto flex max-w-6xl flex-col gap-6">
      <PageHeader title="Przepisy">
        <template #description>
          Zarządzaj swoją kolekcją przepisów
          <span v-if="recipes.meta.total > 0">
            ({{ recipes.meta.total }} {{ pluralize(recipes.meta.total, { one: 'przepis', few: 'przepisy', many: 'przepisów' }) }})
          </span>
        </template>
        <template v-if="!isFirstRun" #actions>
          <Button as-child>
            <Link :href="route('recipes.create')">
              <Plus aria-hidden="true" />
              Dodaj przepis
            </Link>
          </Button>
        </template>
      </PageHeader>

      <Card v-if="isFirstRun">
        <EmptyState
          class="py-12"
          :icon="ChefHat"
          title="Brak przepisów"
          description="Dodaj pierwszy przepis, aby zaplanować tydzień."
        >
          <Button as-child>
            <Link :href="route('recipes.create')">
              <Plus aria-hidden="true" />
              Dodaj przepis
            </Link>
          </Button>
        </EmptyState>
      </Card>

      <template v-else>
        <RecipesToolbar
          :search="searchValue"
          :category="currentFilters.category"
          :sort="currentFilters.sort"
          :direction="currentFilters.direction"
          :categories="categories"
          :category-counts="categoryCounts"
          @search="handleSearch"
          @category-change="handleCategoryChange"
          @sort-change="handleSortChange"
        />

        <Card v-if="noResults">
          <EmptyState
            class="py-12"
            :icon="SearchX"
            title="Brak wyników"
            description="Na tej stronie nie ma przepisów lub żaden nie pasuje do filtrów."
          >
            <Button variant="outline" @click="handleReset">
              Wyczyść filtry
            </Button>
          </EmptyState>
        </Card>

        <template v-else>
          <ul aria-label="Przepisy" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <li v-for="recipe in recipes.data" :key="recipe.id">
              <RecipeTile :recipe="recipe" @delete-requested="handleDeleteRequested" />
            </li>
          </ul>

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
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { ChefHat, Plus, SearchX } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RecipesToolbar from '@/Components/recipes/RecipesToolbar.vue';
import RecipeTile from '@/Components/recipes/RecipeTile.vue';
import ServerPagination from '@/Components/Common/ServerPagination.vue';
import ConfirmDialog from '@/Components/Common/ConfirmDialog.vue';
import PageHeader from '@/Components/Common/PageHeader.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { pluralize } from '@/lib/format.js';
import { toast } from 'vue-sonner';

const DEFAULT_PER_PAGE = 12;
const SEARCH_DEBOUNCE_MS = 300;

const props = defineProps({
  recipes: {
    type: Object,
    required: true
  },
  filters: {
    type: [Object, Array],
    default: () => ({})
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

// Bez parametrów w URL serwer wysyła `filters` jako pustą tablicę JSON, a `[].sort` to funkcja
const normalizedFilters = computed(() => (Array.isArray(props.filters) ? {} : props.filters));

// Stan listy pochodzi wyłącznie z URL (props.filters to echo zapytania); nic nie jest trzymane lokalnie
const currentFilters = computed(() => ({
  search: normalizedFilters.value.search || '',
  category: props.categories.some((category) => category.value === normalizedFilters.value.category)
    ? normalizedFilters.value.category
    : '',
  sort: normalizedFilters.value.sort || 'created_at',
  direction: normalizedFilters.value.direction === 'asc' ? 'asc' : 'desc',
  perPage: Number(props.recipes.meta.per_page) || DEFAULT_PER_PAGE
}));

// Pierwsze uruchomienie: brak przepisów w ogóle (a nie wynik wyszukiwania)
const isFirstRun = computed(() => props.categoryCounts.all === 0 && !currentFilters.value.search);
const noResults = computed(() => props.recipes.data.length === 0);

// Zapytanie z bieżących filtrów i zmiany; puste wartości są pomijane, per_page tylko gdy różni się od domyślnego
const buildQuery = (changes = {}) => {
  const merged = { ...currentFilters.value, ...changes };
  const query = {};

  if (merged.search) {
    query.search = merged.search;
  }
  if (merged.category) {
    query.category = merged.category;
  }
  query.sort = merged.sort;
  query.direction = merged.direction;
  if (merged.perPage !== DEFAULT_PER_PAGE) {
    query.per_page = merged.perPage;
  }

  return query;
};

const visit = (query) => {
  router.get(route('recipes.index'), query, {
    preserveState: true,
    preserveScroll: true,
    replace: true
  });
};

// Pole wyszukiwania ma lokalny stan na czas pisania; do URL trafia po 300 ms
const searchValue = ref(currentFilters.value.search);
let lastSentSearch = currentFilters.value.search;
let searchTimeout = null;

// Nadpisujemy pole tylko gdy nie czeka debounce i wartość nie pochodzi z naszego żądania
// (np. „Wyczyść filtry” lub zewnętrzna wizyta), nigdy przez własne, trwające wyszukiwanie.
watch(() => currentFilters.value.search, (incoming) => {
  if (searchTimeout === null && incoming !== lastSentSearch) {
    searchValue.value = incoming;
    lastSentSearch = incoming;
  }
});

onBeforeUnmount(() => {
  if (searchTimeout !== null) {
    clearTimeout(searchTimeout);
  }
});

const handleSearch = (value) => {
  searchValue.value = value;

  if (searchTimeout !== null) {
    clearTimeout(searchTimeout);
  }

  searchTimeout = setTimeout(() => {
    searchTimeout = null;
    lastSentSearch = value;
    visit(buildQuery({ search: value }));
  }, SEARCH_DEBOUNCE_MS);
};

// Zmiana pigułki, sortowania i resetu wraca na stronę 1 (brak `page` w zapytaniu)
const handleCategoryChange = (category) => {
  visit(buildQuery({ category, search: searchValue.value }));
};

const handleSortChange = ({ column, direction }) => {
  visit(buildQuery({ sort: column, direction, search: searchValue.value }));
};

const handlePageChange = (page) => {
  visit({ ...buildQuery(), page });
};

const handleReset = () => {
  if (searchTimeout !== null) {
    clearTimeout(searchTimeout);
    searchTimeout = null;
  }
  searchValue.value = '';
  lastSentSearch = '';
  visit({});
};

// Delete modal state
const showDeleteModal = ref(false);
const selectedRecipe = ref(null);
const deleting = ref(false);

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
</script>
