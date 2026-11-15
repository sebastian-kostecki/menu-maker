#!/usr/bin/env bash
set -euo pipefail

TEST_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCANNER="$TEST_DIR/lint-tokens.sh"

fail=0
pass() { echo "PASS: $1"; }
failed() {
  echo "FAIL: $1" >&2
  fail=1
}

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
cd "$tmp"

mkdir -p src/Components/ui
printf 'src\n' >paths.txt

# scan: run the scanner on a fixture file containing "$1"; sets out and status
scan() {
  printf '%s\n' "$1" >src/fixture.vue
  status=0
  out="$(bash "$SCANNER" paths.txt 2>&1)" || status=$?
}

expect_flagged() { # kind snippet
  scan "$2"
  if ((status == 1)) && [[ "$out" == *"fixture.vue:1: $1: "* ]]; then
    pass "flags $1: $2"
  else
    failed "flags $1: $2 (status $status: $out)"
  fi
}

expect_clean() { # snippet
  scan "$1"
  if ((status == 0)); then pass "allows: $1"; else failed "allows: $1 (status $status: $out)"; fi
}

expect_flagged palette '<div class="bg-gray-100">'
expect_flagged palette '<div class="hover:text-red-600">'
expect_flagged palette '<div class="bg-white/80">'
expect_flagged palette '<div class="border-b-slate-200">'
expect_flagged palette '<div class="text-black">'
expect_flagged hex 'const c = "#fff"'
expect_flagged hex 'const c = "#FF2D20"'
expect_flagged hex 'const c = "#11223344"'
expect_flagged arbitrary '<div class="w-[120px]">'
expect_flagged arbitrary '<div class="grid-cols-[1fr_2fr]">'
expect_flagged dark '<div class="dark:bg-card">'

expect_clean '<div class="bg-primary/80">'
expect_clean '<div class="text-muted-foreground">'
expect_clean '<div class="bg-meal-breakfast">'
expect_clean '<div class="data-[state=open]:bg-accent">'
expect_clean '<div class="group-data-[state=open]/x:bg-accent">'
expect_clean '<div class="[&_svg]:size-4">'
expect_clean '<template #default>'
expect_clean '<p>&#123;</p>'

rm -f src/fixture.vue
printf '<div class="bg-gray-100">\n' >src/Components/ui/x.vue
status=0
out="$(bash "$SCANNER" paths.txt 2>&1)" || status=$?
if ((status == 0)); then pass "skips Components/ui"; else failed "skips Components/ui (status $status: $out)"; fi

printf 'src\nmissing/dir\n' >paths.txt
status=0
out="$(bash "$SCANNER" paths.txt 2>&1)" || status=$?
if ((status == 2)) && [[ "$out" == *"missing/dir"* ]]; then pass "missing path exits 2"; else failed "missing path exits 2 (status $status: $out)"; fi

printf '# only a comment\n\n' >paths.txt
status=0
out="$(bash "$SCANNER" paths.txt 2>&1)" || status=$?
if ((status == 2)); then pass "empty list exits 2"; else failed "empty list exits 2 (status $status: $out)"; fi

status=0
out="$(bash "$SCANNER" nope.txt 2>&1)" || status=$?
if ((status == 2)); then pass "missing paths file exits 2"; else failed "missing paths file exits 2 (status $status: $out)"; fi

exit "$fail"
