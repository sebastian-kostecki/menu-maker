<template>
  <div class="grid grid-cols-1 md:grid-cols-5 gap-4 p-4 border border-border rounded-lg bg-card">
    <!-- Ingredient Selection -->
    <div class="md:col-span-2 space-y-1">
      <label class="text-sm font-medium text-foreground">Ingredient *</label>
      <Combobox v-model="localIngredient.ingredient_id">
        <ComboboxTrigger :class="{ 'border-red-500': errors.ingredient_id }">
          <span v-if="selectedIngredient">
            {{ selectedIngredient.name }}
          </span>
          <span v-else class="text-gray-400">
            Select ingredient...
          </span>
        </ComboboxTrigger>
        <ComboboxList>
          <ComboboxInput
            placeholder="Search ingredients..."
            @input="handleSearch"
          />
          <ComboboxEmpty>No ingredients found.</ComboboxEmpty>
          <ComboboxItem
            v-for="ingredient in filteredIngredients"
            :key="ingredient.id"
            :value="ingredient.id"
          >
            {{ ingredient.name }}
          </ComboboxItem>
        </ComboboxList>
      </Combobox>
      <div v-if="errors.ingredient_id" class="text-xs text-red-600">
        {{ errors.ingredient_id }}
      </div>
    </div>

    <!-- Quantity -->
    <div class="space-y-1">
      <label class="text-sm font-medium text-foreground">Quantity *</label>
      <Input
        v-model="localIngredient.quantity"
        type="number"
        step="0.01"
        min="0.01"
        placeholder="e.g. 2"
        :class="{ 'border-red-500': errors.quantity }"
      />
      <div v-if="errors.quantity" class="text-xs text-red-600">
        {{ errors.quantity }}
      </div>
    </div>

    <!-- Unit -->
    <div class="space-y-1">
      <label class="text-sm font-medium text-foreground">Unit *</label>
      <Select v-model="localIngredient.unit_id">
        <SelectTrigger :class="{ 'border-red-500': errors.unit_id }">
          <SelectValue placeholder="Unit" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="unit in availableUnits"
            :key="unit.id"
            :value="unit.id"
          >
            {{ unit.code }}
          </SelectItem>
        </SelectContent>
      </Select>
      <div v-if="errors.unit_id" class="text-xs text-red-600">
        {{ errors.unit_id }}
      </div>
    </div>

    <!-- Remove Button -->
    <div class="flex items-end">
      <Button
        type="button"
        variant="outline"
        size="sm"
        @click="$emit('remove', index)"
        class="w-full text-red-600 hover:text-red-700 hover:bg-red-50"
      >
        <Trash2 class="w-4 h-4" />
        <span class="sr-only">Remove ingredient</span>
      </Button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
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
import {
  Combobox,
  ComboboxTrigger,
  ComboboxList,
  ComboboxInput,
  ComboboxEmpty,
  ComboboxItem,
} from '@/Components/ui/combobox'

// Define props
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

// Define emits
const emit = defineEmits(['update', 'remove'])

// Local reactive copy of ingredient
const localIngredient = ref({ ...props.ingredient })

// Search term for filtering ingredients
const searchTerm = ref('')

// Watch for changes in props.ingredient and update local copy
watch(() => props.ingredient, (newIngredient) => {
  localIngredient.value = { ...newIngredient }
}, { deep: true })

// Watch for changes in local ingredient and emit updates
watch(localIngredient, (newIngredient) => {
  emit('update', props.index, { ...newIngredient })
}, { deep: true, immediate: false })

// Computed properties
const selectedIngredient = computed(() => {
  return props.availableIngredients.find(
    ing => ing.id === localIngredient.value.ingredient_id
  )
})

const filteredIngredients = computed(() => {
  if (!searchTerm.value) {
    return props.availableIngredients
  }

  return props.availableIngredients.filter(ingredient =>
    ingredient.name.toLowerCase().includes(searchTerm.value.toLowerCase())
  )
})

// Handle search input
const handleSearch = (event) => {
  searchTerm.value = event.target.value
}
</script>
