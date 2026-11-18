<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="outline" :aria-label="`Sortuj: ${label}`">
        <ArrowUpDown aria-hidden="true" />
        {{ label }}
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-64">
      <DropdownMenuRadioGroup :model-value="currentValue" @update:model-value="handleChange">
        <DropdownMenuRadioItem
          v-for="option in options"
          :key="option.value"
          :value="option.value"
        >
          {{ option.label }}
        </DropdownMenuRadioItem>
      </DropdownMenuRadioGroup>
    </DropdownMenuContent>
  </DropdownMenu>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { ArrowUpDown } from 'lucide-vue-next'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import { Button } from '@/Components/ui/button'

type SortDirection = 'asc' | 'desc'

interface SortOption {
  value: string
  label: string
  column: string
  direction: SortDirection
}

interface Props {
  sort: string
  direction: SortDirection
  options: SortOption[]
}

interface Emits {
  (e: 'sort-change', value: { column: string, direction: SortDirection }): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const current = computed(() =>
  props.options.find((option) => option.column === props.sort && option.direction === props.direction)
)

const currentValue = computed(() => current.value?.value ?? '')
const label = computed(() => current.value?.label ?? 'Sortuj')

const handleChange = (value: string | undefined) => {
  const option = props.options.find((candidate) => candidate.value === value)

  if (option) {
    emit('sort-change', { column: option.column, direction: option.direction })
  }
}
</script>
