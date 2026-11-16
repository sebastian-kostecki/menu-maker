<template>
  <form @submit.prevent="$emit('submit')" class="space-y-6">
    <!-- Recipe Name -->
    <div class="space-y-2">
      <Label for="name">
        Nazwa przepisu *
      </Label>
      <Input
        id="name"
        v-model="form.name"
        type="text"
        placeholder="Wpisz nazwę przepisu"
        :class="{ 'border-destructive': form.errors.name }"
        :aria-invalid="!!form.errors.name"
        :aria-describedby="form.errors.name ? 'name-error' : undefined"
        required
      />
      <p v-if="form.errors.name" id="name-error" class="text-sm text-destructive">
        {{ form.errors.name }}
      </p>
    </div>

    <!-- Category -->
    <div class="space-y-2">
      <Label for="category">
        Kategoria *
      </Label>
      <Select v-model="form.category" :disabled="form.processing">
        <SelectTrigger id="category" :class="{ 'border-destructive': form.errors.category }"
          :aria-invalid="!!form.errors.category"
          :aria-describedby="form.errors.category ? 'category-error' : undefined">
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
      <p v-if="form.errors.category" id="category-error" class="text-sm text-destructive">
        {{ form.errors.category }}
      </p>
    </div>

    <!-- Instructions -->
    <div class="space-y-2">
      <Label for="instructions">
        Sposób przygotowania *
      </Label>
      <Textarea
        id="instructions"
        v-model="form.instructions"
        rows="5"
        placeholder="Opisz sposób przygotowania"
        :class="{ 'border-destructive': form.errors.instructions }"
        :aria-invalid="!!form.errors.instructions"
        :aria-describedby="form.errors.instructions ? 'instructions-error' : undefined"
        required
      />
      <p v-if="form.errors.instructions" id="instructions-error" class="text-sm text-destructive">
        {{ form.errors.instructions }}
      </p>
    </div>

    <!-- Calories and Servings Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- Calories -->
      <div class="space-y-2">
        <Label for="calories">
          Kaloryczność całkowita (kcal) *
        </Label>
        <Input
          id="calories"
          v-model="form.calories"
          type="number"
          min="50"
          placeholder="np. 350"
          :class="{ 'border-destructive': form.errors.calories }"
        :aria-invalid="!!form.errors.calories"
        :aria-describedby="form.errors.calories ? 'calories-error' : undefined"
          required
        />
        <p v-if="form.errors.calories" id="calories-error" class="text-sm text-destructive">
        {{ form.errors.calories }}
      </p>
      </div>

      <!-- Servings -->
      <div class="space-y-2">
        <Label for="servings">
          Liczba porcji *
        </Label>
        <Input
          id="servings"
          v-model="form.servings"
          type="number"
          min="1"
          placeholder="np. 4"
          :class="{ 'border-destructive': form.errors.servings }"
        :aria-invalid="!!form.errors.servings"
        :aria-describedby="form.errors.servings ? 'servings-error' : undefined"
          required
        />
        <p v-if="form.errors.servings" id="servings-error" class="text-sm text-destructive">
        {{ form.errors.servings }}
      </p>
      </div>
    </div>

    <!-- Ingredients -->
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Składniki</h2>
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
        <template v-if="form.processing">
          <Loader2 class="size-4 animate-spin motion-reduce:animate-none" aria-hidden="true" />
          Zapisywanie…
        </template>
        <template v-else>
          {{ isEditing ? 'Zapisz zmiany' : 'Zapisz przepis' }}
        </template>
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
import { Loader2 } from 'lucide-vue-next'
import { Label } from '@/Components/ui/label'
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
