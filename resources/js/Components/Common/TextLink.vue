<script setup>
import { Link } from '@inertiajs/vue3'
</script>

<template>
  <Link
    class="rounded-sm font-semibold underline underline-offset-3 hover:text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
  >
    <slot />
  </Link>
</template>
