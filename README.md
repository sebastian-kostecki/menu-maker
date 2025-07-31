# Menu Maker

An AI-powered web application that helps families effortlessly plan weekly meals, accurately scale recipe ingredients, and generate a single PDF containing both the meal plan and a consolidated shopping list.

![MIT License](https://img.shields.io/badge/license-MIT-blue.svg)
![Status](https://img.shields.io/badge/status-in%20development-yellow.svg)

## Table of Contents

1. [Project Description](#1-project-description)
2. [Tech Stack](#2-tech-stack)
3. [Getting Started Locally](#3-getting-started-locally)
4. [Available Scripts](#4-available-scripts)
5. [Project Scope](#5-project-scope)
6. [Project Status](#6-project-status)
7. [License](#7-license)

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
- PHPUnit (test runner)

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
$ npm install

# 4. Configure environment
$ cp .env.example .env
$ php artisan key:generate

# 5. (Optional) start the Docker environment via Sail
$ ./vendor/bin/sail up -d

# 6. Run database migrations & seeders
$ php artisan migrate --seed

# 7. Start development servers
$ npm run dev           # Vite dev server with HMR
$ php artisan serve      # Or use Sail: ./vendor/bin/sail artisan serve
```

## 4. Available Scripts

### NPM

| Command         | Description                                |
| --------------- | ------------------------------------------ |
| `npm run dev`   | Start the Vite development server with HMR |
| `npm run build` | Compile and bundle assets for production   |

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

## 6. Project Status

🚧 **MVP development in progress** – see the [project board](https://github.com/<org-or-user>/<repo>/projects/1) for up-to-date milestones.

## 7. License

This project is licensed under the **MIT License**. See the [`LICENSE`](LICENSE) file for details.
