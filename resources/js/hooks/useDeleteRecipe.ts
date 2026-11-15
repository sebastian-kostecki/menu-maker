import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

export function useDeleteRecipe() {
  const isLoading = ref(false);
  const error = ref(null);

  const deleteRecipe = async (recipeId) => {
    if (isLoading.value) return;

    try {
      isLoading.value = true;
      error.value = null;

      // Use Inertia's delete method with promise support
      await new Promise((resolve, reject) => {
        router.delete(`/recipes/${recipeId}`, {
          onSuccess: () => {
            // Success message will be handled by Laravel flash message
            resolve();
          },
          onError: (errors) => {
            const errorMessage = errors?.message || 'Nie udało się usunąć przepisu.';
            error.value = errorMessage;

            toast.error('Nie udało się usunąć przepisu.');

            reject(new Error(errorMessage));
          },
          onFinish: () => {
            isLoading.value = false;
          },
        });
      });

    } catch (err) {
      isLoading.value = false;
      throw err;
    }
  };

  return {
    deleteRecipe,
    isLoading,
    error,
  };
}


