<template>
  <Button
    :disabled="disabled"
    variant="outline"
    size="default"
    @click="$emit('click')"
  >
    <RotateCcw class="h-4 w-4 mr-2" />
    Reset Filters
  </Button>
</template>

<script setup>
import { RotateCcw } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';

// Props
defineProps({
  disabled: {
    type: Boolean,
    default: false
  }
});

// Emits
defineEmits(['click']);
</script>
