<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ChevronDown, LogOut, User } from 'lucide-vue-next'
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar'
import { Button } from '@/Components/ui/button'
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
      <Button variant="ghost" class="h-auto gap-3 px-3 py-2">
        <span class="sr-only">Menu użytkownika</span>
        <Avatar>
          <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
          <AvatarFallback>{{ initials }}</AvatarFallback>
        </Avatar>
        <span class="hidden text-left sm:block">
          <span class="block text-sm font-medium">{{ user.name }}</span>
          <span class="block text-xs text-muted-foreground">{{ user.email }}</span>
        </span>
        <ChevronDown class="size-4 text-muted-foreground" />
      </Button>
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
