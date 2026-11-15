# Auth & Profile UI Implementation Plan

## Overview

S-13 in roadmap milestone M-2 (DS-08 in `context/foundation/design-system.md` §8). The five account screens (`Auth/Login`, `Register`, `ForgotPassword`, `ResetPassword`, `ConfirmPassword`) and the profile page (`Profile/Edit` with three partials) are the last views that still use the Breeze components. They move to the `ui/` primitives (`Input`, `Label`, `Checkbox`, `Button`, `Alert`, `Card`) and to two shared contracts: a new `Common/FormField` (label + control + error, deferred to this slice by S-07) and an optional body slot in `Common/ConfirmDialog`, which the delete-account confirmation needs for its password field.

After this change no file imports a Breeze component; deleting the 13 Breeze files stays in S-15 (`legacy-ui-cleanup`).

## Current State Analysis

- **Breeze usage.** Exactly 8 files import Breeze components, all in scope:
  - `Pages/Auth/{ConfirmPassword,ForgotPassword,Register,ResetPassword}.vue` use `InputLabel`, `TextInput`, `InputError` and `PrimaryButton`.
  - `Pages/Auth/Login.vue:2-7` uses the same four plus `Checkbox`.
  - `Pages/Profile/Partials/UpdateProfileInformationForm.vue:2-5` and `UpdatePasswordForm.vue:2-5` use the same four.
  - `Pages/Profile/Partials/DeleteUserForm.vue:2-7` uses `DangerButton`, `SecondaryButton`, `Modal`, `TextInput`, `InputLabel` and `InputError`.
  - Nothing else in `resources/js` imports any of the 13 Breeze files.
- **Literals.** Every in-scope view uses `text-gray-*` with a `dark:` twin, and the links use `indigo` focus rings (`Login.vue:85`, `Register.vue:98`). The status flashes are `text-green-600` (`Login.vue:36`, `ForgotPassword.vue:35`). `Profile/Edit.vue:14-38` has a Breeze `<h2>` header and `bg-white … dark:bg-gray-800` cards. `Pages/Auth` and `Pages/Profile` are not yet in `scripts/lint-tokens.paths`.
- **Guest shell is done.** `Layouts/GuestLayout.vue` (S-08) is already on tokens and wraps the slot in a `ui/card` (`max-w-md`), and it is already in the lint list. The authenticated shell keeps a `#header` slot (`Layouts/AuthenticatedLayout.vue:71-74`). `Dashboard.vue:13-17` is the only migrated page, and it puts a tokenised `<h1>` in that slot.
- **Primitives.**
  - `ui/input/Input.vue` is a plain `<input>`, so attributes such as `id`, `type`, `autocomplete`, `required` and `autofocus` fall through. It has no `aria-invalid` styling and does not expose `focus()` (Breeze `TextInput` did; `UpdatePasswordForm.vue:21-29` and `DeleteUserForm.vue:21,28` call it).
  - `ui/checkbox` is a reka `CheckboxRoot` (a `button[role=checkbox]` with a boolean `v-model`).
  - `ui/alert` has a `success` variant and `role="alert"`.
- **ConfirmDialog has no slot.** `Common/ConfirmDialog.vue:56-82` renders only the title and description strings, then the footer. It holds the S-07 guarantees: focus starts on „Anuluj”, Esc and close are blocked while `processing`, and the confirm button uses `aria-disabled`.
- **Validation messages are server-side.** They come from `lang/pl/validation.php` (e.g. `current_password` → „Podane hasło jest nieprawidłowe.”), and all three profile endpoints validate into the default error bag (`ProfileController.php:45`, `Auth/PasswordController.php:18`). The views only have to keep rendering `form.errors.*`.
- **Duplicate id.** `DeleteUserForm.vue:79` and `UpdatePasswordForm.vue:71` both render `id="password"` on `/profile`.
- **Tests.**
  - **E2E** (`tests/e2e/login.spec.ts`, `password-reset.spec.ts`) selects only by label text and button name. Every other spec logs in through `/login` with `getByLabel('Adres e-mail')`, `getByLabel('Hasło')` (a non-exact match) and `getByRole('button', { name: 'Zaloguj się' })`. No spec covers `/profile` or `/confirm-password`.
  - **Visual.** The only auth snapshot is `tests/e2e/__screenshots__/visual/app-shell.spec.ts/guest-login.png`, and S-08 recorded that S-13 will update it deliberately.
  - **Pest.** The feature tests (`tests/Feature/Auth/*`, `tests/Feature/ProfileTest.php`) assert HTTP behaviour and Polish messages, not Inertia components.

