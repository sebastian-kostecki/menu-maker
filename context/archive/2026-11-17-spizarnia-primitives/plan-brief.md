# Spiżarnia Primitives — Plan Brief

> Full plan: `context/changes/spizarnia-primitives/plan.md`

## What & Why

Roadmap F-06 (M-4, SP-02) gives the `ui/` primitives the Spiżarnia shape: pills with a 2px ink outline, a hard "sticker" shadow, 20px tiles, 48px fields and 24px dialogs. Primitives are restyled **in place**, so the four parallel view slices (S-23…S-26) only re-lay out their screens and don't fight over shared `ui/` files.

## Starting Point

F-05 shipped the Spiżarnia tokens, radii, shadows and font. The primitives still have shadcn shapes on top of them:
- 36px `rounded-md` buttons and 1px borders;
- a 24px card with `shadow-sm`;
- `bg-black/80` overlays and an English "Close" on the dialog X.

Views never override shape or colour on these primitives, and only the visual gate (52 PNGs) and `lint:tokens` check how they look.

## Desired End State

Every screen shows Spiżarnia buttons, badges, alerts, tiles, fields and dialogs without a view edit. Slices get three shared patterns ready to use:
- the filter chip (`ui/toggle`, with meal tones);
- the `dashed` "Dodaj…" button;
- pagination pills.

The kitchen sink demos all of it with its states. Four new screenshots pin hover, pressed and focus-visible, and all baselines are refreshed and reviewed.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Restyle strategy | In place: existing variant names get the Spiżarnia look | Names already map 1:1 onto the mock, and views never override shapes. | Plan (user) |
| Extra patterns | Chip, pagination pills and dashed button in; pill search (S-24) and on-highlight buttons (S-23) out | Build only what two or more parallel slices need. | Plan (user) |
| Interaction states | Press-in sticker: hover `accent` or `/90`, active shifts 3px and drops the shadow (sticker controls only), 2px `outline-ring` focus, disabled `opacity-50` | Matches the fridge-sticker metaphor and the mock's 2px-ring rule. | Plan (user) |
| Visual gate | 4 new state shots plus all baselines refreshed once (52 → 56) | The states are designed here, so they need a regression guard. | Plan (user) |
| Chip implementation | `ui/toggle` via shadcn CLI (reka-ui `Toggle`), restyled, tones `default`/`breakfast`/`supper`/`dinner` | It's a missing primitive (CLAUDE.md: add via CLI), and it gives `aria-pressed` semantics for free. | Plan |
| Status pill | Badge `size="status"` plus a `draft` variant (dashed); `StatusTag` opts in | Category badges stay borderless while statuses get the outlined pill. | Plan |
| Focus indicator | `outline`, not `ring` | Pressing and disabling remove the box-shadow and must not also remove focus. | Plan |
| Dialog title | New `text-dialog-title` role (24/28, 800) | No existing role matches, and §10.3 requires new roles in `app.css` and `cn()`. | Plan |
| Close button | Built in, round `secondary` icon, labelled "Zamknij"; E2E locator updated | `RecipeSwapDialog` can only be closed with the X. | Plan |
| Popover surfaces | Select, combobox and menu get the 2px outline, 12px radius and sticker shadow | Small addition beyond the SP-02 list, so S-22 and S-25 don't restyle them separately. | Plan |

## Scope

**In scope:**
- Button (variants, sizes, `dashed`, state contract), with `AlertDialogAction`/`Cancel` and pagination following through `buttonVariants`
- New `ui/toggle` filter chip
- Badge status pill and `StatusTag`; Alert; Card as tile
- Input, Textarea, SelectTrigger, ComboboxInput and Checkbox
- Select, combobox and dropdown surfaces
- Dialog, AlertDialog and Sheet (`bg-overlay`, shell, close button)
- `text-dialog-title`
- Kitchen sink, the E2E locator fix, visual state shots and baselines
- `design-system.md` and roadmap carry-overs

**Out of scope:**
- View re-layouts and re-pointing view variants (S-22…S-26)
- Pill search and on-highlight buttons
- An interactive `Card`
- Table, skeleton, avatar and the sheet panel layout
- A Button `loading` prop and press motion on flat controls
- Dark mode, D-9 regeneration and a JS unit runner

## Architecture / Approach

All styling policy lives in the cva variants and base classes inside `Components/ui/`, which `lint:tokens` deliberately skips. Views keep their props and get the new look for free. Phases 1–3 restyle one family each, with the kitchen sink section that demos it, and are verified by build, `lint:tokens`, Pest and targeted E2E while the visual gate is knowingly red. Phase 4 adds the state shots, regenerates every baseline once, and syncs the docs.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Buttons, chips and pagination | Spiżarnia pills, `dashed`, press-in states, `ui/toggle` chip, page pills | The shadcn CLI overwrites existing `ui/` files or adds `@lucide/vue`/`cn` (D-9). |
| 2. Badges, alerts, cards and fields | Status pill with dashed draft, 2px alerts, tile card, 48px fields, checkbox, popover surfaces | tailwind-merge keeps `border-transparent` over the status border unless it comes from `compoundVariants`. |
| 3. Dialogs and overlays | 24px dialogs with `shadow-dialog`, `bg-overlay`, round "Zamknij", new title role | A missed `'Close'` locator, or the swap dialog losing its only close control. |
| 4. Visual gate and docs | 4 state shots, 56 reviewed baselines, full suite green, docs and roadmap synced | Taller controls (44px/48px) clip text or break mobile page shots. |

**Prerequisites:**
- Sail up, with the `queue` service and Mailpit, for E2E.
- `.env` with `AI_SCALING_DRIVER=fake`.
- F-05 merged into `develop`. The worktree is branched from `develop`, not `main`, because `main` lacks F-05.

**Estimated effort:** ~1–2 sessions across 4 phases. Reviewing 56 PNGs is the largest single task.

## Open Risks & Assumptions

- **Mixed look until each slice lands.** Between F-06 and S-22…S-26, Spiżarnia primitives sit inside the Zielnik layouts: taller buttons, a tile card on the guest screen with `shadow-none`, and table actions as transparent round icons. This is accepted.
- **Toggle CLI output.** Assumed to add no packages, since reka-ui is already installed. If it does, the plan's dependency check (1.4) stops the phase for review (D-5).
- **States are this change's design.** The mock defines none, so S-22…S-26 inherit them through §10.4 and must not re-invent them.

## Success Criteria (Summary)

- The app and the kitchen sink show Spiżarnia primitives everywhere. Slices find the chip, dashed button and pagination pills ready in `ui/`.
- Build, `lint:tokens`, Pest, full E2E and the visual gate (56 PNGs, including hover, pressed and focus-visible shots) pass.
- `design-system.md` §5.1 and §10 and the roadmap carry-overs tell each slice which primitives and variants to adopt.
