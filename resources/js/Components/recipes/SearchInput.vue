<template>
  <div class="relative w-full sm:w-75">
    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
      <Search class="size-4 text-muted-foreground" aria-hidden="true" />
    </div>
    <Input
      :model-value="modelValue"
      :placeholder="placeholder"
      :maxlength="50"
      type="search"
      class="rounded-full pl-10"
      aria-label="Szukaj przepisów"
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
    default: 'Szukaj…'
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
