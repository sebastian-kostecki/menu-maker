<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { AlertTriangle } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { Button } from '@/Components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'

const props = defineProps({
  member: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const isDeleting = ref(false)
const displayedName = ref(props.member?.first_name ?? '')

watch(
  () => props.member,
  (member) => {
    if (member) {
      displayedName.value = member.first_name
    }
  },
)

const handleRequestFailure = () => {
  toast.error('Nie udało się usunąć członka rodziny')

  return false
}

const handleConfirm = () => {
  if (!props.member) {
    return
  }

  const name = props.member.first_name
  isDeleting.value = true

  router.delete(route('family-members.destroy', props.member.id), {
    preserveScroll: true,
    onSuccess: () => {
      emit('close')
      toast.success(`Członek rodziny "${name}" został usunięty`)
    },
    onError: handleRequestFailure,
    onHttpException: handleRequestFailure,
    onNetworkError: handleRequestFailure,
    onFinish: () => {
      isDeleting.value = false
    },
  })
}

const handleOpenChange = (value) => {
  if (!value && !isDeleting.value) {
    emit('close')
  }
}
</script>

<template>
  <Dialog :open="member !== null" @update:open="handleOpenChange">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2 text-destructive">
          <AlertTriangle class="size-5" />
          Usuń członka rodziny
        </DialogTitle>
        <DialogDescription>
          Czy na pewno chcesz usunąć {{ displayedName }}? Tej operacji nie można cofnąć.
        </DialogDescription>
      </DialogHeader>

      <DialogFooter class="gap-2">
        <Button variant="outline" :disabled="isDeleting" @click="handleOpenChange(false)">
          Anuluj
        </Button>
        <Button variant="destructive" :disabled="isDeleting" @click="handleConfirm">
          {{ isDeleting ? 'Usuwanie...' : 'Usuń' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
