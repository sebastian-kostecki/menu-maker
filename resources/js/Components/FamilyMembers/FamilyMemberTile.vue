<script setup>
import { computed } from 'vue'
import { Pencil, Trash2 } from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'
import { Card } from '@/Components/ui/card'
import { ageInYears, formatDate, formatNumber, pluralize } from '@/lib/format'

const props = defineProps({
  member: { type: Object, required: true },
  colour: {
    type: String,
    required: true,
    validator: (value) => ['breakfast', 'supper', 'dinner'].includes(value),
  },
  today: { type: Date, default: () => new Date() },
})

defineEmits(['edit', 'delete'])

// Full class strings so Tailwind can see them.
const avatarClasses = {
  breakfast: 'bg-meal-breakfast text-meal-breakfast-foreground',
  supper: 'bg-meal-supper text-meal-supper-foreground',
  dinner: 'bg-meal-dinner text-meal-dinner-foreground',
}

const MISSING = '—'

const nameId = computed(() => `family-member-${props.member.id}-name`)
const initial = computed(() => props.member.first_name.charAt(0).toLocaleUpperCase('pl-PL'))
const gender = computed(() => (props.member.gender === 'male' ? 'Mężczyzna' : 'Kobieta'))
const age = computed(() => ageInYears(props.member.birth_date, props.today))

const facts = computed(() => [
  {
    label: 'Wiek',
    value: age.value === null ? MISSING : String(age.value),
    unit: pluralize(age.value ?? 0, { one: 'rok', few: 'lata', many: 'lat' }),
  },
  { label: 'Waga', value: formatNumber(props.member.weight_kg) || MISSING, unit: 'kg' },
  { label: 'Wzrost', value: formatNumber(props.member.height_cm) || MISSING, unit: 'cm' },
])
</script>

<template>
  <Card role="article" :aria-labelledby="nameId" class="flex flex-col gap-4 p-5.5">
    <div class="flex items-center gap-3.5">
      <span
        aria-hidden="true"
        class="flex size-14 shrink-0 items-center justify-center rounded-full border-2 border-foreground text-section-title font-extrabold"
        :class="avatarClasses[colour]"
      >
        {{ initial }}
      </span>
      <div class="min-w-0">
        <h2 :id="nameId" class="truncate text-dialog-title">{{ member.first_name }}</h2>
        <p class="text-meta text-muted-foreground">{{ gender }} · ur. {{ formatDate(member.birth_date) }}</p>
      </div>
    </div>

    <dl class="grid grid-cols-3 gap-2.5">
      <div v-for="fact in facts" :key="fact.label" class="rounded-md bg-muted px-2.5 py-3 text-center">
        <dt class="sr-only">{{ fact.label }}</dt>
        <dd class="flex flex-col">
          <span class="text-section-title font-extrabold tabular-nums">{{ fact.value }}</span> <span class="text-xs font-medium text-muted-foreground">{{ fact.unit }}</span>
        </dd>
      </div>
    </dl>

    <div class="flex items-center gap-2">
      <Button
        variant="outline"
        size="sm"
        :aria-label="`Edytuj ${member.first_name}`"
        @click="$emit('edit', member)"
      >
        <Pencil aria-hidden="true" />
        Edytuj
      </Button>
      <Button
        variant="ghost"
        size="sm"
        :aria-label="`Usuń ${member.first_name}`"
        @click="$emit('delete', member)"
      >
        <Trash2 aria-hidden="true" />
        Usuń
      </Button>
    </div>
  </Card>
</template>
