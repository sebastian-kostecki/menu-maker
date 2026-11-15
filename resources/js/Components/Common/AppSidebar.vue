<script setup>
import { Link } from '@inertiajs/vue3'
import { ChefHat, PanelLeftClose, PanelLeftOpen } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import { Button } from '@/Components/ui/button'

defineProps({
  items: { type: Array, required: true },
  collapsed: { type: Boolean, default: false },
  collapsible: { type: Boolean, default: false },
})

const emit = defineEmits(['toggle-collapse', 'navigate'])
</script>

<template>
  <div class="flex h-full flex-col bg-card">
    <div
      :class="cn(
        'p-4',
        collapsed ? 'flex flex-col items-center gap-2' : 'flex items-center justify-between'
      )"
    >
      <Link
        :href="route('dashboard')"
        class="flex items-center gap-2 rounded-md focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring"
        @start="emit('navigate')"
      >
        <ChefHat class="size-8 shrink-0 text-primary" />
        <span v-if="!collapsed" class="text-xl font-semibold tracking-tight text-foreground">Menu Maker</span>
        <span v-else class="sr-only">Menu Maker</span>
      </Link>

      <Button
        v-if="collapsible"
        variant="ghost"
        size="icon"
        :aria-label="collapsed ? 'Rozwiń panel boczny' : 'Zwiń panel boczny'"
        :aria-expanded="!collapsed"
        @click="emit('toggle-collapse')"
      >
        <PanelLeftOpen v-if="collapsed" />
        <PanelLeftClose v-else />
      </Button>
    </div>

    <nav aria-label="Główna nawigacja" class="flex-1 space-y-1 border-t px-3 py-4">
      <Link
        v-for="item in items"
        :key="item.label"
        :href="item.href"
        :title="collapsed ? item.label : undefined"
        :aria-current="item.current ? 'page' : undefined"
        :class="cn(
          'flex items-center gap-3 rounded-md px-3 py-2 text-sm text-muted-foreground transition-colors motion-reduce:transition-none',
          'hover:bg-muted hover:text-foreground',
          'focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring',
          item.current && 'bg-accent font-medium text-accent-foreground',
          collapsed && 'justify-center'
        )"
        @start="emit('navigate')"
      >
        <component :is="item.icon" :class="cn('size-5 shrink-0', item.current && 'text-primary')" />
        <span :class="collapsed ? 'sr-only' : 'truncate'">{{ item.label }}</span>
      </Link>
    </nav>

    <div v-if="!collapsed" class="border-t p-4 text-xs text-muted-foreground">Menu Maker v1.0</div>
  </div>
</template>
