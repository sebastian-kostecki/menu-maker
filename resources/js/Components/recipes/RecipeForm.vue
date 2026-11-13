<template>
  <form @submit.prevent="$emit('submit')" class="space-y-6">
    <!-- Recipe Name -->
    <div class="space-y-2">
      <label for="name" class="block text-sm font-medium leading-6 peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
        Nazwa przepisu *
      </label>
      <Input
        id="name"
        v-model="form.name"
        type="text"
        placeholder="Wpisz nazwę przepisu"
        :class="{ 'border-red-500': form.errors.name }"
        required
      />
      <div v-if="form.errors.name" class="text-sm text-red-600">
        {{ form.errors.name }}
      </div>
    </div>

    <!-- Category -->
    <div class="space-y-2">
      <label for="category" class="block text-sm font-medium leading-6 peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
        Kategoria *
      </label>
      <Select v-model="form.category" :disabled="form.processing">
        <SelectTrigger id="category" :class="{ 'border-red-500': form.errors.category }">
          <SelectValue placeholder="Wybierz kategorię" />
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
      <label for="instructions" class="block text-sm font-medium leading-6 peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
        Sposób przygotowania *
      </label>
      <Textarea
        id="instructions"
        v-model="form.instructions"
        rows="5"
        placeholder="Opisz sposób przygotowania"
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
        <label for="calories" class="block text-sm font-medium leading-6 peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
          Kaloryczność (kcal) *
        </label>
        <Input
          id="calories"
          v-model="form.calories"
          type="number"
          min="0"
          placeholder="np. 350"
          :class="{ 'border-red-500': form.errors.calories }"
          required
        />
        <div v-if="form.errors.calories" class="text-sm text-red-600">
          {{ form.errors.calories }}
        </div>
      </div>

      <!-- Servings -->
      <div class="space-y-2">
        <label for="servings" class="block text-sm font-medium leading-6 peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
          Liczba porcji *
        </label>
        <Input
          id="servings"
          v-model="form.servings"
          type="number"
          min="1"
          placeholder="np. 4"
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
        <h3 class="text-lg font-medium">Składniki</h3>
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
          Zapisywanie...
        </span>
        <span v-else>
          {{ isEditing ? 'Zapisz zmiany' : 'Zapisz przepis' }}
        </span>
      </Button>

      <Button
        type="button"
        variant="outline"
        @click="$emit('cancel')"
        :disabled="form.processing"
      >
        Anuluj
      </Button>
    </div>

    <div class="text-xs text-muted-foreground">
      * Pola wymagane
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

const hasCompleteIngredient = computed(() => {
  return (props.form.ingredients ?? []).some(row =>
    String(row.name ?? '').trim() !== '' &&
    Number(row.quantity) > 0 &&
    !!row.unit_id
  )
})

// Basic form validation
const isFormValid = computed(() => {
  return !!(
    props.form.name &&
    props.form.category &&
    props.form.instructions &&
    props.form.calories &&
    props.form.servings &&
    hasCompleteIngredient.value
  )
})
</script>
