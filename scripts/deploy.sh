#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

: "${TYPECHO_ROOT:?Set TYPECHO_ROOT, for example TYPECHO_ROOT=/www/wwwroot/example.com/typecho}"

TYPECHO_USER="${TYPECHO_USER:-ubuntu}"
SSH_OPTS="${SSH_OPTS:-}"
RSYNC_OPTS="${RSYNC_OPTS:--az --delete}"

PLUGIN_TARGET="${TYPECHO_ROOT}/usr/plugins/TypechoPay/"
THEME_TARGET="${TYPECHO_ROOT}/usr/themes/VOID/"

EXCLUDES="
--exclude .git
--exclude vendor
--exclude node_modules
--exclude .DS_Store
--exclude temp_screenshot.png
--exclude '*.log'
"

if [ -n "${TYPECHO_HOST:-}" ]; then
  REMOTE="${TYPECHO_USER}@${TYPECHO_HOST}"
  rsync ${RSYNC_OPTS} ${EXCLUDES} -e "ssh ${SSH_OPTS}" \
    "${ROOT_DIR}/plugins/TypechoPay/" "${REMOTE}:${PLUGIN_TARGET}"

  rsync ${RSYNC_OPTS} ${EXCLUDES} -e "ssh ${SSH_OPTS}" \
    "${ROOT_DIR}/themes/VOID/" "${REMOTE}:${THEME_TARGET}"

  echo "Deployed TypechoPay and VOID to ${REMOTE}:${TYPECHO_ROOT}"
else
  rsync ${RSYNC_OPTS} ${EXCLUDES} \
    "${ROOT_DIR}/plugins/TypechoPay/" "${PLUGIN_TARGET}"

  rsync ${RSYNC_OPTS} ${EXCLUDES} \
    "${ROOT_DIR}/themes/VOID/" "${THEME_TARGET}"

  echo "Deployed TypechoPay and VOID to ${TYPECHO_ROOT}"
fi
