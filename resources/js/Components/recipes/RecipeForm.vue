<template>
  <form @submit.prevent="$emit('submit')" class="flex flex-col gap-6">
    <Card class="grid gap-5 p-6">
      <h2 class="text-section-title">Podstawy</h2>

      <FormField id="name" label="Nazwa przepisu *" :error="form.errors.name">
        <template #default="{ invalid, describedBy }">
          <Input
            id="name"
            v-model="form.name"
            type="text"
            placeholder="Wpisz nazwę przepisu"
            :aria-invalid="invalid || undefined"
            :aria-describedby="describedBy"
            required
          />
        </template>
      </FormField>

      <CategoryRadioTiles
        v-model="form.category"
        :categories="categories"
        :error="form.errors.category"
        :disabled="form.processing"
      />

      <div class="grid gap-5 md:grid-cols-2">
        <FormField id="calories" label="Kaloryczność całkowita (kcal) *" :error="form.errors.calories">
          <template #default="{ invalid, describedBy }">
            <Input
              id="calories"
              v-model="form.calories"
              type="number"
              min="50"
              placeholder="np. 350"
              :aria-invalid="invalid || undefined"
              :aria-describedby="describedBy"
              required
            />
          </template>
        </FormField>

        <FormField id="servings" label="Liczba porcji *" :error="form.errors.servings">
          <template #default="{ invalid, describedBy }">
            <Input
              id="servings"
              v-model="form.servings"
              type="number"
              min="1"
              placeholder="np. 4"
              :aria-invalid="invalid || undefined"
              :aria-describedby="describedBy"
              required
            />
          </template>
        </FormField>
      </div>
    </Card>

    <Card class="grid gap-5 p-6">
      <h2 class="text-section-title">Składniki</h2>

      <IngredientManager
        v-model:ingredients="form.ingredients"
        :available-ingredients="ingredients"
        :available-units="units"
        :errors="form.errors"
      />
    </Card>

    <Card class="grid gap-5 p-6">
      <h2 class="text-section-title">Sposób przygotowania</h2>

      <FormField
        id="instructions"
        label="Sposób przygotowania *"
        description="Każda linia to osobny krok."
        :error="form.errors.instructions"
      >
        <template #default="{ invalid, describedBy }">
          <Textarea
            id="instructions"
            v-model="form.instructions"
            rows="6"
            placeholder="Opisz sposób przygotowania"
            :aria-invalid="invalid || undefined"
            :aria-describedby="describedBy"
            required
          />
        </template>
      </FormField>
    </Card>

    <div class="flex flex-wrap items-center gap-4">
      <Button type="submit" size="lg" :disabled="form.processing || !isFormValid">
        <template v-if="form.processing">
          <Loader2 class="size-4 animate-spin motion-reduce:animate-none" aria-hidden="true" />
          Zapisywanie…
        </template>
        <template v-else>
          {{ isEditing ? 'Zapisz zmiany' : 'Zapisz przepis' }}
        </template>
      </Button>

      <Button type="button" variant="outline" size="lg" :disabled="form.processing" @click="$emit('cancel')">
        Anuluj
      </Button>

      <div class="flex-1" />

      <p class="text-meta text-muted-foreground">* Pola wymagane</p>
    </div>
  </form>
</template>

<script setup>
import { computed } from 'vue'
import { Loader2 } from 'lucide-vue-next'
import { Input } from '@/Components/ui/input'
import { Button } from '@/Components/ui/button'
import { Textarea } from '@/Components/ui/textarea'
import { Card } from '@/Components/ui/card'
import FormField from '@/Components/Common/FormField.vue'
import CategoryRadioTiles from './CategoryRadioTiles.vue'
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
