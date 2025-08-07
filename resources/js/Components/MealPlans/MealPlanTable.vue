<template>
  <div class="space-y-4">
    <!-- Table -->
    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
      <Table>
        <TableHeader>
          <TableRow class="bg-gray-50 dark:bg-gray-800">
            <TableHead class="w-[200px] font-semibold">
              <SortableHeader
                field="start_date"
                :current-sort="{ field: sort, direction }"
                @sort-change="handleSortChange"
              >
                Date Range
              </SortableHeader>
            </TableHead>
            <TableHead class="w-[120px] font-semibold">
              <SortableHeader
                field="status"
                :current-sort="{ field: sort, direction }"
                @sort-change="handleSortChange"
              >
                Status
              </SortableHeader>
            </TableHead>
            <TableHead class="w-[100px] font-semibold">Meals</TableHead>
            <TableHead class="w-[100px] font-semibold">Logs</TableHead>
            <TableHead class="w-[120px] font-semibold">
              <SortableHeader
                field="created_at"
                :current-sort="{ field: sort, direction }"
                @sort-change="handleSortChange"
              >
                Created
              </SortableHeader>
            </TableHead>
            <TableHead class="w-[120px] text-right font-semibold">Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <MealPlanRow
            v-for="item in items"
            :key="item.id"
            :item="item"
          />
          <TableRow v-if="items.length === 0">
            <TableCell :colspan="6" class="text-center py-12 text-gray-500 dark:text-gray-400">
              <div class="flex flex-col items-center gap-2">
                <Calendar class="h-12 w-12 text-gray-300" />
                <p class="text-lg font-medium">No meal plans found</p>
                <p class="text-sm">Create your first meal plan to get started.</p>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Calendar } from 'lucide-vue-next'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import SortableHeader from '@/Components/MealPlans/SortableHeader.vue'
import MealPlanRow from '@/Components/MealPlans/MealPlanRow.vue'

// Local type definitions
type SortDirection = 'asc' | 'desc'

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
  }
}

interface Props {
  items: MealPlanListItem[]
  sort?: string
  direction?: SortDirection
}

interface Emits {
  (e: 'sort-change', value: { field: string, direction: SortDirection }): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// Handle sort change from table headers
const handleSortChange = ({ field, direction }: { field: string, direction: SortDirection }) => {
  // Validate sort field
  const allowedSortFields = ['start_date', 'end_date', 'status', 'created_at']

  if (allowedSortFields.includes(field)) {
    emit('sort-change', { field, direction })
  }
}
</script>
