<script setup>
import { computed } from 'vue'
import { Card } from '@/Components/ui/card'
import { formatNumber, pluralize } from '@/lib/format'
import { MEAL_CATEGORIES, MEAL_CATEGORY_FILL_CLASSES } from '@/lib/mealCategories'

const props = defineProps({
  value: { type: Number, required: true },
  forms: { type: Object, required: true },
  icon: { type: [Object, Function], required: true },
  tone: { type: String, required: true, validator: (tone) => MEAL_CATEGORIES.includes(tone) },
})

const toneClass = computed(() => MEAL_CATEGORY_FILL_CLASSES[props.tone])
</script>

<template>
  <Card class="flex flex-col justify-between gap-4 px-6 py-5" :class="toneClass">
    <p class="text-stat tabular-nums">{{ formatNumber(value) }}</p>
    <div class="flex items-center justify-between gap-3 text-base font-semibold">
      <span>{{ pluralize(value, forms) }}</span>
      <component :is="icon" class="size-6" aria-hidden="true" />
    </div>
  </Card>
</template>
