import { cva } from "class-variance-authority";

export { default as Button } from "./Button.vue";

export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full border-2 border-foreground text-label transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:pointer-events-none disabled:opacity-50 disabled:shadow-none [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0",
  {
    variants: {
      variant: {
        default: "bg-primary text-primary-foreground hover:bg-primary/90",
        destructive:
          "bg-destructive text-destructive-foreground hover:bg-destructive/90",
        outline:
          "bg-card text-foreground shadow-sticker hover:bg-accent active:translate-y-0.75 active:shadow-none motion-reduce:active:translate-y-0",
        secondary: "border-transparent bg-muted hover:bg-accent",
        ghost: "border-transparent hover:bg-accent",
        link: "h-auto border-0 p-0 text-foreground underline underline-offset-4 decoration-1 hover:decoration-2",
        inverse:
          "border-card bg-card text-foreground hover:bg-accent focus-visible:outline-highlight-foreground",
        "inverse-outline":
          "border-highlight-foreground bg-transparent text-highlight-foreground hover:bg-highlight-foreground hover:text-highlight focus-visible:outline-highlight-foreground",
        dashed:
          "rounded-md border-dashed bg-transparent text-foreground hover:bg-accent",
      },
      size: {
        default: "h-11 px-5",
        xs: "h-7 px-2.5 text-xs font-semibold",
        sm: "h-8 px-3 text-meta font-semibold",
        lg: "h-13 px-6.5 text-base font-semibold",
        icon: "size-8.5 p-0",
        tile: "min-h-57.5 w-full flex-col gap-2.5 rounded-lg px-5 py-5.5 text-label font-bold",
      },
    },
    compoundVariants: [
      {
        variant: "link",
        class: "h-auto px-0 py-0",
      },
    ],
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
);
