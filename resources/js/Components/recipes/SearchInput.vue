<template>
  <div class="relative">
    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
      <Search class="h-4 w-4 text-gray-400" />
    </div>
    <Input
      :model-value="modelValue"
      :placeholder="placeholder"
      :maxlength="50"
      type="search"
      class="pl-10"
      aria-label="Search recipes"
      @update:model-value="handleInput"
      @keydown.enter="$emit('keydown.enter')"
    />
  </div>
</template>

<script setup>
import { Search } from 'lucide-vue-next';
import { Input } from '@/Components/ui/input';

// Props
defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: 'Search...'
  }
});

// Emits
const emit = defineEmits(['update:modelValue', 'keydown.enter']);

// Event handlers
const handleInput = (value) => {
  // Validate max length
  if (value.length <= 50) {
    emit('update:modelValue', value);
  }
};
</script>
