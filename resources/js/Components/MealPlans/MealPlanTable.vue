<template>
  <div class="bg-white shadow-sm border rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <SortableTableHeader
              field="start_date"
              :active-field="sort"
              :direction="direction"
              label="Date Range"
              @change="handleSortChange"
            />
            <SortableTableHeader
              field="status"
              :active-field="sort"
              :direction="direction"
              label="Status"
              @change="handleSortChange"
            />
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Meals
            </th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Logs
            </th>
            <SortableTableHeader
              field="created_at"
              :active-field="sort"
              :direction="direction"
              label="Created"
              @change="handleSortChange"
            />
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <MealPlanRow
            v-for="item in items"
            :key="item.id"
            :item="item"
          />
          <tr v-if="items.length === 0">
            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
              <div class="flex flex-col items-center gap-2">
                <Calendar class="h-12 w-12 text-gray-300" />
                <p class="text-lg font-medium">No meal plans found</p>
                <p class="text-sm">Create your first meal plan to get started.</p>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Calendar } from 'lucide-vue-next'
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
import SortableTableHeader from '@/Components/MealPlans/SortableTableHeader.vue'
import MealPlanRow from '@/Components/MealPlans/MealPlanRow.vue'

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
