<script setup>
import { AvatarRoot } from "reka-ui";
import { cn } from "@/lib/utils";
import { avatarVariant } from ".";

const props = defineProps({
  class: { type: null, required: false },
  variant: { type: null, required: false, default: "default" },
  size: { type: null, required: false, default: "sm" },
  shape: { type: null, required: false, default: "circle" },
});
</script>

<template>
  <AvatarRoot :class="cn(avatarVariant({ variant, size, shape }), props.class)">
    <slot />
  </AvatarRoot>
</template>
