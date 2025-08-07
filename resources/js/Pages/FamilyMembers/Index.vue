<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import FamilyMemberTable from './FamilyMemberTable.vue'
import InlineEditDialog from './InlineEditDialog.vue'
import { Button } from '@/Components/ui/button'
import { Plus } from 'lucide-vue-next'

const props = defineProps({
  familyMembers: {
    type: Object,
    required: true
  }
})

// Local state management
const members = ref([...props.familyMembers.data])
const showEditDialog = ref(false)
const editingMember = ref(null)

// Computed properties
const totalMembers = computed(() => members.value.length)

// Event handlers
const handleAddMember = () => {
  editingMember.value = null
  showEditDialog.value = true
}

const handleEditMember = (member) => {
  editingMember.value = member
  showEditDialog.value = true
}

const handleMemberSaved = (savedMember) => {
  if (editingMember.value) {
    // Update existing member
    const index = members.value.findIndex(m => m.id === savedMember.id)
    if (index !== -1) {
      members.value[index] = savedMember
    }
  } else {
    // Add new member at the beginning
    members.value.unshift(savedMember)
  }
  showEditDialog.value = false
  editingMember.value = null
}

const handleMemberDeleted = (memberId) => {
  const index = members.value.findIndex(m => m.id === memberId)
  if (index !== -1) {
    members.value.splice(index, 1)
  }
}

const handleDialogClose = () => {
  showEditDialog.value = false
  editingMember.value = null
}
</script>

<template>
  <Head title="Członkowie rodziny" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Członkowie rodziny
          </h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Zarządzaj członkami swojej rodziny
            <span v-if="totalMembers > 0" class="ml-2">
              ({{ totalMembers }} {{ totalMembers === 1 ? 'osoba' : totalMembers < 5 ? 'osoby' : 'osób' }})
            </span>
          </p>
        </div>

        <Button @click="handleAddMember" class="flex items-center gap-2">
          <Plus class="h-4 w-4" />
          Dodaj członka
        </Button>
      </div>
    </template>

    <div class="mx-auto max-w-7xl">
      <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
        <div class="p-6">
          <!-- Family Members Table -->
          <FamilyMemberTable
            :members="members"
            @edit="handleEditMember"
            @deleted="handleMemberDeleted"
          />

          <!-- Empty state when no members -->
          <div v-if="members.length === 0" class="text-center py-12">
            <div class="mx-auto h-12 w-12 text-gray-400">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.196-2.196M17 20H7m10 0v-2c0-5.523-4.477-10-10-10s-10 4.477-10 10v2m10 0V9a3 3 0 114 2.83V20M7 20H2v-2a3 3 0 015.196-2.196M7 20v-2m6-6a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </div>
            <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
              Brak członków rodziny
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              Rozpocznij od dodania pierwszego członka rodziny.
            </p>
            <div class="mt-6">
              <Button @click="handleAddMember" class="flex items-center gap-2">
                <Plus class="h-4 w-4" />
                Dodaj pierwszego członka
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit/Create Dialog -->
    <InlineEditDialog
      :open="showEditDialog"
      :member="editingMember"
      @saved="handleMemberSaved"
      @close="handleDialogClose"
    />
  </AuthenticatedLayout>
</template>
