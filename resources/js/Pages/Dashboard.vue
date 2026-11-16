<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import PageHeader from '@/Components/Common/PageHeader.vue'
import EmptyState from '@/Components/Common/EmptyState.vue'
import StatCard from '@/Components/Dashboard/StatCard.vue'
import PdfDownloadMenu from '@/Components/MealPlans/PdfDownloadMenu.vue'
import GenerateMealPlanDialog from '@/Components/MealPlans/GenerateMealPlanDialog.vue'
import { formatDateRange } from '@/lib/format'
import { Plus, ChefHat, CalendarDays, TriangleAlert } from 'lucide-vue-next'

const props = defineProps({
  stats: { type: Object, required: true },
  latestMealPlan: { type: Object, default: null },
  recipeShortfall: { type: Array, default: () => [] },
})

const isFirstRun = computed(() => props.stats.recipes === 0 && props.stats.mealPlans === 0)
</script>

<template>
  <Head title="Pulpit" />

  <AuthenticatedLayout>
    <div class="mx-auto flex max-w-6xl flex-col gap-6">
      <PageHeader title="Pulpit" />

      <EmptyState
        v-if="isFirstRun"
        class="py-12"
        :icon="ChefHat"
        title="Zacznij od pierwszego przepisu"
        description="Dodaj przepisy do śniadań, obiadów i kolacji. Gdy będzie ich dość, wygenerujesz z nich jadłospis na cały tydzień."
      >
        <Button as-child>
          <Link :href="route('recipes.create')">Dodaj pierwszy przepis</Link>
        </Button>
      </EmptyState>

      <template v-else>
        <Alert v-if="recipeShortfall.length" variant="warning">
          <TriangleAlert aria-hidden="true" />
          <AlertTitle>Nie można jeszcze wygenerować tygodnia</AlertTitle>
          <AlertDescription>
            <p v-for="message in recipeShortfall" :key="message">{{ message }}</p>
          </AlertDescription>
          <div class="col-start-2 mt-3">
            <Button variant="outline" as-child>
              <Link :href="route('recipes.create')">Dodaj brakujące przepisy</Link>
            </Button>
          </div>
        </Alert>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <StatCard label="Przepisy" :value="stats.recipes" :icon="ChefHat" />
          <StatCard label="Jadłospisy" :value="stats.mealPlans" :icon="CalendarDays" />

          <Card class="lg:col-span-2">
            <CardHeader>
              <CardTitle as="h2">Ostatni jadłospis</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
              <template v-if="latestMealPlan">
                <p class="text-lg font-semibold">
                  {{ formatDateRange(latestMealPlan.start_date, latestMealPlan.end_date) }}
                </p>
                <div class="flex flex-wrap items-center gap-2">
                  <Button variant="outline" as-child>
                    <Link :href="latestMealPlan.links.self">Otwórz jadłospis</Link>
                  </Button>
                  <PdfDownloadMenu
                    v-if="latestMealPlan.links.pdf && latestMealPlan.links.pdf_with_recipes"
                    :links="{ pdf: latestMealPlan.links.pdf, pdf_with_recipes: latestMealPlan.links.pdf_with_recipes }"
                  />
                </div>
              </template>
              <p v-else class="text-sm text-muted-foreground">
                Nie masz jeszcze gotowego jadłospisu. Wygeneruj pierwszy, a pojawi się tutaj.
              </p>
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <CardTitle as="h2">Szybkie akcje</CardTitle>
          </CardHeader>
          <CardContent class="flex flex-wrap items-center gap-2">
            <Button as-child>
              <Link :href="route('recipes.create')">
                <Plus class="size-4" aria-hidden="true" />
                Dodaj przepis
              </Link>
            </Button>
            <GenerateMealPlanDialog>
              <Button variant="outline">
                <CalendarDays class="size-4" aria-hidden="true" />
                Wygeneruj jadłospis
              </Button>
            </GenerateMealPlanDialog>
          </CardContent>
        </Card>
      </template>
    </div>
  </AuthenticatedLayout>
</template>
