<script setup>
import { ref } from 'vue'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow
} from '@/Components/ui/table'
import { Button } from '@/Components/ui/button'
import ConfirmDialog from './ConfirmDialog.vue'
import { Edit, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  members: {
    type: Array,
    required: true
  }
})

const emit = defineEmits(['edit', 'deleted'])

// Local state for delete confirmation
const showDeleteDialog = ref(false)
const memberToDelete = ref(null)

// Helper functions
const formatDate = (dateString) => {
  const date = new Date(dateString)
  return date.toLocaleDateString('pl-PL', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const formatGender = (gender) => {
  return gender === 'male' ? 'Mężczyzna' : 'Kobieta'
}

const calculateAge = (birthDate) => {
  const today = new Date()
  const birth = new Date(birthDate)
  let age = today.getFullYear() - birth.getFullYear()
  const monthDiff = today.getMonth() - birth.getMonth()

  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
    age--
  }

  return age
}

// Event handlers
const handleEdit = (member) => {
  emit('edit', member)
}

const handleDeleteClick = (member) => {
  memberToDelete.value = member
  showDeleteDialog.value = true
}

const handleDeleteConfirmed = (memberId) => {
  emit('deleted', memberId)
  showDeleteDialog.value = false
  memberToDelete.value = null
}

const handleDeleteCancelled = () => {
  showDeleteDialog.value = false
  memberToDelete.value = null
}
</script>

<template>
  <div class="space-y-4">
    <!-- Table -->
    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
      <Table>
        <TableHeader>
          <TableRow class="bg-gray-50 dark:bg-gray-800">
            <TableHead class="w-[200px] font-semibold">
              Imię
            </TableHead>
            <TableHead class="w-[150px] font-semibold">
              Data urodzenia
            </TableHead>
            <TableHead class="w-[100px] font-semibold">
              Wiek
            </TableHead>
            <TableHead class="w-[120px] font-semibold">
              Płeć
            </TableHead>
            <TableHead class="w-[120px] text-right font-semibold">
              Akcje
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="member in members"
            :key="member.id"
            class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
          >
            <TableCell class="font-medium">
              {{ member.first_name }}
            </TableCell>
            <TableCell class="text-gray-600 dark:text-gray-300">
              {{ formatDate(member.birth_date) }}
            </TableCell>
            <TableCell class="text-gray-600 dark:text-gray-300">
              {{ calculateAge(member.birth_date) }} lat
            </TableCell>
            <TableCell class="text-gray-600 dark:text-gray-300">
              {{ formatGender(member.gender) }}
            </TableCell>
            <TableCell class="text-right">
              <div class="flex items-center justify-end gap-2">
                <Button
                  variant="ghost"
                  size="sm"
                  @click="handleEdit(member)"
                  class="h-8 w-8 p-0 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-blue-900/20"
                  :aria-label="`Edytuj ${member.first_name}`"
                >
                  <Edit class="h-4 w-4" />
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  @click="handleDeleteClick(member)"
                  class="h-8 w-8 p-0 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
                  :aria-label="`Usuń ${member.first_name}`"
                >
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <!-- Delete Confirmation Dialog -->
    <ConfirmDialog
      v-if="memberToDelete"
      :open="showDeleteDialog"
      :member-id="memberToDelete.id"
      :member-name="memberToDelete.first_name"
      @confirmed="handleDeleteConfirmed"
      @cancelled="handleDeleteCancelled"
    />
  </div>
</template>
