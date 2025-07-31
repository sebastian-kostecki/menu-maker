import { watch, onMounted } from 'vue';

/**
 * Composable for syncing reactive state with localStorage
 * @param {string} key - localStorage key to store the data
 * @param {Object} stateObjects - Object containing reactive state objects to sync
 * @returns {Object} - Object with syncToStorage method
 */
export function useLocalStorageStateSync() {
  const syncToStorage = (key, stateObjects) => {
    // Load initial state from localStorage on mount
    onMounted(() => {
      try {
        const storedState = localStorage.getItem(key);
        if (storedState) {
          const parsedState = JSON.parse(storedState);

          // Restore state from localStorage
          Object.keys(stateObjects).forEach(stateKey => {
            if (parsedState[stateKey] && stateObjects[stateKey]) {
              Object.assign(stateObjects[stateKey], parsedState[stateKey]);
            }
          });
        }
      } catch (error) {
        console.warn('Failed to load state from localStorage:', error);
      }
    });

    // Watch for changes and sync to localStorage
    Object.keys(stateObjects).forEach(stateKey => {
      watch(
        () => stateObjects[stateKey],
        (newState) => {
          try {
            const currentStorage = localStorage.getItem(key);
            const currentState = currentStorage ? JSON.parse(currentStorage) : {};

            currentState[stateKey] = newState;
            localStorage.setItem(key, JSON.stringify(currentState));
          } catch (error) {
            console.warn('Failed to save state to localStorage:', error);
          }
        },
        { deep: true }
      );
    });
  };

  return {
    syncToStorage
  };
}