## Desired End State

- All 9 views render only `ui/*`, `Common/*` and token utilities. `npm run lint:tokens` scans `resources/js/Pages/Auth` and `resources/js/Pages/Profile` and passes.
- Every form field is a `Common/FormField`. The label is linked to the control by `for`/`id`, the server error is linked by `aria-describedby`, and an invalid field has `aria-invalid="true"` and a destructive border.
- Login has a `ui/checkbox` „Zapamiętaj mnie”. The status messages on Login and ForgotPassword are `Alert variant="success"`.
- `/profile` has a page `<h1>Profil</h1>` in the header slot and three `Card` sections inside `max-w-2xl`. „Usuń konto” opens the shared `ConfirmDialog`, with a password `FormField` in its new slot.
- The kitchen sink shows `FormField` in its states and `ConfirmDialog` with a field. The baselines are updated deliberately.
- New `tests/e2e/profile.spec.ts` covers updating profile data, changing the password, confirming the password and deleting the account. New visual snapshots cover `/register` and `/profile`.

Verify with: `vendor/bin/sail npm run build`, `npm run lint:tokens`, `vendor/bin/sail composer test`, the full E2E suite, and `npm run test:visual`.

### Key Discoveries:

- E2E label contract (from S-01, `context/archive/2026-11-14-polish-auth-with-24h-session/plan.md:252-266`). These accessible names must stay exact:
  - Register: `Imię`, `Adres e-mail`, `Hasło`, `Potwierdź hasło`; button „Zarejestruj się”.
  - Login: `Adres e-mail`, `Hasło`; button „Zaloguj się”.
  - Forgot: `Adres e-mail`; button „Wyślij link do resetu hasła”.
  - Reset: `Adres e-mail`, `Nowe hasło`, `Potwierdź hasło`; button „Zresetuj hasło”.
  - Asserted texts: „Podane dane logowania są nieprawidłowe.”, „Wysłaliśmy e-mailem link do resetu hasła.” and „Twoje hasło zostało zresetowane.”.
- The existing hand-written invalid-field demo in the kitchen sink (`Pages/Dev/DesignSystem.vue:365-374`, label + `Input aria-invalid aria-describedby` + `<p class="text-sm text-destructive">`) is exactly the markup `FormField` takes over.
- `lint-tokens.paths` takes one repo-relative file or directory per line, and each path must exist (`scripts/lint-tokens.sh:30-54`). `resources/js/Components/Common` is already listed, so `FormField.vue` is scanned automatically.
- Adding content to the kitchen sink shifts corner pixels of `overlay-dialog.png` and `overlay-alert-dialog.png`. This was accepted in S-07 and S-08 (`context/archive/2026-11-15-app-shell-restyle/plan.md:476`).
- Playwright ignores the per-project `updateSnapshots`, so baselines are created and updated only with `npm run test:visual -- --update-snapshots` (`app-shell-restyle/plan.md:478`).

## What We're NOT Doing

- Deleting the Breeze components (`Checkbox`, `DangerButton`, `InputError`, `InputLabel`, `Modal`, `PrimaryButton`, `SecondaryButton`, `TextInput`, `Dropdown`, `DropdownLink`, `NavLink`, `ResponsiveNavLink`, `ApplicationLogo`). That is S-15.
- Building `Common/PageHeader`. It belongs to S-09/S-10/S-11. Profile uses the Dashboard header pattern and switches to `PageHeader` in a later one-line follow-up.
- Changing labels, button names, Polish messages, routes, controllers, validation or session behaviour.
- A show-password toggle, or any other control on `/login` whose label contains „hasło”.
- Restyling `GuestLayout` (done in S-08), or touching `Welcome.vue` and the `/` redirect (S-15, D-7).
- Changing `ConfirmDialog`'s focus contract. Focus still starts on „Anuluj”, also when the slot holds a field.
- Visual snapshots for ForgotPassword, ResetPassword or ConfirmPassword. They share the Register/Login composition and stay covered by E2E.

## Implementation Approach

Contracts first, then consumers. Phase 1 adds `FormField`, the `aria-invalid` style on `ui/input` and the `ConfirmDialog` slot, and proves them in the kitchen sink and its visual gate before any page depends on them. Phase 2 migrates the five guest screens; the login E2E path guards it, and every spec depends on that path. Phase 3 migrates the profile page, the only one with a behaviour change (Breeze `Modal` → `ConfirmDialog`), and adds the missing E2E and visual coverage for it. Each phase appends its own views to `lint-tokens.paths` and updates only its own baselines.

