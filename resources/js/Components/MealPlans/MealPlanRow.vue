<template>
  <TableRow>
    <!-- Date Range -->
    <TableCell class="font-medium">
      <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
        {{ formatDateRange(item.start_date, item.end_date) }}
      </div>
      <div class="text-sm text-gray-500 dark:text-gray-400">
        {{ formatDuration(item.start_date, item.end_date) }}
      </div>
    </TableCell>

    <!-- Status -->
    <TableCell>
      <StatusTag :value="item.status" />
    </TableCell>

    <!-- Meals Count -->
    <TableCell class="text-gray-600 dark:text-gray-300">
      <div class="flex items-center gap-2">
        <ChefHat class="h-4 w-4 text-gray-400 dark:text-gray-500" />
        <span class="text-sm">
          {{ item.meals_count }}
        </span>
      </div>
    </TableCell>

    <!-- Logs Count -->
    <TableCell class="text-gray-600 dark:text-gray-300">
      <div class="flex items-center gap-2">
        <FileText class="h-4 w-4 text-gray-400 dark:text-gray-500" />
        <span class="text-sm">
          {{ item.logs_count }}
        </span>
      </div>
    </TableCell>

    <!-- Created At -->
    <TableCell class="text-gray-600 dark:text-gray-300">
      <div class="text-sm">
        {{ formatDateTime(item.created_at) }}
      </div>
    </TableCell>

    <!-- Actions -->
    <TableCell class="text-right">
      <ActionDropdown :item="item" />
    </TableCell>
  </TableRow>
</template>

<script setup lang="ts">
import { ChefHat, FileText } from 'lucide-vue-next'
import { TableRow, TableCell } from '@/Components/ui/table'
import StatusTag from '@/Components/MealPlans/StatusTag.vue'
import ActionDropdown from '@/Components/MealPlans/ActionDropdown.vue'

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
  }
}

interface Props {
  item: MealPlanListItem
}

const props = defineProps<Props>()

// Format date range display
const formatDateRange = (startDate: string, endDate: string): string => {
  const start = new Date(startDate).toLocaleDateString('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
  const end = new Date(endDate).toLocaleDateString('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
  return `${start} - ${end}`
}

// Calculate and format duration
const formatDuration = (startDate: string, endDate: string): string => {
  const start = new Date(startDate)
  const end = new Date(endDate)
  const diffTime = Math.abs(end.getTime() - start.getTime())
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1 // +1 because it's inclusive
  return `${diffDays} ${diffDays === 1 ? 'day' : 'days'}`
}

// Format created date and time
const formatDateTime = (dateTime: string): string => {
  return new Date(dateTime).toLocaleDateString('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>
