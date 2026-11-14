#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "${ROOT}"

COMPOSE="docker compose --env-file .env.prod -f docker-compose.prod.yml"

if [[ ! -f .env.prod ]]; then
    echo "ERROR: .env.prod is missing. Run: cp .env.prod.example .env.prod" >&2
    echo "Then fill APP_KEY, DB_PASSWORD, mail, AI and backup values." >&2
    exit 1
fi

if [[ $# -eq 0 ]]; then
    echo "Usage: $0 <artisan-command> [args...]" >&2
    echo "Example: $0 backup:run --only-db" >&2
    exit 1
fi

exec_flags=(-T)
if [[ -t 0 && -t 1 ]]; then
    exec_flags=()
fi

$COMPOSE exec "${exec_flags[@]}" app php artisan "$@"
