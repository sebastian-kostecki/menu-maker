# Tailwind 4 Upgrade Implementation Plan

## Overview

Move the frontend from Tailwind 3 to Tailwind 4 (CSS-first config, `@tailwindcss/vite`), refresh the shadcn-vue components for v4, and bump lucide-vue-next to 1.0, @vueuse/core to 15 and concurrently to 10, with no visible regression in light or dark mode.

## Current State Analysis

Tailwind 3 with `tailwind.config.js`, `postcss.config.js`, `@tailwind` directives and HSL-channel CSS variables in `resources/css/app.css`; 15 shadcn-vue components (new-york) in `resources/js/Components/ui`; `darkMode: ['class']` with `dark:` utilities used but no code toggling the class. See `research.md`.

## Desired End State

`tailwindcss` 4.x + `@tailwindcss/vite`; no `tailwind.config.js`/`postcss.config.js`/autoprefixer/postcss deps; theme in `app.css` via `@theme`; `@plugin "@tailwindcss/forms"`; `tw-animate-css` replaces `tailwindcss-animate`; dark variant preserved through `@custom-variant dark`; components.json consistent with v4; client and SSR builds pass; Pest, phpstan, pint, E2E green; `screenshots/after/` matches `screenshots/before/` apart from intended differences.

### Key Discoveries:

- `resources/css/app.css:1-3` uses `@tailwind` directives; `tailwind.config.js` needs conversion (container, colors, radius, accordion keyframes, forms plugin).
- vite.config.js must stay free of `__dirname`/`require` (Vite 8 native loader); `@tailwindcss/vite` is a plain ESM import.
- `composer.json:63` uses `npx concurrently` (no config to migrate).

## What We're NOT Doing

- Fixing the pre-existing blank `/recipes/{id}` page (`Recipes/Show.vue` reads `recipe.data.name`).
- Adding a dark-mode toggle or redesigning anything.
- Changing the colour palette (keep HSL variables, wrapped via `hsl(var(--x))` in `@theme inline`).
- Adding the screenshot script to the test suite.

## Implementation Approach

Run the official `@tailwindcss/upgrade` tool through Sail on a clean tree, review its diff, and hand-correct: it handles utility renames and css conversion. Then refresh shadcn-vue components with the CLI (`--overwrite`), diff-review them, and finally bump the other three packages. Verify visually after each risky phase with `screenshots/capture.mjs after`.

## Phase 1: Tailwind 4 core migration

### Overview

Dependencies, Vite plugin, CSS-first config, utility renames.

### Changes Required:

#### 1. Dependencies and Vite

**File**: `package.json`, `vite.config.js`, `postcss.config.js`

**Intent**: Install tailwindcss 4 and `@tailwindcss/vite`, register the plugin in Vite, drop postcss/autoprefixer and the PostCSS config.

**Contract**: `import tailwindcss from '@tailwindcss/vite'` added to `plugins`; no `__dirname`/`require`. `@tailwindcss/forms` stays (latest). `tailwindcss-animate` replaced by `tw-animate-css`.

#### 2. CSS-first theme

**File**: `resources/css/app.css`, delete `tailwind.config.js`

**Intent**: Replace `@tailwind` with `@import "tailwindcss"`, port theme (fonts incl. Figtree, colours via `@theme inline` mapping to existing `hsl(var(--x))` variables, radius, accordion keyframes/animations, container utility), `@plugin "@tailwindcss/forms"`, `@import "tw-animate-css"`, `@source` for Laravel view globs, and `@custom-variant dark (&:is(.dark *))`.

**Contract**: Keep `* { border-color: hsl(var(--border)) }` base rule so v3 border default stays.

#### 3. Utility renames

**File**: `resources/js/**/*.vue`, `resources/views/*.blade.php`

**Intent**: Apply v4 renames (shadow/rounded/blur scales, `outline-none`, `ring` width, `!important` position, removed opacity utilities) via the upgrade tool, then review by diff and screenshots.

### Success Criteria:

#### Automated Verification:

