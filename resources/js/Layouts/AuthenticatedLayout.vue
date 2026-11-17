<script setup>
import { computed, ref } from 'vue'
import { Menu } from 'lucide-vue-next'
import { mainNavigation } from '@/lib/navigation'
import AppBrand from '@/Components/Common/AppBrand.vue'
import MainNav from '@/Components/Common/MainNav.vue'
import UserMenu from '@/Components/Common/UserMenu.vue'
import { Button } from '@/Components/ui/button'
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/Components/ui/sheet'
import { Toaster } from '@/Components/ui/sonner'

const mobileNavOpen = ref(false)

const items = computed(() =>
  mainNavigation.map(item => ({
    label: item.label,
    href: route(item.routeName),
    icon: item.icon,
    current: route().current(item.match),
  }))
)
</script>

<template>
  <div class="flex min-h-screen flex-col bg-background text-foreground">
    <Sheet v-model:open="mobileNavOpen">
      <header class="sticky top-0 z-20 bg-sidebar">
        <div class="flex items-center gap-6 px-4 py-4 lg:px-8">
          <SheetTrigger as-child>
            <Button variant="secondary" size="icon" class="shrink-0 rounded-full lg:hidden" aria-label="Otwórz nawigację">
              <Menu />
            </Button>
          </SheetTrigger>

          <AppBrand :href="route('dashboard')" />

          <MainNav :items="items" orientation="horizontal" class="mx-auto hidden lg:flex" />

          <div class="ms-auto lg:ms-0">
            <UserMenu :user="$page.props.auth.user" />
          </div>
        </div>
      </header>

      <SheetContent side="left" class="flex w-72 flex-col gap-6 p-5">
        <SheetHeader>
          <SheetTitle class="sr-only">Nawigacja</SheetTitle>
          <AppBrand :href="route('dashboard')" @start="mobileNavOpen = false" />
        </SheetHeader>
        <MainNav :items="items" orientation="vertical" @navigate="mobileNavOpen = false" />
      </SheetContent>
    </Sheet>

    <main class="flex-1 px-4 pb-10 pt-4 lg:px-8">
      <slot />
    </main>

    <Toaster />
  </div>
</template>
