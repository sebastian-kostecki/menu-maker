<template>
  <div class="relative">
    <Button
      variant="ghost"
      size="sm"
      @click="toggleDropdown"
      class="h-8 w-8 p-0"
    >
      <MoreHorizontal class="h-4 w-4" />
      <span class="sr-only">Open menu</span>
    </Button>

    <div
      v-if="isOpen"
      class="absolute right-0 mt-2 w-32 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-10"
      @click.stop
    >
      <div class="py-1" role="menu">
        <button
          type="button"
          class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900"
          role="menuitem"
          @click="handleShow"
        >
          <Eye class="mr-2 h-4 w-4" />
          Show
        </button>
        <button
          type="button"
          class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900"
          role="menuitem"
          @click="handleEdit"
        >
          <Edit class="mr-2 h-4 w-4" />
          Edit
        </button>
        <button
          type="button"
          class="flex items-center w-full px-4 py-2 text-sm text-red-700 hover:bg-red-50 hover:text-red-900"
          role="menuitem"
          @click="handleDelete"
        >
          <Trash2 class="mr-2 h-4 w-4" />
          Delete
        </button>
      </div>
    </div>
  </div>

  <!-- Overlay to close dropdown when clicking outside -->
  <div
    v-if="isOpen"
    class="fixed inset-0 z-0"
    @click="closeDropdown"
  ></div>
</template>

<script setup>
import { ref } from 'vue';
import { MoreHorizontal, Eye, Edit, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';

// Props
defineProps({
  recipeId: {
    type: Number,
    required: true
  }
});

// Emits
const emit = defineEmits(['show', 'edit', 'delete']);

// State
const isOpen = ref(false);

// Methods
const toggleDropdown = () => {
  isOpen.value = !isOpen.value;
};

const closeDropdown = () => {
  isOpen.value = false;
};

// Event handlers
const handleShow = () => {
  emit('show');
  closeDropdown();
};

const handleEdit = () => {
  emit('edit');
  closeDropdown();
};

const handleDelete = () => {
  emit('delete');
  closeDropdown();
};
</script>
