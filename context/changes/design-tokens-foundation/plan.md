# Design Tokens Foundation Implementation Plan

## Overview

F-03 in roadmap milestone M-2, which is step DS-01 in `context/foundation/design-system.md` minus the visual gate that F-04 owns. It covers four things:

- Move `resources/css/app.css` from grey HSL tokens to the warm OKLCH palette in design-system §4.2–4.4. This adds state and meal-category tokens, the new radius, and a `.dark` block.
- Add the six missing shadcn-vue primitives from §5.1.
- Give `badge` and `alert` state and category variants.
- Write the design-system rule into `CLAUDE.md`.

Every later slice (S-07…S-14) needs these tokens and variants before it can replace literal colours.

## Current State Analysis

- `resources/css/app.css` keeps HSL channel variables in `:root` and `.dark`, wrapped as `hsl(var(--x))` inside `@theme`. That was a deliberate parity choice in the Tailwind 4 upgrade (`context/archive/2026-11-13-tailwind-4-upgrade/plan.md:25`). The palette is pure neutral and `--primary` is almost black.
- `app.css` also has a v3 compatibility rule, `*, ::after, ::before, ::backdrop, ::file-selector-button { border-color: var(--color-gray-200, currentcolor) }`. For elements it is already dead: a later `* { @apply border-border }` in the same `@layer base` wins. It still applies to the pseudo-elements, which `*` does not match, but no pseudo-element in `resources/js` has a border (only `file:border-0`), so removing it changes nothing visible.
- `--chart-1…5` are defined but nothing in `resources/js` uses them.
- The radius scale uses the legacy formulas `md = radius − 2px` and `sm = radius − 4px`, with `--radius: 0.5rem`.
- `resources/js/Components/ui/` holds 15 legacy "new-york" components. They import `cn` from `@/lib/utils` and icons from `lucide-vue-next`. `badge` has only `default`, `secondary`, `destructive` and `outline`, and there is no `alert`.
- The shadcn-vue CLI (`shadcn-vue@2`, 2.9.0) has three problems here:
  - In a Tailwind 4 project it silently switches "new-york" to **new-york-v4**. The v4 files import `cn` from the npm package `cn` and icons from `@lucide/vue`, and the CLI installs both.
  - `alert-dialog` and `pagination` depend on `button`, so the CLI asks to overwrite our `ui/button`. `--yes` does not skip that prompt; only `--overwrite` does.
  - None of the six components carry `cssVars`, so `app.css` should stay untouched.
- Every contrast pair in §4.2–4.4 was recomputed at planning time (OKLCH → sRGB → WCAG) and matches the spec. Examples: primary on card 5.67, muted-foreground on muted 5.29, state pairs ≥ 6.45, meal pairs ≥ 5.94.
- There is no JS unit-test runner. UI verification relies on `npm run build`, the Playwright E2E suite (role and text selectors, `tests/e2e/`) and manual inspection. The kitchen sink and screenshot gate come in F-04.

## Desired End State

- `app.css` defines every token in `:root` and `.dark` as full `oklch(...)` values with the values from the tables below. It exposes them through `@theme inline` as `--color-*`. This includes `success`, `warning`, `info` and `meal-breakfast`, `meal-supper`, `meal-dinner`, each with a `-foreground` pair. `--radius` is `0.625rem`. There is no `hsl(`, `gray-200` or `chart-` in the file.
- The app renders with a cream background, green primary buttons and links, a green focus ring and warm borders. Views that still use literal palette classes keep those colours, which is intended per design-system §8.
- `ui/` contains `alert-dialog`, `alert`, `checkbox`, `skeleton`, `collapsible` and `pagination` in new-york-v4 style. Their imports point at `@/lib/utils` and `lucide-vue-next`. `package.json` has no `cn` or `@lucide/vue`, and `ui/button` is byte-identical to before.
- `badgeVariants` has `breakfast`, `supper`, `dinner`, `success`, `warning` and `info` variants, and a `rounded-full` base. `alertVariants` has `warning`, `info` and `success`. All of them use only token classes.
- `CLAUDE.md` "Structure & Conventions" carries the design-system rule (3 bullets). `design-system.md` records the F-03/F-04 split, the `.dark` values and decision D-9. The F-04 roadmap entry says it adds the kitchen-sink and `lint:tokens` bullet.

