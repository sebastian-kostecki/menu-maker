<template>
  <Dialog :open="true" @update:open="handleClose">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>{{ title }}</DialogTitle>
        <DialogDescription v-if="description">
          {{ description }}
        </DialogDescription>
      </DialogHeader>

      <DialogFooter class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
        <Button variant="outline" @click="handleCancel">
          Cancel
        </Button>
        <Button
          :variant="variant"
          @click="handleConfirm"
          :class="variant === 'destructive' ? 'bg-red-600 hover:bg-red-700' : ''"
        >
          {{ confirmText }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'

interface Props {
  title: string
  description?: string
  confirmText?: string
  variant?: 'destructive' | 'default'
}

interface Emits {
  (e: 'confirm'): void
  (e: 'cancel'): void
}

const props = withDefaults(defineProps<Props>(), {
  confirmText: 'Confirm',
  variant: 'default'
})

const emit = defineEmits<Emits>()

// Handle confirm button click
const handleConfirm = () => {
  emit('confirm')
}

// Handle cancel button click or dialog close
const handleCancel = () => {
  emit('cancel')
}

// Handle dialog close (via overlay or escape)
const handleClose = (open: boolean) => {
  if (!open) {
    emit('cancel')
  }
}
</script>
