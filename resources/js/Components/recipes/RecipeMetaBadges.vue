<template>
  <div class="flex flex-wrap gap-3">
    <Badge variant="tag" size="status" class="gap-2">
      <Flame class="size-4" aria-hidden="true" />
      {{ formatNumber(calories) }} kcal
    </Badge>

    <Badge variant="tag" size="status" class="gap-2">
      <Users class="size-4" aria-hidden="true" />
      {{ servings }} {{ pluralize(servings, { one: 'porcja', few: 'porcje', many: 'porcji' }) }}
    </Badge>

    <Badge variant="tag" size="status">
      {{ formatNumber(Math.round(Number(calories) / servings)) }} kcal / porcja
    </Badge>
  </div>
</template>

<script setup>
import { Flame, Users } from 'lucide-vue-next';
import { Badge } from '@/Components/ui/badge';
import { formatNumber, pluralize } from '@/lib/format';

defineProps({
  calories: {
    type: [Number, String],
    default: 0,
  },
  servings: {
    type: Number,
    default: 1,
  },
});
</script>
