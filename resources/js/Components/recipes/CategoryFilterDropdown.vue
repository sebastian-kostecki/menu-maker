<template>
  <Select
    :model-value="modelValue"
    @update:model-value="handleChange"
  >
    <SelectTrigger class="w-full" aria-label="Filtruj według kategorii">
      <SelectValue placeholder="Wybierz kategorię" />
    </SelectTrigger>
    <SelectContent>
      <SelectItem
        v-for="option in options"
        :key="option.value || 'all'"
        :value="option.value || 'all'"
      >
        {{ option.label }}
      </SelectItem>
    </SelectContent>
  </Select>
</template>

<script setup>
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

// Props
defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  options: {
    type: Array,
    required: true,
    validator: (options) => {
      return options.every(option =>
        typeof option === 'object' &&
        'value' in option &&
        'label' in option
      );
    }
  }
});

// Emits
const emit = defineEmits(['update:modelValue']);

// Event handlers
const handleChange = (value) => {
  // Zamień "all" z powrotem na null (wszystkie kategorie)
  const actualValue = value === 'all' ? null : value;
  emit('update:modelValue', actualValue);
};
</script>
