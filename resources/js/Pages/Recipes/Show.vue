<template>
  <Head :title="`Recipe: ${recipe.data.name}`" />

  <AuthenticatedLayout>
    <template #header>
      <!-- Breadcrumbs -->
      <nav class="flex items-center space-x-2 text-sm text-gray-600 mb-4">
        <Link href="/recipes" class="hover:text-gray-900">Recipes</Link>
        <span>/</span>
        <span class="text-gray-900">{{ recipe.data.name }}</span>
      </nav>

      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Recipe Details
        </h2>

        <!-- Action Bar -->
        <div class="flex items-center space-x-3">
          <!-- Back to List Button -->
          <Link
            href="/recipes"
            class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-md transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Recipes
          </Link>

          <ActionBar
            :can-edit="canEdit"
            @edit="handleEdit"
            @delete-request="handleDeleteRequest"
          />
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900 space-y-8">

            <!-- Recipe Header -->
            <RecipeHeader
              :name="recipe?.data.name || ''"
              :category="recipe?.data.category || 'breakfast'"
            />

            <!-- Recipe Meta Badges -->
            <RecipeMetaBadges
              :calories="recipe?.data.calories || 0"
              :servings="recipe?.data.servings || 1"
            />

            <!-- Ingredients Grid -->
            <div class="space-y-4">
              <h2 class="text-2xl font-semibold text-gray-900">Ingredients</h2>
              <IngredientCardGrid :ingredients="recipe?.data.ingredients || []" />
            </div>

            <!-- Instructions Section -->
            <InstructionsSection :instructions="recipe?.data.instructions || ''" />

          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <DeleteConfirmationDialog
      :open="showDeleteDialog"
      :recipe-name="recipe?.data.name || ''"
      :is-loading="deleteLoading"
      @confirm="handleDeleteConfirm"
      @cancel="handleDeleteCancel"
    />
  </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionBar from '@/Components/recipes/ActionBar.vue';
import RecipeHeader from '@/Components/recipes/RecipeHeader.vue';
import RecipeMetaBadges from '@/Components/recipes/RecipeMetaBadges.vue';
import IngredientCardGrid from '@/Components/recipes/IngredientCardGrid.vue';
import InstructionsSection from '@/Components/recipes/InstructionsSection.vue';
import DeleteConfirmationDialog from '@/Components/recipes/DeleteConfirmationDialog.vue';
import { useDeleteRecipe } from '@/hooks/useDeleteRecipe';

// Props from Inertia
const props = defineProps({
  recipe: {
    type: Object,
    required: true,
  },
  canEdit: {
    type: Boolean,
    default: false,
  },
});

// State management
const showDeleteDialog = ref(false);
const { deleteRecipe, isLoading: deleteLoading } = useDeleteRecipe();

// Event handlers
function handleEdit() {
  router.visit(`/recipes/${props.recipe.data.id}/edit`);
}

function handleDeleteRequest() {
  showDeleteDialog.value = true;
}

function handleDeleteCancel() {
  showDeleteDialog.value = false;
}

async function handleDeleteConfirm() {
  try {
    await deleteRecipe(props.recipe.data.id);
    showDeleteDialog.value = false;
    // Redirect is handled by the hook
  } catch (error) {
    // Error handling is done in the hook
    console.error('Failed to delete recipe:', error);
  }
}
</script>
