#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "${ROOT}"

# shellcheck source=lib/production-deploy.sh disable=SC1091
source "$(dirname "$0")/lib/production-deploy.sh"

COMPOSE="docker compose --env-file .env.prod -f docker-compose.prod.yml"

if [[ ! -f .env.prod ]]; then
    echo "ERROR: .env.prod is missing. Run: cp .env.prod.example .env.prod" >&2
    echo "Then fill APP_KEY, DB_PASSWORD, mail, AI and backup values." >&2
    exit 1
fi

production_assert_env_ready .env.prod
production_assert_env_readable_by_app .env.prod

production_pull_latest

echo "==> Building app image..."
$COMPOSE build app

if [[ -z "${BACKUP_HOST_DIR:-}" ]]; then
    BACKUP_HOST_DIR="$(_production_env_value .env.prod BACKUP_HOST_DIR)"
fi
BACKUP_HOST_DIR="${BACKUP_HOST_DIR:-/storage/menu-maker-backups}"
export BACKUP_HOST_DIR

# Ownership is left alone: production_prepare_storage hands the directory to the app user
# after `up -d`. Changing it to the operator here would break the pre-deploy backup,
# which runs as the app user in the old container.
echo "==> Ensuring backup directory exists..."
sudo mkdir -p "$BACKUP_HOST_DIR"

# shellcheck disable=SC2034 # read by the sourced production_* functions
MAINTENANCE_STARTED=0
trap production_report_failure EXIT

production_prepare_running_instance

echo "==> Starting stack..."
$COMPOSE up -d

production_prepare_storage "$COMPOSE"

production_ensure_maintenance_mode

echo "==> Running migrations..."
$COMPOSE exec -T -u sail app php artisan migrate --force

echo "==> Discovering packages and caching..."
$COMPOSE exec -T -u sail app php artisan package:discover --ansi
$COMPOSE exec -T -u sail app php artisan config:cache
$COMPOSE exec -T -u sail app php artisan route:cache
$COMPOSE exec -T -u sail app php artisan view:cache

echo "==> Restarting queue workers..."
$COMPOSE exec -T -u sail app php artisan queue:restart

production_leave_maintenance_mode

echo "==> Health check..."
$COMPOSE exec -T app curl -fsS http://localhost/up

echo
echo "Done. Check https://menu.kostecki.dev/up"