### Key Discoveries:

- `resources/css/app.css:85-101` holds the `gray-200` compatibility layer (comment and rule). `app.css:160-167` holds the `border-border` rule that actually applies.
- `resources/js/Components/ui/badge/index.js:5` has the badge base (`rounded-md`). §4.6 requires `rounded-full` for badges.
- `resources/js/Components/ui/button/index.js:5-38`: legacy `buttonVariants`, with variants `default|destructive|outline|secondary|ghost|link` and sizes `default|xs|sm|lg|icon`. The v4 `alert-dialog` and `pagination` call `buttonVariants(...)`, so every variant or size they reference must exist here.
- `resources/js/lib/utils.js:4` is the `cn` that all `ui/` files must import.
- `resources/js/Components/recipes/CategoryBadge.vue` and `Components/MealPlans/StatusTag.vue` override `Badge` with literal classes. Migrating them is S-07, not this change.
- `resources/views/app.blade.php:9` already loads Figtree 400/500/600, which is enough for §4.5. No change is needed.

## What We're NOT Doing

- Migrating any view or domain component (`CategoryBadge`, `StatusTag`, `MealPlanGrid`…) to tokens. That is S-07…S-13.
- The kitchen sink page, the Playwright screenshot gate and `npm run lint:tokens`. That is F-04.
- Regenerating the 15 existing `ui/` components to new-york-v4, or moving the app from `lucide-vue-next` to `@lucide/vue`. That is a separate follow-up after F-04, recorded as D-9.
- Adding the `cn` npm package. `@/lib/utils` stays the only `cn`.
- A dark-mode toggle, or removing `dark:` classes from views (D-1). `.dark` values are defined but nothing sets `.dark`.
- PDF colours (S-14) and the typography roles (§4.5 is applied per view, not as tokens).
- Switching the radius scale to the v4 multiplicative formulas. The legacy components assume `− 2px` and `− 4px`.

## Implementation Approach

The order is tokens first, then primitives, then documentation. Changing tokens alone repaints every `ui/` component, which is the intended big-bang colour switch from design-system §8. Doing it on its own phase keeps the visual diff readable.

Primitives come second. They have to be checked against the new tokens, and the CLI step needs its own clean diff for the D-5 dependency review.

The docs come last so they describe what actually landed.

### Token values

Light values come from design-system §4.2–4.4. Dark values were derived at planning time with the same hues and inverted lightness. All are in sRGB gamut, and every text/background pair is ≥ 4.5:1 (computed).

| Token | Light `:root` | Dark `.dark` |
| --- | --- | --- |
| `--background` | `oklch(0.985 0.008 85)` | `oklch(0.20 0.012 60)` |
| `--foreground` | `oklch(0.24 0.02 60)` | `oklch(0.95 0.01 85)` |
| `--card`, `--popover` | `oklch(1 0 0)` | `oklch(0.235 0.012 60)` |
| `--card-foreground`, `--popover-foreground`, `--secondary-foreground` | = `--foreground` value | = `--foreground` value |
| `--muted`, `--secondary` | `oklch(0.955 0.012 85)` | `oklch(0.28 0.012 60)` |
| `--muted-foreground` | `oklch(0.50 0.02 60)` | `oklch(0.74 0.02 70)` |
| `--primary`, `--ring` | `oklch(0.50 0.12 150)` | `oklch(0.72 0.14 150)` |
| `--primary-foreground` | `oklch(0.99 0.005 85)` | `oklch(0.20 0.03 150)` |
| `--accent` | `oklch(0.94 0.03 150)` | `oklch(0.32 0.05 150)` |
| `--accent-foreground` | `oklch(0.32 0.08 150)` | `oklch(0.92 0.04 150)` |
| `--destructive` | `oklch(0.55 0.20 27)` | `oklch(0.65 0.19 25)` |
| `--destructive-foreground` | `oklch(0.99 0.005 85)` | `oklch(0.20 0.03 25)` |
| `--border`, `--input` | `oklch(0.90 0.012 85)` | `oklch(0.32 0.012 60)` |
| `--success` / `--success-foreground` | `oklch(0.95 0.04 150)` / `oklch(0.42 0.10 150)` | `oklch(0.30 0.06 150)` / `oklch(0.86 0.10 150)` |
| `--warning` / `--warning-foreground` | `oklch(0.96 0.06 90)` / `oklch(0.45 0.10 75)` | `oklch(0.32 0.06 85)` / `oklch(0.88 0.10 90)` |
| `--info` / `--info-foreground` | `oklch(0.95 0.03 240)` / `oklch(0.45 0.12 250)` | `oklch(0.30 0.06 250)` / `oklch(0.86 0.07 240)` |
| `--meal-breakfast` / `-foreground` | `oklch(0.95 0.06 85)` / `oklch(0.45 0.10 70)` | `oklch(0.32 0.06 75)` / `oklch(0.88 0.10 80)` |
| `--meal-supper` / `-foreground` | `oklch(0.94 0.045 40)` / `oklch(0.47 0.14 35)` | `oklch(0.32 0.07 35)` / `oklch(0.86 0.06 40)` |
| `--meal-dinner` / `-foreground` | `oklch(0.94 0.035 280)` / `oklch(0.45 0.12 280)` | `oklch(0.32 0.06 280)` / `oklch(0.86 0.06 280)` |
| `--radius` | `0.625rem` | (inherits) |

