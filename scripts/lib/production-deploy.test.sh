#!/usr/bin/env bash
set -euo pipefail

TEST_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=production-deploy.sh disable=SC1091
source "$TEST_DIR/production-deploy.sh"

fail=0
pass() { echo "PASS: $1"; }
failed() {
  echo "FAIL: $1" >&2
  fail=1
}

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

write_complete_env() { # file
  cat >"$1" <<'ENV'
APP_ENV=production
APP_KEY=base64:abc
APP_DEBUG=false
DB_PASSWORD=secret
MAIL_HOST=smtp.example.com
MAIL_FROM_ADDRESS="noreply@example.com"
LOG_ALERT_MAIL_TO=ops@example.com
BACKUP_ARCHIVE_PASSWORD=pw
BACKUP_NOTIFICATION_MAIL=ops@example.com
INERTIA_SSR_ENABLED=false
AI_SCALING_DRIVER=openrouter
OPENROUTER_API_KEY=key
OPENROUTER_MODEL=model
ANTHROPIC_API_KEY=
ENV
}

# expect_refused name key-expected-on-stderr override-line...
expect_refused() {
  local name="$1" key="$2"
  shift 2
  local file="$tmp/case.env" err
  write_complete_env "$file"
  printf '%s\n' "$@" >>"$file"
  if err="$( (production_assert_env_ready "$file") 2>&1 >/dev/null)"; then
    failed "$name (expected refusal)"
  elif [[ "$err" != *"$key"* ]]; then
    failed "$name (stderr lacks $key: $err)"
  else
    pass "$name"
  fi
}

expect_accepted() {
  local name="$1" file="$2"
  if (production_assert_env_ready "$file") >/dev/null 2>&1; then pass "$name"; else failed "$name"; fi
}

complete="$tmp/complete.env"
write_complete_env "$complete"
expect_accepted "complete env passes" "$complete"

for key in APP_KEY DB_PASSWORD MAIL_HOST MAIL_FROM_ADDRESS LOG_ALERT_MAIL_TO BACKUP_ARCHIVE_PASSWORD BACKUP_NOTIFICATION_MAIL; do
  expect_refused "blank $key refused" "$key" "$key="
done

expect_refused "APP_ENV=local refused" APP_ENV "APP_ENV=local"
expect_refused "APP_DEBUG=true refused" APP_DEBUG "APP_DEBUG=true"
expect_refused "SSR enabled refused" INERTIA_SSR_ENABLED "INERTIA_SSR_ENABLED=true"
expect_refused "fake driver refused" AI_SCALING_DRIVER "AI_SCALING_DRIVER=fake"
expect_refused "openrouter without model refused" OPENROUTER_MODEL "OPENROUTER_MODEL="
expect_refused "openrouter without key refused" OPENROUTER_API_KEY "OPENROUTER_API_KEY="
expect_refused "anthropic without key refused" ANTHROPIC_API_KEY "AI_SCALING_DRIVER=anthropic"

anthropic="$tmp/anthropic.env"
write_complete_env "$anthropic"
printf '%s\n' "AI_SCALING_DRIVER=anthropic" "ANTHROPIC_API_KEY=key" >>"$anthropic"
expect_accepted "anthropic with key passes" "$anthropic"

quoted="$tmp/quoted.env"
write_complete_env "$quoted"
printf '%s\n' 'APP_KEY="base64:quoted"' "DB_PASSWORD='s3cret'" >>"$quoted"
expect_accepted "quoted values pass" "$quoted"

dup="$tmp/dup.env"
write_complete_env "$dup"
printf '%s\n' "DB_PASSWORD=" "DB_PASSWORD=last-wins" >>"$dup"
expect_accepted "duplicated key, last wins" "$dup"

dup_blank="$tmp/dup-blank.env"
write_complete_env "$dup_blank"
printf '%s\n' "DB_PASSWORD=first" "DB_PASSWORD=" >>"$dup_blank"
if (production_assert_env_ready "$dup_blank") >/dev/null 2>&1; then
  failed "duplicated key, blank last refused"
else
  pass "duplicated key, blank last refused"
fi

# --- production_assert_branch ---
repo="$tmp/repo"
git init -q -b develop "$repo"
git -C "$repo" -c user.name=t -c user.email=t@example.com commit -q --allow-empty -m init
if (cd "$repo" && production_assert_branch) >/dev/null 2>&1; then
  failed "assert_branch refuses develop"
else
  pass "assert_branch refuses develop"
fi
git -C "$repo" checkout -q -b main
if (cd "$repo" && production_assert_branch) >/dev/null 2>&1; then
  pass "assert_branch accepts main"
else
  failed "assert_branch accepts main"
fi

exit "$fail"
