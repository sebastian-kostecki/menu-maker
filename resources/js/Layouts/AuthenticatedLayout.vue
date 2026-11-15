<script setup>
import { computed, reactive, ref } from 'vue'
import { Menu } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import { mainNavigation } from '@/lib/navigation'
import { useLocalStorageStateSync } from '@/lib/useLocalStorageStateSync'
import AppSidebar from '@/Components/Common/AppSidebar.vue'
import UserMenu from '@/Components/Common/UserMenu.vue'
import { Button } from '@/Components/ui/button'
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/Components/ui/sheet'
import { Toaster } from '@/Components/ui/sonner'

const sidebar = reactive({ collapsed: false })
const mobileNavOpen = ref(false)
const hasToggled = ref(false)

const { syncToStorage } = useLocalStorageStateSync()
syncToStorage('menu-maker:app-shell', { sidebar })

const items = computed(() =>
  mainNavigation.map(item => ({
    label: item.label,
    href: route(item.routeName),
    icon: item.icon,
    current: route().current(item.match),
  }))
)

const toggleCollapse = () => {
  hasToggled.value = true
  sidebar.collapsed = !sidebar.collapsed
}
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <aside
      :class="cn(
        'fixed inset-y-0 left-0 z-30 hidden border-r lg:flex',
        sidebar.collapsed ? 'w-16' : 'w-64',
        hasToggled && 'transition-all duration-300 motion-reduce:transition-none'
      )"
    >
      <AppSidebar
        class="w-full"
        :items="items"
        :collapsed="sidebar.collapsed"
        collapsible
        @toggle-collapse="toggleCollapse"
      />
    </aside>

    <div
      :class="cn(
        'flex min-h-screen flex-col',
        sidebar.collapsed ? 'lg:pl-16' : 'lg:pl-64',
        hasToggled && 'transition-all duration-300 motion-reduce:transition-none'
      )"
    >
      <Sheet v-model:open="mobileNavOpen">
        <header class="sticky top-0 z-20 border-b bg-card px-4 py-3">
          <div class="flex items-center justify-between gap-3">
            <div class="flex flex-1 items-center gap-3">
              <SheetTrigger as-child>
                <Button variant="outline" size="icon" class="shrink-0 lg:hidden" aria-label="Otwórz nawigację">
                  <Menu />
                </Button>
              </SheetTrigger>
              <div class="flex-1">
                <slot v-if="$slots.header" name="header" />
                <p v-else class="text-lg font-semibold">Menu Maker</p>
              </div>
            </div>

            <UserMenu :user="$page.props.auth.user" />
          </div>
        </header>

        <SheetContent side="left" class="w-64 p-0">
          <SheetTitle class="sr-only">Nawigacja</SheetTitle>
          <AppSidebar :items="items" @navigate="mobileNavOpen = false" />
        </SheetContent>
      </Sheet>

      <main class="flex-1 overflow-auto">
        <div class="p-6">
          <slot />
        </div>
      </main>
    </div>

    <Toaster />
  </div>
</template>
