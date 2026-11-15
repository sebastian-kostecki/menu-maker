<template>
  <Button
    :disabled="disabled"
    variant="outline"
    size="default"
    @click="$emit('click')"
  >
    <RotateCcw class="size-4" />
    Wyczyść filtry
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
