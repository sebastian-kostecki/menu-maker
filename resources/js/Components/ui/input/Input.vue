<script setup>
import { useVModel } from "@vueuse/core";
import { onMounted, ref } from "vue";
import { cn } from "@/lib/utils";

const props = defineProps({
  defaultValue: { type: [String, Number], required: false },
  modelValue: { type: [String, Number], required: false },
  class: { type: null, required: false },
});

const emits = defineEmits(["update:modelValue"]);

const modelValue = useVModel(props, "modelValue", emits, {
  passive: true,
  defaultValue: props.defaultValue,
});

// Local edit (keep on D-9 regeneration): native autofocus only fires on the first
// document load, so Inertia client-side visits would otherwise leave the field unfocused.
const inputElement = ref(null);

onMounted(() => {
  if (inputElement.value?.hasAttribute("autofocus")) {
    inputElement.value.focus();
  }
});
</script>

<template>
  <input
    ref="inputElement"
    v-model="modelValue"
    :class="
      cn(
        'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:focus-visible:ring-destructive',
        props.class,
      )
    "
  />
</template>
