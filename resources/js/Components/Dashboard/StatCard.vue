<script setup>
import { Card, CardContent } from '@/Components/ui/card'
import { formatNumber } from '@/lib/format'

defineProps({
  label: { type: String, required: true },
  value: { type: Number, required: true },
  icon: { type: [Object, Function], required: true },
})
</script>

<template>
  <Card>
    <CardContent class="flex items-center gap-4">
      <div class="rounded-full bg-accent p-3">
        <component :is="icon" class="size-6 text-primary" aria-hidden="true" />
      </div>
      <div class="flex flex-col gap-1">
        <p class="text-sm font-medium text-muted-foreground">{{ label }}</p>
        <p class="text-2xl font-semibold tabular-nums">{{ formatNumber(value) }}</p>
      </div>
    </CardContent>
  </Card>
</template>
