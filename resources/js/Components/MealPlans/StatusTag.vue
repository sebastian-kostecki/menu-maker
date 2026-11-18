<template>
  <Badge :variant="badgeVariant" size="status">
    <Loader2 v-if="value === 'processing'" class="size-3 animate-spin motion-reduce:animate-none" aria-hidden="true" />
    {{ statusLabel }}
  </Badge>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Loader2 } from 'lucide-vue-next'
import { Badge } from '@/Components/ui/badge'
import type { MealPlanStatus } from '@/types/meal-plan'

interface Props {
  value: MealPlanStatus
}

const props = defineProps<Props>()

const statusLabels: Record<MealPlanStatus, string> = {
  draft: 'Szkic',
  pending: 'Oczekuje',
  processing: 'Generowanie',
  done: 'Gotowy',
  error: 'Błąd'
}

const statusVariants: Record<MealPlanStatus, string> = {
  draft: 'draft',
  pending: 'muted',
  processing: 'info',
  done: 'success',
  error: 'destructive'
}

const badgeVariant = computed(() => statusVariants[props.value] ?? 'muted')

const statusLabel = computed(() => statusLabels[props.value] || props.value)
</script>
