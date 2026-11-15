<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { Button } from '@/Components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import { useFamilyMemberBodyValidation } from '@/hooks/useFamilyMemberBodyValidation'

const props = defineProps({
  open: { type: Boolean, required: true },
  member: { type: Object, default: null },
})

const emit = defineEmits(['update:open'])

const { validateBodyData } = useFamilyMemberBodyValidation()

const emptyForm = () => ({
  first_name: '',
  birth_date: '',
  gender: '',
  weight_kg: '',
  height_cm: '',
})

const form = ref(emptyForm())
const errors = ref({})
const isSubmitting = ref(false)

const isEditing = computed(() => props.member !== null)
const dialogTitle = computed(() => (isEditing.value ? 'Edytuj członka rodziny' : 'Dodaj członka rodziny'))

const formatLocalDate = (date) => {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${date.getFullYear()}-${month}-${day}`
}

const maxDate = computed(() => {
  const yesterday = new Date()
  yesterday.setDate(yesterday.getDate() - 1)

  return formatLocalDate(yesterday)
})

const resetForm = () => {
  errors.value = {}
  form.value = props.member
    ? {
        first_name: props.member.first_name,
        birth_date: props.member.birth_date,
        gender: props.member.gender,
        weight_kg: props.member.weight_kg ?? '',
        height_cm: props.member.height_cm ?? '',
      }
    : emptyForm()
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      resetForm()
    }
  },
  { immediate: true },
)

const validateForm = () => {
  const newErrors = {}

  if (!form.value.first_name.trim()) {
    newErrors.first_name = 'Imię jest wymagane'
  } else if (form.value.first_name.length > 255) {
    newErrors.first_name = 'Imię nie może być dłuższe niż 255 znaków'
  }

  if (!form.value.birth_date) {
    newErrors.birth_date = 'Data urodzenia jest wymagana'
  } else if (form.value.birth_date > maxDate.value) {
    newErrors.birth_date = 'Data urodzenia musi być wcześniejsza niż dzisiaj'
  }

  if (!form.value.gender) {
    newErrors.gender = 'Płeć jest wymagana'
  } else if (!['male', 'female'].includes(form.value.gender)) {
    newErrors.gender = 'Nieprawidłowa wartość płci'
  }

  Object.assign(newErrors, validateBodyData(form.value))

  errors.value = newErrors

  return Object.keys(newErrors).length === 0
}

const handleSubmit = () => {
  if (isSubmitting.value || !validateForm()) {
    return
  }

  isSubmitting.value = true

  const options = {
    preserveScroll: true,
    onSuccess: () => {
      emit('update:open', false)
      toast.success(isEditing.value ? 'Członek rodziny został zaktualizowany' : 'Nowy członek rodziny został dodany')
    },
    onError: (serverErrors) => {
      errors.value = serverErrors
      toast.error('Sprawdź poprawność wprowadzonych danych')
    },
    onFinish: () => {
      isSubmitting.value = false
    },
  }

  if (isEditing.value) {
    router.put(`/family-members/${props.member.id}`, form.value, options)
  } else {
    router.post('/family-members', form.value, options)
  }
}

const handleOpenChange = (value) => {
  if (!value && isSubmitting.value) {
    return
  }

  emit('update:open', value)
}

const clearError = (field) => {
  if (errors.value[field]) {
    errors.value = { ...errors.value, [field]: undefined }
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="handleOpenChange">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>{{ dialogTitle }}</DialogTitle>
        <DialogDescription>
          {{ isEditing ? 'Zaktualizuj informacje o członku rodziny.' : 'Dodaj nowego członka rodziny.' }}
        </DialogDescription>
      </DialogHeader>

      <form class="space-y-4" novalidate @submit.prevent="handleSubmit">
        <div class="space-y-2">
          <Label for="first_name">Imię *</Label>
          <Input
            id="first_name"
            v-model="form.first_name"
            type="text"
            placeholder="Wpisz imię"
            maxlength="255"
            :class="{ 'border-destructive': errors.first_name }"
            :aria-invalid="errors.first_name ? 'true' : undefined"
            :aria-describedby="errors.first_name ? 'first_name-error' : undefined"
            @update:model-value="clearError('first_name')"
          />
          <p v-if="errors.first_name" id="first_name-error" class="text-sm text-destructive">
            {{ errors.first_name }}
          </p>
        </div>

        <div class="space-y-2">
          <Label for="birth_date">Data urodzenia *</Label>
          <Input
            id="birth_date"
            v-model="form.birth_date"
            type="date"
            :max="maxDate"
            :class="{ 'border-destructive': errors.birth_date }"
            :aria-invalid="errors.birth_date ? 'true' : undefined"
            :aria-describedby="errors.birth_date ? 'birth_date-error' : undefined"
            @update:model-value="clearError('birth_date')"
          />
          <p v-if="errors.birth_date" id="birth_date-error" class="text-sm text-destructive">
            {{ errors.birth_date }}
          </p>
        </div>

        <div class="space-y-2">
          <Label for="gender">Płeć *</Label>
          <Select v-model="form.gender" @update:model-value="clearError('gender')">
            <SelectTrigger
              id="gender"
              :class="{ 'border-destructive': errors.gender }"
              :aria-invalid="errors.gender ? 'true' : undefined"
              :aria-describedby="errors.gender ? 'gender-error' : undefined"
            >
              <SelectValue placeholder="Wybierz płeć" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="male">Mężczyzna</SelectItem>
              <SelectItem value="female">Kobieta</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.gender" id="gender-error" class="text-sm text-destructive">
            {{ errors.gender }}
          </p>
        </div>

        <div class="space-y-2">
          <Label for="weight_kg">Waga (kg)</Label>
          <Input
            id="weight_kg"
            v-model="form.weight_kg"
            type="number"
            min="30"
            max="300"
            step="0.1"
            placeholder="np. 70"
            :class="{ 'border-destructive': errors.weight_kg }"
            :aria-invalid="errors.weight_kg ? 'true' : undefined"
            :aria-describedby="errors.weight_kg ? 'weight_kg-error' : undefined"
            @update:model-value="clearError('weight_kg')"
          />
          <p v-if="errors.weight_kg" id="weight_kg-error" class="text-sm text-destructive">
            {{ errors.weight_kg }}
          </p>
        </div>

        <div class="space-y-2">
          <Label for="height_cm">Wzrost (cm)</Label>
          <Input
            id="height_cm"
            v-model="form.height_cm"
            type="number"
            min="100"
            max="250"
            step="0.1"
            placeholder="np. 175"
            :class="{ 'border-destructive': errors.height_cm }"
            :aria-invalid="errors.height_cm ? 'true' : undefined"
            :aria-describedby="errors.height_cm ? 'height_cm-error' : undefined"
            @update:model-value="clearError('height_cm')"
          />
          <p v-if="errors.height_cm" id="height_cm-error" class="text-sm text-destructive">
            {{ errors.height_cm }}
          </p>
        </div>

        <DialogFooter class="gap-2">
          <Button type="button" variant="outline" :disabled="isSubmitting" @click="handleOpenChange(false)">
            Anuluj
          </Button>
          <Button type="submit" :disabled="isSubmitting">
            {{ isSubmitting ? 'Zapisywanie...' : 'Zapisz' }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
