<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import ConfirmDialog from '@/Components/Common/ConfirmDialog.vue'

const props = defineProps({
  member: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const isDeleting = ref(false)
const displayedName = ref(props.member?.first_name ?? '')

const isOpen = computed(() => props.member !== null)
const description = computed(
  () => `Czy na pewno chcesz usunąć ${displayedName.value}? Tej operacji nie można cofnąć.`,
)

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
  if (!props.member || isDeleting.value) {
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
  if (!value) {
    emit('close')
  }
}
</script>

<template>
  <ConfirmDialog
    :open="isOpen"
    title="Usuń członka rodziny"
    :description="description"
    confirm-label="Usuń"
    variant="destructive"
    :processing="isDeleting"
    @update:open="handleOpenChange"
    @confirm="handleConfirm"
  />
</template>
