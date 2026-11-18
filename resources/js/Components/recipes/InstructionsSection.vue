<template>
  <Card class="flex flex-col gap-4 p-6">
    <h2 :id="headingId" class="flex items-center gap-2 text-section-title">
      <ScrollText class="size-5" aria-hidden="true" />
      Sposób przygotowania
    </h2>

    <ol v-if="parsed.steps.length > 0" :aria-labelledby="headingId" class="flex flex-col gap-3">
      <li v-for="(step, index) in parsed.steps" :key="index" class="flex items-start gap-3">
        <span
          class="flex size-9 shrink-0 items-center justify-center rounded-full bg-highlight font-extrabold text-highlight-foreground"
          aria-hidden="true"
        >{{ index + 1 }}</span>
        <span class="min-w-0 break-words pt-1.5">{{ step }}</span>
      </li>
    </ol>

    <EmptyState
      v-else
      class="py-6"
      :icon="ScrollText"
      title="Brak sposobu przygotowania"
      description="Sposób przygotowania tego przepisu nie został jeszcze dodany."
      :heading-level="3"
    />

    <p v-if="parsed.source" class="text-meta text-muted-foreground">
      Źródło:
      <a
        v-if="isUrl"
        :href="parsed.source"
        target="_blank"
        rel="noopener noreferrer"
        class="break-all rounded-sm underline underline-offset-3 hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
      >{{ parsed.source }}</a>
      <template v-else>{{ parsed.source }}</template>
    </p>
  </Card>
</template>

<script setup>
import { computed, useId } from 'vue';
import { ScrollText } from 'lucide-vue-next';
import { Card } from '@/Components/ui/card';
import EmptyState from '@/Components/Common/EmptyState.vue';
import { parseInstructions } from '@/lib/recipeInstructions.js';

const props = defineProps({
  instructions: {
    type: String,
    default: '',
  },
});

const headingId = useId();
const parsed = computed(() => parseInstructions(props.instructions));
const isUrl = computed(() => /^https?:\/\//.test(parsed.value.source ?? ''));
</script>
