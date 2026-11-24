#!/usr/bin/env bash
# ==============================================================================
# Enterprise Daily Backup & Rotation Script for SCUM Server Manager
# ==============================================================================
set -euo pipefail

BACKUP_DIR="/var/backups/scum-manager"
APP_DIR="/home/www/ssm"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/ssm_backup_${TIMESTAMP}.tar.gz"
RETENTION_DAYS=7

mkdir -p "${BACKUP_DIR}"
chmod 700 "${BACKUP_DIR}"

echo "[$(date +'%Y-%m-%d %H:%M:%S')] Starting SCUM Manager backup..."

tar -czf "${BACKUP_FILE}"     -C "${APP_DIR}"     .env     config     storage/settings 2>/dev/null || true

# Add sqlite database if exists
if [ -f "${APP_DIR}/database/database.sqlite" ]; then
    tar -rf "${BACKUP_FILE%.gz}" -C "${APP_DIR}" database/database.sqlite 2>/dev/null || true
fi

chmod 600 "${BACKUP_FILE}"
echo "[$(date +'%Y-%m-%d %H:%M:%S')] Backup created: ${BACKUP_FILE} ($(du -h "${BACKUP_FILE}" | cut -f1))"

# Retention: remove backups older than 7 days
find "${BACKUP_DIR}" -type f -name "ssm_backup_*.tar.gz" -mtime +${RETENTION_DAYS} -delete
echo "[$(date +'%Y-%m-%d %H:%M:%S')] Old backups older than ${RETENTION_DAYS} days pruned."
