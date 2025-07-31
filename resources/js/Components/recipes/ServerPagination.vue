<template>
  <nav
    v-if="meta.last_page > 1"
    class="flex items-center justify-between"
    aria-label="Pagination"
  >
    <div class="flex-1 flex justify-between sm:hidden">
      <!-- Mobile pagination -->
      <Button
        :disabled="!links.prev"
        variant="outline"
        size="sm"
        @click="changePage(meta.current_page - 1)"
      >
        Previous
      </Button>
      <Button
        :disabled="!links.next"
        variant="outline"
        size="sm"
        @click="changePage(meta.current_page + 1)"
      >
        Next
      </Button>
    </div>

    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
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

      <div>
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
          <!-- Previous button -->
          <Button
            :disabled="!links.prev"
            variant="outline"
            size="sm"
            class="rounded-r-none"
            @click="changePage(meta.current_page - 1)"
          >
            <ChevronLeft class="h-4 w-4" />
            <span class="sr-only">Previous</span>
          </Button>

          <!-- Page numbers -->
          <template v-for="page in pageNumbers" :key="page">
            <Button
              v-if="page !== '...'"
              :variant="page === meta.current_page ? 'default' : 'outline'"
              size="sm"
              class="rounded-none"
              @click="changePage(page)"
            >
              {{ page }}
            </Button>
            <span
              v-else
              class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500"
            >
              ...
            </span>
          </template>

          <!-- Next button -->
          <Button
            :disabled="!links.next"
            variant="outline"
            size="sm"
            class="rounded-l-none"
            @click="changePage(meta.current_page + 1)"
          >
            <ChevronRight class="h-4 w-4" />
            <span class="sr-only">Next</span>
          </Button>
        </nav>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { computed } from 'vue';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';

// Props
const props = defineProps({
  meta: {
    type: Object,
    required: true,
    validator: (meta) => {
      return typeof meta === 'object' &&
             'current_page' in meta &&
             'last_page' in meta &&
             'total' in meta;
    }
  },
  links: {
    type: Object,
    required: true,
    validator: (links) => {
      return typeof links === 'object';
    }
  }
});

// Emits
const emit = defineEmits(['page-change']);

// Computed
const pageNumbers = computed(() => {
  const current = props.meta.current_page;
  const last = props.meta.last_page;
  const delta = 2;
  const range = [];
  const rangeWithDots = [];

  for (let i = Math.max(2, current - delta);
       i <= Math.min(last - 1, current + delta);
       i++) {
    range.push(i);
  }

  if (current - delta > 2) {
    rangeWithDots.push(1, '...');
  } else {
    rangeWithDots.push(1);
  }

  rangeWithDots.push(...range);

  if (current + delta < last - 1) {
    rangeWithDots.push('...', last);
  } else if (last > 1) {
    rangeWithDots.push(last);
  }

  return rangeWithDots;
});

// Methods
const changePage = (page) => {
  if (page >= 1 && page <= props.meta.last_page && page !== props.meta.current_page) {
    emit('page-change', page);
  }
};
</script>
