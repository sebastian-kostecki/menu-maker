<template>
  <section class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800 sm:p-6">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
      Lista zakupów
    </h2>

    <p v-if="list.items.length === 0" class="mt-3 text-sm text-gray-600 dark:text-gray-400">
      Przepisy w tym jadłospisie nie mają składników.
    </p>

    <ul v-else class="mt-3 divide-y divide-gray-100 text-sm dark:divide-gray-700" data-testid="shopping-list">
      <li
        v-for="item in list.items"
        :key="`${item.ingredient_id}-${item.unit}`"
        class="flex items-baseline justify-between gap-4 py-2"
      >
        <span class="font-medium text-gray-900 dark:text-gray-100">{{ item.name }}</span>
        <span class="whitespace-nowrap text-gray-700 dark:text-gray-300">{{ item.display }}</span>
      </li>
    </ul>

    <p v-if="list.recipes_without_ingredients.length > 0" class="mt-4 text-xs text-gray-500 dark:text-gray-400">
      Bez składników (nie ma ich na liście): {{ list.recipes_without_ingredients.join(', ') }}.
    </p>
  </section>
</template>

<script setup lang="ts">
export interface ShoppingListItem {
  ingredient_id: number
  name: string
  quantity: string
  unit: string
  display: string
}

export interface ShoppingListPayload {
  items: ShoppingListItem[]
  recipes_without_ingredients: string[]
}

defineProps<{
  list: ShoppingListPayload
}>()
</script>
