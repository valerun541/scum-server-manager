#!/usr/bin/env bash
# ==============================================================================
# Daily Backup & Rotation Script for SCUM Server Manager (Linux)
# ==============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${APP_DIR:-$(cd "${SCRIPT_DIR}/.." && pwd)}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/scum-manager}"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/ssm_backup_${TIMESTAMP}.tar.gz"
RETENTION_DAYS=7

mkdir -p "${BACKUP_DIR}"
chmod 700 "${BACKUP_DIR}"

echo "[$(date +'%Y-%m-%d %H:%M:%S')] Starting SCUM Manager backup from ${APP_DIR}..."

TARGETS=()
[ -f "${APP_DIR}/.env" ] && TARGETS+=(".env")
[ -d "${APP_DIR}/config" ] && TARGETS+=("config")

# Use SQLite online backup if sqlite3 CLI is available (safe with WAL mode)
TMP_DB_BACKUP=""
if [ -f "${APP_DIR}/database/database.sqlite" ]; then
    if command -v sqlite3 >/dev/null 2>&1; then
        TMP_DB_BACKUP="${APP_DIR}/database/database.sqlite.backup_tmp"
        sqlite3 "${APP_DIR}/database/database.sqlite" ".backup '${TMP_DB_BACKUP}'"
        TARGETS+=("database/database.sqlite.backup_tmp")
    else
        TARGETS+=("database/database.sqlite")
    fi
fi

if [ ${#TARGETS[@]} -eq 0 ]; then
    echo "Nothing to back up in ${APP_DIR}" >&2
    exit 1
fi

tar -czf "${BACKUP_FILE}" -C "${APP_DIR}" "${TARGETS[@]}"
[ -n "${TMP_DB_BACKUP}" ] && rm -f "${TMP_DB_BACKUP}"

chmod 600 "${BACKUP_FILE}"
echo "[$(date +'%Y-%m-%d %H:%M:%S')] Backup created: ${BACKUP_FILE} ($(du -h "${BACKUP_FILE}" | cut -f1))"

# Retention: remove backups older than RETENTION_DAYS days
find "${BACKUP_DIR}" -type f -name "ssm_backup_*.tar.gz" -mtime +${RETENTION_DAYS} -delete
echo "[$(date +'%Y-%m-%d %H:%M:%S')] Old backups older than ${RETENTION_DAYS} days pruned."
