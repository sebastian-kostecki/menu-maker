import { clsx } from "clsx"
import { extendTailwindMerge } from "tailwind-merge"

const twMerge = extendTailwindMerge({
  extend: {
    theme: {
      text: ['display', 'hero', 'page-title', 'stat', 'section-title', 'dialog-title', 'body', 'label', 'meta'],
      shadow: ['sticker', 'dialog'],
    },
  },
})

export function cn(...inputs) {
  return twMerge(clsx(inputs))
}
