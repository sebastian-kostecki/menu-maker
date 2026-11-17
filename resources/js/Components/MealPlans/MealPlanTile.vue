<template>
  <Card class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:gap-6">
    <div class="flex min-w-0 flex-1 flex-col gap-1">
      <Link
        :href="item.links.self"
        class="rounded-sm text-section-title text-foreground hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
      >
        {{ range }}
      </Link>
      <p class="text-meta text-muted-foreground">
        Utworzono {{ formatDateTime(item.created_at) }}
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-4 lg:contents">
      <div class="lg:w-32">
        <StatusTag :value="item.status" />
      </div>

      <div class="flex items-center gap-3 lg:w-64">
        <div class="flex items-center gap-1.5" aria-hidden="true">
          <span
            v-for="(fill, index) in squares"
            :key="index"
            class="size-5.5 rounded-sm"
            :class="fill"
          />
        </div>
        <span class="text-meta tabular-nums">
          <span class="sr-only">Posiłki: </span>{{ item.meals_count }}/{{ TOTAL_MEALS }}
        </span>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 lg:w-72 lg:justify-end">
      <PdfDownloadMenu
        v-if="item.links.pdf && item.links.pdf_with_recipes"
        :links="{ pdf: item.links.pdf, pdf_with_recipes: item.links.pdf_with_recipes }"
        label="PDF"
        size="sm"
        :aria-label="`Pobierz PDF: ${range}`"
      />
      <Button size="sm" as-child>
        <Link :href="item.links.self" :aria-label="`Otwórz jadłospis: ${range}`">Otwórz</Link>
      </Button>
      <ActionDropdown :item="item" />
    </div>
  </Card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import { Card } from '@/Components/ui/card'
import StatusTag from '@/Components/MealPlans/StatusTag.vue'
import ActionDropdown from '@/Components/MealPlans/ActionDropdown.vue'
import PdfDownloadMenu from '@/Components/MealPlans/PdfDownloadMenu.vue'
import { formatDateRange, formatDateTime } from '@/lib/format'
import { MEAL_CATEGORIES } from '@/lib/mealCategories'
import type { MealPlanListItem } from '@/types/meal-plan'

interface Props {
  item: MealPlanListItem
}

const props = defineProps<Props>()

const DAYS_PER_WEEK = 7
const TOTAL_MEALS = MEAL_CATEGORIES.length * DAYS_PER_WEEK

// Full class strings so Tailwind can see them; square i uses the category i % 3.
const fillClasses = ['bg-meal-breakfast', 'bg-meal-supper', 'bg-meal-dinner']

const range = computed(() => formatDateRange(props.item.start_date, props.item.end_date))

// One square per full day's worth of meals (floor(meals_count / 3)); the rest stay muted.
const squares = computed(() => {
  const filled = Math.floor(props.item.meals_count / MEAL_CATEGORIES.length)

  return Array.from({ length: DAYS_PER_WEEK }, (_, index) =>
    index < filled ? fillClasses[index % fillClasses.length] : 'bg-muted'
  )
})
</script>
