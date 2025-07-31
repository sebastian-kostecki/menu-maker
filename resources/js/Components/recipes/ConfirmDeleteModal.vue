<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-center justify-center"
      @click.self="handleCancel"
    >
      <!-- Backdrop -->
      <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>

      <!-- Modal -->
      <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
        <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
          <AlertTriangle class="w-6 h-6 text-red-600" />
        </div>

        <div class="text-center">
          <h3 class="text-lg font-medium text-gray-900 mb-2">
            Delete Recipe
          </h3>
          <p class="text-sm text-gray-500 mb-6">
            Are you sure you want to delete "{{ recipeName }}"? This action cannot be undone.
          </p>
        </div>

        <div class="flex gap-3 justify-end">
          <Button
            variant="outline"
            @click="handleCancel"
          >
            Cancel
          </Button>
          <Button
            variant="destructive"
            @click="handleConfirm"
          >
            <Trash2 class="w-4 h-4 mr-2" />
            Delete Recipe
          </Button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { AlertTriangle, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';

// Props
defineProps({
  open: {
    type: Boolean,
    default: false
  },
  recipeName: {
    type: String,
    default: ''
  }
});

// Emits
const emit = defineEmits(['confirm', 'cancel']);

// Event handlers
const handleConfirm = () => {
  emit('confirm');
};

const handleCancel = () => {
  emit('cancel');
};
</script>