- Client and SSR build passes: `vendor/bin/sail npm run build`
- No leftover v3 config: `test ! -f tailwind.config.js && test ! -f postcss.config.js`
- Pest suite passes: `vendor/bin/sail composer test`
- E2E passes: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:8081 npm run test:e2e`

#### Manual Verification:

- `screenshots/after/` reviewed against `before/`: no unintended differences (spacing, borders, ring/focus, shadows, radius, dark)

---

## Phase 2: shadcn-vue refresh

### Overview

Bring `resources/js/Components/ui` and components.json in line with shadcn-vue for Tailwind 4.

### Changes Required:

#### 1. components.json and components

**File**: `components.json`, `resources/js/Components/ui/**`

**Intent**: Set `tailwind.config` to empty (v4), keep css path, then re-add each of the 15 components with `vendor/bin/sail npx shadcn-vue@2 add <c> --overwrite`, reviewing the diff for local customisations and keeping them.

**Contract**: component import paths and public props unchanged so pages need no edits. The CLI may also rewrite `resources/css/app.css` (variables, imports): diff it after each add and restore the HSL variables/theme from Phase 1.

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Pest passes: `vendor/bin/sail composer test`
- E2E passes: `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:8081 npm run test:e2e`

#### Manual Verification:

- Dialog, select, dropdown, combobox, sheet, table, sonner toasts look and behave as before (screenshots plus dialog capture)

---

## Phase 3: lucide, vueuse, concurrently

### Overview

Bump the remaining packages and fix breaking changes.

### Changes Required:

#### 1. Package bumps

**File**: `package.json`, icon imports in `resources/js/**`

**Intent**: `lucide-vue-next` to 1.x (rename any removed icon imports), `@vueuse/core` to 15.x (check `reactiveOmit`, `useVModel` usage in ui components), `concurrently` to 10.x (check `composer run dev` command still valid).

### Success Criteria:

#### Automated Verification:

- Build passes: `vendor/bin/sail npm run build`
- Concurrently runs: `npx concurrently --version`
- Pest passes: `vendor/bin/sail composer test`

#### Manual Verification:

- All icons still render (screenshots)

---

## Phase 4: Docs, guidelines and final verification

### Overview

Update stack docs and prove no regression.

### Changes Required:

#### 1. CLAUDE.md and Boost guidelines

**File**: `CLAUDE.md`

**Intent**: Update "Tailwind 3" mentions; refresh Boost guidelines if they mention Tailwind 3 without touching hand-written content and Hard Rules.

#### 2. Final screenshots

**File**: `context/changes/tailwind-4-upgrade/screenshots/after/`

**Intent**: Capture and compare every screen light and dark; fix regressions.

### Success Criteria:

#### Automated Verification:

- Larastan: `vendor/bin/sail composer phpstan`
- Pint: `vendor/bin/sail composer pint-test`
- Pest: `vendor/bin/sail composer test`
- Build: `vendor/bin/sail npm run build`

#### Manual Verification:

- Human skim of `screenshots/before` vs `after` for subtle visual drift

---

## Testing Strategy

Existing Pest suite and Playwright E2E (6 tests) guard behaviour; visual parity via the screenshot script and image review.

## Performance Considerations

Tailwind 4 Vite plugin should be faster than PostCSS; check bundle CSS size does not blow up.

## Migration Notes

Dev-only tooling; rollback is `git revert` of the phase commits.

## References

- Related research: `context/changes/tailwind-4-upgrade/research.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Tailwind 4 core migration

#### Automated

- [x] 1.1 Client and SSR build passes
- [x] 1.2 No leftover v3 config files
- [x] 1.3 Pest suite passes
- [x] 1.5 E2E passes

#### Manual

- [ ] 1.4 After screenshots reviewed against before

### Phase 2: shadcn-vue refresh

#### Automated

- [x] 2.1 Build passes
- [x] 2.2 Pest passes
- [x] 2.3 E2E passes

#### Manual

- [ ] 2.4 Dialog, select, dropdown, combobox, sheet, table, sonner look as before

### Phase 3: lucide, vueuse, concurrently

#### Automated

- [ ] 3.1 Build passes
- [ ] 3.2 Concurrently runs
- [ ] 3.3 Pest passes

#### Manual

- [ ] 3.4 All icons still render

### Phase 4: Docs, guidelines and final verification

#### Automated

- [ ] 4.1 Larastan passes
- [ ] 4.2 Pint passes
- [ ] 4.3 Pest passes
- [ ] 4.4 Build passes

#### Manual

- [ ] 4.5 Human skim of before vs after screenshots
