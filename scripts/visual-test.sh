#!/usr/bin/env bash
# Runs the Playwright `visual` project in the version-pinned official Docker image,
# so screenshots render identically on WSL/Sail and in GitHub Actions.
# Usage: scripts/visual-test.sh [playwright args...]   (e.g. -- --update-snapshots)
# Env:   VISUAL_DOCKER_NETWORK (default menu-maker_sail; CI uses host)
#        VISUAL_BASE_URL       (default http://laravel.test; CI uses http://127.0.0.1:8000)
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
network="${VISUAL_DOCKER_NETWORK:-menu-maker_sail}"
base_url="${VISUAL_BASE_URL:-http://laravel.test}"

if [[ -e "$ROOT_DIR/public/hot" ]]; then
  echo "visual-test: public/hot exists (npm run dev is running). Stop it, delete public/hot and run 'vendor/bin/sail npm run build'." >&2
  exit 1
fi

if [[ ! -d "$ROOT_DIR/node_modules" ]]; then
  echo "visual-test: node_modules is missing. Run 'npm ci' first." >&2
  exit 1
fi

if ! docker network inspect "$network" >/dev/null 2>&1; then
  echo "visual-test: docker network '$network' does not exist. Run 'vendor/bin/sail up -d' (or set VISUAL_DOCKER_NETWORK)." >&2
  exit 1
fi

version="$(sed -n 's/^[[:space:]]*"version":[[:space:]]*"\([^"]*\)".*/\1/p' "$ROOT_DIR/node_modules/@playwright/test/package.json" | head -n 1)"
if [[ -z "$version" ]]; then
  echo "visual-test: cannot read the @playwright/test version from node_modules." >&2
  exit 1
fi

image="mcr.microsoft.com/playwright:v${version}-noble"
echo "visual-test: image $image, network $network, base URL $base_url"

exec docker run --rm \
  --ipc=host \
  --network "$network" \
  --user "$(id -u):$(id -g)" \
  -e HOME=/tmp \
  -e E2E_SKIP_WEBSERVER=1 \
  -e PLAYWRIGHT_VISUAL_DOCKER=1 \
  -e CI \
  -e E2E_BASE_URL="$base_url" \
  -v "$ROOT_DIR:/work" \
  -w /work \
  "$image" \
  npx playwright test --project=visual "$@"
