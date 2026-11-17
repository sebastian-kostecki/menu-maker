<script setup>
import { cn } from "@/lib/utils";

const props = defineProps({
  class: { type: null, required: false },
});
</script>

<template>
  <div
    :class="
      cn('rounded-lg border-2 border-foreground bg-card text-card-foreground shadow-sticker', props.class)
    "
  >
    <slot />
  </div>
</template>
