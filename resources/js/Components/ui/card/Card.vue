<script setup>
import { computed, provide } from "vue";
import { cn } from "@/lib/utils";
import { CARD_VARIANT_KEY, cardVariants } from ".";

const props = defineProps({
  class: { type: null, required: false },
  variant: { type: String, required: false, default: "default" },
});

provide(
  CARD_VARIANT_KEY,
  computed(() => props.variant),
);
</script>

<template>
  <div :class="cn(cardVariants({ variant }), props.class)">
    <slot />
  </div>
</template>
