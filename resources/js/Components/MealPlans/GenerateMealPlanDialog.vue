<template>
  <Dialog v-model:open="open">
    <DialogTrigger as-child>
      <slot>
        <Button :variant="variant">
          <Plus class="mr-2 h-4 w-4" />
          {{ label }}
        </Button>
      </slot>
    </DialogTrigger>
    <DialogContent class="sm:max-w-md">
      <form class="space-y-4" @submit.prevent="submit">
        <DialogHeader>
          <DialogTitle>Wygeneruj jadłospis</DialogTitle>
          <DialogDescription>
            Wybierz dzień rozpoczęcia tygodnia. Jadłospis obejmie 7 dni po 3 posiłki.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-2">
          <Label for="start_date">Data rozpoczęcia</Label>
          <Input id="start_date" v-model="form.start_date" type="date" required />
          <p v-if="form.errors.start_date" class="text-sm text-red-600">
            {{ form.errors.start_date }}
          </p>
        </div>

        <div v-if="recipeErrors.length > 0" class="space-y-1 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          <p v-for="message in recipeErrors" :key="message">{{ message }}</p>
          <Link :href="route('recipes.create')" class="font-medium underline">
            Dodaj przepis
          </Link>
        </div>

        <p v-if="form.errors.rate_limit" class="text-sm text-red-600">
          {{ form.errors.rate_limit }}
        </p>

        <DialogFooter class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button type="button" variant="outline" @click="open = false">
            Anuluj
          </Button>
          <Button type="submit" :disabled="form.processing">
            Wygeneruj
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/Components/ui/dialog'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'

interface Props {
  label?: string
  variant?: 'default' | 'outline'
}

withDefaults(defineProps<Props>(), {
  label: 'Wygeneruj jadłospis',
  variant: 'default',
})

const open = ref(false)

// Local date parts on purpose: toISOString() would convert to UTC and can shift the day.
const nearestUpcomingMonday = (): string => {
  const date = new Date()
  const daysUntilMonday = (8 - date.getDay()) % 7
  date.setDate(date.getDate() + daysUntilMonday)
  const pad = (value: number): string => String(value).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

const form = useForm({
  start_date: nearestUpcomingMonday(),
})

const recipeErrors = computed<string[]>(() => {
  const errors = form.errors as Record<string, string | string[] | undefined>
  const value = errors.recipes
  if (!value) {
    return []
  }

  return Array.isArray(value) ? value : [value]
})

const submit = () => {
  form.clearErrors()
  form.post(route('meal-plans.store'))
}
</script>
