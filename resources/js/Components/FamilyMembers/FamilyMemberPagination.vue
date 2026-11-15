<script setup>
import { Link } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { buttonVariants } from '@/Components/ui/button'
import { cn } from '@/lib/utils'

defineProps({
  currentPage: { type: Number, required: true },
  lastPage: { type: Number, required: true },
  prevPageUrl: { type: String, default: null },
  nextPageUrl: { type: String, default: null },
})

const linkClass = (enabled) =>
  cn(buttonVariants({ variant: 'outline', size: 'sm' }), !enabled && 'pointer-events-none opacity-50')
</script>

<template>
  <nav
    v-if="lastPage > 1"
    aria-label="Paginacja"
    class="flex items-center justify-between gap-4 border-t border-border px-4 py-3"
  >
    <p class="text-sm text-muted-foreground">Strona {{ currentPage }} z {{ lastPage }}</p>

    <div class="flex items-center gap-2">
      <Link v-if="prevPageUrl" :href="prevPageUrl" preserve-scroll :class="linkClass(true)">
        <ChevronLeft />
        Poprzednia
      </Link>
      <span v-else aria-disabled="true" :class="linkClass(false)">
        <ChevronLeft />
        Poprzednia
      </span>

      <Link v-if="nextPageUrl" :href="nextPageUrl" preserve-scroll :class="linkClass(true)">
        Następna
        <ChevronRight />
      </Link>
      <span v-else aria-disabled="true" :class="linkClass(false)">
        Następna
        <ChevronRight />
      </span>
    </div>
  </nav>
</template>
