#!/usr/bin/env bash
# Nightly backup of the ImportFlow database and private files.
#
#   BACKUP_DIR=/var/backups/importflow ./deploy/backup/backup.sh
#
# Schedule (cron, as a user that can read the app and .env):
#   15 1 * * * /var/www/importflow/deploy/backup/backup.sh >> /var/log/importflow-backup.log 2>&1
#
# Copy BACKUP_DIR off the server (object storage / another host) — a backup
# on the same disk does not survive losing that disk. Test a restore with
# restore-test.sh at least monthly; an untested backup is not a backup.

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/importflow}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/importflow}"
KEEP_DAYS="${KEEP_DAYS:-14}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"

# Read DB settings from the app's .env (values must not contain spaces).
env_value() { grep -E "^$1=" "$APP_DIR/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
DB_HOST="$(env_value DB_HOST)"; DB_PORT="$(env_value DB_PORT)"
DB_DATABASE="$(env_value DB_DATABASE)"; DB_USERNAME="$(env_value DB_USERNAME)"; DB_PASSWORD="$(env_value DB_PASSWORD)"

mkdir -p "$BACKUP_DIR"
umask 077   # backups contain customer data: owner-only

# Password via a temporary option file, never on the command line (visible in `ps`).
MYCNF="$(mktemp)"; trap 'rm -f "$MYCNF"' EXIT
printf '[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n' "$DB_USERNAME" "$DB_PASSWORD" "${DB_HOST:-127.0.0.1}" "${DB_PORT:-3306}" > "$MYCNF"

# --single-transaction: consistent InnoDB snapshot without locking the app.
mysqldump --defaults-extra-file="$MYCNF" --single-transaction --quick --routines --no-tablespaces "$DB_DATABASE" \
  | gzip -9 > "$BACKUP_DIR/db-$STAMP.sql.gz"

# Private uploads still within their retention period (exports are temporary and skipped).
tar -czf "$BACKUP_DIR/files-$STAMP.tar.gz" -C "$APP_DIR/storage/app/private" --exclude=exports --exclude=benchmarks .

find "$BACKUP_DIR" -name 'db-*.sql.gz' -mtime +"$KEEP_DAYS" -delete
find "$BACKUP_DIR" -name 'files-*.tar.gz' -mtime +"$KEEP_DAYS" -delete

echo "$(date -u) backup ok: db-$STAMP.sql.gz files-$STAMP.tar.gz"
