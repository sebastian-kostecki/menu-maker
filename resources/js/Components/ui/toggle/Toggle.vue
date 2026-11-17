<script setup>
import { reactiveOmit } from "@vueuse/core";
import { Toggle, useForwardPropsEmits } from "reka-ui";
import { cn } from "@/lib/utils";
import { toggleVariants } from ".";

const props = defineProps({
  defaultValue: { type: Boolean, required: false },
  modelValue: { type: [Boolean, null], required: false, default: undefined },
  disabled: { type: Boolean, required: false },
  name: { type: String, required: false },
  required: { type: Boolean, required: false },
  variant: { type: null, required: false },
  size: { type: null, required: false },
  class: { type: null, required: false },
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false, default: "button" },
});
const emits = defineEmits(["update:modelValue"]);

const delegatedProps = reactiveOmit(props, "class", "size", "variant");
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <Toggle
    data-slot="toggle"
    v-bind="forwarded"
    :class="cn(toggleVariants({ variant, size }), props.class)"
  >
    <slot />
  </Toggle>
</template>
