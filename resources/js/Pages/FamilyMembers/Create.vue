<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import { Label } from '@/Components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { ArrowLeft, Save } from 'lucide-vue-next'

const props = defineProps({
  genders: {
    type: Array,
    required: true
  }
})

// Form state
const form = ref({
  first_name: '',
  birth_date: '',
  gender: ''
})

const errors = ref({})
const isSubmitting = ref(false)

// Computed properties
const maxDate = computed(() => {
  const today = new Date()
  today.setDate(today.getDate() - 1) // Yesterday as max date
  return today.toISOString().split('T')[0]
})

// Methods
const validateForm = () => {
  const newErrors = {}

  // First name validation
  if (!form.value.first_name.trim()) {
    newErrors.first_name = 'Imię jest wymagane'
  } else if (form.value.first_name.length > 255) {
    newErrors.first_name = 'Imię nie może być dłuższe niż 255 znaków'
  }

  // Birth date validation
  if (!form.value.birth_date) {
    newErrors.birth_date = 'Data urodzenia jest wymagana'
  } else {
    const birthDate = new Date(form.value.birth_date)
    const today = new Date()
    today.setHours(0, 0, 0, 0)

    if (birthDate >= today) {
      newErrors.birth_date = 'Data urodzenia musi być wcześniejsza niż dzisiaj'
    }
  }

  // Gender validation
  if (!form.value.gender) {
    newErrors.gender = 'Płeć jest wymagana'
  } else if (!props.genders.includes(form.value.gender)) {
    newErrors.gender = 'Nieprawidłowa wartość płci'
  }

  errors.value = newErrors
  return Object.keys(newErrors).length === 0
}

const handleSubmit = async () => {
  if (!validateForm()) {
    return
  }

  isSubmitting.value = true
  errors.value = {}

  router.post('/family-members', form.value, {
    onError: (serverErrors) => {
      errors.value = serverErrors
    },
    onFinish: () => {
      isSubmitting.value = false
    }
  })
}

const handleGenderChange = (value) => {
  form.value.gender = value
  if (errors.value.gender) {
    errors.value.gender = ''
  }
}

const goBack = () => {
  router.visit('/family-members')
}
</script>

<template>
  <Head title="Dodaj członka rodziny" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Button variant="ghost" size="sm" @click="goBack">
          <ArrowLeft class="h-4 w-4" />
        </Button>
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Dodaj członka rodziny
          </h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Wprowadź informacje o nowym członku rodziny
          </p>
        </div>
      </div>
    </template>

    <div class="mx-auto max-w-2xl">
      <Card>
        <CardHeader>
          <CardTitle>Informacje o członku rodziny</CardTitle>
          <CardDescription>
            Wszystkie pola oznaczone gwiazdką (*) są wymagane.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form @submit.prevent="handleSubmit" class="space-y-6">
            <!-- First Name -->
            <div class="space-y-2">
              <Label for="first_name">Imię *</Label>
              <Input
                id="first_name"
                v-model="form.first_name"
                type="text"
                placeholder="Wpisz imię"
                :class="{ 'border-red-500 focus:border-red-500': errors.first_name }"
                maxlength="255"
                required
              />
              <p v-if="errors.first_name" class="text-sm text-red-600">
                {{ errors.first_name }}
              </p>
            </div>

            <!-- Birth Date -->
            <div class="space-y-2">
              <Label for="birth_date">Data urodzenia *</Label>
              <Input
                id="birth_date"
                v-model="form.birth_date"
                type="date"
                :max="maxDate"
                :class="{ 'border-red-500 focus:border-red-500': errors.birth_date }"
                required
              />
              <p v-if="errors.birth_date" class="text-sm text-red-600">
                {{ errors.birth_date }}
              </p>
            </div>

            <!-- Gender -->
            <div class="space-y-2">
              <Label for="gender">Płeć *</Label>
              <Select
                :model-value="form.gender"
                @update:model-value="handleGenderChange"
                required
              >
                <SelectTrigger
                  :class="{ 'border-red-500 focus:border-red-500': errors.gender }"
                >
                  <SelectValue placeholder="Wybierz płeć" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="male">Mężczyzna</SelectItem>
                  <SelectItem value="female">Kobieta</SelectItem>
                </SelectContent>
              </Select>
              <p v-if="errors.gender" class="text-sm text-red-600">
                {{ errors.gender }}
              </p>
            </div>

            <!-- Form Actions -->
            <div class="flex gap-4 pt-4">
              <Button
                type="button"
                variant="outline"
                @click="goBack"
                :disabled="isSubmitting"
                class="flex-1"
              >
                Anuluj
              </Button>
              <Button
                type="submit"
                :disabled="isSubmitting"
                class="flex-1 flex items-center justify-center gap-2"
              >
                <Save class="h-4 w-4" />
                {{ isSubmitting ? 'Zapisywanie...' : 'Zapisz' }}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </AuthenticatedLayout>
</template>
