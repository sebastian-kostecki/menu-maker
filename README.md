# Menu Maker

A web application that plans a family's week of meals from its own recipes, scales the ingredients to the family with help from AI, and exports one PDF with the meal plan and a consolidated shopping list.

![Version](https://img.shields.io/github/v/release/sebastian-kostecki/menu-maker)
![MIT License](https://img.shields.io/badge/license-MIT-blue.svg)

## Table of Contents

- [1. Project Description](#1-project-description)
- [2. Tech Stack](#2-tech-stack)
- [3. Getting Started Locally](#3-getting-started-locally)
- [4. Available Scripts](#4-available-scripts)
- [5. Testing \& Quality](#5-testing--quality)
- [6. Deployment](#6-deployment)
- [7. Project Scope](#7-project-scope)
- [8. Project Status](#8-project-status)
- [9. License](#9-license)

## 1. Project Description

Menu Maker automates weekly meal planning for a family:

- Save your own recipes (category, ingredients, preparation, calories, servings) and the members of your family (birth date, gender, weight, height).
- Draw a week of 7 days × 3 meals from your recipes. The week is a draft: swap any recipe, leave a meal empty, cook a dinner once for two days or turn supper leftovers into the next day's breakfast.
- Approve the draft. A queue job scales every ingredient to your family in code and then refines the quantities with AI; if the AI call fails, you still get the computed quantities.
- Read the plan and the shopping list on screen, or download one PDF with the plan, the shopping list and, optionally, the full recipes.

The interface is in Polish.

## 2. Tech Stack

**Backend**

- PHP 8.5, Laravel 13, Inertia.js 3 (Laravel adapter)
- PostgreSQL 18 (`unaccent` extension for accent-insensitive search)
- Database queue for meal plan generation
- AI scaling through OpenRouter or the Anthropic API (`AI_SCALING_DRIVER`)
- DomPDF for the PDF export, `spatie/laravel-backup` for production backups

**Frontend**

- Vue 3 with Inertia.js 3, Vite 8
- Tailwind CSS 4 (CSS-first, `@tailwindcss/vite`) and shadcn-vue (reka-ui)
- Design tokens in `resources/css/app.css`, described in `context/foundation/design-system.md`

**Tooling & Testing**

- Laravel Sail (Docker) for development
- Pest 5 + PHPUnit 13, Larastan (level 5), Laravel Pint
- Playwright for E2E tests and the visual gate

## 3. Getting Started Locally

### Prerequisites

- Docker and Docker Compose
- Node.js 22+ and npm on the host (Playwright tests run from the host)
- Composer for the first `composer install`. Every later `php`, `composer` and `artisan` command runs through Sail (PHP 8.5 in Docker).

### Setup

```bash
# 1. Clone the repository
git clone git@github.com:sebastian-kostecki/menu-maker.git
cd menu-maker

# 2. Configure the environment
cp .env.example .env
# Without AI keys, set AI_SCALING_DRIVER=fake in .env

# 3. Install PHP dependencies (needs PHP 8.5 on the host, or add --ignore-platform-reqs)
composer install

# 4. Start the containers: app, queue worker, PostgreSQL and Mailpit
./vendor/bin/sail up -d

# 5. Generate the key and migrate (the seeder creates test@example.com / password)
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed

# 6. Install JS dependencies and start Vite
./vendor/bin/sail npm ci
./vendor/bin/sail npm run dev
```

The app runs on `http://localhost` (or `APP_PORT` from `.env`), and Mailpit, which catches password-reset e-mails, on `http://localhost:8025`.

To import Markdown recipes onto an account:

```bash
./vendor/bin/sail artisan recipes:import <dir> --user=<id|email> --dry-run
```

## 4. Available Scripts

### NPM

| Command                   | Description                                                |
| ------------------------- | ---------------------------------------------------------- |
| `npm run dev`             | Start the Vite development server with HMR                 |
| `npm run build`           | Build the client and SSR bundles                           |
| `npm run lint:tokens`     | Reject palette classes, hex colours and `dark:` in `resources/js` |
| `npm run test:e2e`        | Run the Playwright E2E tests (Chromium and Firefox)        |
| `npm run test:e2e:headed` | Run the E2E tests in headed mode                           |
| `npm run test:e2e:ui`     | Run the E2E tests in the Playwright UI                     |
| `npm run test:visual`     | Run the visual gate in the pinned Playwright Docker image  |
| `npm run pw:install`      | Install Playwright browsers                                |

### Composer (run through Sail)

| Command                     | Description                                         |
| --------------------------- | --------------------------------------------------- |
| `composer test`             | Clear the config cache and run the Pest suite       |
| `composer pint`             | Fix code style with Laravel Pint                    |
| `composer pint-test`        | Check code style without fixing                     |
| `composer phpstan`          | Run static analysis with Larastan                   |

## 5. Testing & Quality

```bash
./vendor/bin/sail composer test        # Pest
./vendor/bin/sail composer pint-test   # code style
./vendor/bin/sail composer phpstan     # static analysis
./vendor/bin/sail npm run lint:tokens  # design-token lint
```

**E2E (Playwright, from the host, against Sail).** `.env` needs `AI_SCALING_DRIVER=fake` and Mailpit as the mailer:

```bash
./vendor/bin/sail npm run build
./vendor/bin/sail artisan db:seed --class=E2eMealPlanSeeder   # re-run before every E2E run
E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://localhost npm run test:e2e
```

**Visual gate.** After `npm run build`, with `npm run dev` stopped and the E2E seeder run, `npm run test:visual` compares screenshots with the approved baselines. Update the baselines only on purpose, with `npm run test:visual -- --update-snapshots`, and review every changed PNG.

CI (`.github/workflows/master.yml`) runs all of the above on pull requests into `main`, plus a smoke test of the production image.

## 6. Deployment

Production runs on a VPS behind Traefik with `docker-compose.prod.yml` and `Dockerfile.prod`: nginx and PHP-FPM, the queue worker and the scheduler under supervisord, PostgreSQL 18 and encrypted nightly database backups. Copy `.env.prod.example` to `.env.prod`, fill it in and run `scripts/deploy.sh`. The script backs up the database, migrates inside a maintenance window and restarts the workers.

## 7. Project Scope

### Included

- E-mail authentication (registration, login, password reset) with a 24-hour session
- Family members (name, birth date, gender, weight, height)
- Recipe CRUD with required fields, accent-insensitive search and Markdown import
- Weekly meal plan (7 days × 3 meals) drawn from your recipes without repeats, redrawn without limit
- Editable draft: swap a recipe, leave a meal empty, repeat a dinner, carry supper over to breakfast
- AI-refined scaling of ingredient quantities to the family, with a fallback to computed quantities
- Shopping list with summed quantities, g → kg and ml → l above 1000, rounded to two decimals
- One PDF with the meal plan, the shopping list and optional recipe pages

### Out of Scope

- Importing recipes from external URLs
- Images or videos
- Sharing recipes with other users
- Dark mode

## 8. Project Status

Version 1.1.0 is released and runs in production. Changes are listed in [`CHANGELOG.md`](CHANGELOG.md), and the milestones in [`context/foundation/roadmap.md`](context/foundation/roadmap.md).

## 9. License

This project is licensed under the **MIT License**. See the [`LICENSE`](LICENSE) file for details.
