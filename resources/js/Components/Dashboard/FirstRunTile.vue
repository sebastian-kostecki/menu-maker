<script setup>
import { Link } from '@inertiajs/vue3'
import { ChefHat, Plus } from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'
import { Card } from '@/Components/ui/card'
</script>

<template>
  <Card class="flex flex-col items-center gap-3.5 px-6 py-14 text-center">
    <div class="grid size-18 place-items-center rounded-full border-2 border-foreground bg-meal-breakfast text-meal-breakfast-foreground">
      <ChefHat class="size-8" aria-hidden="true" />
    </div>
    <h2 class="text-dialog-title">Zacznij od pierwszego przepisu</h2>
    <p class="max-w-md text-muted-foreground">
      Dodaj przepisy do śniadań, obiadów i kolacji, a potem wygeneruj z nich jadłospis na cały tydzień.
    </p>
    <Button size="lg" as-child>
      <Link :href="route('recipes.create')">
        <Plus aria-hidden="true" />
        Dodaj pierwszy przepis
      </Link>
    </Button>
  </Card>
</template>
