# Changelog

All notable changes to Menu Maker are recorded here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-11-18

First tagged release. It covers everything built from the first commit to the close of milestone M-4: the MVP (M-1), the design system (M-2), the editable meal plan draft (M-3) and the Spiżarnia restyle (M-4). Each change was planned, reviewed and archived under `context/archive/`; the milestones are listed in `context/foundation/roadmap.md`.

### Added

#### Accounts and family

- Registration, login and password reset on Polish screens. The session expires after 24 hours of inactivity, and the reset link (valid for 1 hour) is sent by e-mail.
- Family members with name, birth date, gender, weight and height, used to scale ingredient quantities.
- Profile editing and account deletion.

#### Recipes

- Recipe CRUD with required fields: name, category (breakfast, dinner, supper), at least one ingredient (name, unit g/kg/ml/l/szt., quantity), preparation, calories and servings. Deletion asks for confirmation.
- Accent-insensitive recipe search (`unaccent`), so "zurek" finds "Żurek".
- `php artisan recipes:import <dir> --user=<id|email>` imports Markdown recipes (front matter, ingredients table, steps). It converts spoons, glasses and pinches to the allowed units, merges repeated ingredients, skips existing names and supports `--dry-run`.

#### Meal plans

- One click draws a 7 days × 3 meals week from the user's own recipes, by category and without repeats. The week can be redrawn any number of times.
- The drawn week is a draft first. The user can swap the recipe in any cell, leave a meal empty ("Nie robimy"), cook a dinner once for two days ("Jak wczoraj") and turn supper leftovers into the next day's breakfast ("z kolacji"). A category with fewer than 7 recipes leaves empty cells in the draft instead of blocking generation.
- Approving the draft starts ingredient scaling in a queue job. Quantities are computed in code from the recipe's calories and servings and the family profile, then refined by AI (`AI_SCALING_DRIVER`: `openrouter`, `anthropic` or `fake`). If the AI call fails, the plan keeps the computed quantities and shows a warning.
- "Wróć do szkicu" turns a finished plan back into a draft and keeps the edits.
- Shopping list next to the plan: ingredients are summed, quantities above 1000 g and 1000 ml become kg and l, values are rounded to two decimals and sorted alphabetically. A dish cooked once for two meals appears once.
- PDF export with the meal plan table followed by the shopping list. On download the user chooses whether to include recipes; each included recipe gets its own page, once.

#### Interface

- Spiżarnia visual direction on every screen: OKLCH design tokens, the Bricolage Grotesque typeface, meal-category colours (breakfast, dinner, supper) used on screen and in the PDF, and shadcn-vue primitives with pill, outline and sticker variants.
- Top bar with navigation pills and a user menu (a drawer below `lg`), and a split guest screen for login, registration and password reset.
- Dashboard with the latest meal plan, statistics tiles, "Ten tydzień" and an empty state for new users.
- Recipes as category tiles with filter pills, meal plans as week tiles with a fill bar and status, the meal plan as a days × meals grid, and family members as person cards.
- `/` redirects to login or to the app.

#### Operations

- Production stack for a VPS behind Traefik: `Dockerfile.prod`, `docker-compose.prod.yml`, supervisord running nginx, PHP-FPM, the queue worker and the scheduler.
- `scripts/deploy.sh` checks `.env.prod`, backs up the database and runs migrations inside a maintenance window.
- Encrypted nightly database backups through `spatie/laravel-backup`.
- Exceptions from HTTP requests and queue jobs are logged with user and meal plan context and mailed to an alert address.
- Security headers through Traefik, `expose_php = Off`, and trusted proxies limited to the Docker networks and to `X-Forwarded-For` / `X-Forwarded-Proto`.

#### Quality

- CI on pull requests into `main`: front-end build, `lint:tokens` self-test and scan, Pint, Larastan (level 5), Pest, config/route/view caching, Playwright E2E (Chromium and Firefox), the visual gate and a production image smoke test.
- Visual gate: 64 Playwright screenshots of the kitchen sink (`/dev/design-system`, `local` and `testing` only), the app shell, account screens, family members, meal plans and the dashboard, run in a version-pinned Docker image.
- `npm run lint:tokens` rejects Tailwind palette classes, hex colours, arbitrary values and `dark:` outside `Components/ui/`.
- Dependabot updates GitHub Actions, Composer and npm dependencies weekly.

### Changed

- Stack upgraded to Laravel 13, Inertia 3, Vite 8, Tailwind CSS 4 (CSS-first, `@tailwindcss/vite`), Pest 5 and PHPUnit 13.
- PostgreSQL 18 replaces MySQL in Sail, CI and production; Redis is removed.
- Production serves through nginx and PHP-FPM instead of `php artisan serve`.
- Polish is the default locale.

### Removed

- Laravel Breeze components and the default Laravel welcome page.

[Unreleased]: https://github.com/sebastian-kostecki/menu-maker/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/sebastian-kostecki/menu-maker/releases/tag/v1.0.0
