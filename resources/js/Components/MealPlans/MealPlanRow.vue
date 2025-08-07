<template>
  <tr class="hover:bg-gray-50 transition-colors">
    <!-- Date Range -->
    <td class="px-6 py-4 whitespace-nowrap">
      <div class="text-sm font-medium text-gray-900">
        {{ formatDateRange(item.start_date, item.end_date) }}
      </div>
      <div class="text-sm text-gray-500">
        {{ formatDuration(item.start_date, item.end_date) }}
      </div>
    </td>

    <!-- Status -->
    <td class="px-6 py-4 whitespace-nowrap">
      <StatusTag :value="item.status" />
    </td>

    <!-- Meals Count -->
    <td class="px-6 py-4 whitespace-nowrap">
      <div class="flex items-center gap-2">
        <ChefHat class="h-4 w-4 text-gray-400" />
        <span class="text-sm text-gray-900">
          {{ item.meals_count }}
        </span>
      </div>
    </td>

    <!-- Logs Count -->
    <td class="px-6 py-4 whitespace-nowrap">
      <div class="flex items-center gap-2">
        <FileText class="h-4 w-4 text-gray-400" />
        <span class="text-sm text-gray-900">
          {{ item.logs_count }}
        </span>
      </div>
    </td>

    <!-- Created At -->
    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
      {{ formatDateTime(item.created_at) }}
    </td>

    <!-- Actions -->
    <td class="px-6 py-4 whitespace-nowrap text-right">
      <ActionDropdown :item="item" />
    </td>
  </tr>
</template>

<script setup lang="ts">
import { ChefHat, FileText } from 'lucide-vue-next'
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
import StatusTag from '@/Components/MealPlans/StatusTag.vue'
import ActionDropdown from '@/Components/MealPlans/ActionDropdown.vue'

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
