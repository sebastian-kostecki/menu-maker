<script setup>
import { reactiveOmit } from "@vueuse/core";
import { DropdownMenuItem, useForwardProps } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  disabled: { type: Boolean, required: false },
  textValue: { type: String, required: false },
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: { type: null, required: false },
  inset: { type: Boolean, required: false },
  variant: { type: String, required: false, default: "default" },
});

const delegatedProps = reactiveOmit(props, "class", "variant");

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <DropdownMenuItem
    v-bind="forwardedProps"
    :data-variant="variant"
    :class="
      cn(
        'relative flex cursor-default select-none items-center rounded-sm gap-2 px-2 py-1.5 text-sm outline-hidden transition-colors focus:bg-accent focus:text-accent-foreground data-disabled:pointer-events-none data-disabled:opacity-50 [&>svg]:size-4 [&>svg]:shrink-0 data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:[&>svg]:text-destructive',
        inset && 'pl-8',
        props.class,
      )
    "
  >
    <slot />
  </DropdownMenuItem>
</template>
