<template>
  <Head :title="recipe.name" />

  <AuthenticatedLayout>
    <template #header>
      <PageHeader :title="recipe.name || ''">
        <template #breadcrumbs>
          <div class="flex items-center gap-2">
            <Link :href="route('recipes.index')" class="rounded-sm hover:text-foreground hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Przepisy</Link>
            <span aria-hidden="true">/</span>
            <span aria-current="page" class="text-foreground">{{ recipe.name }}</span>
          </div>
        </template>

        <template v-if="recipe.category" #description>
          <CategoryBadge :category="recipe.category" />
        </template>

        <template #actions>
          <ActionBar
            :can-edit="canEdit"
            :edit-href="route('recipes.edit', recipe.id)"
            @delete-request="handleDeleteRequest"
          />
        </template>
      </PageHeader>
    </template>

    <div class="mx-auto flex max-w-6xl flex-col gap-6">
      <RecipeMetaBadges
        :calories="recipe.calories || 0"
        :servings="recipe.servings || 1"
      />

      <section class="flex flex-col gap-4">
        <h2 class="text-lg font-semibold">Składniki</h2>
        <IngredientCardGrid :ingredients="recipe.ingredients || []" />
      </section>

      <InstructionsSection :instructions="recipe.instructions || ''" />
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
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionBar from '@/Components/recipes/ActionBar.vue';
import CategoryBadge from '@/Components/recipes/CategoryBadge.vue';
import RecipeMetaBadges from '@/Components/recipes/RecipeMetaBadges.vue';
import IngredientCardGrid from '@/Components/recipes/IngredientCardGrid.vue';
import InstructionsSection from '@/Components/recipes/InstructionsSection.vue';
import PageHeader from '@/Components/Common/PageHeader.vue';
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
