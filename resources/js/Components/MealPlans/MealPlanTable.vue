<template>
  <div class="space-y-4">
    <!-- Table -->
    <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
      <Table>
        <TableHeader>
          <TableRow class="bg-gray-50 dark:bg-gray-800">
            <SortableHeader
              class="w-[200px] font-semibold"
              column="start_date"
              :sort="sort ?? ''"
              :direction="direction ?? 'asc'"
              @sort-change="handleSortChange"
            >
              Date Range
            </SortableHeader>
            <SortableHeader
              class="w-[120px] font-semibold"
              column="status"
              :sort="sort ?? ''"
              :direction="direction ?? 'asc'"
              @sort-change="handleSortChange"
            >
              Status
            </SortableHeader>
            <TableHead class="w-[100px] font-semibold">Meals</TableHead>
            <TableHead class="w-[100px] font-semibold">Logs</TableHead>
            <SortableHeader
              class="w-[120px] font-semibold"
              column="created_at"
              :sort="sort ?? ''"
              :direction="direction ?? 'asc'"
              @sort-change="handleSortChange"
            >
              Created
            </SortableHeader>
            <TableHead class="w-[120px] text-right font-semibold">Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <MealPlanRow
            v-for="item in items"
            :key="item.id"
            :item="item"
          />
          <TableEmpty v-if="items.length === 0" :colspan="6" class="whitespace-normal">
            <EmptyState
              v-if="filtered"
              :icon="CalendarDays"
              title="Brak jadłospisów o wybranym statusie"
            >
              <Button variant="outline" @click="emit('clear-filters')">
                Wyczyść filtry
              </Button>
            </EmptyState>
            <EmptyState
              v-else
              :icon="CalendarDays"
              title="Nie masz jeszcze jadłospisów"
              description="Wygeneruj tydzień 7 dni × 3 posiłki z własnych przepisów."
            >
              <GenerateMealPlanDialog label="Wygeneruj pierwszy jadłospis" />
            </EmptyState>
          </TableEmpty>
        </TableBody>
      </Table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { CalendarDays } from 'lucide-vue-next'
import {
  Table,
  TableBody,
  TableEmpty,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import { Button } from '@/Components/ui/button'
import EmptyState from '@/Components/Common/EmptyState.vue'
import GenerateMealPlanDialog from '@/Components/MealPlans/GenerateMealPlanDialog.vue'
import SortableHeader from '@/Components/Common/SortableHeader.vue'
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
    pdf: string | null
  }
}

interface Props {
  items: MealPlanListItem[]
  sort?: string
  direction?: SortDirection
  filtered?: boolean
}

interface Emits {
  (e: 'sort-change', value: { column: string, direction: SortDirection }): void
  (e: 'clear-filters'): void
}

withDefaults(defineProps<Props>(), { filtered: false })
const emit = defineEmits<Emits>()

// Handle sort change from table headers
const handleSortChange = ({ column, direction }: { column: string, direction: SortDirection }) => {
  // Validate sort column
  const allowedSortFields = ['start_date', 'end_date', 'status', 'created_at']

  if (allowedSortFields.includes(column)) {
    emit('sort-change', { column, direction })
  }
}
</script>
