<template>
  <!-- Draft placeholder: deliberately carries no data-testid="shopping-list" (the spec asserts it is hidden on a draft). -->
  <div v-if="!list && pending" class="rounded-lg border-2 border-dashed border-muted-foreground p-5">
    <h2 class="flex items-center gap-2 text-section-title">
      <ShoppingBasket class="size-4" aria-hidden="true" />
      Lista zakupów
    </h2>
    <p class="mt-2 text-sm text-muted-foreground">
      Pojawi się po zatwierdzeniu — wtedy przeliczymy ilości na Twoją rodzinę.
    </p>
  </div>

  <Card v-else-if="list">
    <CardHeader>
      <!-- h2: the list sits directly under the page's h1. -->
      <CardTitle as="h2" class="flex items-center gap-2 text-section-title">
        <ShoppingBasket class="size-4" aria-hidden="true" />
        Lista zakupów
      </CardTitle>
    </CardHeader>

    <CardContent class="text-sm">
      <p v-if="list.items.length === 0" class="text-muted-foreground">
        Przepisy w tym jadłospisie nie mają składników.
      </p>

      <ul v-else data-testid="shopping-list">
        <li
          v-for="item in list.items"
          :key="`${item.ingredient_id}-${item.unit}`"
          class="flex items-baseline justify-between gap-4 border-b-2 border-dotted border-border py-2 last:border-b-0"
        >
          <span class="font-medium text-foreground">{{ item.name }}</span>
          <span class="whitespace-nowrap text-right font-bold tabular-nums text-foreground">{{ item.display }}</span>
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

withDefaults(
  defineProps<{
    list?: ShoppingListPayload | null
    /** Draft: no list yet, show the dashed placeholder instead. */
    pending?: boolean
  }>(),
  {
    list: null,
    pending: false,
  }
)
</script>
