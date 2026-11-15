<template>
  <div
    v-if="meta.last_page > 1"
    class="flex flex-col items-center justify-between gap-3 sm:flex-row"
  >
    <p class="text-sm text-muted-foreground tabular-nums">
      Wyniki {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} z {{ meta.total }}
    </p>

    <Pagination
      v-slot="{ page: currentPage }"
      :page="meta.current_page"
      :items-per-page="meta.per_page"
      :total="meta.total"
      :sibling-count="1"
      show-edges
      aria-label="Paginacja"
      class="mx-0 w-auto"
      @update:page="handlePageUpdate"
    >
      <PaginationContent v-slot="{ items }">
        <PaginationPrevious>
          <ChevronLeft aria-hidden="true" />
          <span>Poprzednia</span>
        </PaginationPrevious>
        <template v-for="(item, index) in items" :key="index">
          <PaginationItem
            v-if="item.type === 'page'"
            :value="item.value"
            :is-active="item.value === currentPage"
            :aria-label="`Strona ${item.value}`"
          >
            {{ item.value }}
          </PaginationItem>
          <PaginationEllipsis v-else :index="index" />
        </template>
        <PaginationNext>
          <span>Następna</span>
          <ChevronRight aria-hidden="true" />
        </PaginationNext>
      </PaginationContent>
    </Pagination>
  </div>
</template>

<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationNext,
  PaginationPrevious,
} from '@/Components/ui/pagination'

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

const props = defineProps<{
  meta: PaginationMeta
}>()

const emit = defineEmits<{
  (e: 'page-change', page: number): void
}>()

const handlePageUpdate = (page: number) => {
  if (page !== props.meta.current_page) {
    emit('page-change', page)
  }
}
</script>
