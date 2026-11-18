<script setup>
import { MEAL_CATEGORY_FILL_CLASSES, MEAL_CATEGORY_ICONS } from '@/lib/mealCategories'
import { cn } from '@/lib/utils'

defineProps({
  name: {
    type: String,
    default: 'category',
  },
  modelValue: {
    type: String,
    default: null,
  },
  categories: {
    type: Array,
    required: true,
  },
  error: {
    type: String,
    default: undefined,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['update:modelValue'])
</script>

<template>
  <fieldset
    role="radiogroup"
    :aria-labelledby="`${name}-legend`"
    aria-required="true"
    :aria-invalid="!!error || undefined"
    :aria-describedby="error ? `${name}-error` : undefined"
    class="grid gap-2"
  >
    <legend :id="`${name}-legend`" class="text-label">Kategoria *</legend>
    <div class="grid gap-3 sm:grid-cols-3">
      <label
        v-for="category in categories"
        :key="category.value"
        :class="
          cn(
            'relative flex h-16 items-center gap-3 rounded-md border-2 px-4 font-bold has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-ring',
            error ? 'border-destructive' : 'border-foreground',
            modelValue === category.value
              ? [MEAL_CATEGORY_FILL_CLASSES[category.value] ?? 'bg-muted text-foreground', 'shadow-sticker']
              : 'bg-card',
            disabled && 'opacity-50',
          )
        "
      >
        <input
          type="radio"
          :name="name"
          :value="category.value"
          :checked="modelValue === category.value"
          :disabled="disabled"
          class="absolute inset-0 size-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
          @change="$emit('update:modelValue', category.value)"
        />
        <component
          :is="MEAL_CATEGORY_ICONS[category.value]"
          v-if="MEAL_CATEGORY_ICONS[category.value]"
          class="pointer-events-none size-5 shrink-0"
          aria-hidden="true"
        />
        <span class="pointer-events-none flex-1">{{ category.label }}</span>
        <span
          class="pointer-events-none flex size-5.5 shrink-0 items-center justify-center rounded-full border-2 border-foreground bg-card"
          aria-hidden="true"
        >
          <span v-if="modelValue === category.value" class="size-2.5 rounded-full bg-foreground" />
        </span>
      </label>
    </div>
    <p v-if="error" :id="`${name}-error`" class="text-sm text-destructive">{{ error }}</p>
  </fieldset>
</template>
