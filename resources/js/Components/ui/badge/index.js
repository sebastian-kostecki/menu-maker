import { cva } from "class-variance-authority";

export { default as Badge } from "./Badge.vue";

export const badgeVariants = cva(
  "inline-flex items-center rounded-full border transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring",
  {
    variants: {
      variant: {
        default:
          "border-transparent bg-primary text-primary-foreground shadow-sm hover:bg-primary/80",
        secondary:
          "border-transparent bg-secondary text-secondary-foreground hover:bg-secondary/80",
        destructive:
          "border-transparent bg-destructive text-destructive-foreground shadow-sm hover:bg-destructive/80",
        outline: "text-foreground",
        draft: "border-dashed bg-card text-foreground",
        breakfast:
          "border-transparent bg-meal-breakfast text-meal-breakfast-foreground",
        supper: "border-transparent bg-meal-supper text-meal-supper-foreground",
        dinner: "border-transparent bg-meal-dinner text-meal-dinner-foreground",
        success: "border-transparent bg-success text-success-foreground",
        warning: "border-transparent bg-warning text-warning-foreground",
        info: "border-transparent bg-info text-info-foreground",
        muted: "border-transparent bg-muted text-muted-foreground",
      },
      size: {
        default: "px-2.5 py-0.5 text-xs font-semibold",
        status: "h-8 gap-1.5 px-3 text-meta font-bold",
      },
    },
    compoundVariants: [
      { size: "status", class: "border-2 border-foreground shadow-none" },
    ],
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
);
