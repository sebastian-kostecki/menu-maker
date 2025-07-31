<script setup>
import { ChefHat } from 'lucide-vue-next'
import { Link } from '@inertiajs/vue3'
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
      <!-- Logo and branding -->
      <div class="text-center">
        <Link
          href="/"
          class="inline-flex items-center space-x-3 text-2xl font-bold text-gray-900 dark:text-white hover:text-gray-700 dark:hover:text-gray-300 transition-colors"
        >
          <ChefHat class="h-10 w-10 text-primary" />
          <span>Menu Maker</span>
        </Link>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          Twój osobisty asystent kulinarny
        </p>
      </div>

      <!-- Form container -->
      <div class="mt-8 w-full max-w-md">
        <div class="bg-white py-8 px-6 shadow-lg rounded-xl dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
          <slot />
        </div>
      </div>

      <!-- Footer -->
      <div class="mt-8 text-center">
        <p class="text-xs text-gray-500 dark:text-gray-400">
          © 2024 Menu Maker. Wszystkie prawa zastrzeżone.
        </p>
      </div>
    </div>
  </div>
</template>
