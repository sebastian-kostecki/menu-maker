<script setup>
import { Link } from '@inertiajs/vue3'
import { ChefHat } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

defineProps({
  href: { type: String, required: true },
  tone: { type: String, default: 'default', validator: value => ['default', 'inverse'].includes(value) },
})

const emit = defineEmits(['start'])
</script>

<template>
  <Link
    :href="href"
    class="group inline-flex items-center gap-3 rounded-full outline-ring focus-visible:outline-2 focus-visible:outline-offset-2"
    @start="emit('start')"
  >
    <span
      :class="cn(
        'flex size-10 shrink-0 items-center justify-center rounded-full',
        tone === 'inverse' ? 'bg-card text-foreground' : 'bg-primary text-primary-foreground'
      )"
    >
      <ChefHat class="size-5" aria-hidden="true" />
    </span>
    <span class="text-section-title font-extrabold tracking-tight group-hover:underline group-hover:underline-offset-4">
      Menu Maker
    </span>
  </Link>
</template>
