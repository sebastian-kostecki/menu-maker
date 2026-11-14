#!/usr/bin/env bash
# Boot smoke for the production image: builds it, boots the real compose stack and
# checks migrations, health, supervisor programs, caches and backups.
# Runs in CI and locally. Refuses to run next to a real .env.prod.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "${ROOT}"

# shellcheck source=../lib/production-deploy.sh disable=SC1091
source "${ROOT}/scripts/lib/production-deploy.sh"

# Own project name: `down` must never touch a Sail stack of the same checkout.
COMPOSE="docker compose -p menu-maker-smoke --env-file .env.prod -f docker-compose.prod.yml"
ENV_FILE=".env.prod"

if [[ -f "${ENV_FILE}" ]]; then
    echo "ERROR: ${ENV_FILE} already exists. The smoke test only runs where it can create and delete its own throwaway ${ENV_FILE}." >&2
    exit 1
fi

SMOKE_TMP="$(mktemp -d)"
BACKUP_HOST_DIR="${RUNNER_TEMP:-${SMOKE_TMP}}/backups"
export BACKUP_HOST_DIR
STARTED=0

cleanup() {
    local status=$?
    set +e

    if [[ ${status} -ne 0 && "${STARTED}" == "1" ]]; then
        echo "==> Smoke test FAILED (exit ${status}). Container logs:" >&2
        ${COMPOSE} logs --no-color >&2
    fi

    if [[ -f "${ENV_FILE}" ]]; then
        # Backup files are owned by the container user: open them up so the host can delete them.
        ${COMPOSE} exec -T -u root app chmod -R a+rwX storage/app/backups
        ${COMPOSE} down -v
    fi
    rm -f "${ENV_FILE}"
    rm -rf "${BACKUP_HOST_DIR}" "${SMOKE_TMP}"

    exit "${status}"
}
trap cleanup EXIT

# Replaces every KEY= line of the env file with a single KEY=value line.
set_env() {
    local key="$1" value="$2"

    grep -v "^${key}=" "${ENV_FILE}" > "${ENV_FILE}.tmp" || true
    mv "${ENV_FILE}.tmp" "${ENV_FILE}"
    printf '%s=%s\n' "${key}" "${value}" >> "${ENV_FILE}"
}

echo "==> Preparing throwaway ${ENV_FILE}..."
docker network inspect proxy > /dev/null 2>&1 || docker network create proxy > /dev/null
mkdir -p "${BACKUP_HOST_DIR}"

cp .env.prod.example "${ENV_FILE}"
set_env APP_KEY "base64:$(openssl rand -base64 32)"
set_env DB_PASSWORD "$(openssl rand -hex 16)"
set_env BACKUP_ARCHIVE_PASSWORD "$(openssl rand -hex 16)"
set_env BACKUP_HOST_DIR "${BACKUP_HOST_DIR}"
set_env MAIL_MAILER log
set_env MAIL_HOST smoke.invalid
set_env LOG_ALERT_MAIL_TO smoke@example.com
set_env BACKUP_NOTIFICATION_MAIL smoke@example.com
set_env AI_SCALING_DRIVER openrouter
set_env OPENROUTER_API_KEY smoke-dummy-key
set_env OPENROUTER_MODEL smoke/dummy-model
set_env WWWUSER "$(id -u)"
set_env WWWGROUP "$(id -g)"

production_assert_env_ready "${ENV_FILE}"

echo "==> Building and starting the stack..."
STARTED=1
${COMPOSE} up -d --build
production_prepare_storage "${COMPOSE}"

echo "==> Checking migrations..."
${COMPOSE} exec -T -u sail app php artisan migrate --force

echo "==> Checking health endpoint..."
health_ok=0
for _ in $(seq 1 30); do
    if ${COMPOSE} exec -T app curl -fsS http://localhost/up > /dev/null; then
        health_ok=1
        break
    fi
    sleep 2
done
if [[ "${health_ok}" != "1" ]]; then
    echo "ERROR: /up did not return 200." >&2
    exit 1
fi

echo "==> Checking supervisor programs after 15 s..."
sleep 15
supervisor_status="$(${COMPOSE} exec -T app supervisorctl status || true)"
echo "${supervisor_status}"
for program in php queue scheduler; do
    if ! grep -E "^${program}[: ].*RUNNING" <<< "${supervisor_status}" > /dev/null; then
        echo "ERROR: supervisor program '${program}' is not RUNNING." >&2
        exit 1
    fi
done

echo "==> Checking config and route caching..."
${COMPOSE} exec -T -u sail app php artisan config:cache
${COMPOSE} exec -T -u sail app php artisan route:cache

echo "==> Checking database backup..."
${COMPOSE} exec -T -u sail app php artisan backup:run --only-db --disable-notifications
if ! ${COMPOSE} exec -T app find storage/app/backups -name '*.zip' | grep -q .; then
    echo "ERROR: no .zip backup found under ${BACKUP_HOST_DIR}." >&2
    exit 1
fi

echo "==> Production smoke test passed."
