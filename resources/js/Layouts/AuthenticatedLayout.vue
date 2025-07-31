<script setup>
import { ref } from 'vue'
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import Sidebar from '@/Components/Sidebar.vue'
import Avatar from '@/Components/ui/Avatar.vue'
import AvatarFallback from '@/Components/ui/AvatarFallback.vue'
import AvatarImage from '@/Components/ui/AvatarImage.vue'
import { User, LogOut } from 'lucide-vue-next'

// Get user initials for avatar fallback
const getUserInitials = (name) => {
  return name
    .split(' ')
    .map(word => word.charAt(0))
    .join('')
    .toUpperCase()
    .slice(0, 2)
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <!-- Sidebar Navigation -->
    <Sidebar>
      <div class="flex min-h-screen flex-col">
        <!-- Top Header -->
        <header class="sticky top-0 z-30 border-b border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
          <div class="flex items-center justify-between">
            <!-- Page title slot -->
            <div v-if="$slots.header" class="flex-1">
              <slot name="header" />
            </div>
            <div v-else class="flex-1">
              <h1 class="text-lg font-semibold text-gray-900 dark:text-white">
                Menu Maker
              </h1>
            </div>

            <!-- User menu -->
            <div class="flex items-center space-x-4">
              <!-- User avatar dropdown -->
              <Dropdown align="right" width="56">
                <template #trigger>
                  <button
                    type="button"
                    class="flex items-center space-x-3 rounded-lg px-3 py-2 text-sm transition-colors hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:hover:bg-gray-700 dark:focus:bg-gray-700"
                    aria-label="Menu użytkownika"
                  >
                    <Avatar size="sm">
                      <AvatarImage
                        :src="$page.props.auth.user.avatar"
                        :alt="$page.props.auth.user.name"
                      />
                      <AvatarFallback>
                        {{ getUserInitials($page.props.auth.user.name) }}
                      </AvatarFallback>
                    </Avatar>
                    <div class="hidden text-left sm:block">
                      <div class="text-sm font-medium text-gray-900 dark:text-white">
                        {{ $page.props.auth.user.name }}
                      </div>
                      <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $page.props.auth.user.email }}
                      </div>
                    </div>
                    <svg
                      class="h-4 w-4 text-gray-400"
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 20 20"
                      fill="currentColor"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                        clip-rule="evenodd"
                      />
                    </svg>
                  </button>
                </template>

                <template #content>
                  <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-600">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                      {{ $page.props.auth.user.name }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                      {{ $page.props.auth.user.email }}
                    </div>
                  </div>

                  <DropdownLink :href="route('profile.edit')">
                    <div class="flex items-center space-x-2">
                      <User class="h-4 w-4" />
                      <span>Profil</span>
                    </div>
                  </DropdownLink>

                  <DropdownLink
                    :href="route('logout')"
                    method="post"
                    as="button"
                  >
                    <div class="flex items-center space-x-2">
                      <LogOut class="h-4 w-4" />
                      <span>Wyloguj</span>
                    </div>
                  </DropdownLink>
                </template>
              </Dropdown>
            </div>
          </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 overflow-auto">
          <div class="p-6">
            <slot />
          </div>
        </main>
      </div>
    </Sidebar>
  </div>
</template>
