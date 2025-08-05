<template>
  <div
    class="grid grid-cols-1 md:grid-cols-5 gap-4 p-4 border border-border rounded-lg bg-card"
    role="group"
    :aria-label="`Ingredient ${index + 1}`"
  >
    <!-- Ingredient Selection -->
    <div class="md:col-span-2 space-y-1">
      <label
        :for="`ingredient-${index}`"
        class="text-sm font-medium text-foreground"
      >
        Ingredient *
      </label>
                  <Select
        v-model="localIngredient.ingredient_id"
        :open="isSelectOpen"
        @update:open="handleSelectOpenChange"
      >
        <SelectTrigger
          :class="{ 'border-red-500': errors.ingredient_id }"
          :aria-invalid="!!errors.ingredient_id"
          :aria-describedby="errors.ingredient_id ? `ingredient-error-${index}` : undefined"
        >
          <SelectValue placeholder="Select ingredient..." />
        </SelectTrigger>
        <SelectContent>
          <div class="p-2">
            <Input
              v-model="searchTerm"
              placeholder="Search or add ingredient..."
              class="mb-2"
              @keydown.escape="isSelectOpen = false"
              @keydown.enter.prevent="handleEnterKey"
            />
          </div>
          <SelectItem
            v-for="ingredient in filteredIngredients"
            :key="ingredient.id"
            :value="ingredient.id"
            @click="handleIngredientSelect"
          >
            {{ ingredient.name }}
          </SelectItem>
          <div v-if="searchTerm && !exactMatch && filteredIngredients.length === 0" class="p-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              @click="handleAddNewIngredient"
              class="w-full"
            >
              Add "{{ searchTerm }}"
            </Button>
          </div>
          <div v-else-if="filteredIngredients.length === 0" class="p-2 text-sm text-muted-foreground">
            No ingredients found.
          </div>
        </SelectContent>
      </Select>
      <div
        v-if="errors.ingredient_id"
        :id="`ingredient-error-${index}`"
        class="text-xs text-red-600"
        role="alert"
      >
        {{ errors.ingredient_id }}
      </div>
    </div>

    <!-- Quantity -->
    <div class="space-y-1">
      <label
        :for="`quantity-${index}`"
        class="text-sm font-medium text-foreground"
      >
        Quantity *
      </label>
      <Input
        :id="`quantity-${index}`"
        v-model="localIngredient.quantity"
        type="number"
        step="0.01"
        min="0.01"
        placeholder="e.g. 2"
        :class="{ 'border-red-500': errors.quantity }"
        :aria-invalid="!!errors.quantity"
        :aria-describedby="errors.quantity ? `quantity-error-${index}` : undefined"
        aria-label="Ingredient quantity"
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
        class="text-sm font-medium text-foreground"
      >
        Unit *
      </label>
      <Select
        v-model="localIngredient.unit_id"
        :id="`unit-${index}`"
      >
        <SelectTrigger
          :class="{ 'border-red-500': errors.unit_id }"
          :aria-invalid="!!errors.unit_id"
          :aria-describedby="errors.unit_id ? `unit-error-${index}` : undefined"
        >
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
        @click="handleRemoveIngredient"
        class="w-full text-red-600 hover:text-red-700 hover:bg-red-50"
        :aria-label="`Remove ingredient ${index + 1}`"
      >
        <Trash2 class="w-4 h-4" />
        <span class="sr-only">Remove ingredient</span>
      </Button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch, nextTick } from 'vue'
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
const emit = defineEmits(['update', 'remove', 'add-ingredient'])

// Local reactive copy of ingredient
const localIngredient = ref({ ...props.ingredient })

// Search term for filtering ingredients
const searchTerm = ref('')

// Control Select open state
const isSelectOpen = ref(false)

// Flag to prevent infinite watcher loops
const isUpdatingFromParent = ref(false)

// Watch for changes in props.ingredient and update local copy
watch(() => props.ingredient, (newIngredient) => {
  isUpdatingFromParent.value = true
  localIngredient.value = { ...newIngredient }
  // Reset flag after nextTick to allow DOM updates
  nextTick(() => {
    isUpdatingFromParent.value = false
  })
}, { deep: true })

// Watch for changes in local ingredient and emit updates
watch(localIngredient, (newIngredient) => {
  // Don't emit if we're updating from parent to prevent infinite loops
  if (!isUpdatingFromParent.value) {
    emit('update', props.index, { ...newIngredient })
  }
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

// Check if search term exactly matches an existing ingredient
const exactMatch = computed(() => {
  return props.availableIngredients.some(
    ingredient => ingredient.name.toLowerCase() === searchTerm.value.toLowerCase()
  )
})

// Handle Select open/close
const handleSelectOpenChange = (open) => {
  isSelectOpen.value = open
  // Clear search term when closing
  if (!open) {
    searchTerm.value = ''
  }
}

// Handle ingredient selection - close select after choosing
const handleIngredientSelect = () => {
  isSelectOpen.value = false
  searchTerm.value = ''
}

// Handle Enter key in search input
const handleEnterKey = () => {
  if (filteredIngredients.value.length === 1) {
    // If only one ingredient matches, select it
    localIngredient.value.ingredient_id = filteredIngredients.value[0].id
    handleIngredientSelect()
  } else if (searchTerm.value && !exactMatch.value && filteredIngredients.value.length === 0) {
    // If no matches and search term exists, add new ingredient
    handleAddNewIngredient()
  }
}

// Handle adding new ingredient
const handleAddNewIngredient = () => {
  if (searchTerm.value.trim()) {
    emit('add-ingredient', searchTerm.value.trim())
    searchTerm.value = ''
    isSelectOpen.value = false
  }
}

// Handle remove ingredient
const handleRemoveIngredient = () => {
  emit('remove', props.index)
}
</script>