## Critical Implementation Details

- **Clean baseline.** Before Phase 1, commit the uncommitted M-2 roadmap rewrite (`context/foundation/roadmap.md`) and the untracked change folder as a separate `docs:` commit, so no phase commit sweeps them in. Commit Phase 1 before running the CLI in Phase 2, because the Phase 2 checks diff against `HEAD`.
- **CLI overwrite and ordering.** Run the CLI non-interactively with `--overwrite`. This is only so it doesn't block on the `button` prompt; `button` is the only existing file it can touch. Then immediately restore `ui/button` from git and prove it is unchanged. This is a deliberate, reviewed overwrite, not a blind one, and it keeps unattended runs from hanging on a TTY prompt. Phase 2 starts from the Phase 1 commit, so `git diff HEAD` cleanly isolates what the CLI did.
- **`@theme inline` vs `@theme`.** Full-value tokens must sit in `@theme inline { --color-x: var(--x) }`, as in the shadcn-vue Tailwind 4 theming docs. Plain `@theme` would bake the `:root` value into utilities, and `.dark` overrides would stop working. Opacity modifiers such as `bg-primary/90` keep working because Tailwind 4 uses `color-mix` with OKLCH values.

## Phase 1: OKLCH tokens in app.css

### Overview

Swap the colour system in `resources/css/app.css` to the OKLCH palette above, add the state and domain tokens, raise the radius, and remove the dead compatibility layer and unused chart tokens.

### Changes Required:

#### 1. Token definitions and Tailwind mapping

**File**: `resources/css/app.css`

**Intent**: Replace the HSL channel variables with full `oklch()` values for `:root` and `.dark`, taken from the Token values table. Add the 12 new state and domain tokens. Move the colour mapping from `@theme { --color-x: hsl(var(--x)) }` to `@theme inline { --color-x: var(--x) }`, so `.dark` can override at runtime and every token gets its Tailwind utility (`bg-warning`, `text-meal-dinner-foreground`, `border-meal-supper`…).

**Contract**:
- Token names in `:root` and `.dark`: the existing shadcn names (unchanged, so `ui/` needs no edits) plus `--success`, `--success-foreground`, `--warning`, `--warning-foreground`, `--info`, `--info-foreground`, `--meal-breakfast`, `--meal-breakfast-foreground`, `--meal-supper`, `--meal-supper-foreground`, `--meal-dinner` and `--meal-dinner-foreground`. Each one has a `--color-<name>: var(--<name>)` entry in `@theme inline`.
- `--radius: 0.625rem`. `--radius-lg`, `--radius-md` and `--radius-sm` keep their current formulas (`var(--radius)`, `− 2px`, `− 4px`).
- `--font-sans`, the `container` utility, `@custom-variant dark`, the `@source` globs, the `@plugin`/`@import` lines and the accordion keyframes stay as they are (the keyframes can move into the `@theme` block that holds the non-colour values).
- Remove `--chart-1…5` (light, dark and theme mapping) and the `border-color: var(--color-gray-200, currentcolor)` base layer. Keep the `* { @apply border-border }` and `body { @apply bg-background text-foreground }` base rules.

