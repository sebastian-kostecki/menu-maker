<script setup>
import { ChefHat } from 'lucide-vue-next'
import { Link } from '@inertiajs/vue3'
import { Card } from '@/Components/ui/card'

const currentYear = new Date().getFullYear()
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
      <!-- Logo and branding -->
      <div class="text-center">
        <Link
          :href="route('login')"
          class="inline-flex items-center space-x-3 rounded-md text-2xl font-semibold tracking-tight text-foreground transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
        >
          <ChefHat class="size-10 text-primary" />
          <span>Menu Maker</span>
        </Link>
        <p class="mt-2 text-sm text-muted-foreground">
          Twój osobisty asystent kulinarny
        </p>
      </div>

      <!-- Form container -->
      <div class="mt-8 w-full max-w-md">
        <Card class="px-6 py-8 shadow-none">
          <slot />
        </Card>
      </div>

      <!-- Footer -->
      <div class="mt-8 text-center">
        <p class="text-xs text-muted-foreground">
          © {{ currentYear }} Menu Maker. Wszystkie prawa zastrzeżone.
        </p>
      </div>
    </div>
  </div>
</template>
