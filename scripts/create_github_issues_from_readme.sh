#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
README_SOURCE="${README_SOURCE:-$ROOT_DIR/README_ISSUES_RESTANTES.md}"
REPO="${REPO:-corbidev/corbidev-logs}"
PROJECT_OWNER="${PROJECT_OWNER:-corbidev}"
PROJECT_NUMBER="${PROJECT_NUMBER:-1}"
DRY_RUN="${DRY_RUN:-0}"

is_dry_run() {
  case "$DRY_RUN" in
    1|true|TRUE|yes|YES) return 0 ;;
    *) return 1 ;;
  esac
}

if ! is_dry_run; then
  command -v gh >/dev/null 2>&1 || {
    echo "gh n'est pas installé ou n'est pas dans le PATH." >&2
    exit 1
  }
fi

current_code=""
current_title=""
current_state=""
labels=()
depends_on=()
body_lines=()
acceptance_lines=()
issues_count=0
PROJECT_ID=""

reset_issue() {
  current_code=""
  current_title=""
  current_state=""
  labels=()
  depends_on=()
  body_lines=()
  acceptance_lines=()
}

build_issue_body() {
  local body
  body="Code: $current_code"

  if ((${#depends_on[@]} > 0)); then
    body+=$'\n\nDépendances:'
    local dependency
    for dependency in "${depends_on[@]}"; do
      body+=$'\n- '
      body+="$dependency"
    done
  fi

  if ((${#body_lines[@]} > 0)); then
    body+=$'\n\nObjectif:'
    local item
    for item in "${body_lines[@]}"; do
      body+=$'\n- '
      body+="$item"
    done
  fi

  if ((${#acceptance_lines[@]} > 0)); then
    body+=$'\n\nCritères de fin:'
    local item
    for item in "${acceptance_lines[@]}"; do
      body+=$'\n- '
      body+="$item"
    done
  fi

  printf '%s' "$body"
}

get_project_id() {
  gh api graphql \
    -f owner="$PROJECT_OWNER" \
    -F number="$PROJECT_NUMBER" \
    -f query='query($owner:String!, $number:Int!) { user(login: $owner) { projectV2(number: $number) { id } } }' \
    --jq '.data.user.projectV2.id'
}

add_issue_to_project() {
  local project_id="$1"
  local issue_id="$2"

  gh api graphql \
    -f projectId="$project_id" \
    -f contentId="$issue_id" \
    -f query='mutation($projectId:ID!, $contentId:ID!) { addProjectV2ItemById(input: {projectId: $projectId, contentId: $contentId}) { item { id } } }' >/dev/null
}

label_color_for() {
  local label="$1"
  case "$label" in
    p0) echo "B60205" ;;
    p1) echo "D93F0B" ;;
    p2) echo "FBCA04" ;;
    tests) echo "0E8A16" ;;
    docs) echo "0052CC" ;;
    auth) echo "5319E7" ;;
    api) echo "1D76DB" ;;
    queue) echo "C2E0C6" ;;
    persistence) echo "BFD4F2" ;;
    dashboard) echo "F9D0C4" ;;
    search) echo "FEF2C0" ;;
    project) echo "D4C5F9" ;;
    ingestion) echo "C5DEF5" ;;
    *) echo "A2EEEF" ;;
  esac
}

ensure_labels_exist() {
  local label color
  for label in "$@"; do
    color="$(label_color_for "$label")"
    gh label create "$label" \
      --repo "$REPO" \
      --color "$color" \
      --description "Auto-created by issue bootstrap script" \
      --force >/dev/null
  done
}

process_current_issue() {
  if [[ -z "$current_code" ]]; then
    return
  fi

  issues_count=$((issues_count + 1))

  if is_dry_run; then
    local labels_joined
    if ((${#labels[@]} > 0)); then
      labels_joined="$(IFS=, ; echo "${labels[*]}")"
    else
      labels_joined="(aucun label)"
    fi
    printf -- '- %s: %s [%s]\n' "$current_code" "$current_title" "$labels_joined"
    return
  fi

  local body label_joined issue_url issue_number issue_id
  body="$(build_issue_body)"

  if ((${#labels[@]} > 0)); then
    ensure_labels_exist "${labels[@]}"
    label_joined="$(IFS=, ; echo "${labels[*]}")"
    issue_url="$(gh issue create --repo "$REPO" --title "$current_title" --body "$body" --label "$label_joined")"
  else
    issue_url="$(gh issue create --repo "$REPO" --title "$current_title" --body "$body")"
  fi

  issue_number="$(printf '%s' "$issue_url" | sed -n 's#.*/issues/\([0-9][0-9]*\).*#\1#p')"
  if [[ -z "$issue_number" ]]; then
    echo "Impossible d'extraire le numéro d'issue depuis: $issue_url" >&2
    exit 1
  fi

  issue_id="$(gh issue view --repo "$REPO" "$issue_number" --json id --jq '.id')"
  add_issue_to_project "$PROJECT_ID" "$issue_id"
  printf 'Créée et attachée: %s -> %s\n' "$current_code" "$issue_url"
}

if ! is_dry_run; then
  PROJECT_ID="$(get_project_id)"
  printf 'Project v2 cible: %s/%s (%s)\n' "$PROJECT_OWNER" "$PROJECT_NUMBER" "$PROJECT_ID"
fi

while IFS= read -r line || [[ -n "$line" ]]; do
  line="${line%$'\r'}"

  if [[ "$line" == '### '* ]]; then
    process_current_issue
    reset_issue
    current_code="${line:4}"
    continue
  fi

  if [[ -z "$current_code" ]]; then
    continue
  fi

  if [[ "$line" == title:* ]]; then
    current_title="${line#title: }"
    current_title="${current_title#\`}"
    current_title="${current_title%\`}"
    continue
  fi

  if [[ "$line" == labels: ]]; then
    current_state="labels"
    continue
  fi

  if [[ "$line" == depends_on:* ]]; then
    current_state="depends"
    depends_tail="${line#depends_on:}"
    depends_tail="${depends_tail# }"
    if [[ -n "$depends_tail" && "$depends_tail" != "none" ]]; then
      depends_tail="${depends_tail//\`/}"
      depends_on+=("$depends_tail")
    fi
    continue
  fi

  if [[ "$line" == body: ]]; then
    current_state="body"
    continue
  fi

  if [[ "$line" == acceptance: ]]; then
    current_state="acceptance"
    continue
  fi

  if [[ -z "$line" ]]; then
    continue
  fi

  if [[ "$line" == '## '* ]]; then
    current_state=""
    continue
  fi

  if [[ "$line" == '- '* ]]; then
    item="${line#- }"
    item="${item//\`/}"
    case "$current_state" in
      labels)
        labels+=("$item")
        ;;
      depends)
        depends_on+=("$item")
        ;;
      body)
        body_lines+=("$item")
        ;;
      acceptance)
        acceptance_lines+=("$item")
        ;;
    esac
  fi
done < "$README_SOURCE"

process_current_issue

if is_dry_run; then
  printf 'DRY RUN terminé: %d issues analysées depuis %s\n' "$issues_count" "$README_SOURCE"
fi
