<template>
  <div v-if="meta.last_page > 1" class="flex items-center justify-between px-4 py-3 bg-white border-t border-gray-200 sm:px-6">
    <!-- Mobile view -->
    <div class="flex justify-between flex-1 sm:hidden">
      <Button
        v-if="links.prev"
        variant="outline"
        size="sm"
        @click="navigateToPage(links.prev)"
      >
        Previous
      </Button>
      <div v-else></div>

      <div class="flex items-center text-sm text-gray-700">
        Page {{ meta.current_page }} of {{ meta.last_page }}
      </div>

      <Button
        v-if="links.next"
        variant="outline"
        size="sm"
        @click="navigateToPage(links.next)"
      >
        Next
      </Button>
      <div v-else></div>
    </div>

    <!-- Desktop view -->
    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
      <!-- Results info -->
      <div>
        <p class="text-sm text-gray-700">
          Showing
          <span class="font-medium">{{ meta.from || 0 }}</span>
          to
          <span class="font-medium">{{ meta.to || 0 }}</span>
          of
          <span class="font-medium">{{ meta.total }}</span>
          results
        </p>
      </div>

      <!-- Pagination controls -->
      <div class="flex items-center gap-2">
        <!-- First page -->
        <Button
          v-if="links.first && meta.current_page > 2"
          variant="outline"
          size="sm"
          @click="navigateToPage(links.first)"
          class="px-2"
        >
          <ChevronsLeft class="h-4 w-4" />
          <span class="sr-only">First page</span>
        </Button>

        <!-- Previous page -->
        <Button
          v-if="links.prev"
          variant="outline"
          size="sm"
          @click="navigateToPage(links.prev)"
          class="px-2"
        >
          <ChevronLeft class="h-4 w-4" />
          <span class="sr-only">Previous page</span>
        </Button>

        <!-- Page numbers -->
        <div class="flex items-center gap-1">
          <Button
            v-for="page in visiblePages"
            :key="page"
            :variant="page === meta.current_page ? 'default' : 'outline'"
            size="sm"
            @click="navigateToPage(buildPageUrl(page))"
            class="px-3"
            :class="page === meta.current_page ? 'bg-primary text-primary-foreground' : ''"
          >
            {{ page }}
          </Button>
        </div>

        <!-- Next page -->
        <Button
          v-if="links.next"
          variant="outline"
          size="sm"
          @click="navigateToPage(links.next)"
          class="px-2"
        >
          <ChevronRight class="h-4 w-4" />
          <span class="sr-only">Next page</span>
        </Button>

        <!-- Last page -->
        <Button
          v-if="links.last && meta.current_page < meta.last_page - 1"
          variant="outline"
          size="sm"
          @click="navigateToPage(links.last)"
          class="px-2"
        >
          <ChevronsRight class="h-4 w-4" />
          <span class="sr-only">Last page</span>
        </Button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import {
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight
} from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'
// Local type definitions
interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

interface PaginationLinks {
  first: string | null
  last: string | null
  prev: string | null
  next: string | null
}

interface Props {
  links: PaginationLinks
  meta: PaginationMeta
}

const props = defineProps<Props>()

// Calculate visible page numbers (show current page ± 2)
const visiblePages = computed(() => {
  const current = props.meta.current_page
  const last = props.meta.last_page
  const pages: number[] = []

  // Always show first page
  if (current > 3) {
    pages.push(1)
  }

  // Add ellipsis indicator if needed
  if (current > 4) {
    pages.push(-1) // -1 represents ellipsis
  }

  // Show pages around current page
  for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
    pages.push(i)
  }

  // Add ellipsis indicator if needed
  if (current < last - 3) {
    pages.push(-2) // -2 represents ellipsis
  }

  // Always show last page
  if (current < last - 2) {
    pages.push(last)
  }

  return pages
})

// Build URL for specific page
const buildPageUrl = (page: number): string => {
  const url = new URL(window.location.href)
  url.searchParams.set('page', page.toString())
  return url.toString()
}

// Navigate to specific page
const navigateToPage = (url: string | null) => {
  if (!url) return

  router.get(url, {}, {
    preserveScroll: true,
    replace: false
  })
}
</script>
