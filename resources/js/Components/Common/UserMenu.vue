<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { LogOut, User } from 'lucide-vue-next'
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'

const props = defineProps({
  user: { type: Object, required: true },
})

const initials = computed(() =>
  props.user.name
    .split(' ')
    .filter(Boolean)
    .map(word => word.charAt(0))
    .join('')
    .toUpperCase()
    .slice(0, 2)
)
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <button
        type="button"
        aria-label="Menu użytkownika"
        class="rounded-full outline-ring hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2"
      >
        <Avatar size="sm" variant="sticker" aria-hidden="true">
          <AvatarImage v-if="user.avatar" :src="user.avatar" alt="" />
          <AvatarFallback>{{ initials }}</AvatarFallback>
        </Avatar>
      </button>
    </DropdownMenuTrigger>

    <DropdownMenuContent align="end" class="w-56">
      <DropdownMenuLabel>
        <span class="block text-sm font-medium">{{ user.name }}</span>
        <span class="block text-xs font-normal text-muted-foreground">{{ user.email }}</span>
      </DropdownMenuLabel>
      <DropdownMenuSeparator />
      <DropdownMenuItem as-child>
        <Link :href="route('profile.edit')">
          <User />
          Profil
        </Link>
      </DropdownMenuItem>
      <DropdownMenuItem as-child>
        <Link :href="route('logout')" method="post" as="button" class="w-full">
          <LogOut />
          Wyloguj
        </Link>
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
