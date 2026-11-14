#!/usr/bin/env bash

PRODUCTION_BRANCH="${PRODUCTION_BRANCH:-main}"

production_assert_branch() {
    local current_branch
    current_branch="$(git rev-parse --abbrev-ref HEAD)"

    if [[ "${current_branch}" != "${PRODUCTION_BRANCH}" ]]; then
        echo "ERROR: Production deploy must run on branch '${PRODUCTION_BRANCH}', currently on '${current_branch}'." >&2
        echo "Run: git checkout ${PRODUCTION_BRANCH}" >&2
        exit 1
    fi
}

production_pull_latest() {
    echo "==> Fetching and pulling ${PRODUCTION_BRANCH}..."
    git fetch origin "${PRODUCTION_BRANCH}"
    production_assert_branch
    git pull --ff-only origin "${PRODUCTION_BRANCH}"
}

# Reads the last occurrence of KEY= in an env file without sourcing it,
# stripping surrounding single or double quotes from the value.
_production_env_value() {
    local env_file="$1" key="$2"
    local line value

    line="$( { grep -E "^${key}=" "${env_file}" || true; } | tail -n1)"
    value="${line#*=}"

    if [[ "${value}" =~ ^\"(.*)\"$ ]]; then
        value="${BASH_REMATCH[1]}"
    elif [[ "${value}" =~ ^\'(.*)\'$ ]]; then
        value="${BASH_REMATCH[1]}"
    fi

    printf '%s' "${value}"
}

_production_is_blank() {
    local value="$1"
    local lower="${value,,}"

    [[ -z "${value}" || "${lower}" == "null" ]]
}

_production_is_truthy() {
    local value="$1"
    local lower="${value,,}"

    case "${lower}" in
        true | 1 | yes | on)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

_production_is_falsy() {
    local value="$1"
    local lower="${value,,}"

    case "${lower}" in
        false | 0 | no | off)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

_production_require_value() {
    local env_file="$1" key="$2" value

    value="$(_production_env_value "${env_file}" "${key}")"
    if _production_is_blank "${value}"; then
        echo "ERROR: ${key} is empty in ${env_file}. Set it before deploying." >&2
        exit 1
    fi
}

production_assert_env_ready() {
    local env_file="$1"
    local key value driver

    for key in APP_KEY DB_PASSWORD MAIL_HOST MAIL_FROM_ADDRESS LOG_ALERT_MAIL_TO BACKUP_ARCHIVE_PASSWORD BACKUP_NOTIFICATION_MAIL; do
        _production_require_value "${env_file}" "${key}"
    done

    value="$(_production_env_value "${env_file}" "APP_ENV")"
    if [[ "${value}" != "production" ]]; then
        echo "ERROR: APP_ENV must be 'production' in ${env_file}, got '${value}'." >&2
        exit 1
    fi

    value="$(_production_env_value "${env_file}" "APP_DEBUG")"
    if _production_is_truthy "${value}"; then
        echo "ERROR: APP_DEBUG must be false in ${env_file}." >&2
        exit 1
    fi

    value="$(_production_env_value "${env_file}" "INERTIA_SSR_ENABLED")"
    if ! _production_is_falsy "${value}"; then
        echo "ERROR: INERTIA_SSR_ENABLED must be false in ${env_file} (the production image has no SSR bundle)." >&2
        exit 1
    fi

    driver="$(_production_env_value "${env_file}" "AI_SCALING_DRIVER")"
    case "${driver}" in
        openrouter)
            _production_require_value "${env_file}" OPENROUTER_API_KEY
            _production_require_value "${env_file}" OPENROUTER_MODEL
            ;;
        anthropic)
            _production_require_value "${env_file}" ANTHROPIC_API_KEY
            ;;
        *)
            echo "ERROR: AI_SCALING_DRIVER must be 'openrouter' or 'anthropic' in ${env_file}, got '${driver}'." >&2
            exit 1
            ;;
    esac
}

# Before `up -d`: the OLD container still holds the old code and schema. `artisan down`
# goes first (the maintenance file lives in the bind-mounted storage and survives
# `up -d`), then a DB-only backup. Only an absent app container counts as a first
# deployment; a stopped one stops the deployment unless DEPLOY_WITHOUT_BACKUP=1.
# Sets MAINTENANCE_STARTED=1 when it put the application in maintenance mode.
production_prepare_running_instance() {
    local running_app any_app

    running_app="$(${COMPOSE:?} ps --status running --quiet app)"

    if [[ -z "${running_app}" ]]; then
        any_app="$(${COMPOSE} ps --all --quiet app)"

        if [[ -n "${any_app}" && "${DEPLOY_WITHOUT_BACKUP:-0}" != "1" ]]; then
            echo "ERROR: The app container exists but is not running, so the pre-deploy backup cannot run." >&2
            echo "Start it (${COMPOSE} start app) and run scripts/deploy.sh again, or set DEPLOY_WITHOUT_BACKUP=1 to deploy without a backup." >&2
            exit 1
        fi

        echo "==> No running app container (first deployment or DEPLOY_WITHOUT_BACKUP=1): skipping artisan down and backup."
        return 0
    fi

    echo "==> Entering maintenance mode in the old container..."
    $COMPOSE exec -T -u sail app php artisan down
    MAINTENANCE_STARTED=1

    echo "==> Backing up the database..."
    $COMPOSE exec -T -u sail app php artisan backup:run --only-db --disable-notifications
}

# After `up -d` on a first deployment there was no old container to put in maintenance
# mode, so do it in the new one right away.
production_ensure_maintenance_mode() {
    if [[ "${MAINTENANCE_STARTED:-0}" == "1" ]]; then
        return 0
    fi

    echo "==> Entering maintenance mode in the new container..."
    $COMPOSE exec -T -u sail app php artisan down
    MAINTENANCE_STARTED=1
}

production_leave_maintenance_mode() {
    echo "==> Leaving maintenance mode..."
    $COMPOSE exec -T -u sail app php artisan up
    MAINTENANCE_STARTED=0
}

# Image files are owned by uid 1337, the runtime user is WWWUSER, and the bind-mounted
# storage and backup dir keep their host owner. Fix it as root in the running container.
production_prepare_storage() {
    local compose_cmd="$1"

    echo "==> Preparing storage permissions..."
    ${compose_cmd} exec -T -u root app sh -c 'mkdir -p storage/fonts storage/logs storage/app/backups storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache && chown -R sail:sail storage bootstrap/cache'
}

# EXIT trap: a failed deployment keeps the application in maintenance mode on purpose.
production_report_failure() {
    local status=$?

    if [[ ${status} -ne 0 && "${MAINTENANCE_STARTED:-0}" == "1" ]]; then
        echo "ERROR: Deployment failed. The application STAYS in maintenance mode." >&2
        echo "Do not run scripts/deploy.sh again before reading the recovery section of docs/menu-maker.md in the kostecki-dev-infra repo." >&2
    fi
}
