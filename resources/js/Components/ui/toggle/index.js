import { cva } from "class-variance-authority";

export { default as Toggle } from "./Toggle.vue";

export const toggleVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full border-2 border-foreground bg-card text-label text-foreground transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:pointer-events-none disabled:opacity-50 data-[state=on]:bg-primary data-[state=on]:text-primary-foreground [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0",
  {
    variants: {
      variant: {
        default: "hover:bg-accent data-[state=on]:hover:bg-primary/90",
        breakfast:
          "bg-meal-breakfast text-meal-breakfast-foreground hover:brightness-95",
        supper: "bg-meal-supper text-meal-supper-foreground hover:brightness-95",
        dinner: "bg-meal-dinner text-meal-dinner-foreground hover:brightness-95",
      },
      size: {
        default: "h-11 px-4",
        sm: "h-9 px-3.5",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
);
