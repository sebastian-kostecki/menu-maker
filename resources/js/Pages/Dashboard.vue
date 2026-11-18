<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import PageHeader from '@/Components/Common/PageHeader.vue'
import FirstRunTile from '@/Components/Dashboard/FirstRunTile.vue'
import LatestPlanHero from '@/Components/Dashboard/LatestPlanHero.vue'
import StatCard from '@/Components/Dashboard/StatCard.vue'
import WeekOverview from '@/Components/Dashboard/WeekOverview.vue'
import GenerateMealPlanDialog from '@/Components/MealPlans/GenerateMealPlanDialog.vue'
import { Plus, ChefHat, CalendarDays } from 'lucide-vue-next'

const props = defineProps({
  stats: { type: Object, required: true },
  latestMealPlan: { type: Object, default: null },
})

const page = usePage()
const greeting = computed(() => `Dzień dobry, ${page.props.auth.user.name}.`)
const isFirstRun = computed(() => props.stats.recipes === 0 && props.stats.mealPlans === 0)
</script>

<template>
  <Head title="Pulpit" />

  <AuthenticatedLayout>
    <div class="mx-auto flex max-w-6xl flex-col gap-6">
      <PageHeader title="Pulpit" :description="greeting">
        <template v-if="!isFirstRun" #actions>
          <GenerateMealPlanDialog>
            <Button variant="outline">
              <CalendarDays aria-hidden="true" />
              Wygeneruj jadłospis
            </Button>
          </GenerateMealPlanDialog>
          <Button as-child>
            <Link :href="route('recipes.create')">
              <Plus aria-hidden="true" />
              Dodaj przepis
            </Link>
          </Button>
        </template>
      </PageHeader>

      <FirstRunTile v-if="isFirstRun" />

      <template v-else>
        <div class="grid gap-6 lg:grid-cols-3">
          <LatestPlanHero :meal-plan="latestMealPlan" class="lg:col-span-2" />

          <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-1">
            <StatCard tone="breakfast" :value="stats.recipes" :forms="{ one: 'przepis', few: 'przepisy', many: 'przepisów' }" :icon="ChefHat" />
            <StatCard tone="supper" :value="stats.mealPlans" :forms="{ one: 'jadłospis', few: 'jadłospisy', many: 'jadłospisów' }" :icon="CalendarDays" />
          </div>
        </div>

        <WeekOverview
          v-if="latestMealPlan"
          :start-date="latestMealPlan.start_date"
          :end-date="latestMealPlan.end_date"
          :meals="latestMealPlan.meals"
        />
      </template>
    </div>
  </AuthenticatedLayout>
</template>
