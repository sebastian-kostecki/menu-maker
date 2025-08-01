<template>
  <div class="min-h-screen bg-gray-100 py-6">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 bg-white border-b border-gray-200">
          <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold text-gray-900">
              {{ recipe ? 'Edit Recipe' : 'Create Recipe' }}
            </h1>
          </div>

          <RecipeForm
            :form="form"
            :categories="categories"
            :ingredients="ingredients"
            :units="units"
            :is-editing="!!recipe"
            @submit="handleSubmit"
            @cancel="handleCancel"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import RecipeForm from '@/Components/recipes/RecipeForm.vue'

// Define props interface
const props = defineProps({
  recipe: {
    type: Object,
    default: null
  },
  categories: {
    type: Array,
    required: true
  },
  ingredients: {
    type: Array,
    required: true
  },
  units: {
    type: Array,
    required: true
  }
})

// Initialize form with useForm hook
const form = useForm({
  name: props.recipe?.name ?? '',
  category: props.recipe?.category ?? null,
  instructions: props.recipe?.instructions ?? '',
  calories: props.recipe?.calories ?? '',
  servings: props.recipe?.servings ?? '',
  ingredients: props.recipe?.ingredients?.map(ingredient => ({
    ingredient_id: ingredient.ingredient_id,
    quantity: ingredient.quantity,
    unit_id: ingredient.unit_id,
  })) ?? []
})

// Handle form submission
const handleSubmit = () => {
  if (props.recipe) {
    // Update existing recipe
    form.put(`/recipes/${props.recipe.id}`, {
      onSuccess: () => {
        // Success handled by redirect from controller
      }
    })
  } else {
    // Create new recipe
    form.post('/recipes', {
      onSuccess: () => {
        // Success handled by redirect from controller
      }
    })
  }
}

// Handle cancel action
const handleCancel = () => {
  window.history.back()
}
</script>
