<template>
  <Card>
    <CardHeader>
      <CardTitle class="flex items-center gap-2 text-lg">
        <ShoppingBasket class="size-4" aria-hidden="true" />
        Lista zakupów
      </CardTitle>
    </CardHeader>

    <CardContent class="text-sm">
      <p v-if="list.items.length === 0" class="text-muted-foreground">
        Przepisy w tym jadłospisie nie mają składników.
      </p>

      <ul v-else class="divide-y divide-border" data-testid="shopping-list">
        <li
          v-for="item in list.items"
          :key="`${item.ingredient_id}-${item.unit}`"
          class="flex items-baseline justify-between gap-4 py-2"
        >
          <span class="font-medium text-foreground">{{ item.name }}</span>
          <span class="whitespace-nowrap text-right tabular-nums text-foreground">{{ item.display }}</span>
        </li>
      </ul>

      <p v-if="list.recipes_without_ingredients.length > 0" class="mt-4 text-xs text-muted-foreground">
        Bez składników (nie ma ich na liście): {{ list.recipes_without_ingredients.join(', ') }}.
      </p>
    </CardContent>
  </Card>
</template>

<script setup lang="ts">
import { ShoppingBasket } from 'lucide-vue-next'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'

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
