<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button
        variant="ghost"
        size="icon"
        class="size-8"
        :aria-label="`Akcje: ${formatDateRange(item.start_date, item.end_date)}`"
      >
        <MoreHorizontal aria-hidden="true" />
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-56">
      <!-- View Details -->
      <DropdownMenuItem @click="handleViewDetails">
        <Eye />
        Szczegóły
      </DropdownMenuItem>

      <!-- Download PDF -->
      <DropdownMenuItem
        :disabled="!canDownloadPdf"
        @click="handleDownloadPdf"
      >
        <Download />
        Pobierz PDF
      </DropdownMenuItem>

      <DropdownMenuSeparator />

      <!-- Regenerate -->
      <DropdownMenuItem
        :disabled="!canRegenerate"
        @click="handleRegenerateClick"
      >
        <RefreshCw />
        Regeneruj
      </DropdownMenuItem>

      <!-- Delete -->
      <DropdownMenuItem
        :disabled="!canDelete"
        variant="destructive"
        @click="handleDeleteClick"
      >
        <Trash2 />
        Usuń
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>

  <!-- Confirm Dialogs -->
  <ConfirmDialog
    v-model:open="showRegenerateDialog"
    title="Regeneruj jadłospis"
    description="Obecne posiłki zostaną zastąpione nowym losowaniem przepisów. Czy chcesz kontynuować?"
    confirm-label="Regeneruj"
    :processing="regenerating"
    @confirm="handleRegenerateConfirm"
  />

  <ConfirmDialog
    v-model:open="showDeleteDialog"
    title="Usuń jadłospis"
    description="Tej operacji nie można cofnąć. Jadłospis zostanie trwale usunięty."
    confirm-label="Usuń"
    variant="destructive"
    :processing="deleting"
    @confirm="handleDeleteConfirm"
  />
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import {
  MoreHorizontal,
  Eye,
  Download,
  RefreshCw,
  Trash2
} from 'lucide-vue-next'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import { Button } from '@/Components/ui/button'
import { toast } from 'vue-sonner'
import { formatDateRange } from '@/lib/format'
// Local type definition
interface MealPlanListItem {
  id: number
  start_date: string
  end_date: string
  status: string
  created_at: string
  updated_at: string
  meals_count: number
  logs_count: number
  links: {
    self: string
    pdf: string | null
  }
}
import ConfirmDialog from '@/Components/Common/ConfirmDialog.vue'

interface Props {
  item: MealPlanListItem
}

const props = defineProps<Props>()
// toast is imported directly from vue-sonner

// Dialog state
const showRegenerateDialog = ref(false)
const showDeleteDialog = ref(false)
const regenerating = ref(false)
const deleting = ref(false)

// Action availability based on status
const canDownloadPdf = computed(() => !!props.item.links.pdf)
const canRegenerate = computed(() =>
  props.item.status === 'done' || props.item.status === 'error'
)
const canDelete = computed(() => props.item.status !== 'processing')

// Handle view details
const handleViewDetails = () => {
  router.visit(props.item.links.self)
}

// Handle PDF download
const handleDownloadPdf = () => {
  if (!canDownloadPdf.value) return

  window.open(props.item.links.pdf as string, '_blank', 'noopener')
}

// Handle regenerate confirmation dialog
const handleRegenerateClick = () => {
  if (!canRegenerate.value) return
  showRegenerateDialog.value = true
}

// Handle regenerate confirm
const handleRegenerateConfirm = async () => {
  regenerating.value = true

  try {
    await router.put(
      route('meal-plans.update', { meal_plan: props.item.id }),
      { regenerate: true },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success('Regeneracja jadłospisu rozpoczęta.')
        },
        onError: (errors) => {
          const message = errors.status ?? errors.recipes ?? errors.regenerate ?? errors.message
          toast.error(message || 'Nie udało się zregenerować jadłospisu. Spróbuj ponownie.')
        },
        onFinish: () => {
          regenerating.value = false
          showRegenerateDialog.value = false
        }
      }
    )
  } catch (error) {
    console.error('Regeneration error:', error)
    toast.error('Wystąpił nieoczekiwany błąd. Spróbuj ponownie.')
    regenerating.value = false
    showRegenerateDialog.value = false
  }
}

// Handle delete confirmation dialog
const handleDeleteClick = () => {
  if (!canDelete.value) return
  showDeleteDialog.value = true
}

// Handle delete confirm
const handleDeleteConfirm = async () => {
  deleting.value = true

  try {
    await router.delete(
      route('meal-plans.destroy', { meal_plan: props.item.id }),
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success('Jadłospis został usunięty.')
        },
        onError: (errors) => {
          console.error('Delete failed:', errors)

          if (errors.message) {
            toast.error(errors.message)
          } else {
            toast.error('Nie udało się usunąć jadłospisu. Spróbuj ponownie.')
          }
        },
        onFinish: () => {
          deleting.value = false
          showDeleteDialog.value = false
        }
      }
    )
  } catch (error) {
    console.error('Delete error:', error)
    toast.error('Wystąpił nieoczekiwany błąd. Spróbuj ponownie.')
    deleting.value = false
    showDeleteDialog.value = false
  }
}
</script>
