<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import { toast } from 'vue-sonner'
import { AlertTriangle, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  open: {
    type: Boolean,
    required: true
  },
  memberId: {
    type: Number,
    required: true
  },
  memberName: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['confirmed', 'cancelled'])

const isDeleting = ref(false)

const handleConfirm = async () => {
  isDeleting.value = true

  router.delete(`/family-members/${props.memberId}`, {
    preserveScroll: true,
    onSuccess: () => {
      emit('confirmed', props.memberId)
      toast.success(`Członek rodziny "${props.memberName}" został usunięty`)
    },
    onError: (errors) => {
      console.error('Error deleting family member:', errors)
      toast.error('Nie udało się usunąć członka rodziny')
    },
    onFinish: () => {
      isDeleting.value = false
    }
  })
}

const handleCancel = () => {
  if (!isDeleting.value) {
    emit('cancelled')
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="handleCancel">
    <DialogContent class="sm:max-w-[425px]">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2 text-red-600">
          <AlertTriangle class="h-5 w-5" />
          Usuń członka rodziny
        </DialogTitle>
        <DialogDescription class="text-gray-600 dark:text-gray-300">
          Ta akcja jest nieodwracalna. Wszystkie dane związane z tym członkiem rodziny zostaną trwale usunięte.
        </DialogDescription>
      </DialogHeader>

      <div class="py-4">
        <div class="rounded-lg bg-red-50 border border-red-200 p-4 dark:bg-red-900/20 dark:border-red-800">
          <p class="text-sm text-red-800 dark:text-red-200">
            Czy na pewno chcesz usunąć członka rodziny:
            <span class="font-semibold">{{ memberName }}</span>?
          </p>
        </div>
      </div>

      <DialogFooter class="gap-2">
        <Button
          variant="outline"
          @click="handleCancel"
          :disabled="isDeleting"
        >
          Anuluj
        </Button>
        <Button
          variant="destructive"
          @click="handleConfirm"
          :disabled="isDeleting"
          class="flex items-center gap-2"
        >
          <Trash2 class="h-4 w-4" />
          {{ isDeleting ? 'Usuwanie...' : 'Usuń' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
