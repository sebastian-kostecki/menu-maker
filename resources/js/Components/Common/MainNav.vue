<script setup>
import { Link } from '@inertiajs/vue3'
import { cn } from '@/lib/utils'

defineProps({
  items: { type: Array, required: true },
  orientation: { type: String, default: 'horizontal', validator: value => ['horizontal', 'vertical'].includes(value) },
})

const emit = defineEmits(['navigate'])
</script>

<template>
  <nav aria-label="Główna nawigacja">
    <ul
      :class="orientation === 'horizontal'
        ? 'inline-flex gap-1 rounded-full border-2 border-foreground bg-card p-1'
        : 'flex flex-col gap-1'"
    >
      <li v-for="item in items" :key="item.label" :class="orientation === 'vertical' && 'w-full'">
        <Link
          :href="item.href"
          :aria-current="item.current ? 'page' : undefined"
          :class="cn(
            'flex items-center rounded-full text-label outline-ring transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 motion-reduce:transition-none',
            orientation === 'horizontal' ? 'h-9 gap-2 px-4' : 'h-11 w-full gap-3 px-4',
            item.current
              ? 'bg-sidebar-active text-sidebar-active-foreground'
              : 'text-sidebar-foreground hover:bg-accent hover:text-accent-foreground'
          )"
          @start="emit('navigate')"
        >
          <component :is="item.icon" :class="cn('shrink-0', orientation === 'horizontal' ? 'size-4' : 'size-5')" aria-hidden="true" />
          <span class="truncate">{{ item.label }}</span>
        </Link>
      </li>
    </ul>
  </nav>
</template>
