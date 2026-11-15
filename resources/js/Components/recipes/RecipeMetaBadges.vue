<template>
  <div class="flex flex-wrap gap-3">
    <Badge variant="outline" class="gap-2 px-3 py-1">
      <Flame class="size-4" aria-hidden="true" />
      <span class="font-medium">{{ formatNumber(calories) }} kcal</span>
    </Badge>

    <Badge variant="outline" class="gap-2 px-3 py-1">
      <Users class="size-4" aria-hidden="true" />
      <span class="font-medium">{{ servings }} {{ pluralize(servings, { one: 'porcja', few: 'porcje', many: 'porcji' }) }}</span>
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
