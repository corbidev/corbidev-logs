#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
README_SOURCE="${README_SOURCE:-$ROOT_DIR/README_ISSUES_RESTANTES.md}"
REPO="${REPO:-corbidev/corbidev-logs}"
STATE="${STATE:-all}"
LIMIT="${LIMIT:-200}"
DRY_RUN="${DRY_RUN:-0}"

is_dry_run() {
  case "$DRY_RUN" in
    1|true|TRUE|yes|YES) return 0 ;;
    *) return 1 ;;
  esac
}

command -v gh >/dev/null 2>&1 || {
  echo "gh n'est pas installé ou n'est pas dans le PATH." >&2
  exit 1
}

if [[ ! -f "$README_SOURCE" ]]; then
  echo "Fichier source introuvable: $README_SOURCE" >&2
  exit 1
fi

declare -A TARGET_TITLE_BY_CODE

current_code=""
while IFS= read -r line || [[ -n "$line" ]]; do
  line="${line%$'\r'}"

  if [[ "$line" == '### '* ]]; then
    current_code="${line:4}"
    continue
  fi

  if [[ -n "$current_code" && "$line" == title:* ]]; then
    title="${line#title: }"
    title="${title#\`}"
    title="${title%\`}"
    TARGET_TITLE_BY_CODE["$current_code"]="$title"
    current_code=""
  fi
done < "$README_SOURCE"

issue_numbers="$(gh issue list --repo "$REPO" --state "$STATE" --limit "$LIMIT" --json number --jq '.[].number')"

if [[ -z "$issue_numbers" ]]; then
  echo "Aucune issue trouvée dans $REPO (state=$STATE, limit=$LIMIT)."
  exit 0
fi

renamed=0
skipped=0
missing_code=0

while IFS= read -r issue_number; do
  [[ -z "$issue_number" ]] && continue

  issue_title="$(gh issue view --repo "$REPO" "$issue_number" --json title --jq '.title')"
  issue_body="$(gh issue view --repo "$REPO" "$issue_number" --json body --jq '.body')"

  issue_code="$(printf '%s\n' "$issue_body" | sed -n 's/^Code:[[:space:]]*\([A-Za-z0-9-]\+\).*/\1/p' | head -n 1)"

  if [[ -z "$issue_code" ]]; then
    missing_code=$((missing_code + 1))
    continue
  fi

  target_title="${TARGET_TITLE_BY_CODE[$issue_code]:-}"
  if [[ -z "$target_title" ]]; then
    skipped=$((skipped + 1))
    continue
  fi

  if [[ "$issue_title" == "$target_title" ]]; then
    skipped=$((skipped + 1))
    continue
  fi

  if is_dry_run; then
    printf '[DRY RUN] #%s %s -> %s\n' "$issue_number" "$issue_title" "$target_title"
  else
    gh issue edit --repo "$REPO" "$issue_number" --title "$target_title" >/dev/null
    printf 'Renommée #%s -> %s\n' "$issue_number" "$target_title"
  fi

  renamed=$((renamed + 1))
done <<< "$issue_numbers"

echo "Terminé. Renommées: $renamed | Ignorées: $skipped | Sans code: $missing_code"

