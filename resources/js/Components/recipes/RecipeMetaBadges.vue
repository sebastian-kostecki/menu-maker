<template>
  <div class="flex flex-wrap gap-3">
    <Badge variant="outline" class="flex items-center gap-2 px-3 py-1">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
      </svg>
      <span class="font-medium">{{ calories }} cal</span>
    </Badge>

    <Badge variant="outline" class="flex items-center gap-2 px-3 py-1">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
      </svg>
      <span class="font-medium">{{ servings }} {{ servings === 1 ? 'serving' : 'servings' }}</span>
    </Badge>
  </div>
</template>

<script setup>
import { Badge } from '@/Components/ui/badge';

defineProps({
  calories: {
    type: Number,
    default: 0,
  },
  servings: {
    type: Number,
    default: 1,
  },
});
</script>
