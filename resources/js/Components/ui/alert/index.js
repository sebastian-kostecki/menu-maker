import { cva } from "class-variance-authority";

export { default as Alert } from "./Alert.vue";
export { default as AlertDescription } from "./AlertDescription.vue";
export { default as AlertTitle } from "./AlertTitle.vue";

export const alertVariants = cva(
  "relative w-full rounded-md border-2 border-foreground px-4.5 py-3.5 text-sm font-medium grid has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr] grid-cols-[0_1fr] has-[>svg]:gap-x-3 gap-y-0.5 items-start [&>svg]:size-4 [&>svg]:translate-y-0.5 [&>svg]:text-current",
  {
    variants: {
      variant: {
        default: "bg-card text-card-foreground",
        destructive:
          "border-destructive text-destructive bg-card [&>svg]:text-current *:data-[slot=alert-description]:text-destructive/90",
        warning:
          "bg-warning text-warning-foreground [&>svg]:text-current *:data-[slot=alert-description]:text-warning-foreground",
        info: "bg-info text-info-foreground [&>svg]:text-current *:data-[slot=alert-description]:text-info-foreground",
        success:
          "bg-success text-success-foreground [&>svg]:text-current *:data-[slot=alert-description]:text-success-foreground",
      },
    },
    defaultVariants: { variant: "default" },
  },
);
