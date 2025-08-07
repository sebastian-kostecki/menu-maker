import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

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
            const errorMessage = errors?.message || 'Failed to delete recipe. Please try again.';
            error.value = errorMessage;

            // Show error toast if available
            if (window.toast) {
              window.toast.error(errorMessage);
            }

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


