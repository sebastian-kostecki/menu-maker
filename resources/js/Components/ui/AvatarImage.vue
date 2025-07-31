<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { cn } from '@/lib/utils'

export interface AvatarImageProps {
  src?: string
  alt?: string
  class?: string
}

const props = defineProps<AvatarImageProps>()

const imageLoaded = ref(false)
const imageError = ref(false)

const handleLoad = () => {
  imageLoaded.value = true
  imageError.value = false
}

const handleError = () => {
  imageError.value = true
  imageLoaded.value = false
}
</script>

<template>
  <img
    v-if="src && !imageError"
    :src="src"
    :alt="alt"
    :class="cn('aspect-square h-full w-full', props.class)"
    @load="handleLoad"
    @error="handleError"
  />
</template>
