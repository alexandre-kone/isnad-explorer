#!/usr/bin/env bash
# Hook PreToolUse/Bash : bloque tout `git commit` ou `gh pr create|edit` dont le
# texte contient une attribution Claude (Co-Authored-By / Generated with Claude Code).
# Règle repo — voir CLAUDE.md § Conventions Git.
set -euo pipefail

cmd="$(jq -r '.tool_input.command // ""')"

# Ne s'applique qu'aux commandes de commit / PR.
if printf '%s' "$cmd" | grep -qiE 'git +commit|gh +pr +(create|edit)'; then
  if printf '%s' "$cmd" | grep -qiE 'co-authored-by|generated with claude code'; then
    cat <<'JSON'
{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"deny","permissionDecisionReason":"Attribution Claude interdite dans les commits et PR de ce repo (CLAUDE.md § Conventions Git). Retire la ligne « Co-Authored-By » / « Generated with Claude Code » puis relance."}}
JSON
    exit 0
  fi
fi

# Rien à signaler : la commande passe.
exit 0
