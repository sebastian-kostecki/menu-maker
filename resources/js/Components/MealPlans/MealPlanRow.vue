<template>
  <TableRow>
    <!-- Week -->
    <TableCell>
      <Link
        :href="item.links.self"
        class="rounded-sm font-medium text-foreground hover:text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
      >
        {{ formatDateRange(item.start_date, item.end_date) }}
      </Link>
    </TableCell>

    <!-- Status -->
    <TableCell>
      <StatusTag :value="item.status" />
    </TableCell>

    <!-- Posiłki -->
    <TableCell class="hidden sm:table-cell">
      <div class="flex items-center justify-end gap-2">
        <ChefHat class="size-4 text-muted-foreground" aria-hidden="true" />
        <span class="text-sm tabular-nums">
          {{ item.meals_count }}
        </span>
      </div>
    </TableCell>

    <!-- Utworzono -->
    <TableCell class="hidden text-sm text-muted-foreground sm:table-cell">
      {{ formatDateTime(item.created_at) }}
    </TableCell>

    <!-- Akcje -->
    <TableCell class="text-right">
      <ActionDropdown :item="item" />
    </TableCell>
  </TableRow>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ChefHat } from 'lucide-vue-next'
import { TableRow, TableCell } from '@/Components/ui/table'
import StatusTag from '@/Components/MealPlans/StatusTag.vue'
import ActionDropdown from '@/Components/MealPlans/ActionDropdown.vue'
import { formatDateRange, formatDateTime } from '@/lib/format'

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
    pdf: string | null
    pdf_with_recipes: string | null
  }
}

interface Props {
  item: MealPlanListItem
}

defineProps<Props>()
</script>
