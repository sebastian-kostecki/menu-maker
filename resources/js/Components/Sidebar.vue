<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { cn } from '@/lib/utils'
import {
  Home,
  ChefHat,
  Calendar,
  Users,
  Menu,
  X
} from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'
import { Separator } from '@/Components/ui/separator'

const isCollapsed = ref(false)
const isMobileMenuOpen = ref(false)

const navigationItems = [
  {
    name: 'Dashboard',
    href: '/dashboard',
    routeName: 'dashboard',
    icon: Home,
    current: () => route().current('dashboard')
  },
  {
    name: 'Przepisy',
    href: '/recipes',
    routeName: 'recipes.index',
    icon: ChefHat,
    current: () => route().current('recipes.*')
  },
  {
    name: 'Jadłospisy',
    href: '/meal-plans',
    routeName: 'meal-plans.index',
    icon: Calendar,
    current: () => route().current('meal-plans.*')
  },
  {
    name: 'Członkowie rodziny',
    href: '/family-members',
    routeName: 'family-members.index',
    icon: Users,
    current: () => route().current('family-members.*')
  }
]

const toggleCollapse = () => {
  isCollapsed.value = !isCollapsed.value
}

const toggleMobileMenu = () => {
  isMobileMenuOpen.value = !isMobileMenuOpen.value
}

const closeMobileMenu = () => {
  isMobileMenuOpen.value = false
}
</script>

<template>
  <!-- Mobile menu button -->
  <div class="fixed top-4 left-4 z-50 lg:hidden">
    <Button
      variant="outline"
      size="icon"
      @click="toggleMobileMenu"
      :aria-label="isMobileMenuOpen ? 'Zamknij menu' : 'Otwórz menu'"
    >
      <Menu v-if="!isMobileMenuOpen" class="h-4 w-4" />
      <X v-else class="h-4 w-4" />
    </Button>
  </div>

  <!-- Mobile overlay -->
  <div
    v-if="isMobileMenuOpen"
    class="fixed inset-0 z-40 bg-black/50 lg:hidden"
    @click="closeMobileMenu"
  />

  <!-- Sidebar -->
  <aside
    :class="cn(
      'fixed left-0 top-0 z-40 h-screen bg-white shadow-lg transition-all duration-300 dark:bg-gray-900',
      {
        // Mobile
        'w-64 translate-x-0': isMobileMenuOpen,
        'w-64 -translate-x-full': !isMobileMenuOpen,
        // Desktop
        'lg:translate-x-0 lg:w-64': !isCollapsed,
        'lg:translate-x-0 lg:w-16': isCollapsed
      }
    )"
    role="navigation"
    aria-label="Główna nawigacja"
  >
    <div class="flex h-full flex-col">
      <!-- Logo section -->
      <div class="flex items-center justify-between p-4">
                <Link
          href="/dashboard"
          class="flex items-center space-x-2"
          @click="closeMobileMenu"
        >
          <ChefHat class="h-8 w-8 text-primary" />
          <span
            v-if="!isCollapsed || isMobileMenuOpen"
            class="text-xl font-bold text-gray-900 dark:text-white"
          >
            Menu Maker
          </span>
        </Link>

        <!-- Desktop collapse button -->
        <Button
          v-if="!isMobileMenuOpen"
          variant="ghost"
          size="icon"
          @click="toggleCollapse"
          class="hidden lg:flex"
          :aria-label="isCollapsed ? 'Rozwiń sidebar' : 'Zwiń sidebar'"
        >
          <Menu class="h-4 w-4" />
        </Button>
      </div>

      <Separator />

      <!-- Navigation items -->
      <nav class="flex-1 space-y-2 p-4">
        <Link
          v-for="item in navigationItems"
          :key="item.name"
          :href="item.href"
          @click="closeMobileMenu"
          :class="cn(
            'flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors',
            'hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-800 dark:hover:text-white',
            'focus:bg-gray-100 focus:text-gray-900 focus:outline-none dark:focus:bg-gray-800 dark:focus:text-white',
            {
              'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-white': item.current(),
              'text-gray-600 dark:text-gray-400': !item.current(),
              'justify-center': isCollapsed && !isMobileMenuOpen,
              'space-x-3': !isCollapsed || isMobileMenuOpen
            }
          )"
          :aria-current="item.current() ? 'page' : undefined"
        >
          <component :is="item.icon" class="h-5 w-5 flex-shrink-0" />
          <span
            v-if="!isCollapsed || isMobileMenuOpen"
            class="truncate"
          >
            {{ item.name }}
          </span>
        </Link>
      </nav>

      <Separator />

      <!-- Footer section -->
      <div class="p-4">
        <div
          v-if="!isCollapsed || isMobileMenuOpen"
          class="text-xs text-gray-500 dark:text-gray-400"
        >
          Menu Maker v1.0
        </div>
      </div>
    </div>
  </aside>

  <!-- Main content spacer -->
  <div
    :class="cn(
      'transition-all duration-300',
      {
        'lg:ml-64': !isCollapsed,
        'lg:ml-16': isCollapsed
      }
    )"
  >
    <slot />
  </div>
</template>
