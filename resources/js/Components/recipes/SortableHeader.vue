<template>
  <button
    type="button"
    class="flex items-center space-x-1 hover:text-gray-900 transition-colors cursor-pointer select-none"
    :aria-label="`Sort by ${field}`"
    @click="handleSort"
    @keydown.enter="handleSort"
    @keydown.space.prevent="handleSort"
  >
    <span><slot /></span>
    <div class="flex flex-col">
      <ChevronUp
        :class="[
          'h-3 w-3 transition-colors',
          isActiveSortField && currentSort.direction === 'asc'
            ? 'text-gray-900'
            : 'text-gray-400'
        ]"
      />
      <ChevronDown
        :class="[
          'h-3 w-3 -mt-1 transition-colors',
          isActiveSortField && currentSort.direction === 'desc'
            ? 'text-gray-900'
            : 'text-gray-400'
        ]"
      />
    </div>
  </button>
</template>

<script setup>
import { computed } from 'vue';
import { ChevronUp, ChevronDown } from 'lucide-vue-next';

// Props
const props = defineProps({
  field: {
    type: String,
    required: true
  },
  currentSort: {
    type: Object,
    required: true,
    validator: (sort) => {
      return typeof sort === 'object' &&
             'field' in sort &&
             'direction' in sort;
    }
  }
});

// Emits
const emit = defineEmits(['sort-change']);

// Computed
const isActiveSortField = computed(() => {
  return props.currentSort.field === props.field;
});

// Event handlers
const handleSort = () => {
  emit('sort-change', props.field);
};
</script>
