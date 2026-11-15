<script setup>
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import { Plus, Users } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import FamilyMemberDeleteDialog from '@/Components/FamilyMembers/FamilyMemberDeleteDialog.vue'
import FamilyMemberDialog from '@/Components/FamilyMembers/FamilyMemberDialog.vue'
import FamilyMemberPagination from '@/Components/FamilyMembers/FamilyMemberPagination.vue'
import FamilyMemberTable from '@/Components/FamilyMembers/FamilyMemberTable.vue'
import { Button } from '@/Components/ui/button'
import { pluralizePl } from '@/lib/pluralizePl'

const props = defineProps({
  familyMembers: {
    type: Object,
    required: true,
  },
})

const dialogOpen = ref(false)
const editingMember = ref(null)
const memberToDelete = ref(null)

const totalLabel = computed(() => {
  const total = props.familyMembers.total

  return `${total} ${pluralizePl(total, 'osoba', 'osoby', 'osób')}`
})

const openCreateDialog = () => {
  editingMember.value = null
  dialogOpen.value = true
}

const openEditDialog = (member) => {
  editingMember.value = member
  dialogOpen.value = true
}
</script>

<template>
  <Head title="Członkowie rodziny" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold tracking-tight">Członkowie rodziny</h1>
          <p class="mt-1 text-sm text-muted-foreground">
            Zarządzaj członkami swojej rodziny
            <span v-if="familyMembers.total > 0" class="ml-2">({{ totalLabel }})</span>
          </p>
        </div>

        <Button @click="openCreateDialog">
          <Plus class="size-4" />
          Dodaj członka
        </Button>
      </div>
    </template>

    <div class="mx-auto max-w-6xl">
      <div class="overflow-hidden rounded-lg border border-border bg-card">
        <div v-if="familyMembers.total === 0" class="flex flex-col items-center gap-2 px-6 py-12 text-center">
          <Users class="size-10 text-muted-foreground" />
          <h2 class="text-sm font-semibold">Brak członków rodziny</h2>
          <p class="text-sm text-muted-foreground">Rozpocznij od dodania pierwszego członka rodziny.</p>
          <Button class="mt-4" @click="openCreateDialog">
            <Plus class="size-4" />
            Dodaj pierwszego członka
          </Button>
        </div>

        <template v-else>
          <FamilyMemberTable
            :members="familyMembers.data"
            @edit="openEditDialog"
            @delete="memberToDelete = $event"
          />
          <FamilyMemberPagination
            :current-page="familyMembers.current_page"
            :last-page="familyMembers.last_page"
            :prev-page-url="familyMembers.prev_page_url"
            :next-page-url="familyMembers.next_page_url"
          />
        </template>
      </div>
    </div>

    <FamilyMemberDialog v-model:open="dialogOpen" :member="editingMember" />
    <FamilyMemberDeleteDialog :member="memberToDelete" @close="memberToDelete = null" />
  </AuthenticatedLayout>
</template>
