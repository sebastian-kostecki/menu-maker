<template>
  <div class="flex items-center justify-between gap-3 rounded-md bg-muted px-3.5 py-2.5">
    <span class="font-medium">{{ ingredient.name }}</span>
    <span class="font-bold tabular-nums">{{ `${formatNumber(ingredient.quantity)} ${ingredient.unit.code}` }}</span>
  </div>
</template>

<script setup>
import { formatNumber } from '@/lib/format';

defineProps({
  ingredient: {
    type: Object,
    required: true,
  },
});
</script>