### Success Criteria:

#### Automated Verification:

- No HSL, gray-200 or chart tokens remain: `! grep -nE "hsl\(|gray-200|chart-" resources/css/app.css`
- All 12 new state/domain tokens are mapped: `grep -cE "^\s*--color-(success|warning|info|meal-(breakfast|supper|dinner))(-foreground)?:" resources/css/app.css` prints `12`
- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Pest suite passes: `vendor/bin/sail composer test`
- E2E suite passes against Sail (build, `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder`, then `E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`)

#### Manual Verification:

- Dashboard, recipe list, meal-plan detail and login render with a cream background, green primary buttons and a green focus ring (Tab onto a button)
- Cards, tables, inputs and dialogs still have visible warm-grey borders; no element lost its border after removing the gray-200 layer
- DevTools spot check: computed `--primary` on `:root` is `oklch(0.5 0.12 150)` and a primary button's text/background contrast reads ≈ 5.5:1

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Primitives and variants

### Overview

Add the six §5.1 primitives as new-york-v4 through the CLI and normalise their imports to the repo's `cn` and icon library, so no new packages land. Then add the state and category variants to `badge` and `alert`.

### Changes Required:

#### 1. Add the six primitives through the CLI

**File**: `resources/js/Components/ui/{alert-dialog,alert,checkbox,skeleton,collapsible,pagination}/` (new), `package.json`, `package-lock.json`

**Intent**: Bring in the missing primitives with the official CLI (design-system §3 rule 4), in the newest registry style (D-9), without changing the existing `button` or adding duplicate utilities.

**Contract**:
- Run `vendor/bin/sail npx shadcn-vue@2 add alert-dialog alert checkbox skeleton collapsible pagination --yes --overwrite`, then `git checkout -- resources/js/Components/ui/button`.
- Rewrite each new file's `import { cn } from "cn"` to `@/lib/utils`, and its `@lucide/vue` imports to `lucide-vue-next` (same icon names).
- Run `vendor/bin/sail npm uninstall cn @lucide/vue`.
- Every `buttonVariants({ variant, size })` call in the new files must use a variant and size that exists in the legacy `ui/button/index.js`. If one doesn't, map it to the nearest existing one rather than editing `button`.
- Dependency bumps the CLI makes to existing packages (expected: `reka-ui` to the latest 2.x) are accepted under D-5 after reviewing the `package.json` diff. A major-version bump of anything is not accepted: revert it and pin the previous range.
- `app.css` and `components.json` must come out of this step unchanged. If the CLI touched them, revert those hunks.

#### 2. Badge variants

**File**: `resources/js/Components/ui/badge/index.js`

**Intent**: Give badges the category and state colours as `cva` variants (design-system §3 rule 3), so S-07 can turn `CategoryBadge` and `StatusTag` into `<Badge :variant>` with no literals. The base also changes to `rounded-full` (§4.6).

**Contract**: `badgeVariants` gains `breakfast`, `supper` and `dinner` (`border-transparent bg-meal-<x> text-meal-<x>-foreground`), plus `success`, `warning` and `info` (`border-transparent bg-<x> text-<x>-foreground`). Existing variants and `defaultVariants` stay. Variant keys match `App\Enums\MealCategory` values, so a category string can be passed straight in.

#### 3. Alert variants

**File**: `resources/js/Components/ui/alert/index.js` (created in step 1)

**Intent**: Give alerts the state pairs for the AI-fallback and generation messages (§5.1).

**Contract**: `alertVariants` gains `warning`, `info` and `success`. Each uses the `<x>` background with `<x>-foreground` text, and colours the description slot and icon from the same pair. Only token classes, and the v4 `default` and `destructive` stay as generated.

### Success Criteria:

#### Automated Verification:

