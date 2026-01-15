#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
CHECKLIST_PATH="${ROOT_DIR}/WORKFLOW_CHECKLIST.md"

if [ -f "${ROOT_DIR}/.env.deploy" ]; then
  set -a
  . "${ROOT_DIR}/.env.deploy"
  set +a
fi

PROD_HOST="${PROD_HOST:-}"
PROD_USER="${PROD_USER:-}"
PROD_PORT="${PROD_PORT:-10022}"
PROD_WP_CONTENT_PATH="${PROD_WP_CONTENT_PATH:-}"

if [ -z "$PROD_HOST" ] || [ -z "$PROD_USER" ] || [ -z "$PROD_WP_CONTENT_PATH" ]; then
  echo "Missing PROD connection settings."
  echo "Set PROD_HOST, PROD_USER, PROD_WP_CONTENT_PATH (and optional PROD_PORT)."
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
read -r -p "Press Enter to continue deploy to Production, or Ctrl+C to cancel... " _
echo

echo "== Deploy: theme =="
rsync -az --delete -e "ssh -p ${PROD_PORT}" \
  "${THEME_SRC}" \
  "${PROD_USER}@${PROD_HOST}:${PROD_WP_CONTENT_PATH}/themes/okivaluelab-child/"

echo "== Deploy: mu-plugins =="
rsync -az --delete -e "ssh -p ${PROD_PORT}" \
  "${MU_PLUGIN_SRC}" \
  "${PROD_USER}@${PROD_HOST}:${PROD_WP_CONTENT_PATH}/mu-plugins/"

echo "Deploy complete."
