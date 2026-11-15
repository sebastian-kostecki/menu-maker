<template>
  <Button as-child class="w-full sm:w-auto">
    <Link :href="route('recipes.create')">
      <Plus class="size-4" aria-hidden="true" />
      Dodaj przepis
    </Link>
  </Button>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
</script>
