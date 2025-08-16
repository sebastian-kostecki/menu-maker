# Menu Maker

An AI-powered web application that helps families effortlessly plan weekly meals, accurately scale recipe ingredients, and generate a single PDF containing both the meal plan and a consolidated shopping list.

![MIT License](https://img.shields.io/badge/license-MIT-blue.svg)
![Status](https://img.shields.io/badge/status-in%20development-yellow.svg)

## Table of Contents

- [Menu Maker](#menu-maker)
  - [Table of Contents](#table-of-contents)
  - [1. Project Description](#1-project-description)
  - [2. Tech Stack](#2-tech-stack)
  - [3. Getting Started Locally](#3-getting-started-locally)
    - [Prerequisites](#prerequisites)
    - [Setup](#setup)
  - [4. Available Scripts](#4-available-scripts)
    - [NPM](#npm)
    - [Composer / Artisan](#composer--artisan)
  - [5. Testing \& Quality](#5-testing--quality)
    - [Backend (Pest via Sail)](#backend-pest-via-sail)
    - [Frontend (Vitest) \& E2E (Playwright)](#frontend-vitest--e2e-playwright)
  - [5. Project Scope](#5-project-scope)
    - [Included in MVP](#included-in-mvp)
    - [Out of Scope for MVP](#out-of-scope-for-mvp)
  - [7. Project Status](#7-project-status)
  - [8. License](#8-license)

## 1. Project Description

Menu Maker automates family meal planning by letting users save their own recipes, store an ingredient dictionary, and instantly produce a seven-day meal plan (three meals per day). The system leverages artificial intelligence to scale ingredient quantities based on recipe calories, serving size, and each family member’s profile (age and gender). It outputs a single PDF that first lists the meal plan and then a consolidated, unit-converted shopping list.

## 2. Tech Stack

**Backend**

- PHP 8.2+
- Laravel 12.0+
- Inertia.js Laravel 2.0+
- MySQL
- Redis

**Frontend**

- Node.js 22
- Vue.js 3.4+
- Inertia.js Vue3 2.0+
- TailwindCSS 3.2.1+
- Shadcn-vue 2.2.0+

**Tooling & Testing**

- Vite 7
- Laravel Sail (Docker environment)
- Laravel Pint (code style)
- Larastan (static analysis)
- Pest (test runner) + PHPUnit

## 3. Getting Started Locally

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 22 & npm
- Docker & Docker Compose (for Laravel Sail)

### Setup

```bash
# 1. Clone the repository
$ git clone <repository-url>
$ cd menu-maker

# 2. Install PHP dependencies
$ composer install

# 3. Install JavaScript dependencies
$ npm ci

# 4. Configure environment
$ cp .env.example .env

# 5. Start the Docker environment via Sail (recommended)
$ ./vendor/bin/sail up -d

# 6. Generate application key (inside Sail)
$ ./vendor/bin/sail artisan key:generate

# 7. Run database migrations & seeders (inside Sail)
$ ./vendor/bin/sail artisan migrate --seed

# 8. Start development servers
$ npm run dev            # Vite dev server with HMR
# If needed, you can also start Laravel's server inside Sail:
$ ./vendor/bin/sail artisan serve
```

## 4. Available Scripts

### NPM

| Command             | Description                                |
| ------------------- | ------------------------------------------ |
| `npm run dev`       | Start the Vite development server with HMR |
| `npm run build`     | Compile and bundle assets for production   |
| `npm run test:unit` | Run Vue unit tests with Vitest             |
| `npm run test:e2e`  | Run E2E tests with Playwright              |
| `npm run lint`      | Lint frontend code (ESLint/Prettier)       |

### Composer / Artisan

| Command                     | Description                                                          |
| --------------------------- | -------------------------------------------------------------------- |
| `composer dev`              | Convenience script: web server, queue listener, logs & Vite together |
| `composer test`             | Clear config cache and run the PHPUnit test suite                    |
| `composer pint`             | Fix code style issues using Laravel Pint                             |
| `composer pint-test`        | Check code style without fixing                                      |
| `composer phpstan`          | Run static analysis with Larastan                                    |
| `composer phpstan-baseline` | Generate baseline for static analysis                                |
| `composer phpstan-verbose`  | Static analysis with verbose output                                  |

Note: For backend commands, prefer running them via Sail:

```bash
./vendor/bin/sail composer test
./vendor/bin/sail composer pint-test
./vendor/bin/sail composer phpstan
```

## 5. Testing & Quality

### Backend (Pest via Sail)

```bash
# Start containers
./vendor/bin/sail up -d

# Run test suite (Pest)
./vendor/bin/sail composer test

# Code style (Laravel Pint)
./vendor/bin/sail composer pint-test

# Static analysis (Larastan)
./vendor/bin/sail composer phpstan
```

### Frontend (Vitest) & E2E (Playwright)

```bash
# Install dependencies (first time)
npm ci

# Install Playwright browsers/drivers (first time)
npx playwright install --with-deps

# Unit tests (Vue + Vitest)
npm run test:unit

# E2E tests (Playwright)
E2E_BASE_URL=http://localhost npm run test:e2e

# Lint frontend code
npm run lint
```

## 5. Project Scope

### Included in MVP

- Email-based authentication (registration, login, password reset)
- Family profile management (name, birth date, gender)
- Recipe CRUD with mandatory fields (name, category, ingredients, instructions, calories, servings)
- Weekly meal plan generation (7 days × 3 meals) with no duplications and unlimited regenerations
- AI-driven scaling of ingredient quantities per family profile
- Consolidated shopping list with automatic unit conversion (>1000 g → kg, >1000 ml → l) and rounding to two decimals
- Single PDF export containing the meal plan followed by the shopping list

### Out of Scope for MVP

- Importing recipes from external URLs
- Multimedia support (images or videos)
- Sharing recipes with other users / social features
- Back-ups and advanced hosting infrastructure
- Editing generated meal plans or shopping lists

## 7. Project Status

🚧 **MVP development in progress** – see the [project board](https://github.com/<org-or-user>/<repo>/projects/1) for up-to-date milestones.

## 8. License

This project is licensed under the **MIT License**. See the [`LICENSE`](LICENSE) file for details.
