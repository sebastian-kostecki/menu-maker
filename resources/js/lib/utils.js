import { clsx } from "clsx"
import { extendTailwindMerge } from "tailwind-merge"

const twMerge = extendTailwindMerge({
  extend: {
    theme: {
      text: ['display', 'page-title', 'stat', 'section-title', 'body', 'label', 'meta'],
      shadow: ['sticker', 'dialog'],
    },
  },
})

export function cn(...inputs) {
  return twMerge(clsx(inputs))
}
