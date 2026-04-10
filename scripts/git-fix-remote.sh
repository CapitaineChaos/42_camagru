#!/usr/bin/env bash
# ============================================================
# git-fix-remote.sh - Auto-fix the git remote to use the
#                     correct SSH Host from ~/.ssh/config.
#
# HOW IT WORKS:
#   1. Read the origin remote URL, extract the owner
#   2. Scan ~/.ssh/config for all "Host github.*" entries
#   3. For each one, run `ssh -T` and check which GitHub
#      account it authenticates as
#   4. If one matches the repo owner, rewrite the remote URL
#
# USAGE:
#   ./scripts/git-fix-remote.sh
#
# EXAMPLE:
#   Remote: git@github.com:CapitaineChaos/42_camagru.git
#   SSH config has: Host github.captainechaos
#   ssh -T git@github.captainechaos -> "Hi CapitaineChaos!"
#   -> match found, remote rewritten.
#
# PREREQUISITES:
#   ~/.ssh/config must have Host entries like:
#
#     Host github.captainechaos
#         HostName github.com
#         User git
#         IdentityFile ~/.ssh/captainechaos
#         IdentitiesOnly yes
# ============================================================
set -euo pipefail

# --- Get current remote ---
CURRENT="$(git remote get-url origin 2>/dev/null)" || {
    echo "[git-fix] ERROR: no 'origin' remote found." >&2
    exit 1
}

# --- Extract owner and repo path ---
REPO_PATH="$(echo "$CURRENT" | sed -E 's#.*[:/]([^/]+/[^/]+?)(\.git)?$#\1#')"
# Strip trailing .git if still present
REPO_PATH="${REPO_PATH%.git}"
if [[ -z "$REPO_PATH" || "$REPO_PATH" == "$CURRENT" ]]; then
    echo "[git-fix] ERROR: cannot parse owner/repo from: $CURRENT" >&2
    exit 1
fi
OWNER="$(echo "$REPO_PATH" | cut -d/ -f1)"
OWNER_LOWER="$(echo "$OWNER" | tr '[:upper:]' '[:lower:]')"

echo "[git-fix] remote: $CURRENT"
echo "[git-fix] owner:  $OWNER"

# --- Scan ~/.ssh/config for github.* hosts ---
HOSTS="$(grep -i '^Host github\.' ~/.ssh/config 2>/dev/null | awk '{print $2}')" || true
if [[ -z "$HOSTS" ]]; then
    echo "[git-fix] ERROR: no 'Host github.*' entries in ~/.ssh/config" >&2
    exit 1
fi

# --- Test each host, find the one that authenticates as OWNER ---
MATCH=""
for HOST in $HOSTS; do
    # ssh -T returns 1 but prints "Hi <user>!" on stderr
    RESPONSE="$(ssh -T "git@${HOST}" 2>&1 || true)"
    WHO="$(echo "$RESPONSE" | grep -oP 'Hi \K[^!]+' || true)"
    WHO_LOWER="$(echo "$WHO" | tr '[:upper:]' '[:lower:]')"

    if [[ "$WHO_LOWER" == "$OWNER_LOWER" ]]; then
        MATCH="$HOST"
        echo "[git-fix] found: $HOST -> authenticates as $WHO"
        break
    fi
done

if [[ -z "$MATCH" ]]; then
    echo "[git-fix] ERROR: no SSH host authenticates as '$OWNER'" >&2
    echo "[git-fix] tested: $HOSTS" >&2
    exit 1
fi

# --- Rewrite remote ---
NEW="git@${MATCH}:${REPO_PATH}.git"
if [[ "$CURRENT" == "$NEW" ]]; then
    echo "[git-fix] already correct."
else
    git remote set-url origin "$NEW"
    echo "[git-fix] done: $NEW"
fi