- The six primitive folders exist: `ls resources/js/Components/ui/{alert-dialog,alert,checkbox,skeleton,collapsible,pagination}/index.js`
- No new-package imports in ui/: `! grep -rnE "from ['\"](cn|@lucide/vue)['\"]" resources/js/Components/ui`
- `cn` and `@lucide/vue` are not dependencies: `! grep -nE '"(cn|@lucide/vue)"' package.json`
- Only the six primitives, badge and package files changed: `git diff --quiet HEAD -- resources/js/Components/ui/button resources/css/app.css components.json`, and `git status --porcelain | grep -vE "resources/js/Components/ui/(alert-dialog|alert|checkbox|skeleton|collapsible|pagination|badge)/|package(-lock)?\.json$| context/"` prints nothing
- Badge and alert expose the new variants: `grep -cE "^\s*(breakfast|supper|dinner|success|warning|info):" resources/js/Components/ui/badge/index.js` prints `6`, `grep -cE "^\s*(success|warning|info):" resources/js/Components/ui/alert/index.js` prints `3`, and `grep -n "rounded-full" resources/js/Components/ui/badge/index.js` matches
- Client and SSR bundles build: `vendor/bin/sail npm run build`
- E2E suite passes against Sail (re-run the seeder first; this covers a possible `reka-ui` bump in existing dialogs, selects and comboboxes)

#### Manual Verification:

- `package.json` / `package-lock.json` diff reviewed and approved (D-5): only expected minor bumps, no new top-level packages
- Spot-read one generated file (e.g. `alert-dialog/AlertDialogContent.vue`) and confirm it uses `@/lib/utils`, `lucide-vue-next` and token classes only
- Existing dialogs (recipe delete, meal-plan generate) and the recipe category select still open, focus and close correctly in the browser

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Docs and rule

### Overview

Record the contract where agents and humans will read it: the rule in `CLAUDE.md`, and the actual F-03 outcome in `design-system.md` and the roadmap.

### Changes Required:

#### 1. Design-system rule

**File**: `CLAUDE.md`

**Intent**: Make every future agent use tokens and existing primitives instead of literals (design-system §7.1), without pointing at the kitchen sink or `lint:tokens`, which don't exist until F-04.

**Contract**: Add three bullets to "Structure & Conventions":
1. Tokens live in `resources/css/app.css` and are described in `context/foundation/design-system.md`.
2. Before writing a new component, check `Components/ui/` and `Components/Common/` (`Common/` is created in S-07). A missing primitive is added with the CLI. Then rewrite its imports to `@/lib/utils` and `lucide-vue-next`, run `npm uninstall cn @lucide/vue`, and restore every existing `ui/` file the CLI overwrote (D-9).
3. In new files under `Pages/` and `Components/<Feature>/`, and in a view being migrated in its own slice: no Tailwind palette classes (`gray-*`, `red-*`…), hex colours, arbitrary values or `dark:`. Use token utilities and `Badge`/`Alert` variants. In views not yet migrated, add no new literals and do not restyle the existing ones outside their slice.

#### 2. Design-system document

**File**: `context/foundation/design-system.md`

**Intent**: Keep the spec in step with what F-03 delivered.

**Contract**:
- §8: split the DS-01 row into F-03 (`design-tokens-foundation`: tokens, primitives, variants, the 3-bullet rule) and F-04 (`design-system-visual-gate`: kitchen sink, screenshot gate, `lint:tokens`, the kitchen-sink bullet in `CLAUDE.md`).
- §4.8: add the `.dark` values table.
- §5.1: note that new primitives come from new-york-v4 with normalised imports.
- §9: add D-9 ("New primitives in new-york-v4 with imports normalised to `@/lib/utils` and `lucide-vue-next`; regenerating the 15 legacy components and moving to `@lucide/vue` is a separate change after F-04", confirmed 2026-11-15).
- Frontmatter: `updated: <today>`.

#### 3. Roadmap F-04 entry

**File**: `context/foundation/roadmap.md`

**Intent**: Make sure F-04 completes the rule this change deliberately left short.

**Contract**: In the F-04 **Outcome** line, add that F-04 also adds the kitchen-sink and `lint:tokens` bullet to the design-system rule in `CLAUDE.md`. Touch nothing else (the `Status` fields are owned by the 10x skills).

### Success Criteria:

#### Automated Verification:

