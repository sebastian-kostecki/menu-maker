<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next'
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog'
import { Button } from '@/Components/ui/button'

interface Props {
  open: boolean
  title: string
  description: string
  confirmLabel: string
  variant?: 'default' | 'destructive'
  processing?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'default',
  processing: false,
})

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
  (e: 'confirm'): void
}>()

const handleOpenChange = (value: boolean) => {
  if (!value && props.processing) {
    return
  }

  emit('update:open', value)
}

const handleEscape = (event: KeyboardEvent) => {
  if (props.processing) {
    event.preventDefault()
  }
}

const handleConfirm = () => {
  if (props.processing) {
    return
  }

  emit('confirm')
}
</script>

<template>
  <AlertDialog :open="open" @update:open="handleOpenChange">
    <AlertDialogContent @escape-key-down="handleEscape">
      <AlertDialogHeader>
        <AlertDialogTitle>{{ title }}</AlertDialogTitle>
        <AlertDialogDescription>{{ description }}</AlertDialogDescription>
      </AlertDialogHeader>
      <slot v-if="$slots.default" />
      <AlertDialogFooter>
        <AlertDialogCancel :disabled="processing">Anuluj</AlertDialogCancel>
        <Button
          type="button"
          :variant="variant"
          :aria-disabled="processing ? 'true' : undefined"
          class="aria-disabled:pointer-events-none aria-disabled:opacity-50"
          @click="handleConfirm"
        >
          <Loader2
            v-if="processing"
            class="size-4 animate-spin motion-reduce:animate-none"
            aria-hidden="true"
          />
          {{ confirmLabel }}
        </Button>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
