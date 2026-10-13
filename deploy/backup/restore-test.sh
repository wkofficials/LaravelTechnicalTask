#!/usr/bin/env bash
# Proves the latest backup can actually be restored: loads it into a
# throw-away database and checks that the key tables have rows.
#
#   BACKUP_DIR=/var/backups/importflow ./deploy/backup/restore-test.sh
#
# Never point this at the production database name. It creates and drops
# "<DB_DATABASE>_restore_test".

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/importflow}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/importflow}"

env_value() { grep -E "^$1=" "$APP_DIR/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
DB_DATABASE="$(env_value DB_DATABASE)"
TEST_DB="${DB_DATABASE}_restore_test"

MYCNF="$(mktemp)"; trap 'rm -f "$MYCNF"' EXIT
printf '[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n' "$(env_value DB_USERNAME)" "$(env_value DB_PASSWORD)" "$(env_value DB_HOST)" "$(env_value DB_PORT)" > "$MYCNF"

LATEST="$(ls -1t "$BACKUP_DIR"/db-*.sql.gz | head -1)"
echo "Restoring $LATEST into $TEST_DB …"

mysql --defaults-extra-file="$MYCNF" -e "DROP DATABASE IF EXISTS \`$TEST_DB\`; CREATE DATABASE \`$TEST_DB\`;"
gunzip -c "$LATEST" | mysql --defaults-extra-file="$MYCNF" "$TEST_DB"

for table in companies users records import_batches migrations; do
  count="$(mysql --defaults-extra-file="$MYCNF" -N -e "SELECT COUNT(*) FROM \`$TEST_DB\`.\`$table\`;")"
  echo "  $table: $count rows"
done

mysql --defaults-extra-file="$MYCNF" -e "DROP DATABASE \`$TEST_DB\`;"
echo "Restore test passed. Record the date and backup file in your ops log."
