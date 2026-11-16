<script setup lang="ts">
import { computed } from 'vue'
import { Label } from '@/Components/ui/label'

interface Props {
  id: string
  label: string
  error?: string
  description?: string
}

const props = defineProps<Props>()

const invalid = computed(() => Boolean(props.error))

const describedBy = computed(() => {
  const ids = [
    props.description ? `${props.id}-description` : null,
    props.error ? `${props.id}-error` : null,
  ].filter((value): value is string => value !== null)

  return ids.length > 0 ? ids.join(' ') : undefined
})
</script>

<template>
  <div class="grid gap-2">
    <Label :for="id">{{ label }}</Label>
    <slot :id="id" :invalid="invalid" :describedBy="describedBy" />
    <p v-if="description" :id="`${id}-description`" class="text-xs text-muted-foreground">
      {{ description }}
    </p>
    <p v-if="error" :id="`${id}-error`" class="text-sm text-destructive">{{ error }}</p>
  </div>
</template>
