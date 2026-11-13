<template>
  <div
    class="grid grid-cols-1 md:grid-cols-5 gap-4 p-4 border border-border rounded-lg bg-card"
    role="group"
    :aria-label="`Składnik ${index + 1}`"
  >
    <!-- Ingredient name -->
    <div class="md:col-span-2 space-y-1">
      <label
        :for="`ingredient-name-${index}`"
        class="block text-sm font-medium leading-6 text-foreground"
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
        :class="{ 'border-red-500': errors.name }"
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
        class="text-xs text-red-600"
        role="alert"
      >
        {{ errors.name }}
      </div>
    </div>

    <!-- Quantity -->
    <div class="space-y-1">
      <label
        :for="`quantity-${index}`"
        class="block text-sm font-medium leading-6 text-foreground"
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
        :class="{ 'border-red-500': errors.quantity }"
        :aria-invalid="!!errors.quantity"
        :aria-describedby="errors.quantity ? `quantity-error-${index}` : undefined"
        @update:model-value="updateField('quantity', $event)"
      />
      <div
        v-if="errors.quantity"
        :id="`quantity-error-${index}`"
        class="text-xs text-red-600"
        role="alert"
      >
        {{ errors.quantity }}
      </div>
    </div>

    <!-- Unit -->
    <div class="space-y-1">
      <label
        :for="`unit-${index}`"
        class="block text-sm font-medium leading-6 text-foreground"
      >
        Jednostka *
      </label>
      <Select
        :model-value="ingredient.unit_id"
        @update:model-value="updateField('unit_id', $event)"
      >
        <SelectTrigger
          :id="`unit-${index}`"
          :class="{ 'border-red-500': errors.unit_id }"
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
        class="text-xs text-red-600"
        role="alert"
      >
        {{ errors.unit_id }}
      </div>
    </div>

    <!-- Remove Button -->
    <div class="flex items-end">
      <Button
        type="button"
        variant="outline"
        size="sm"
        @click="emit('remove', index)"
        class="w-full text-red-600 hover:text-red-700 hover:bg-red-50"
        :aria-label="`Usuń składnik ${index + 1}`"
      >
        <Trash2 class="w-4 h-4" />
        <span class="sr-only">Usuń składnik</span>
      </Button>
    </div>
  </div>
</template>

<script setup>
import { Trash2 } from 'lucide-vue-next'
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
