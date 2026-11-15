<template>
  <section class="flex flex-col gap-4">
    <h2 class="text-lg font-semibold">Sposób przygotowania</h2>
    <div
      v-if="instructions && instructions.trim()"
      class="whitespace-pre-line rounded-lg bg-muted p-6 text-sm leading-relaxed"
    >
      {{ instructions }}
    </div>
    <EmptyState
      v-else
      :icon="ScrollText"
      title="Brak sposobu przygotowania"
      description="Sposób przygotowania tego przepisu nie został jeszcze dodany."
      :heading-level="3"
    />
  </section>
</template>

<script setup>
import { ScrollText } from 'lucide-vue-next';
import EmptyState from '@/Components/Common/EmptyState.vue';

defineProps({
  instructions: {
    type: String,
    default: '',
  },
});
</script>
