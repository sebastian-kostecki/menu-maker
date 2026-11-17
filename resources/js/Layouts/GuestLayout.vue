<script setup>
import AppBrand from '@/Components/Common/AppBrand.vue'
import { MEAL_CATEGORIES, MEAL_CATEGORY_LABELS } from '@/lib/mealCategories'

defineProps({
  title: { type: String, required: true },
  description: { type: String, default: null },
  slogan: { type: String, default: 'Cały tydzień jedzenia na jednej kartce.' },
})

const categoryBlockClasses = {
  breakfast: 'bg-meal-breakfast text-meal-breakfast-foreground',
  supper: 'bg-meal-supper text-meal-supper-foreground',
  dinner: 'bg-meal-dinner text-meal-dinner-foreground',
}
</script>

<template>
  <div class="flex min-h-screen flex-col bg-background text-foreground lg:grid lg:grid-cols-2">
    <aside
      class="flex flex-col justify-between gap-6 bg-highlight px-4 py-6 text-highlight-foreground sm:px-8 lg:px-12 lg:py-10"
    >
      <AppBrand tone="inverse" :href="route('login')" />

      <p class="hidden max-w-115 text-hero lg:block">
        {{ slogan }}
      </p>

      <div class="flex gap-3">
        <div
          v-for="category in MEAL_CATEGORIES"
          :key="category"
          :class="[
            'flex h-12 flex-1 items-end rounded-lg border-2 border-foreground px-3.5 pb-2.5 text-sm font-bold lg:h-18',
            categoryBlockClasses[category],
          ]"
        >
          {{ MEAL_CATEGORY_LABELS[category] }}
        </div>
      </div>
    </aside>

    <main class="flex flex-1 flex-col justify-center px-4 py-10 sm:px-8 lg:px-12 xl:px-22">
      <div class="mx-auto flex w-full max-w-116 flex-col gap-5 lg:mx-0">
        <div class="flex flex-col gap-3">
          <h1 class="text-page-title">{{ title }}</h1>
          <p v-if="description" class="text-muted-foreground">{{ description }}</p>
        </div>
        <slot />
      </div>
    </main>
  </div>
</template>
