<template>
  <div
    class="grid gap-3 md:grid-cols-12 md:items-end"
    role="group"
    :aria-label="`Składnik ${index + 1}`"
  >
    <!-- Ingredient name -->
    <div class="space-y-1 md:col-span-7">
      <label
        :for="`ingredient-name-${index}`"
        class="block text-label text-foreground"
      >
        Składnik *
      </label>
      <Input
        :id="`ingredient-name-${index}`"
        :model-value="ingredient.name"
        type="text"
        :list="`ingredient-options-${index}`"
        autocomplete="off"
        placeholder="np. mąka"
        :class="{ 'border-destructive': errors.name }"
        :aria-invalid="!!errors.name"
        :aria-describedby="errors.name ? `ingredient-error-${index}` : undefined"
        @update:model-value="updateField('name', $event)"
      />
      <datalist :id="`ingredient-options-${index}`">
        <option
          v-for="option in availableIngredients"
          :key="option.id"
          :value="option.name"
        />
      </datalist>
      <div
        v-if="errors.name"
        :id="`ingredient-error-${index}`"
        class="text-xs text-destructive"
        role="alert"
      >
        {{ errors.name }}
      </div>
    </div>

    <!-- Quantity -->
    <div class="space-y-1 md:col-span-2">
      <label
        :for="`quantity-${index}`"
        class="block text-label text-foreground"
      >
        Ilość *
      </label>
      <Input
        :id="`quantity-${index}`"
        :model-value="ingredient.quantity"
        type="number"
        step="0.01"
        min="0.01"
        placeholder="np. 2"
        :class="{ 'border-destructive': errors.quantity }"
        :aria-invalid="!!errors.quantity"
        :aria-describedby="errors.quantity ? `quantity-error-${index}` : undefined"
        @update:model-value="updateField('quantity', $event)"
      />
      <div
        v-if="errors.quantity"
        :id="`quantity-error-${index}`"
        class="text-xs text-destructive"
        role="alert"
      >
        {{ errors.quantity }}
      </div>
    </div>

    <!-- Unit -->
    <div class="space-y-1 md:col-span-2">
      <label
        :for="`unit-${index}`"
        class="block text-label text-foreground"
      >
        Jednostka *
      </label>
      <Select
        :model-value="ingredient.unit_id"
        @update:model-value="updateField('unit_id', $event)"
      >
        <SelectTrigger
          :id="`unit-${index}`"
          :class="{ 'border-destructive': errors.unit_id }"
          :aria-invalid="!!errors.unit_id"
          :aria-describedby="errors.unit_id ? `unit-error-${index}` : undefined"
        >
          <SelectValue placeholder="Jednostka" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="unit in availableUnits"
            :key="unit.id"
            :value="unit.id"
          >
            {{ unitLabel(unit) }}
          </SelectItem>
        </SelectContent>
      </Select>
      <div
        v-if="errors.unit_id"
        :id="`unit-error-${index}`"
        class="text-xs text-destructive"
        role="alert"
      >
        {{ errors.unit_id }}
      </div>
    </div>

    <!-- Remove Button -->
    <div class="flex md:col-span-1">
      <Button
        type="button"
        variant="secondary"
        size="icon"
        :aria-label="`Usuń składnik ${ingredient.name || index + 1}`"
        @click="emit('remove', index)"
      >
        <X aria-hidden="true" />
      </Button>
    </div>
  </div>
</template>

<script setup>
import { X } from 'lucide-vue-next'
import { Input } from '@/Components/ui/input'
import { Button } from '@/Components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select'

const props = defineProps({
  ingredient: {
    type: Object,
    required: true
  },
  index: {
    type: Number,
    required: true
  },
  availableIngredients: {
    type: Array,
    required: true
  },
  availableUnits: {
    type: Array,
    required: true
  },
  errors: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update', 'remove'])

const updateField = (field, value) => {
  emit('update', props.index, { ...props.ingredient, [field]: value })
}

const unitLabel = (unit) => (unit.code === 'szt' ? 'szt.' : unit.code)
</script>