- CLAUDE.md references the design system and the CLI normalisation: `grep -n "context/foundation/design-system.md" CLAUDE.md` and `grep -n "npm uninstall cn @lucide/vue" CLAUDE.md` both match
- Decision D-9 recorded: `grep -n "^| D-9" context/foundation/design-system.md`
- The F-03/F-04 split appears in §8: `grep -nE "design-system-visual-gate" context/foundation/design-system.md`
- The F-04 roadmap entry completes the rule: `grep -n "lint:tokens" context/foundation/roadmap.md` matches the F-04 **Outcome** line

#### Manual Verification:

- The three CLAUDE.md bullets read as rules an agent can follow without the kitchen sink, and don't duplicate existing bullets

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human.

---

## Testing Strategy

### Unit Tests:

- None. There is no JS unit runner, and the change has no PHP logic. Adding tests is out of proportion (Test Enforcement: styling and layout-only changes need no new tests).

### Integration Tests:

- The existing Playwright E2E suite (login, password reset, recipe form, meal plan) after each of Phases 1–2. It uses role and text selectors, so a pass shows the repaint and the `reka-ui` bump didn't change semantics.
- The Pest suite after Phase 1, as a sanity check on SSR and view rendering.

### Manual Testing Steps:

1. Open `/dashboard`, `/recipes`, one meal plan and `/login`. Confirm the green primary, cream background, warm borders and green focus ring.
2. Tab through a form and confirm the focus ring is visible on inputs and buttons.
3. Open and close the recipe delete dialog and the generate-meal-plan dialog, and use the category select.
4. Review the `package.json` diff from Phase 2.

## Performance Considerations

None. CSS variable count grows by 12 and the six primitives are tree-shaken until a view imports them.

## Migration Notes

There is no data migration. To roll back, revert the phase commits: tokens and primitives are independent of the database, and the views don't reference the new tokens yet.

## References

- Source spec: `context/foundation/design-system.md` (§4.1–4.8, §5.1, §7.1, §8, D-1, D-2, D-5, D-8)
- Roadmap item: `context/foundation/roadmap.md` → F-03
- Prior Tailwind 4 decisions: `context/archive/2026-11-13-tailwind-4-upgrade/plan.md`
- shadcn-vue theming (Tailwind 4): https://www.shadcn-vue.com/docs/theming
- shadcn-vue CLI source (style resolution, cssVars updater): https://github.com/unovue/shadcn-vue/tree/dev/packages/cli/src

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: OKLCH tokens in app.css

#### Automated

- [x] 1.1 No HSL, gray-200 or chart tokens remain — 241b3bc
- [x] 1.2 All 12 new state/domain tokens are mapped — 241b3bc
- [x] 1.3 Client and SSR bundles build — 241b3bc
- [x] 1.4 Pest suite passes — 241b3bc
- [x] 1.5 E2E suite passes against Sail — 241b3bc

#### Manual

- [ ] 1.6 Key views render cream background, green primary and green focus ring
- [ ] 1.7 No element lost its border after removing the gray-200 layer
- [ ] 1.8 DevTools spot check of primary token and contrast

### Phase 2: Primitives and variants

#### Automated

- [x] 2.1 The six primitive folders exist — cee8145
- [x] 2.2 No new-package imports in ui/ — cee8145
- [x] 2.3 cn and @lucide/vue are not dependencies — cee8145
- [x] 2.4 Only the six primitives, badge and package files changed — cee8145
- [x] 2.5 Badge and alert expose the new variants — cee8145
- [x] 2.6 Client and SSR bundles build — cee8145
- [x] 2.7 E2E suite passes against Sail — cee8145

#### Manual

- [ ] 2.8 package.json diff reviewed and approved (D-5)
- [ ] 2.9 Generated file spot-read uses repo cn, lucide-vue-next and tokens
- [ ] 2.10 Existing dialogs and selects still work in the browser

### Phase 3: Docs and rule

#### Automated

- [x] 3.1 CLAUDE.md references the design system and the CLI normalisation — f09e5aa
- [x] 3.2 Decision D-9 recorded — f09e5aa
- [x] 3.3 The F-03/F-04 split appears in §8 — f09e5aa
- [x] 3.4 The F-04 roadmap entry completes the rule — f09e5aa

#### Manual

- [ ] 3.5 CLAUDE.md bullets are followable and non-duplicated
