#!/usr/bin/env bash
# Hook PreToolUse/Bash : à la création d'une branche, impose la convention Angular
# `type/description` où type ∈ build|ci|docs|feat|fix|perf|refactor|style|test|chore|revert.
# Règle repo — voir CLAUDE.md § Conventions Git > Branches.
set -euo pipefail

cmd="$(jq -r '.tool_input.command // ""')"

# Extrait le nom de la nouvelle branche selon la forme de la commande.
name=""
if [[ "$cmd" =~ git[[:space:]]+checkout[[:space:]]+-b[[:space:]]+([^[:space:]]+) ]]; then
  name="${BASH_REMATCH[1]}"
elif [[ "$cmd" =~ git[[:space:]]+switch[[:space:]]+-[cC][[:space:]]+([^[:space:]]+) ]]; then
  name="${BASH_REMATCH[1]}"
elif [[ "$cmd" =~ git[[:space:]]+branch[[:space:]]+([^-][^[:space:]]*) ]]; then
  name="${BASH_REMATCH[1]}"
fi

# Pas une commande de création de branche → laisser passer.
[ -z "$name" ] && exit 0

if ! printf '%s' "$name" | grep -qE '^(build|ci|docs|feat|fix|perf|refactor|style|test|chore|revert)/[a-z0-9._/-]+$'; then
  cat <<JSON
{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"deny","permissionDecisionReason":"Nom de branche « $name » non conforme à la convention Angular (CLAUDE.md). Utilise type/description, ex. feat/39-recherche-isnad — type ∈ build|ci|docs|feat|fix|perf|refactor|style|test|chore|revert."}}
JSON
  exit 0
fi

exit 0
