<script setup>
import { computed, inject } from "vue";
import { cn } from "@/lib/utils";
import { CARD_VARIANT_KEY } from ".";

const props = defineProps({
  class: { type: null, required: false },
  as: { type: String, required: false, default: "h3" },
});

const variant = inject(CARD_VARIANT_KEY, "default");
const isDestructive = computed(() => (variant?.value ?? variant) === "destructive");
</script>

<template>
  <component
    :is="as"
    :class="cn('text-section-title', isDestructive && 'text-destructive', props.class)"
  >
    <slot />
  </component>
</template>
