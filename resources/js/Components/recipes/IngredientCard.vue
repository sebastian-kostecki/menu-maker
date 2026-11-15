<template>
  <div class="rounded-lg border bg-card p-4">
    <div class="flex items-center justify-between gap-2">
      <h3 class="text-sm font-medium">{{ ingredient.name }}</h3>
      <Badge variant="secondary" class="tabular-nums">{{ `${formatNumber(ingredient.quantity)} ${ingredient.unit.code}` }}</Badge>
    </div>
  </div>
</template>

<script setup>
import { Badge } from '@/Components/ui/badge';
import { formatNumber } from '@/lib/format';

defineProps({
  ingredient: {
    type: Object,
    required: true,
  },
});
</script>
