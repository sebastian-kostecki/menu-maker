<template>
  <form @submit.prevent="$emit('submit')" class="space-y-6">
    <!-- Recipe Name -->
    <div class="space-y-2">
      <label for="name" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
        Recipe Name *
      </label>
      <Input
        id="name"
        v-model="form.name"
        type="text"
        placeholder="Enter recipe name"
        :class="{ 'border-red-500': form.errors.name }"
        required
      />
      <div v-if="form.errors.name" class="text-sm text-red-600">
        {{ form.errors.name }}
      </div>
    </div>

    <!-- Category -->
    <div class="space-y-2">
      <label for="category" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
        Category *
      </label>
      <Select v-model="form.category" :disabled="form.processing">
        <SelectTrigger :class="{ 'border-red-500': form.errors.category }">
          <SelectValue placeholder="Select a category" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="category in categories"
            :key="category.value"
            :value="category.value"
          >
            {{ category.label }}
          </SelectItem>
        </SelectContent>
      </Select>
      <div v-if="form.errors.category" class="text-sm text-red-600">
        {{ form.errors.category }}
      </div>
    </div>

    <!-- Instructions -->
    <div class="space-y-2">
      <label for="instructions" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
        Instructions *
      </label>
      <Textarea
        id="instructions"
        v-model="form.instructions"
        rows="5"
        placeholder="Enter cooking instructions"
        :class="{ 'border-red-500': form.errors.instructions }"
        required
      />
      <div v-if="form.errors.instructions" class="text-sm text-red-600">
        {{ form.errors.instructions }}
      </div>
    </div>

    <!-- Calories and Servings Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- Calories -->
      <div class="space-y-2">
        <label for="calories" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
          Calories *
        </label>
        <Input
          id="calories"
          v-model="form.calories"
          type="number"
          min="0"
          placeholder="e.g. 350"
          :class="{ 'border-red-500': form.errors.calories }"
          required
        />
        <div v-if="form.errors.calories" class="text-sm text-red-600">
          {{ form.errors.calories }}
        </div>
      </div>

      <!-- Servings -->
      <div class="space-y-2">
        <label for="servings" class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
          Servings *
        </label>
        <Input
          id="servings"
          v-model="form.servings"
          type="number"
          min="1"
          placeholder="e.g. 4"
          :class="{ 'border-red-500': form.errors.servings }"
          required
        />
        <div v-if="form.errors.servings" class="text-sm text-red-600">
          {{ form.errors.servings }}
        </div>
      </div>
    </div>

    <!-- Ingredients -->
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-medium">Ingredients</h3>
      </div>

      <IngredientManager
        v-model:ingredients="form.ingredients"
        :available-ingredients="ingredients"
        :available-units="units"
        :errors="form.errors"
      />
    </div>

        <!-- Action Buttons -->
    <div class="flex items-center gap-4 pt-6 border-t">
      <Button
        type="submit"
        :disabled="form.processing || !isFormValid"
        class="flex-1 md:flex-none"
      >
        <span v-if="form.processing">
          {{ isEditing ? 'Updating...' : 'Creating...' }}
        </span>
        <span v-else>
          {{ isEditing ? 'Update Recipe' : 'Create Recipe' }}
        </span>
      </Button>

      <Button
        type="button"
        variant="outline"
        @click="$emit('cancel')"
        :disabled="form.processing"
      >
        Cancel
      </Button>
    </div>

    <div class="text-xs text-muted-foreground">
      * Required fields
    </div>
  </form>
</template>

<script setup>
import { computed } from 'vue'
import { Input } from '@/Components/ui/input'
import { Button } from '@/Components/ui/button'
import { Textarea } from '@/Components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select'
import IngredientManager from './IngredientManager.vue'

// Define props
const props = defineProps({
  form: {
    type: Object,
    required: true
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
  },
  isEditing: {
    type: Boolean,
    default: false
  }
})

// Define emits
defineEmits(['submit', 'cancel'])

// Basic form validation
const isFormValid = computed(() => {
  return !!(
    props.form.name &&
    props.form.category &&
    props.form.instructions &&
    props.form.calories &&
    props.form.servings
  )
})
</script>
