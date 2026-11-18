import { cva } from "class-variance-authority";

export { default as Card } from "./Card.vue";
export { default as CardContent } from "./CardContent.vue";
export { default as CardDescription } from "./CardDescription.vue";
export { default as CardFooter } from "./CardFooter.vue";
export { default as CardHeader } from "./CardHeader.vue";
export { default as CardTitle } from "./CardTitle.vue";

export const CARD_VARIANT_KEY = Symbol("card-variant");

export const cardVariants = cva(
  "rounded-lg border-2 bg-card text-card-foreground shadow-sticker",
  {
    variants: {
      variant: {
        default: "border-foreground",
        destructive: "border-destructive shadow-destructive",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
);
