<script setup>
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import { Label } from '@/Components/ui/label'
import { toast } from 'vue-sonner'
import { X, Save } from 'lucide-vue-next'

const props = defineProps({
  open: {
    type: Boolean,
    required: true
  },
  member: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['saved', 'close'])

// Form state
const form = ref({
  first_name: '',
  birth_date: '',
  gender: '',
  weight_kg: '',
  height_cm: ''
})

const errors = ref({})
const isSubmitting = ref(false)

// Computed properties
const isEditing = computed(() => !!props.member)
const dialogTitle = computed(() =>
  isEditing.value ? 'Edytuj członka rodziny' : 'Dodaj członka rodziny'
)

const maxDate = computed(() => {
  const today = new Date()
  today.setDate(today.getDate() - 1) // Yesterday as max date
  return today.toISOString().split('T')[0]
})

// Watch for prop changes and reset form
watch(() => props.open, (newValue) => {
  if (newValue) {
    resetForm()
    errors.value = {}
  }
})

watch(() => props.member, (newMember) => {
  if (newMember) {
    form.value = {
      first_name: newMember.first_name,
      birth_date: newMember.birth_date,
      gender: newMember.gender,
      weight_kg: newMember.weight_kg ?? '',
      height_cm: newMember.height_cm ?? ''
    }
  } else {
    resetForm()
  }
})

// Methods
const resetForm = () => {
  if (props.member) {
    form.value = {
      first_name: props.member.first_name,
      birth_date: props.member.birth_date,
      gender: props.member.gender,
      weight_kg: props.member.weight_kg ?? '',
      height_cm: props.member.height_cm ?? ''
    }
  } else {
    form.value = {
      first_name: '',
      birth_date: '',
      gender: '',
      weight_kg: '',
      height_cm: ''
    }
  }
}

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
  } else if (!['male', 'female'].includes(form.value.gender)) {
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

  try {
    const url = isEditing.value
      ? `/family-members/${props.member.id}`
      : '/family-members'

    const method = isEditing.value ? 'put' : 'post'

        router[method](url, form.value, {
      preserveScroll: true,
      onSuccess: (page) => {
        // Extract the created/updated member from the response
        let savedMember = null

        if (isEditing.value) {
          // For edit, create the updated member object
          savedMember = {
            ...props.member,
            ...form.value,
            updated_at: new Date().toISOString()
          }
          toast.success('Członek rodziny został zaktualizowany')
        } else {
          // For create, we need to get the new member
          // Since Laravel redirects back to index, we can get the latest member
          const familyMembers = page.props.familyMembers?.data || []
          savedMember = familyMembers.find(m =>
            m.first_name === form.value.first_name &&
            m.birth_date === form.value.birth_date &&
            m.gender === form.value.gender
          ) || {
            id: Date.now(), // Fallback temporary ID
            ...form.value,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString()
          }
          toast.success('Nowy członek rodziny został dodany')
        }

        emit('saved', savedMember)
      },
      onError: (serverErrors) => {
        // Handle validation errors from backend
        errors.value = serverErrors
        toast.error('Sprawdź poprawność wprowadzonych danych')
      },
      onFinish: () => {
        isSubmitting.value = false
      }
    })
  } catch (error) {
    console.error('Error submitting form:', error)
    toast.error('Wystąpił błąd podczas zapisywania. Spróbuj ponownie.')
    isSubmitting.value = false
  }
}

const handleClose = () => {
  if (!isSubmitting.value) {
    emit('close')
  }
}

const handleGenderChange = (value) => {
  form.value.gender = value
  if (errors.value.gender) {
    errors.value.gender = ''
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="handleClose">
    <DialogContent class="sm:max-w-[425px]">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          {{ dialogTitle }}
        </DialogTitle>
        <DialogDescription>
          {{ isEditing ? 'Zaktualizuj informacje o członku rodziny.' : 'Dodaj nowego członka rodziny.' }}
        </DialogDescription>
      </DialogHeader>

      <form @submit.prevent="handleSubmit" class="space-y-4">
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
      </form>

      <DialogFooter class="gap-2">
        <Button
          variant="outline"
          @click="handleClose"
          :disabled="isSubmitting"
        >
          <X class="h-4 w-4 mr-2" />
          Anuluj
        </Button>
        <Button
          @click="handleSubmit"
          :disabled="isSubmitting"
          class="flex items-center gap-2"
        >
          <Save class="h-4 w-4" />
          {{ isSubmitting ? 'Zapisywanie...' : 'Zapisz' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
