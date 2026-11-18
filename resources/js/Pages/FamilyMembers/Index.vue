<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Plus, Users } from 'lucide-vue-next'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import EmptyState from '@/Components/Common/EmptyState.vue'
import PageHeader from '@/Components/Common/PageHeader.vue'
import ServerPagination from '@/Components/Common/ServerPagination.vue'
import FamilyMemberDeleteDialog from '@/Components/FamilyMembers/FamilyMemberDeleteDialog.vue'
import FamilyMemberDialog from '@/Components/FamilyMembers/FamilyMemberDialog.vue'
import FamilyMemberTile from '@/Components/FamilyMembers/FamilyMemberTile.vue'
import { Button } from '@/Components/ui/button'
import { pluralize } from '@/lib/format'

const props = defineProps({
  familyMembers: {
    type: Object,
    required: true,
  },
})

// The avatar colour cycles by the tile's position on the current page.
const TILE_COLOURS = ['supper', 'dinner', 'breakfast']

const dialogOpen = ref(false)
const editingMember = ref(null)
const memberToDelete = ref(null)

const totalLabel = computed(() => {
  const total = props.familyMembers.total

  return `${total} ${pluralize(total, { one: 'osoba', few: 'osoby', many: 'osób' })}`
})

const openCreateDialog = () => {
  editingMember.value = null
  dialogOpen.value = true
}

const openEditDialog = (member) => {
  editingMember.value = member
  dialogOpen.value = true
}

const handlePageChange = (page) => {
  router.get(route('family-members.index'), { page }, {
    preserveScroll: true,
    replace: true,
  })
}
</script>

<template>
  <Head title="Członkowie rodziny" />

  <AuthenticatedLayout>
    <div class="mx-auto flex max-w-6xl flex-col gap-6">
      <PageHeader title="Członkowie rodziny">
        <template #description>
          Zarządzaj członkami swojej rodziny
          <span v-if="familyMembers.total > 0" class="ml-2">({{ totalLabel }})</span>
        </template>

        <template #actions>
          <Button @click="openCreateDialog">
            <Plus class="size-4" />
            Dodaj członka
          </Button>
        </template>
      </PageHeader>

      <EmptyState
        v-if="familyMembers.total === 0"
        class="py-12"
        :icon="Users"
        title="Brak członków rodziny"
        description="Rozpocznij od dodania pierwszego członka rodziny."
      >
        <Button @click="openCreateDialog">
          <Plus class="size-4" />
          Dodaj pierwszego członka
        </Button>
      </EmptyState>

      <template v-else>
        <ul aria-label="Członkowie rodziny" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          <li
            v-for="(member, index) in familyMembers.data"
            :key="member.id"
            class="grid"
          >
            <FamilyMemberTile
              :member="member"
              :colour="TILE_COLOURS[index % TILE_COLOURS.length]"
              @edit="openEditDialog"
              @delete="memberToDelete = $event"
            />
          </li>

          <li class="grid">
            <Button variant="dashed" size="tile" @click="openCreateDialog">
              <span
                aria-hidden="true"
                class="flex size-12 items-center justify-center rounded-full bg-primary text-primary-foreground"
              >
                <Plus />
              </span>
              Dodaj członka rodziny
            </Button>
          </li>
        </ul>

        <ServerPagination :meta="familyMembers" @page-change="handlePageChange" />
      </template>
    </div>

    <FamilyMemberDialog v-model:open="dialogOpen" :member="editingMember" />
    <FamilyMemberDeleteDialog :member="memberToDelete" @close="memberToDelete = null" />
  </AuthenticatedLayout>
</template>
