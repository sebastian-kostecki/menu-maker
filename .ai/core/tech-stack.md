# Tech Stack

## Backend
- **PHP** (^8.2) - Server-side programming language
- **Laravel** (^12.0) - PHP web application framework
- **Inertia.js Laravel** (^2.0) - Server-side adapter for SPA-like experience
- **PostgreSQL 18** - Relational database management system (Sail)

## Frontend
- **Node.js** (22) - JavaScript runtime for building and development tools
- **Vue.js** (^3.4.0) - Progressive JavaScript framework
- **Inertia.js Vue3** (^2.0.0) - Client-side adapter for SPA-like experience
- **TailwindCSS** (^3.2.1) - Utility-first CSS framework
- **Shadcn-vue** (^2.2.0) - Vue component library

## Tools
- **Laravel Pint** (^1.13) - Code style fixer
- **Laravel Sail** (^1.41) - Docker development environment
- **Larastan** (^3.6) - Static analysis tool for Laravel
- **PHPUnit** (^11.5.3) - Testing framework

## Komunikacja z AI
- **Openrouter.ai** - dostęp do wielu modeli (sterownik `openrouter`, klucz `OPENROUTER_API_KEY`, model `OPENROUTER_MODEL`)
- **Anthropic API** (`anthropic-ai/sdk`) - sterownik `anthropic` (`ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`, `ANTHROPIC_EFFORT`)
- Wybór sterownika do zaokrąglania ilości składników: `AI_SCALING_DRIVER` = `openrouter`, `anthropic` albo `fake` (testy, CI i E2E używają `fake`).
- Subskrypcja Claude (Pro/Max) nie może obsługiwać wdrożonej aplikacji; produkcja wymaga klucza API (OpenRouter lub konsola Anthropic).

## CI/CD i Hosting:
- **Github Actions** do tworzenia pipeline’ów CI/CD
- **Mikrus** (VPS) do hostowania aplikacji; konfiguracja serwera i wdrożenia w osobnym repo `kostecki-infra`
