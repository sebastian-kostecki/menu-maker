<template>
  <Head :title="recipe.name" />

  <AuthenticatedLayout>
    <div class="mx-auto flex max-w-6xl flex-col gap-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <nav aria-label="Ścieżka nawigacji" class="flex items-center gap-2 text-sm text-muted-foreground">
          <Link
            :href="route('recipes.index')"
            class="inline-flex items-center gap-1.5 rounded-sm text-label text-muted-foreground hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
          >
            <ArrowLeft class="size-4" aria-hidden="true" />
            Przepisy
          </Link>
          <span aria-hidden="true">/</span>
          <span aria-current="page" class="text-foreground">{{ recipe.name }}</span>
        </nav>

        <ActionBar
          :can-edit="canEdit"
          :edit-href="route('recipes.edit', recipe.id)"
          @delete-request="handleDeleteRequest"
        />
      </div>

      <RecipeHero :recipe="recipe" />

      <div class="grid gap-6 lg:grid-cols-5">
        <div class="lg:col-span-2">
          <IngredientCardGrid :ingredients="recipe.ingredients || []" />
        </div>
        <div class="lg:col-span-3">
          <InstructionsSection :instructions="recipe.instructions || ''" />
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <ConfirmDialog
      v-model:open="showDeleteDialog"
      title="Usuń przepis"
      :description="`Czy na pewno chcesz usunąć przepis „${recipe.name || ''}”? Tej operacji nie można cofnąć.`"
      confirm-label="Usuń"
      variant="destructive"
      :processing="deleteLoading"
      @confirm="handleDeleteConfirm"
    />
  </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionBar from '@/Components/recipes/ActionBar.vue';
import RecipeHero from '@/Components/recipes/RecipeHero.vue';
import IngredientCardGrid from '@/Components/recipes/IngredientCardGrid.vue';
import InstructionsSection from '@/Components/recipes/InstructionsSection.vue';
import ConfirmDialog from '@/Components/Common/ConfirmDialog.vue';
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
function handleDeleteRequest() {
  showDeleteDialog.value = true;
}

async function handleDeleteConfirm() {
  try {
    await deleteRecipe(props.recipe.id);
    // Redirect is handled by the server response
  } catch {
    // toast already shown by the hook
  } finally {
    showDeleteDialog.value = false;
  }
}
</script>
