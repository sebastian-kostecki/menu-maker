<script setup>
import { Toaster as SonnerToaster } from 'vue-sonner'

const props = defineProps({
  position: {
    type: String,
    default: 'bottom-right'
  },
  expand: {
    type: Boolean,
    default: false
  },
  richColors: {
    type: Boolean,
    default: false
  },
  closeButton: {
    type: Boolean,
    default: false
  },
  toastOptions: {
    type: Object,
    default: () => ({})
  }
})
</script>

<template>
  <SonnerToaster
    :position="position"
    :expand="expand"
    :rich-colors="richColors"
    :close-button="closeButton"
    :toast-options="{
      style: {
        background: 'hsl(var(--background))',
        color: 'hsl(var(--foreground))',
        border: '1px solid hsl(var(--border))',
      },
      ...toastOptions
    }"
  />
</template>
