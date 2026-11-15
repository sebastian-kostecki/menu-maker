<script setup>
import { Pencil, Trash2 } from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import { pluralizePl } from '@/lib/pluralizePl'

defineProps({
  members: { type: Array, required: true },
})

defineEmits(['edit', 'delete'])

const parseLocalDate = (dateString) => {
  const [year, month, day] = String(dateString).slice(0, 10).split('-').map(Number)

  return new Date(year, month - 1, day)
}

const dateFormatter = new Intl.DateTimeFormat('pl-PL', { year: 'numeric', month: 'long', day: 'numeric' })
const numberFormatter = new Intl.NumberFormat('pl-PL')

const formatDate = (dateString) => dateFormatter.format(parseLocalDate(dateString))

const formatAge = (dateString) => {
  const today = new Date()
  const birth = parseLocalDate(dateString)
  let age = today.getFullYear() - birth.getFullYear()

  if (today.getMonth() < birth.getMonth() || (today.getMonth() === birth.getMonth() && today.getDate() < birth.getDate())) {
    age--
  }

  return `${age} ${pluralizePl(age, 'rok', 'lata', 'lat')}`
}

const formatGender = (gender) => (gender === 'male' ? 'Mężczyzna' : 'Kobieta')

const formatMeasure = (value, unit) => {
  if (value === null || value === undefined || value === '') {
    return '—'
  }

  return `${numberFormatter.format(Number(value))} ${unit}`
}
</script>

<template>
  <Table>
    <TableHeader>
      <TableRow class="bg-muted">
        <TableHead class="text-sm font-medium">Imię</TableHead>
        <TableHead class="text-sm font-medium">Data urodzenia</TableHead>
        <TableHead class="text-sm font-medium tabular-nums text-right">Wiek</TableHead>
        <TableHead class="text-sm font-medium">Płeć</TableHead>
        <TableHead class="text-sm font-medium tabular-nums text-right">Waga</TableHead>
        <TableHead class="text-sm font-medium tabular-nums text-right">Wzrost</TableHead>
        <TableHead class="text-sm font-medium text-right">Akcje</TableHead>
      </TableRow>
    </TableHeader>
    <TableBody>
      <TableRow v-for="member in members" :key="member.id">
        <TableCell class="font-medium">{{ member.first_name }}</TableCell>
        <TableCell class="text-muted-foreground">{{ formatDate(member.birth_date) }}</TableCell>
        <TableCell class="text-muted-foreground tabular-nums text-right">{{ formatAge(member.birth_date) }}</TableCell>
        <TableCell class="text-muted-foreground">{{ formatGender(member.gender) }}</TableCell>
        <TableCell class="text-muted-foreground tabular-nums text-right">{{ formatMeasure(member.weight_kg, 'kg') }}</TableCell>
        <TableCell class="text-muted-foreground tabular-nums text-right">{{ formatMeasure(member.height_cm, 'cm') }}</TableCell>
        <TableCell class="text-right">
          <div class="flex items-center justify-end gap-2">
            <Button
              variant="ghost"
              size="icon"
              :aria-label="`Edytuj ${member.first_name}`"
              @click="$emit('edit', member)"
            >
              <Pencil class="size-4" />
            </Button>
            <Button
              variant="ghost"
              size="icon"
              class="text-destructive"
              :aria-label="`Usuń ${member.first_name}`"
              @click="$emit('delete', member)"
            >
              <Trash2 class="size-4" />
            </Button>
          </div>
        </TableCell>
      </TableRow>
    </TableBody>
  </Table>
</template>
