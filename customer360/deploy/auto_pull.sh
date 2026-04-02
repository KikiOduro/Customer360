#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="${REPO_DIR:-/var/www/customer360}"
BRANCH="${BRANCH:-main}"
REMOTE="${REMOTE:-origin}"
LOG_FILE="${LOG_FILE:-/var/log/customer360-auto-pull.log}"

timestamp() {
  date +"%Y-%m-%d %H:%M:%S"
}

log() {
  mkdir -p "$(dirname "$LOG_FILE")"
  echo "[$(timestamp)] $*" >> "$LOG_FILE"
}

if [ ! -d "$REPO_DIR/.git" ]; then
  log "Repository not found at $REPO_DIR"
  exit 1
fi

cd "$REPO_DIR"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  log "Not a git repository: $REPO_DIR"
  exit 1
fi

if [ -n "$(git status --porcelain)" ]; then
  log "Working tree is dirty; skipping pull to avoid overwriting server changes."
  exit 0
fi

git fetch "$REMOTE" "$BRANCH" --quiet

LOCAL_COMMIT="$(git rev-parse HEAD)"
REMOTE_COMMIT="$(git rev-parse "$REMOTE/$BRANCH")"

if [ "$LOCAL_COMMIT" = "$REMOTE_COMMIT" ]; then
  exit 0
fi

log "New commit detected. Pulling $REMOTE/$BRANCH into $REPO_DIR."
git pull --ff-only "$REMOTE" "$BRANCH" >> "$LOG_FILE" 2>&1
log "Pull completed successfully."
