<template>
  <Button
    variant="default"
    size="default"
    @click="handleCreateRecipe"
    class="w-full sm:w-auto"
  >
    <Plus class="h-4 w-4 mr-2" />
    Dodaj przepis
  </Button>
</template>

<script setup>
import { Plus } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { router } from '@inertiajs/vue3';

// Handle create recipe navigation
const handleCreateRecipe = () => {
  router.visit(route('recipes.create'));
};
</script>
