# Auth & Profile UI — Plan Brief

> Full plan: `context/changes/auth-profile-ui/plan.md`

## What & Why

S-13 / DS-08 of milestone M-2. The login, registration, password-reset and profile screens are the last ones still built from Breeze components, so they look different from the rest of the app and block S-15 from deleting Breeze. This change moves them onto the same `ui/` primitives and tokens as every other view, and builds the `Common/FormField` that S-07 deferred to this slice.

## Starting Point

- Eight files (5 `Pages/Auth/*` and 3 `Pages/Profile/Partials/*`) import Breeze `TextInput`, `InputLabel`, `InputError`, `PrimaryButton`, `Checkbox`, `DangerButton`, `SecondaryButton` and `Modal`, styled with `gray-*`/`dark:` literals.
- `GuestLayout` and `AuthenticatedLayout` were already restyled in S-08.
- `ConfirmDialog` exists but has no slot for extra content.
- `/profile` and `/confirm-password` have no E2E or visual coverage.

## Desired End State

- Account screens use the brand look: green primary buttons, warm inputs with a red border and a linked error when invalid, a shadcn checkbox for „Zapamiętaj mnie”, and success messages in an `Alert`.
- `/profile` has a page title and three cards.
- Deleting the account uses the same confirmation dialog as the rest of the app, with the password field inside.
- Every label, button name and Polish message is unchanged, so all existing E2E specs pass untouched.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Where FormField is built | In S-13, with its first callers | S-07 deferred it here so it isn't code without a call site | Roadmap / design-system §5.2 |
| Delete-account dialog | Optional default slot in `Common/ConfirmDialog`, password `FormField` inside | Keeps one destructive-confirm pattern with the S-07 focus and processing guards; existing callers are unaffected | Plan |
| FormField API | Scoped-slot wrapper (`id`, `label`, `error`, `description?`; slot gives `id`, `invalid`, `describedBy`) + `aria-invalid` style on `ui/input` | Works for any control, and the page keeps `v-model`, `ref` and attributes on the real `Input` | Plan |
| Profile page title | Tokenised `<h1>` in the `#header` slot, like `Dashboard.vue` | `PageHeader` belongs to S-09/S-10/S-11 running in parallel, so building it here would collide | Plan |
| Test coverage | New `profile.spec.ts` E2E + visual snapshots of `/register` and `/profile` (+ kitchen-sink dialog overlay) | The only behaviour change (Modal → ConfirmDialog) and the never-tested screens get guarded | Plan |
| Breeze file deletion | Not here | S-15 deletes them once no view imports them (which this change achieves) | Roadmap |
| Dialog focus | Stays on „Anuluj”, even with the field | Unchanged ConfirmDialog contract for destructive actions | Design system §5.2 |

## Scope

**In scope:**
- `Common/FormField`, the invalid state on `ui/input` and the `ConfirmDialog` slot, with kitchen-sink demos and baselines
- Login, Register, ForgotPassword, ResetPassword, ConfirmPassword, `Profile/Edit` and its 3 partials on primitives and tokens
- `Pages/Auth` and `Pages/Profile` added to `lint-tokens.paths`
- `profile.spec.ts` and `account.spec.ts` (visual: register, profile), plus the updated `guest-login.png`
- Updates to design-system §5.1, §5.2 and §7

**Out of scope:**
- Deleting Breeze components, the `Welcome.vue`/`/` redirect (S-15)
- `Common/PageHeader` (S-09/S-10/S-11)
- Any change to labels, messages, routes, validation or sessions
- A show-password toggle
- Restyling `GuestLayout`

## Architecture / Approach

The work runs contracts first, then consumers. `FormField` renders the `Label` and the error `<p>`, and hands `{id, invalid, describedBy}` to a slot where the page places `<Input>` or another control. `ConfirmDialog` renders an optional slot between its description and its footer. The guest pages then swap Breeze for `FormField` + `Input`/`Checkbox`/`Button`/`Alert` inside the existing `GuestLayout` card. The profile partials become `Card`s, and `DeleteUserForm` opens `ConfirmDialog` with the password field in the slot. Each phase appends its views to the token scan and updates only its own visual baselines.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Shared form contracts | `FormField`, input invalid style, `ConfirmDialog` slot, kitchen sink + baselines | Slot change silently altering existing confirm dialogs (guarded by `confirm-dialog.spec.ts` and the overlay baselines) |
| 2. Auth screens | 5 guest screens on primitives, `guest-login`/`guest-register` baselines | Login break fails every E2E spec; a new label containing „hasło” breaks the loose `getByLabel('Hasło')` |
| 3. Profile screen | Profile + 3 partials, delete via `ConfirmDialog`, `profile.spec.ts`, `profile-desktop` baseline | Focus refs (`$el.focus()`), duplicate `id="password"`, dialog close/reset behaviour |

**Prerequisites:**
- The branch now sits on `develop` (`72ab6b0`), which has F-03, F-04, S-07 and S-08.
- The worktree needs its own Sail stack and `.env` (plan § Environment).

**Estimated effort:** ~1–2 sessions across 3 phases.

## Open Risks & Assumptions

- The visual gate has zero tolerance, so any unplanned PNG change in a phase counts as a regression and must be explained before baselines are accepted.
- With focus on „Anuluj”, the user tabs into the password field instead of typing right away. This is accepted as the ConfirmDialog contract.
- `recipes-ui` and `family-members-ui` worktrees are still on the old `main` base. The optional slot is backward-compatible, but they'll meet it when they rebase.

## Success Criteria (Summary)

- A user signs up, logs in (with „Zapamiętaj mnie”), resets the password and edits or deletes the profile on screens that look like the rest of the app, with no Breeze component involved.
- All existing E2E specs pass unchanged, the new `profile.spec.ts` passes, and the token scan and visual gate are green.
