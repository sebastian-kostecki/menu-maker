<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ArrowRight } from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'
import { Card } from '@/Components/ui/card'
import PdfDownloadMenu from '@/Components/MealPlans/PdfDownloadMenu.vue'
import StatusTag from '@/Components/MealPlans/StatusTag.vue'
import { formatDateRange, pluralize } from '@/lib/format'

const props = defineProps({
  mealPlan: { type: Object, default: null },
})

const mealsCount = computed(() => (props.mealPlan?.meals ?? []).filter((meal) => meal.recipe).length)
const mealsLabel = computed(() => pluralize(mealsCount.value, { one: 'posiłek', few: 'posiłki', many: 'posiłków' }))
const hasPdfLinks = computed(() => Boolean(props.mealPlan?.links?.pdf && props.mealPlan?.links?.pdf_with_recipes && props.mealPlan?.links?.pdf_menu))
</script>

<template>
  <Card class="flex flex-col gap-5 bg-highlight p-7 text-highlight-foreground">
    <template v-if="mealPlan">
      <div class="flex flex-wrap items-center gap-3">
        <p class="text-label">Ostatni jadłospis</p>
        <StatusTag value="done" />
      </div>
      <h2 class="text-page-title"><span class="sr-only">Ostatni jadłospis: </span>{{ formatDateRange(mealPlan.start_date, mealPlan.end_date) }}</h2>
      <p class="max-w-xl">{{ mealsCount }} {{ mealsLabel }}. Lista zakupów i ilości są już przeliczone.</p>
      <div class="flex flex-wrap items-center gap-3">
        <Button variant="inverse" as-child>
          <Link :href="mealPlan.links.self">
            Otwórz jadłospis
            <ArrowRight aria-hidden="true" />
          </Link>
        </Button>
        <PdfDownloadMenu
          v-if="hasPdfLinks"
          variant="inverse-outline"
          :links="{ pdf: mealPlan.links.pdf, pdf_with_recipes: mealPlan.links.pdf_with_recipes, pdf_menu: mealPlan.links.pdf_menu }"
        />
      </div>
    </template>
    <template v-else>
      <p class="text-label">Ostatni jadłospis</p>
      <h2 class="text-section-title">Nie masz jeszcze gotowego jadłospisu</h2>
      <p class="max-w-xl">Wygeneruj pierwszy, a pojawi się tutaj.</p>
    </template>
  </Card>
</template>
