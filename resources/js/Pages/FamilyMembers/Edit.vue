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
  familyMember: {
    type: Object,
    required: true
  },
  genders: {
    type: Array,
    required: true
  }
})

// Form state
const form = ref({
  first_name: props.familyMember.first_name,
  birth_date: props.familyMember.birth_date,
  gender: props.familyMember.gender,
  weight_kg: props.familyMember.weight_kg ?? '',
  height_cm: props.familyMember.height_cm ?? ''
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

  // Body data validation (required from 19 years of age)
  const birthForAge = form.value.birth_date ? new Date(form.value.birth_date) : null
  let isAdult = false
  if (birthForAge && !Number.isNaN(birthForAge.getTime())) {
    const now = new Date()
    let age = now.getFullYear() - birthForAge.getFullYear()
    if (now.getMonth() < birthForAge.getMonth() || (now.getMonth() === birthForAge.getMonth() && now.getDate() < birthForAge.getDate())) {
      age--
    }
    isAdult = age >= 19
  }

  const weight = String(form.value.weight_kg ?? '').trim()
  if (weight === '') {
    if (isAdult) {
      newErrors.weight_kg = 'Podaj wagę (wymagana od 19 lat).'
    }
  } else if (Number.isNaN(Number(weight)) || Number(weight) < 30 || Number(weight) > 300) {
    newErrors.weight_kg = 'Waga musi wynosić od 30 do 300 kg.'
  }

  const height = String(form.value.height_cm ?? '').trim()
  if (height === '') {
    if (isAdult) {
      newErrors.height_cm = 'Podaj wzrost (wymagany od 19 lat).'
    }
  } else if (Number.isNaN(Number(height)) || Number(height) < 100 || Number(height) > 250) {
    newErrors.height_cm = 'Wzrost musi wynosić od 100 do 250 cm.'
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

  router.put(`/family-members/${props.familyMember.id}`, form.value, {
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
  <Head title="Edytuj członka rodziny" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Button variant="ghost" size="sm" @click="goBack">
          <ArrowLeft class="h-4 w-4" />
        </Button>
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Edytuj członka rodziny
          </h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Zaktualizuj informacje o {{ familyMember.first_name }}
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
              <Label for="first_name" class="block leading-6">Imię *</Label>
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
              <Label for="birth_date" class="block leading-6">Data urodzenia *</Label>
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
              <Label for="gender" class="block leading-6">Płeć *</Label>
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

            <!-- Weight -->
            <div class="space-y-2">
              <Label for="weight_kg" class="block leading-6">Waga (kg)</Label>
              <Input
                id="weight_kg"
                v-model="form.weight_kg"
                type="number"
                min="30"
                max="300"
                step="0.1"
                placeholder="np. 70"
                :class="{ 'border-red-500 focus:border-red-500': errors.weight_kg }"
              />
              <p v-if="errors.weight_kg" class="text-sm text-red-600">
                {{ errors.weight_kg }}
              </p>
            </div>

            <!-- Height -->
            <div class="space-y-2">
              <Label for="height_cm" class="block leading-6">Wzrost (cm)</Label>
              <Input
                id="height_cm"
                v-model="form.height_cm"
                type="number"
                min="100"
                max="250"
                step="0.1"
                placeholder="np. 175"
                :class="{ 'border-red-500 focus:border-red-500': errors.height_cm }"
              />
              <p v-if="errors.height_cm" class="text-sm text-red-600">
                {{ errors.height_cm }}
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
                {{ isSubmitting ? 'Zapisywanie...' : 'Zaktualizuj' }}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </AuthenticatedLayout>
</template>
