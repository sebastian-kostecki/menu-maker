<template>
  <section
    class="flex flex-col gap-4 rounded-lg border-2 border-foreground px-5 py-7 shadow-sticker md:px-8"
    :class="fillClass"
  >
    <p class="flex items-center gap-2 font-bold">
      <component :is="icon" v-if="icon" class="size-5" aria-hidden="true" />
      {{ mealCategoryLabel(recipe.category) }}
    </p>
    <component :is="`h${headingLevel}`" class="break-words text-page-title md:text-hero">{{ recipe.name }}</component>
    <RecipeMetaBadges
      :calories="recipe.calories || 0"
      :servings="recipe.servings || 1"
    />
  </section>
</template>

<script setup>
import { computed } from 'vue';
import RecipeMetaBadges from './RecipeMetaBadges.vue';
import { MEAL_CATEGORY_FILL_CLASSES, MEAL_CATEGORY_ICONS, mealCategoryLabel } from '@/lib/mealCategories.js';

const props = defineProps({
  recipe: {
    type: Object,
    required: true,
  },
  headingLevel: {
    type: Number,
    default: 1,
    validator: (value) => value === 1 || value === 2,
  },
});

const fillClass = computed(() => MEAL_CATEGORY_FILL_CLASSES[props.recipe.category] ?? 'bg-muted text-foreground');
const icon = computed(() => MEAL_CATEGORY_ICONS[props.recipe.category] ?? null);
</script>
