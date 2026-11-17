<script setup>
import { reactiveOmit } from "@vueuse/core";
import { cn } from "@/lib/utils";
import { PaginationListItem } from "reka-ui";
import { buttonVariants } from '@/Components/ui/button';

const props = defineProps({
  value: { type: Number, required: true },
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  size: { type: null, required: false, default: "icon" },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
  isActive: { type: Boolean, required: false },
});

const delegatedProps = reactiveOmit(props, "class", "size", "isActive");
</script>

<template>
  <PaginationListItem
    data-slot="pagination-item"
    v-bind="delegatedProps"
    :class="
      cn(
        buttonVariants({ variant: isActive ? 'default' : 'outline', size }),
        'h-11 min-w-11 px-3 shadow-none active:translate-y-0',
        props.class,
      )
    "
  >
    <slot />
  </PaginationListItem>
</template>
