---
date: 2026-11-13
researcher: Claude (unattended)
git_commit: 9b52c59
branch: develop
repository: menu-maker
topic: "Tailwind 3 to 4 upgrade, shadcn-vue refresh, lucide-vue-next 1.0, @vueuse/core 15, concurrently 10"
tags: [research, tailwind, shadcn-vue, vite, css]
status: complete
last_updated: 2026-11-13
last_updated_by: Claude (unattended)
---

# Research: Tailwind 4 upgrade

## Research Question

What must change in this repo to move tailwindcss 3 -> 4, refresh shadcn-vue components, and bump lucide-vue-next 1.0, @vueuse/core 15, concurrently 10, without visual regressions (light and dark)?

## Summary

- Tailwind setup is classic v3: `tailwind.config.js` (content globs, `darkMode: ['class']`, shadcn colors as `hsl(var(--x))`, radius scale, accordion keyframes, plugins `@tailwindcss/forms` + `tailwindcss-animate`), `postcss.config.js` (tailwindcss + autoprefixer), `resources/css/app.css` (`@tailwind` directives, HSL channel variables in `:root` and `.dark`, `@apply border-border`). `vite.config.js` has no CSS plugin (PostCSS auto-detected).
- Dark mode is only partly live: `dark:` classes exist in several pages and `.dark` variables exist, but the sampled source (grep of `resources/js`, `resources/views`) found no code that toggles the `dark` class; so dark only applies if `.dark` is set manually. It must still be preserved: v4 needs `@custom-variant dark (&:is(.dark *))`.
- 15 shadcn-vue component folders live in `resources/js/Components/ui` (avatar, badge, button, card, combobox, dialog, dropdown-menu, input, label, select, separator, sheet, sonner, table, textarea); components.json is style `new-york`, `tailwind.config: tailwind.config.js`, `iconLibrary: lucide`.
- Icons imported from lucide-vue-next (distinct names found): AlertTriangle ArrowLeft Calendar Check ChefHat ChevronDown ChevronLeft ChevronRight ChevronUp ChevronsUpDown Circle Edit Eye FileText LogOut MoreHorizontal Plus RotateCcw Save Search Trash2 User X. Several may be renamed/removed in 1.0 (e.g. AlertTriangle -> TriangleAlert, Edit -> SquarePen alias status unknown): verify by build.
- @vueuse/core used only via `reactiveOmit` and `useVModel` in ui components (10 files seen). Both are expected to remain in v15; verify by build.
- concurrently is only used through `npx concurrently` in composer.json:63 (`dev` script); devDependency bump is low risk.
- Baseline visuals captured: `screenshots/before/` (14 screens x light/dark, script `screenshots/capture.mjs`). `/recipes/1` (Show) renders blank in the baseline with a console TypeError (`recipe.data.name` undefined, Pages/Recipes/Show.vue:2): pre-existing, out of scope.

## Detailed Findings

### Config surface
- `tailwind.config.js` - everything above; `require("tailwindcss-animate")` inside an ESM file.
- `postcss.config.js` - to be removed with `@tailwindcss/vite`.
- `resources/css/app.css:1-3` `@tailwind` directives; `:5-58` variable blocks; `:60-68` base layer.
- `container` config (center, 2rem padding, 2xl 1400px) must become `@utility container`.
- v4 default changes that hit this app: border color default is currentColor (mitigated by `* { @apply border-border }` in base, keep it); `ring` default 3px -> 1px; `shadow-sm`->`shadow-xs`, `rounded-sm`->`rounded-xs`, `outline-none`->`outline-hidden`, `shadow`->`shadow-sm`, `blur` etc.; `bg-opacity-*` removed; important modifier syntax `!x` -> `x!`; `space-*`/`divide-*` selector change; buttons use `cursor: default`; `hsl(var(--x))` colour usage must be kept consistent with the variables.

### shadcn-vue for Tailwind 4
- Official path (shadcn-vue docs, Tailwind v4): `@import "tailwindcss"; @import "tw-animate-css"; @custom-variant dark ...; :root {...oklch} @theme inline {...}`; components.json `tailwind.config` empty string; `tailwindcss-animate` replaced by `tw-animate-css`. Re-adding components with the CLI (`vendor/bin/sail npx shadcn-vue@2 add <c> --overwrite`) rewrites them to v4 classes; project edits to ui files must be diffed (git) before overwrite.
- The ui/ folder is checked in, so a diff review after regeneration is the safety net; keep existing HSL variables if conversion to oklch would change colours (visual parity is the goal).

### Tooling
- Vite 8.3 with `@tailwindcss/vite` (current v4.x) is the preferred integration; `laravel-vite-plugin` 3 unaffected. `@tailwindcss/forms` still supports v4 via `@plugin "@tailwindcss/forms"`.
- Content scanning is automatic in v4 (gitignore aware); extra sources, e.g. Laravel pagination views and `storage/framework/views`, need `@source` if used. The app uses Inertia/Vue; `resources/views/app.blade.php` only.
- SSR build (`vite build --ssr`) must keep working.

## Code References

- `tailwind.config.js:1-100`, `postcss.config.js:1-6`, `resources/css/app.css:1-68`, `components.json`, `vite.config.js:1-37`, `package.json`, `composer.json:63`, `resources/js/lib/utils.js` (tailwind-merge 3 already v4-ready).

## Architecture Insights

Visual parity is the acceptance criterion; the capture script plus Read of PNGs is the verification. Existing legacy components (Components/*.vue like PrimaryButton, TextInput) use Breeze-era classes and `@tailwindcss/forms` styles; those are most exposed to default changes (ring, border).

## Historical Context (from prior changes)

- `context/archive/` holds earlier upgrade changes (e.g. inertia-3-vite-8-upgrade): vite.config.js must stay free of `__dirname`/`require` (Vite 8 native loader).

## Related Research

None applicable.

## Open Questions

- Exact lucide-vue-next 1.0 icon renames: resolved at build time.
- Whether `npx @tailwindcss/upgrade` runs on Node 22 in Sail: expected yes (needs Node 20+).
