<template>
  <div class="space-y-4">
    <!-- Ingredients List -->
    <div v-if="ingredients.length > 0" class="space-y-3">
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
        @add-ingredient="handleAddNewIngredient"
      />
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-8 text-muted-foreground">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-muted mb-4">
        <Plus class="w-6 h-6" />
      </div>
      <p class="text-sm">No ingredients added yet. Click "Add Ingredient" to get started.</p>
    </div>

    <!-- Add Ingredient Button -->
    <Button
      type="button"
      variant="outline"
      @click="handleAddIngredient"
      class="w-full"
    >
      <Plus class="w-4 h-4 mr-2" />
      Add Ingredient
    </Button>

    <!-- General Ingredients Error -->
    <div v-if="errors.ingredients && typeof errors.ingredients === 'string'" class="text-sm text-red-600">
      {{ errors.ingredients }}
    </div>
  </div>
</template>

<script setup>
import { Plus } from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'
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
const emit = defineEmits(['update:ingredients', 'add-new-ingredient'])

// Handle adding new ingredient
const handleAddIngredient = () => {
  const newIngredients = [
    ...props.ingredients,
    {
      ingredient_id: null,
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

// Handle adding new ingredient from combobox
const handleAddNewIngredient = (ingredientName) => {
  // Emit event to parent component to handle creating new ingredient
  emit('add-new-ingredient', ingredientName)
}

// Get errors for specific ingredient
const getIngredientErrors = (index) => {
  const ingredientErrors = {}

  // Check for ingredient_id errors
  if (props.errors[`ingredients.${index}.ingredient_id`]) {
    ingredientErrors.ingredient_id = props.errors[`ingredients.${index}.ingredient_id`]
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
