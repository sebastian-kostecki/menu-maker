<template>
  <div class="space-y-4">
    <!-- Ingredients List -->
    <div v-if="ingredients.length > 0" class="space-y-4">
      <IngredientRow
        v-for="(ingredient, index) in ingredients"
        :key="`ingredient-${index}`"
        :ingredient="ingredient"
        :index="index"
        :available-ingredients="availableIngredients"
        :available-units="availableUnits"
        :errors="getIngredientErrors(index)"
        @update="handleUpdateIngredient"
        @remove="handleRemoveIngredient"
      />
    </div>

    <!-- Empty State -->
    <EmptyState v-else :icon="ShoppingBasket" title="Nie dodano jeszcze składników." :heading-level="3" class="py-4" />

    <!-- Add Ingredient Button -->
    <Button type="button" variant="dashed" class="h-12 w-full" @click="handleAddIngredient">
      <Plus aria-hidden="true" />
      Dodaj składnik
    </Button>

    <!-- General Ingredients Error -->
    <div v-if="errors.ingredients && typeof errors.ingredients === 'string'" class="text-sm text-destructive">
      {{ errors.ingredients }}
    </div>
  </div>
</template>

<script setup>
import { Plus, ShoppingBasket } from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'
import EmptyState from '@/Components/Common/EmptyState.vue'
import IngredientRow from './IngredientRow.vue'

// Define props
const props = defineProps({
  ingredients: {
    type: Array,
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

// Define emits for v-model support
const emit = defineEmits(['update:ingredients'])

// Handle adding new ingredient
const handleAddIngredient = () => {
  const newIngredients = [
    ...props.ingredients,
    {
      name: '',
      quantity: '',
      unit_id: null
    }
  ]
  emit('update:ingredients', newIngredients)
}

// Handle updating ingredient
const handleUpdateIngredient = (index, updatedIngredient) => {
  const newIngredients = [...props.ingredients]
  newIngredients[index] = updatedIngredient
  emit('update:ingredients', newIngredients)
}

// Handle removing ingredient
const handleRemoveIngredient = (index) => {
  const newIngredients = props.ingredients.filter((_, i) => i !== index)
  emit('update:ingredients', newIngredients)
}

// Get errors for specific ingredient
const getIngredientErrors = (index) => {
  const ingredientErrors = {}

  // Check for name errors
  if (props.errors[`ingredients.${index}.name`]) {
    ingredientErrors.name = props.errors[`ingredients.${index}.name`]
  }

  // Check for quantity errors
  if (props.errors[`ingredients.${index}.quantity`]) {
    ingredientErrors.quantity = props.errors[`ingredients.${index}.quantity`]
  }

  // Check for unit_id errors
  if (props.errors[`ingredients.${index}.unit_id`]) {
    ingredientErrors.unit_id = props.errors[`ingredients.${index}.unit_id`]
  }

  return ingredientErrors
}
</script>
