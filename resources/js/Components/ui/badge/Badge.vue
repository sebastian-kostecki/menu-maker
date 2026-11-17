<script setup>
import { cn } from "@/lib/utils";
import { badgeVariants } from ".";

const props = defineProps({
  variant: { type: null, required: false },
  size: { type: null, required: false },
  class: { type: null, required: false },
});
</script>

<template>
  <div :class="cn(badgeVariants({ variant, size }), props.class)">
    <slot />
  </div>
</template>
