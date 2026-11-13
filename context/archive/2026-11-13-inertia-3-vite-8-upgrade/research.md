---
date: 2026-11-13
researcher: Claude (unattended)
git_commit: 6bf5fa0
branch: develop
repository: menu-maker
topic: "Inertia 3 + laravel-vite-plugin 3 + Vite 8 upgrade: inventory and breaking changes"
tags: [research, inertia, vite, ssr, ziggy]
status: complete
last_updated: 2026-11-13
last_updated_by: Claude (unattended)
---

# Research: Inertia 3 / Vite 8 upgrade

## Summary

Sources: official Inertia v3 upgrade guide (inertiajs.com/docs/v3/getting-started/upgrade-guide), npm registry peer metadata (checked 2026-11-13).

Target versions: inertia-laravel ^3 (needs PHP 8.2+, Laravel 11+; we have PHP 8.5, Laravel 13), @inertiajs/vue3 ^3 (3.11.0, peer vue ^3), laravel-vite-plugin ^3 (3.2.1, peer vite ^8, node ^20.19||>=22.12; host node 24.21), vite ^8 (8.3.8), @vitejs/plugin-vue 6.0.11 already supports vite ^8 (a bump within ^6 only). ziggy-js 2.6.5 is current (no v3 exists); Ziggy is independent of Inertia and needs no change. `@inertiajs/vite` is optional; not required.

The inventory of Inertia usage in this repo shows few v3 breakpoints. Required changes found:
1. `resources/views/app.blade.php:8` uses `<title inertia>`; v3 requires `data-inertia`.
2. `config/inertia.php` does not exist (no published config; checked `ls config`); v3 defaults apply. Optionally publish.
3. Axios is no longer shipped by Inertia; `resources/js/bootstrap.js` imports axios directly from the project's own devDependency (`package.json`), so it keeps working. Inertia does not use axios any more by default (built-in XHR).
4. `view:clear` needed after upgrade (`@inertia` directive output changed).
5. Vite 8 (rolldown): `vite.config.js` has a `test:` block (vitest keys, no vitest installed; harmless). Build scripts `vite build` and `vite build --ssr` need to be verified.

No usage found (grep over app/, routes/, tests/, resources/js, this checked scope): `Inertia::lazy`, `router.on('invalid'|'exception')`, `router.cancel`, `hideProgress/revealProgress`, `future:` options in createInertiaApp, `inertia` attribute elsewhere than app.blade.php.

## Inventory

- Client entry `resources/js/app.js:1-28`: createInertiaApp with `resolvePageComponent` from `laravel-vite-plugin/inertia-helpers` (still exported by laravel-vite-plugin 3: package exports `./inertia-helpers`), ZiggyVue plugin, `progress` option. No `future` block.
- SSR entry `resources/js/ssr.js:1-30`: `createServer` from `@inertiajs/vue3/server`, `renderToString` from `@vue/server-renderer`, Ziggy with `page.props.ziggy`. `vite.config.js` registers `ssr: 'resources/js/ssr.js'`. Build output `bootstrap/ssr/` is gitignored (`.gitignore:15`). SSR server is not started by Sail/compose/CI (no grep hits for ssr in docker/compose/.github, scope: those files); `config/inertia.php` absent so inertia SSR defaults apply (enabled: true by default in the package, url http://127.0.0.1:13714; a failing/absent server falls back to client rendering). SSR verification therefore needs a manual `php artisan inertia:start-ssr` inside Sail.
- Middleware `app/Http/Middleware/HandleInertiaRequests.php`: extends `Inertia\Middleware`, shares `auth.user` and a closure `ziggy` prop, uses `...parent::share()`, `rootView = 'app'`.
- Blade `resources/views/app.blade.php`: `@routes`, `@vite([app.js, page component])`, `@inertiaHead`, `@inertia`, `<title inertia>`.
- Server render calls: `Inertia::render` in 14 controller/route sites (MealPlanController, RecipeController, FamilyMemberController, Profile, Auth/*, routes/web.php). No defer/lazy/optional/merge.
- Client: `useForm` in Auth, Profile, FamilyMembers, Recipes pages; `Link`, `Head`, `router` (visit/delete/get patterns in Sidebar, MealPlans, Recipes, hooks/useDeleteRecipe.ts), `usePage` in 2 files. No `<Form>` component usage. v3 `useForm` processing semantics change (stays true until onFinish): check forms that react to `processing`.
- Tests: `assertInertia` used 14 times across tests/Feature/{FamilyMember,MealPlanController,RecipeController}Test.php; v3 keeps AssertableInertia. Playwright E2E 6 tests (playwright.config.ts) exercise login/forms.
- Ziggy: `tightenco/ziggy` ^2.0 + `ziggy-js` ^2.0, `@routes` blade directive plus shared prop for SSR. Unchanged.

## Historical Context

- `context/archive/2026-11-13-{deps-safe-updates,laravel-13-upgrade,pest-5-upgrade,postgres-migration}` : prior stage changes; baseline 129 tests, E2E 6/6 per task brief.

## Open Questions

- Whether composer resolves inertia-laravel ^3 with Laravel 13.42 and Boost 2 (to be proven in phase 1).
- Whether `vite build --ssr` under Vite 8 emits `bootstrap/ssr/ssr.js` correctly (phase 2).
- Whether the `@inertiajs/vue3` v3 ESM/ES2022 target breaks the SSR bundle (phase 2).