## Critical Implementation Details

- **User experience spec (login label collision).** `getByLabel('Hasło')` on `/login` is a non-exact substring match used by every E2E spec. The checkbox label must stay „Zapamiętaj mnie”, and no new label, `aria-label` or `placeholder`-as-label containing „hasło” may appear on that page. On `/profile`, tests must scope selectors to a card or to the `alertdialog`, because „Hasło”, „Obecne hasło”, „Nowe hasło” and „Potwierdź hasło” all match loosely.
- **User experience spec (delete-account dialog).**
  - Focus starts on „Anuluj” (ConfirmDialog contract).
  - Enter in the password field submits (`@keydown.enter.prevent`), matching the old `@keyup.enter`. Enter bypasses `ConfirmDialog`'s own processing guard, so `deleteUser` returns early while `form.processing` (no second DELETE after the first one has logged the user out).
  - A wrong password keeps the dialog open, shows the error under the field and refocuses the field.
  - Closing with „Anuluj” or Esc resets the form and clears its errors.
  - On success the server logs the user out and redirects to `/`.
  - The field needs its own id (e.g. `delete-account-password`), because `UpdatePasswordForm` already owns `id="password"` on the page.
- **Timing & lifecycle (focus refs).** `ui/input` exposes no `focus()`, so the refs in `UpdatePasswordForm` and `DeleteUserForm` must focus the underlying element (`inputRef.value.$el.focus()`). Do not add `defineExpose` to the primitive.

## Environment (prerequisite for every phase)

The planning session moved branch `worktree-auth-profile-ui` from stale `main` (`c547133`) onto `develop` (`72ab6b0`), where F-03, F-04, S-07 and S-08 live. If implementation runs in `.claude/worktrees/auth-profile-ui` (no `vendor/`, `node_modules/` or `.env`), it needs its own Sail stack, the same as S-08 (`context/archive/2026-11-15-app-shell-restyle/plan.md:110-118`):

- Copy `.env` from the main checkout and give it its own `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT` and `FORWARD_MAILPIT_DASHBOARD_PORT`. Set `MAILPIT_URL` to match, and keep `AI_SCALING_DRIVER=fake`, `MAIL_MAILER=smtp` and `MAIL_HOST=mailpit`.
- Install `vendor/` with the Sail Composer container (not host PHP), then run `vendor/bin/sail up -d`, `vendor/bin/sail npm ci` and `vendor/bin/sail artisan migrate`.
- The visual gate runs as `VISUAL_DOCKER_NETWORK=auth-profile-ui_sail VISUAL_BASE_URL=http://laravel.test npm run test:visual`.

## Phase 1: Shared form contracts

### Overview

Add `Common/FormField`, give `ui/input` an invalid state, give `ConfirmDialog` an optional body slot, and demonstrate all three in the kitchen sink under the visual gate.

### Changes Required:

#### 1. FormField component

**File**: `resources/js/Components/Common/FormField.vue` (new)

**Intent**: One component that replaces `InputLabel` + `InputError`. It owns the label, optional description and server error, and the a11y wiring between them, while the page keeps the real control (and its `v-model`, `ref` and attributes) in a scoped slot.

