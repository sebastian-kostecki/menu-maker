#!/usr/bin/env bash
# Flags Tailwind palette classes, hex colours, arbitrary values and dark: variants
# in the paths listed in scripts/lint-tokens.paths (design-system §3, rule 1).
# Usage: scripts/lint-tokens.sh [paths-file]
# Exit: 0 no hits, 1 hits found, 2 configuration error.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
paths_file="${1:-$SCRIPT_DIR/lint-tokens.paths}"

if [[ ! -f "$paths_file" ]]; then
  echo "lint:tokens: paths file not found: $paths_file" >&2
  exit 2
fi

kinds=(palette hex arbitrary dark)
patterns=(
  '(?<![\w-])(?:bg|text|border(?:-[xytrblse])?|ring(?:-offset)?|outline|divide|from|via|to|fill|stroke|shadow|inset-shadow|inset-ring|accent|caret|decoration|placeholder)-(?:(?:slate|gray|zinc|neutral|stone|mauve|olive|mist|taupe|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}|white|black)(?![\w-])'
  '(?<![\w&])#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})(?![\w-])'
  '(?<=[\w)])-\[[^\]\s]+\](?!:)'
  '(?<![\w-])dark:'
)

entries=()
while IFS= read -r line || [[ -n "$line" ]]; do
  line="${line%%#*}"
  line="${line#"${line%%[![:space:]]*}"}"
  line="${line%"${line##*[![:space:]]}"}"
  [[ -n "$line" ]] && entries+=("$line")
done <"$paths_file"

if ((${#entries[@]} == 0)); then
  echo "lint:tokens: no paths listed in $paths_file (add at least one path)" >&2
  exit 2
fi

for entry in "${entries[@]}"; do
  if [[ ! -e "$entry" ]]; then
    echo "lint:tokens: listed path does not exist: $entry (check $paths_file)" >&2
    exit 2
  fi
done

files=()
while IFS= read -r -d '' file; do
  [[ "$file" == *"/Components/ui/"* ]] && continue
  files+=("$file")
done < <(find "${entries[@]}" -type f \( -name '*.vue' -o -name '*.js' -o -name '*.ts' \) -print0 | sort -z)

hits=0
for file in "${files[@]}"; do
  for i in "${!kinds[@]}"; do
    while IFS= read -r hit; do
      [[ -z "$hit" ]] && continue
      echo "$file:${hit%%:*}: ${kinds[$i]}: ${hit#*:}"
      hits=$((hits + 1))
    done < <(grep -noP "${patterns[$i]}" "$file" || true)
  done
done

if ((hits > 0)); then
  echo "lint:tokens: $hits hit(s). Use token utilities (see context/foundation/design-system.md)." >&2
  exit 1
fi
echo "lint:tokens: OK (${#files[@]} files, 0 hits)"
