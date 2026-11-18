<template>
  <Card class="flex h-full flex-col overflow-hidden">
    <div
      class="flex items-center justify-between gap-3 border-b-2 border-foreground px-5 py-2.5"
      :class="fillClass"
    >
      <span class="text-meta font-bold">{{ mealCategoryLabel(recipe.category) }}</span>
      <span class="font-extrabold tabular-nums">{{ formatNumber(recipe.calories) }} kcal</span>
    </div>

    <div class="flex flex-1 flex-col gap-2 p-5">
      <h2 class="text-section-title">
        <Link
          :href="route('recipes.show', recipe.id)"
          class="rounded-sm text-foreground hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >{{ recipe.name }}</Link>
      </h2>
      <p class="text-meta text-muted-foreground">
        {{ recipe.servings }} {{ pluralize(recipe.servings, { one: 'porcja', few: 'porcje', many: 'porcji' }) }} · {{ formatDate(recipe.created_at) }}
      </p>

      <div class="mt-auto flex items-center justify-end gap-2 pt-3">
        <Button variant="secondary" size="icon" as-child>
          <Link :href="route('recipes.edit', recipe.id)" :aria-label="`Edytuj ${recipe.name}`">
            <Pencil aria-hidden="true" />
          </Link>
        </Button>
        <Button
          variant="secondary"
          size="icon"
          :aria-label="`Usuń ${recipe.name}`"
          @click="emit('delete-requested', recipe)"
        >
          <Trash2 aria-hidden="true" />
        </Button>
      </div>
    </div>
  </Card>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import { formatDate, formatNumber, pluralize } from '@/lib/format.js';
import { MEAL_CATEGORY_FILL_CLASSES, mealCategoryLabel } from '@/lib/mealCategories.js';

const props = defineProps({
  recipe: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['delete-requested']);

const fillClass = computed(() => MEAL_CATEGORY_FILL_CLASSES[props.recipe.category] ?? 'bg-muted text-foreground');
</script>
