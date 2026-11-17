<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="outline" :size="size" :aria-label="ariaLabel">
        <FileDown class="size-4" aria-hidden="true" />
        {{ label }}
        <ChevronDown class="size-4 text-muted-foreground" aria-hidden="true" />
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-48">
      <DropdownMenuItem as-child>
        <a :href="links.pdf_with_recipes" target="_blank" rel="noopener">
          <ChefHat aria-hidden="true" />
          Z przepisami
        </a>
      </DropdownMenuItem>
      <DropdownMenuItem as-child>
        <a :href="links.pdf" target="_blank" rel="noopener">
          <FileDown aria-hidden="true" />
          Bez przepisów
        </a>
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>

<script setup lang="ts">
import { ChefHat, ChevronDown, FileDown } from 'lucide-vue-next'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import { Button } from '@/Components/ui/button'

withDefaults(
  defineProps<{
    links: { pdf: string; pdf_with_recipes: string }
    label?: string
    size?: 'default' | 'xs' | 'sm' | 'lg'
    ariaLabel?: string
  }>(),
  { label: 'Pobierz PDF', size: 'default', ariaLabel: undefined }
)
</script>
