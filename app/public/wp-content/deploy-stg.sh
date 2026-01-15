#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
CHECKLIST_PATH="${ROOT_DIR}/WORKFLOW_CHECKLIST.md"

if [ -f "${ROOT_DIR}/.env.deploy" ]; then
  set -a
  . "${ROOT_DIR}/.env.deploy"
  set +a
fi

STG_HOST="${STG_HOST:-}"
STG_USER="${STG_USER:-}"
STG_PORT="${STG_PORT:-10022}"
STG_WP_CONTENT_PATH="${STG_WP_CONTENT_PATH:-}"

if [ -z "$STG_HOST" ] || [ -z "$STG_USER" ] || [ -z "$STG_WP_CONTENT_PATH" ]; then
  echo "Missing STG connection settings."
  echo "Set STG_HOST, STG_USER, STG_WP_CONTENT_PATH (and optional STG_PORT)."
  exit 1
fi

THEME_SRC="${ROOT_DIR}/themes/okivaluelab-child/"
MU_PLUGIN_SRC="${ROOT_DIR}/mu-plugins/"

echo "== Checklist =="
if [ -f "$CHECKLIST_PATH" ]; then
  cat "$CHECKLIST_PATH"
else
  echo "Checklist not found: $CHECKLIST_PATH"
fi
echo
read -r -p "Press Enter to continue deploy to STG, or Ctrl+C to cancel... " _
echo

echo "== Deploy: theme =="
rsync -az --delete -e "ssh -p ${STG_PORT}" \
  "${THEME_SRC}" \
  "${STG_USER}@${STG_HOST}:${STG_WP_CONTENT_PATH}/themes/okivaluelab-child/"

echo "== Deploy: mu-plugins =="
rsync -az --delete -e "ssh -p ${STG_PORT}" \
  "${MU_PLUGIN_SRC}" \
  "${STG_USER}@${STG_HOST}:${STG_WP_CONTENT_PATH}/mu-plugins/"

echo "Deploy complete."
