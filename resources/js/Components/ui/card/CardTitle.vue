<script setup>
import { cn } from "@/lib/utils";

const props = defineProps({
  class: { type: null, required: false },
  as: { type: String, required: false, default: "h3" },
});
</script>

<template>
  <component :is="as" :class="cn('text-section-title', props.class)">
    <slot />
  </component>
</template>
