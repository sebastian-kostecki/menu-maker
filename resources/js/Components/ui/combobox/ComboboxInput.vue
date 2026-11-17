<script setup>
import { reactiveOmit } from "@vueuse/core";
import { ComboboxInput, useForwardPropsEmits } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  displayValue: { type: Function, required: false },
  modelValue: { type: String, required: false },
  autoFocus: { type: Boolean, required: false },
  disabled: { type: Boolean, required: false },
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: { type: null, required: false },
});

const emits = defineEmits(["update:modelValue"]);

const delegatedProps = reactiveOmit(props, "class");

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <ComboboxInput
    v-bind="forwarded"
    :class="
      cn(
        'flex transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium h-12 w-full rounded-md border-2 border-foreground bg-card px-4 text-body placeholder:text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive',
        props.class,
      )
    "
  >
    <slot />
  </ComboboxInput>
</template>
