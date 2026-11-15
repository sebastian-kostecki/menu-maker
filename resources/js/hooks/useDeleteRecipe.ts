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

      // The promise settles on every outcome: Inertia calls onSuccess/onError only for
      // redirects and validation errors, while 403/404/500 and network failures reach onFinish only.
      await new Promise((resolve, reject) => {
        let settled = false;
        const failureMessage = 'Nie udało się usunąć przepisu.';

        router.delete(`/recipes/${recipeId}`, {
          onSuccess: () => {
            settled = true;
            resolve();
          },
          onError: (errors) => {
            const errorMessage = errors?.message || failureMessage;
            error.value = errorMessage;
            settled = true;

            toast.error(failureMessage);

            reject(new Error(errorMessage));
          },
          onFinish: () => {
            isLoading.value = false;

            if (!settled) {
              settled = true;
              error.value = failureMessage;

              toast.error(failureMessage);

              reject(new Error(failureMessage));
            }
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


