<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
        <MoreHorizontal class="h-4 w-4" />
        <span class="sr-only">Otwórz menu</span>
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-56">
      <!-- View Details -->
      <DropdownMenuItem @click="handleViewDetails">
        <Eye class="mr-2 h-4 w-4" />
        Szczegóły
      </DropdownMenuItem>

      <!-- Download PDF -->
      <DropdownMenuItem
        :disabled="!canDownloadPdf"
        @click="handleDownloadPdf"
      >
        <Download class="mr-2 h-4 w-4" />
        Pobierz PDF
      </DropdownMenuItem>

      <DropdownMenuSeparator />

      <!-- Regenerate -->
      <DropdownMenuItem
        :disabled="!canRegenerate"
        @click="handleRegenerateClick"
      >
        <RefreshCw class="mr-2 h-4 w-4" />
        Regeneruj
      </DropdownMenuItem>

      <!-- Delete -->
      <DropdownMenuItem
        :disabled="!canDelete"
        @click="handleDeleteClick"
        class="text-red-600 focus:text-red-600 focus:bg-red-50"
      >
        <Trash2 class="mr-2 h-4 w-4" />
        Usuń
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>

  <!-- Confirm Dialogs -->
  <ConfirmDialog
    v-if="showRegenerateDialog"
    title="Regeneruj jadłospis"
    description="Obecne posiłki zostaną zastąpione nowym losowaniem przepisów. Czy chcesz kontynuować?"
    confirm-text="Regeneruj"
    @confirm="handleRegenerateConfirm"
    @cancel="showRegenerateDialog = false"
  />

  <ConfirmDialog
    v-if="showDeleteDialog"
    title="Usuń jadłospis"
    description="Tej operacji nie można cofnąć. Jadłospis zostanie trwale usunięty."
    confirm-text="Usuń"
    variant="destructive"
    @confirm="handleDeleteConfirm"
    @cancel="showDeleteDialog = false"
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
  pdf_path?: string | null
  links: {
    self: string
  }
}
import ConfirmDialog from '@/Components/MealPlans/ConfirmDialog.vue'

interface Props {
  item: MealPlanListItem
}

const props = defineProps<Props>()
// toast is imported directly from vue-sonner

// Dialog state
const showRegenerateDialog = ref(false)
const showDeleteDialog = ref(false)

// Action availability based on status
const canDownloadPdf = computed(() => props.item.status === 'done' && !!props.item.pdf_path)
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

  const pdfUrl = route('meal-plans.pdf', { meal_plan: props.item.id })
  window.open(pdfUrl, '_blank')
}

// Handle regenerate confirmation dialog
const handleRegenerateClick = () => {
  if (!canRegenerate.value) return
  showRegenerateDialog.value = true
}

// Handle regenerate confirm
const handleRegenerateConfirm = async () => {
  showRegenerateDialog.value = false

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
        }
      }
    )
  } catch (error) {
    console.error('Regeneration error:', error)
    toast.error('Wystąpił nieoczekiwany błąd. Spróbuj ponownie.')
  }
}

// Handle delete confirmation dialog
const handleDeleteClick = () => {
  if (!canDelete.value) return
  showDeleteDialog.value = true
}

// Handle delete confirm
const handleDeleteConfirm = async () => {
  showDeleteDialog.value = false

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
        }
      }
    )
  } catch (error) {
    console.error('Delete error:', error)
    toast.error('Wystąpił nieoczekiwany błąd. Spróbuj ponownie.')
  }
}
</script>
