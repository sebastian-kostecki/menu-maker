<script setup>
import { cn } from "@/lib/utils";

const props = defineProps({
  class: { type: null, required: false },
});
</script>

<template>
  <div
    :class="
      cn(
        'flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