**Contract**: `<script setup lang="ts">`, like the other `Common/` components.
- Props:
  - `id: string` (required; the control's id)
  - `label: string`
  - `error?: string`
  - `description?: string`
- Renders, in a `grid gap-2`:
  - `ui/label` `for=id`;
  - the default slot;
  - the description as `<p id="${id}-description" class="text-xs text-muted-foreground">`, when set;
  - the error as `<p id="${id}-error" class="text-sm text-destructive">`, when set.
- Default slot props: `{ id: string, invalid: boolean, describedBy: string | undefined }`.
  - `describedBy` joins the description and error ids that are present.
  - Call sites bind them as `:id`, `:aria-invalid="invalid || undefined"` and `:aria-describedby="describedBy"`.
- Token utilities only.

#### 2. Input invalid state

**File**: `resources/js/Components/ui/input/Input.vue`

**Intent**: An `aria-invalid` input shows a destructive border and focus ring, so `FormField` errors are visible on the control itself.

**Contract**: Append `aria-invalid:border-destructive aria-invalid:focus-visible:ring-destructive` to the existing class list. Nothing else changes. Inputs without `aria-invalid="true"` render unchanged.

**Known caller outside the slice**: `Components/recipes/IngredientRow.vue:22-23,60,89` (recipe form, not yet migrated) already passes `:aria-invalid` and `border-red-500` to `ui/input`. Its invalid fields will also get the destructive border and focus ring. This is an accepted side effect with the same intent (error red → `destructive` token). Do not edit `IngredientRow` here; its `border-red-500` is removed in its own slice (see Migration Notes).

#### 3. ConfirmDialog body slot

**File**: `resources/js/Components/Common/ConfirmDialog.vue`

**Intent**: Callers can put extra content (the delete-account password field) between the description and the footer, without a second dialog implementation.

**Contract**: An optional default slot, rendered inside `AlertDialogContent` between `AlertDialogHeader` and `AlertDialogFooter`, only when `$slots.default` exists. Props, emits, the focus-on-„Anuluj” rule and the processing guards stay unchanged. The existing callers (recipes, meal plans, family members) render identically.

#### 4. Kitchen sink demos

**File**: `resources/js/Pages/Dev/DesignSystem.vue`

**Intent**: Every new shared pattern shows its states (CLAUDE.md, §5.4 „Formularze dodatkowo: błąd walidacji z serwera”).

**Contract**:
- In „Formularze”, replace the hand-written invalid input (`:365-374`) with `FormField` demos:
  - default;
  - with description;
  - with a server error (`Input` invalid);
  - a password field.
- Keep the remaining input, textarea, checkbox, select and combobox demos.
- In „Komponenty aplikacji”, add a trigger („Potwierdź z polem”) that opens a `ConfirmDialog` (`variant="destructive"`) with a password `FormField` showing an error in its slot.

#### 5. Visual gate for the new overlay

**File**: `tests/e2e/visual/design-system.spec.ts`

**Intent**: The dialog-with-field state is screenshotted like the other `ConfirmDialog` overlays.

**Contract**: A new overlays test, `confirm dialog with field`, opens the new trigger and asserts `overlay-confirm-dialog-field.png` on the `alertdialog`. Regenerate baselines with `npm run test:visual -- --update-snapshots`. Expected changed or new PNGs:
- `section-formularze`
- `section-komponenty-aplikacji`
- `overlay-confirm-dialog-field` (new)
- any `overlay-*.png` whose diff is confined to its corner pixels. Overlay element screenshots show the page behind their rounded corners (`app-shell-restyle/plan.md:476`), and this phase changes the content around the dialog, alert-dialog, `ConfirmDialog` (both), select and combobox triggers.

Any other changed PNG, or an overlay diff outside its corners, is a regression.

#### 6. Design-system spec

**File**: `context/foundation/design-system.md`

**Intent**: Keep the spec truthful after this phase.

**Contract**:
- §5.1: move `checkbox` (and the other five primitives F-03 already added) from „Dodać” to „Są”.
- §5.2 `FormField` row: replace „Odroczony do S-13” with the contract from change 1.
- §5.2 `ConfirmDialog` row: add „opcjonalny slot domyślny między opisem a stopką (np. pole hasła)”.
- §7, item 3 (the visual-gate paragraph): the `design-system.spec.ts` count goes from 26 to 27, and the overlay list goes from 10 to 11 with „`ConfirmDialog` z polem”.

### Success Criteria:

#### Automated Verification:

- Client and SSR bundles build: `vendor/bin/sail npm run build`
- Token scan passes (covers `Components/Common` and `Pages/Dev`): `npm run lint:tokens`
- Existing ConfirmDialog behaviour E2E stays green: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npx playwright test tests/e2e/confirm-dialog.spec.ts`
- Visual gate passes with the regenerated baselines: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && VISUAL_DOCKER_NETWORK=auth-profile-ui_sail VISUAL_BASE_URL=http://laravel.test npm run test:visual`

#### Manual Verification:

- Every changed or new PNG in the diff was reviewed: only the two sections and the new overlay changed, plus overlays whose diff is confined to their corners
- In `/dev/design-system`, the FormField error demo announces the error (screen reader or DevTools accessibility pane shows the description) and has a destructive border
- „Potwierdź z polem” opens with focus on „Anuluj”; Tab reaches the field and both buttons; Esc closes it

**Implementation Note**: After this phase passes automated verification, pause for manual confirmation before Phase 2.

---

## Phase 2: Auth screens

### Overview

Migrate the five guest screens to `FormField`, `ui/input`, `ui/checkbox`, `ui/button` and `ui/alert`, with no change to labels, button names or behaviour.

### Changes Required:

#### 1. Guest form pages

**Files**: `resources/js/Pages/Auth/Login.vue`, `Register.vue`, `ForgotPassword.vue`, `ResetPassword.vue`, `ConfirmPassword.vue`

**Intent**: Replace the Breeze imports and the palette and `dark:` literals with primitives and tokens, keeping the exact labels, ids, autocomplete, autofocus and submit logic.

**Contract**:
- Each field becomes `FormField :id :label :error="form.errors.<field>"` wrapping `<Input>`, which keeps its current `id`, `type`, `v-model`, `required`, `autofocus` and `autocomplete`.
- The form layout is `grid gap-4`, and the action row is `flex items-center justify-end gap-4`.
- Submit is `<Button type="submit" :disabled="form.processing">`, without the `opacity-25` hack.
- Intro paragraphs use `text-sm text-muted-foreground`.
- The `status` flash (Login, ForgotPassword) becomes `<Alert variant="success"><AlertDescription>{{ status }}</AlertDescription></Alert>`.
- The text links („Nie pamiętasz hasła?”, „Masz już konto?”) use token classes only: `text-muted-foreground`, underline, `hover:text-foreground`, and a `focus-visible` ring on `ring`, the same as the `GuestLayout` logo link.
- Login only: `<Checkbox id="remember" v-model="form.remember" />` + `<Label for="remember">Zapamiętaj mnie</Label>`.

#### 2. Token scan coverage

**File**: `scripts/lint-tokens.paths`

**Intent**: The migrated guest screens can't regress to literals.

**Contract**: Append the line `resources/js/Pages/Auth`.

#### 3. Guest visual snapshots

**Files**: `tests/e2e/visual/account.spec.ts` (new), `tests/e2e/__screenshots__/visual/app-shell.spec.ts/guest-login.png`

**Intent**: Register (the longest guest form) gets its own baseline. The existing login baseline is updated deliberately, as S-08 planned.

**Contract**: `account.spec.ts` follows `app-shell.spec.ts`:
- skip unless `PLAYWRIGHT_VISUAL_DOCKER`;
- `page.clock.setFixedTime(new Date('2026-06-15T10:00:00'))` in `beforeEach`;
- `expectFigtreeLoaded`;
- `mouse.move(0, 0)`.

Test `guest register` takes `guest-register.png` with `fullPage: true`. Regenerate with `--update-snapshots`. The expected PNG changes are `guest-login.png` (changed) and `guest-register.png` (new).

### Success Criteria:

#### Automated Verification:

- Bundles build: `vendor/bin/sail npm run build`
- Token scan passes with `Pages/Auth` listed: `npm run lint:tokens`
- No Breeze import remains in `Pages/Auth`: `grep -rE "Components/(PrimaryButton|TextInput|InputLabel|InputError|Checkbox)\.vue" resources/js/Pages/Auth` prints nothing
- Pest auth suite (Polish messages, session, reset) passes: `vendor/bin/sail pest tests/Feature/Auth`
- Full E2E suite passes; every spec logs in through `/login`, and `login.spec.ts` and `password-reset.spec.ts` cover register, invalid login and reset: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- Visual gate passes with the updated and new baselines: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && VISUAL_DOCKER_NETWORK=auth-profile-ui_sail VISUAL_BASE_URL=http://laravel.test npm run test:visual`

#### Manual Verification:

- `guest-login.png` and `guest-register.png` were reviewed in the diff, and no other PNG changed
- On `/login`, clicking the „Zapamiętaj mnie” label toggles the checkbox, Space toggles it when focused, and a remembered login survives a browser restart
- A failed login shows the error under „Adres e-mail” with a destructive border, and a screen reader announces it with the field
- `/forgot-password` shows the success Alert after sending; `/confirm-password` (logged in) renders in the new style

**Implementation Note**: After this phase passes automated verification, pause for manual confirmation before Phase 3.

---

## Phase 3: Profile screen

### Overview

Migrate `Profile/Edit` and its three partials to `Card`, `FormField` and `Button`, move account deletion to `ConfirmDialog` with the password field in its slot, and add the E2E and visual coverage the page never had.

### Changes Required:

#### 1. Profile page

**File**: `resources/js/Pages/Profile/Edit.vue`

**Intent**: The page header and layout follow the migrated Dashboard and the design-system form width.

**Contract**:
- The `#header` slot holds `<h1 class="text-2xl font-semibold tracking-tight">Profil</h1>`, matching `Dashboard.vue:13-17`.
- The body is `mx-auto grid max-w-2xl gap-6`, listing the three partials.
- The wrapper `div`s with `bg-white`/`shadow-sm`/`dark:` go away; each partial renders its own `Card`.

#### 2. Profile data and password forms

**Files**: `resources/js/Components/ui/card/CardTitle.vue`, `resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue`, `UpdatePasswordForm.vue`

**Intent**: Same fields, endpoints and focus-on-error behaviour, on primitives.

**Contract**:
- `ui/card/CardTitle.vue` is a hard-coded `<h3>` today. It gets an `as` prop (`String`, default `'h3'`) and renders `<component :is="as">` with the same classes. Its existing callers (`FamilyMembers/Create.vue:132`, `FamilyMembers/Edit.vue:136`, the kitchen sink) pass nothing and still render `<h3>`, so no baseline changes.
- The root is a `Card`:
  - `CardHeader` holds `<CardTitle as="h2">` (section heading under the page `<h1>`) and `CardDescription` with the existing Polish texts;
  - `CardContent` holds the form (`grid gap-4`).
- Fields use `FormField` + `Input` with the current ids (`name`, `email`, `current_password`, `password`, `password_confirmation`) and labels.
- „Zapisano.” stays inside the existing `Transition`, as `text-sm text-muted-foreground`.
- The password form's `onError` focus uses `ref.value.$el.focus()`.

#### 3. Delete account

**File**: `resources/js/Pages/Profile/Partials/DeleteUserForm.vue`

**Intent**: Account deletion uses the shared destructive confirmation instead of Breeze `Modal`, keeping password confirmation.

**Contract**:
- A `Card` with the existing heading (`<CardTitle as="h2">`) and description, plus `<Button variant="destructive">Usuń konto</Button>` that opens it.
- The `ConfirmDialog` takes:
  - `v-model:open`
  - `title="Czy na pewno chcesz usunąć swoje konto?"`
  - `description` = the existing dialog text
  - `confirm-label="Usuń konto"`
  - `variant="destructive"`
  - `:processing="form.processing"`
  - `@confirm="deleteUser"`
- Its slot holds `FormField id="delete-account-password" label="Hasło" :error="form.errors.password"` with a password `Input` that submits on Enter.
- `deleteUser` starts with `if (form.processing) return`, because Enter in the field does not pass through `ConfirmDialog`'s processing guard.
- Closing (open → false while not processing) resets the form and clears errors. The rest follows Critical Implementation Details.

#### 4. Token scan coverage

**File**: `scripts/lint-tokens.paths`

**Intent**: The profile views are scanned from now on.

**Contract**: Append the line `resources/js/Pages/Profile`.

#### 5. Profile E2E

**File**: `tests/e2e/profile.spec.ts` (new)

**Intent**: Guard the profile forms and the changed delete-account behaviour; no spec covered them before.

**Contract**:
- Setup: register a fresh user (`generateRandomEmail` from `tests/e2e/support/mailpit.ts`, same register selectors as `login.spec.ts`), then open `/profile`.
- Test 1, update data:
  - change „Imię” and save;
  - „Zapisano.” is visible in that card;
  - after a reload, the field holds the new name.
- Test 2, change password:
  - a wrong „Obecne hasło” shows „Podane hasło jest nieprawidłowe.”;
  - the correct one saves.
- Test 3, confirm password: `/confirm-password` with the current password redirects to `/dashboard`.
- Test 4, delete account:
  - „Usuń konto” opens an `alertdialog`;
  - a wrong password keeps it open and shows „Podane hasło jest nieprawidłowe.”;
  - the correct password lands on `/`;
  - logging in with the deleted credentials shows „Podane dane logowania są nieprawidłowe.”.
- Selectors are scoped to the card or the `alertdialog`, and use `exact: true` where „hasło” labels overlap.

#### 6. Profile visual snapshot and spec docs

**Files**: `tests/e2e/visual/support/auth.ts` (new), `tests/e2e/visual/app-shell.spec.ts`, `tests/e2e/visual/account.spec.ts`, `context/foundation/design-system.md`

**Intent**: The composed profile page gets a baseline, and §7, item 3 lists the new spec.

**Contract**:
- Move the local `logIn(page)` from `app-shell.spec.ts:16-23` unchanged into `tests/e2e/visual/support/auth.ts` (next to `support/fonts.ts`), exported. Both specs import it, and the `app-shell` screenshots do not change.
- Test `profile` logs in as `e2e-planner-visual@example.com` with that `logIn`, opens `/profile` and takes `profile-desktop.png` with `fullPage: true`. The only new PNG is `profile-desktop.png`.
- Extend §7, item 3 (the visual-gate paragraph) with `account.spec.ts` (2 shots: `guest-register`, `profile-desktop`).

### Success Criteria:

#### Automated Verification:

- Bundles build: `vendor/bin/sail npm run build`
- Token scan passes with `Pages/Profile` listed: `npm run lint:tokens`
- No Breeze import remains anywhere outside `Components/`: `grep -rE "Components/(PrimaryButton|SecondaryButton|DangerButton|TextInput|InputLabel|InputError|Checkbox|Modal)\.vue" resources/js/Pages resources/js/Layouts` prints nothing
- Pest profile and password tests pass: `vendor/bin/sail pest tests/Feature/ProfileTest.php tests/Feature/Auth/PasswordUpdateTest.php tests/Feature/Auth/PasswordConfirmationTest.php`
- Full Pest suite passes: `vendor/bin/sail composer test`
- Full E2E suite including the new `profile.spec.ts` passes: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- Visual gate passes with `profile-desktop.png`: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && VISUAL_DOCKER_NETWORK=auth-profile-ui_sail VISUAL_BASE_URL=http://laravel.test npm run test:visual`

#### Manual Verification:

- `profile-desktop.png` was reviewed, and no other PNG changed in this phase
- `/profile` at mobile width (390 px) has no horizontal scroll, and the cards stack at full width
- The delete dialog: focus starts on „Anuluj”; Tab reaches the password field; Enter in the field submits; Esc and „Anuluj” close it and clear a previous error; while the request runs, the dialog can't be closed
- The page has one `<h1>` („Profil”) and three `<h2>` section headings (DevTools accessibility tree)

**Implementation Note**: After this phase passes automated verification, pause for manual confirmation, then run `/10x-impl-review`.

---

## Testing Strategy

### Unit Tests:

- No new Pest tests: no PHP behaviour changes. The existing `tests/Feature/Auth/*` and `tests/Feature/ProfileTest.php` already assert the Polish messages and endpoint behaviour that the views render.

### Integration Tests:

- `login.spec.ts` and `password-reset.spec.ts` are unchanged and must pass. They are the contract that labels and button names did not move.
- The new `profile.spec.ts` covers the profile flows, including the dialog behaviour change.
- The visual gate covers the kitchen sink (FormField states, dialog with field), `/login`, `/register` and `/profile`.

### Manual Testing Steps:

1. Register, log out, log in with „Zapamiętaj mnie”, then restart the browser: you are still logged in.
2. Trigger an error on each guest form (empty or wrong values) and check that the error sits under the right field with a red border.
3. On `/profile`, change name and password, then delete the account with a wrong and then a correct password, using only the keyboard.

## Performance Considerations

None. Same number of components per page; no new dependencies.

## Migration Notes

- Visual baselines change in three steps (one per phase). Each phase reviews only its own PNGs.
- After merge, sibling worktrees still on the old `c547133` base (`recipes-ui`, `family-members-ui`) will see `ConfirmDialog` gain a slot. It is optional, so their callers are unaffected.
- S-15 can delete all 13 Breeze files once this lands.
- `recipes-ui`: after this lands, `IngredientRow.vue` invalid inputs get the destructive border from `ui/input`, so its `border-red-500` becomes redundant and goes away in that slice.
- `ui/input/Input.vue` (invalid style) and `ui/card/CardTitle.vue` (`as` prop) now carry local edits. The D-9 regeneration of the older primitives must keep them.

## References

- Roadmap item: `context/foundation/roadmap.md` (S-13, lines 192-203)
- Design system: `context/foundation/design-system.md` §5.1, §5.2, §5.4, §7, §8 (DS-08)
- Prior decisions:
  - `context/archive/2026-11-15-shared-app-components/plan.md` (FormField deferral, ConfirmDialog contract)
  - `context/archive/2026-11-15-app-shell-restyle/plan.md` (GuestLayout, `#header` slot, baseline policy, worktree environment)
  - `context/archive/2026-11-14-polish-auth-with-24h-session/plan.md` (labels and messages)
- Similar implementation:
  - `resources/js/Pages/Dashboard.vue:13-17` (header pattern)
  - `resources/js/Pages/Dev/DesignSystem.vue:365-374` (invalid field markup)
  - `tests/e2e/visual/app-shell.spec.ts` (real-page visual spec)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Shared form contracts

#### Automated

- [x] 1.1 Client and SSR bundles build: `vendor/bin/sail npm run build` — 8347100
- [x] 1.2 Token scan passes (covers `Components/Common` and `Pages/Dev`): `npm run lint:tokens` — 8347100
- [x] 1.3 Existing ConfirmDialog behaviour E2E stays green: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npx playwright test tests/e2e/confirm-dialog.spec.ts` — 8347100
- [x] 1.4 Visual gate passes with the regenerated baselines: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && VISUAL_DOCKER_NETWORK=auth-profile-ui_sail VISUAL_BASE_URL=http://laravel.test npm run test:visual` — 8347100

#### Manual

- [ ] 1.5 Every changed or new PNG in the diff was reviewed: only the two sections and the new overlay changed, plus overlays whose diff is confined to their corners
- [ ] 1.6 In `/dev/design-system`, the FormField error demo announces the error (screen reader or DevTools accessibility pane shows the description) and has a destructive border
- [ ] 1.7 „Potwierdź z polem” opens with focus on „Anuluj”; Tab reaches the field and both buttons; Esc closes it

### Phase 2: Auth screens

#### Automated

- [x] 2.1 Bundles build: `vendor/bin/sail npm run build` — 2a8fa6f
- [x] 2.2 Token scan passes with `Pages/Auth` listed: `npm run lint:tokens` — 2a8fa6f
- [x] 2.3 No Breeze import remains in `Pages/Auth`: `grep -rE "Components/(PrimaryButton|TextInput|InputLabel|InputError|Checkbox)\.vue" resources/js/Pages/Auth` prints nothing — 2a8fa6f
- [x] 2.4 Pest auth suite (Polish messages, session, reset) passes: `vendor/bin/sail pest tests/Feature/Auth` — 2a8fa6f
- [x] 2.5 Full E2E suite passes; every spec logs in through `/login`, and `login.spec.ts` and `password-reset.spec.ts` cover register, invalid login and reset: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e` — 2a8fa6f
- [x] 2.6 Visual gate passes with the updated and new baselines: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && VISUAL_DOCKER_NETWORK=auth-profile-ui_sail VISUAL_BASE_URL=http://laravel.test npm run test:visual` — 2a8fa6f

#### Manual

- [ ] 2.7 `guest-login.png` and `guest-register.png` were reviewed in the diff, and no other PNG changed
- [ ] 2.8 On `/login`, clicking the „Zapamiętaj mnie” label toggles the checkbox, Space toggles it when focused, and a remembered login survives a browser restart
- [ ] 2.9 A failed login shows the error under „Adres e-mail” with a destructive border, and a screen reader announces it with the field
- [ ] 2.10 `/forgot-password` shows the success Alert after sending; `/confirm-password` (logged in) renders in the new style

### Phase 3: Profile screen

#### Automated

- [x] 3.1 Bundles build: `vendor/bin/sail npm run build`
- [x] 3.2 Token scan passes with `Pages/Profile` listed: `npm run lint:tokens`
- [x] 3.3 No Breeze import remains anywhere outside `Components/`: `grep -rE "Components/(PrimaryButton|SecondaryButton|DangerButton|TextInput|InputLabel|InputError|Checkbox|Modal)\.vue" resources/js/Pages resources/js/Layouts` prints nothing
- [x] 3.4 Pest profile and password tests pass: `vendor/bin/sail pest tests/Feature/ProfileTest.php tests/Feature/Auth/PasswordUpdateTest.php tests/Feature/Auth/PasswordConfirmationTest.php`
- [x] 3.5 Full Pest suite passes: `vendor/bin/sail composer test`
- [x] 3.6 Full E2E suite including the new `profile.spec.ts` passes: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost:${APP_PORT} npm run test:e2e`
- [x] 3.7 Visual gate passes with `profile-desktop.png`: `vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder && VISUAL_DOCKER_NETWORK=auth-profile-ui_sail VISUAL_BASE_URL=http://laravel.test npm run test:visual`

#### Manual

- [ ] 3.8 `profile-desktop.png` was reviewed, and no other PNG changed in this phase
- [ ] 3.9 `/profile` at mobile width (390 px) has no horizontal scroll, and the cards stack at full width
- [ ] 3.10 The delete dialog: focus starts on „Anuluj”; Tab reaches the password field; Enter in the field submits; Esc and „Anuluj” close it and clear a previous error; while the request runs, the dialog can't be closed
- [ ] 3.11 The page has one `<h1>` („Profil”) and three `<h2>` section headings (DevTools accessibility tree)
