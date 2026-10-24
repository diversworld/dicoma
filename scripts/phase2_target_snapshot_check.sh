#!/usr/bin/env bash
# Local DDEV only: read db, restore and migrate a new database on db_test.
set -euo pipefail
cd "$(dirname "$0")/.."
if [[ "${1:-}" != '--confirm-local-db-snapshot' || $# -gt 2 || ($# -eq 2 && "$2" != '--keep-backup') ]]; then
  echo 'BLOCKIERT: --confirm-local-db-snapshot erforderlich.' >&2
  exit 2
fi
backup_directory=''
if [[ "${2:-}" == '--keep-backup' ]]; then
  backup_directory="${DICOMA_PHASE2_BACKUP_DIRECTORY:-}"
  if [[ "$backup_directory" != /* || -e "$backup_directory" || -L "$backup_directory" ]]; then
    echo 'BLOCKIERT: --keep-backup erfordert DICOMA_PHASE2_BACKUP_DIRECTORY als neuen absoluten Pfad.' >&2
    exit 2
  fi
fi

source_container=ddev-dicoma-db
target_container=ddev-dicoma-db_test
web_container=ddev-dicoma-web
source_sql() {
  docker exec --env MYSQL_PWD=db "$source_container" mariadb --no-defaults --host=127.0.0.1 --user=db --database=db --batch --skip-column-names --execute="$1"
}
target_sql() {
  docker exec --env MYSQL_PWD=root "$target_container" mariadb --no-defaults --host=127.0.0.1 --user=root --batch --skip-column-names --execute="$1"
}
[[ "$(source_sql 'SELECT DATABASE()')" == 'db' ]]
[[ "$(source_sql 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND ENGINE <> "InnoDB"')" == '0' ]]
[[ "$(target_sql 'SELECT @@GLOBAL.event_scheduler')" != 'ON' ]]
[[ "$(docker exec "$target_container" printenv MARIADB_DATABASE)" == 'db_test' ]]

workdir="$(mktemp -d /tmp/dicoma-target.XXXXXXXX)"
chmod 700 "$workdir"
remote=''
database="dicoma_snapshot_test_$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"
created=0
cleanup() {
  local status=$?
  trap - EXIT
  if [[ "$created" == '1' && "$database" =~ ^dicoma_snapshot_test_[a-f0-9]{16}$ ]]; then
    if ! target_sql "DROP DATABASE \`$database\`"; then
      echo "FEHLER: Temporäre Snapshot-Datenbank $database konnte nicht entfernt werden." >&2
      status=1
    fi
  fi
  if [[ "$remote" =~ ^/tmp/dicoma-target\.[a-zA-Z0-9]{8}$ ]]; then
    if ! docker exec "$web_container" rm -rf -- "$remote"; then
      echo "FEHLER: Temporäre Anwendungskopie $remote konnte nicht entfernt werden." >&2
      status=1
    fi
  fi
  rm -f -- "$workdir/database.sql" "$workdir/restored.sql" "$workdir/application.tar"
  rmdir -- "$workdir"
  exit "$status"
}
trap cleanup EXIT
umask 077

items=(bin config migrations public scripts src templates translations vendor composer.json composer.lock symfony.lock .env)
for path in assets .env.local .env.local.php .env.prod .env.prod.local var/private var/install-completed.marker; do
  if [[ -e "$path" ]]; then
    items+=("$path")
  fi
done
if [[ ! -f var/install-completed.marker ]]; then
  echo 'OFFEN: Der aktuelle Bestand hat keinen persistenten Installationsabschlussmarker.'
fi
started=$SECONDS
tar -cf "$workdir/application.tar" -- "${items[@]}"
docker exec --env MYSQL_PWD=db "$source_container" mariadb-dump --no-defaults --host=127.0.0.1 --user=db \
  --single-transaction --routines --events --triggers --hex-blob \
  --order-by-primary --skip-comments --skip-dump-date db > "$workdir/database.sql"
echo "Lokaler Snapshot: Anwendung, vorhandene Konfiguration/Dateien und db gesichert ($((SECONDS - started))s)."
echo "Datenbank-SHA-256: $(sha256sum "$workdir/database.sql" | cut -d ' ' -f 1)"
echo "Anwendungsarchiv-SHA-256: $(sha256sum "$workdir/application.tar" | cut -d ' ' -f 1)"
if [[ "$backup_directory" != '' ]]; then
  mkdir -m 700 -- "$backup_directory"
  cp -- "$workdir/database.sql" "$workdir/application.tar" "$backup_directory/"
  chmod 600 "$backup_directory/database.sql" "$backup_directory/application.tar"
  (cd "$backup_directory" && sha256sum database.sql application.tar > SHA256SUMS)
  echo "Geschütztes Vorher-Backup bleibt erhalten: $backup_directory"
fi

remote="$(docker exec "$web_container" mktemp -d /tmp/dicoma-target.XXXXXXXX)"
[[ "$remote" =~ ^/tmp/dicoma-target\.[a-zA-Z0-9]{8}$ ]]
docker exec "$web_container" chmod 700 "$remote"
docker exec "$web_container" mkdir "$remote/app"
docker cp "$workdir/application.tar" "$web_container:$remote/application.tar"
docker exec "$web_container" tar --same-permissions -xf "$remote/application.tar" -C "$remote/app"
docker exec "$web_container" tar --compare -f "$remote/application.tar" -C "$remote/app"
target_sql "CREATE DATABASE \`$database\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
created=1
started=$SECONDS
docker exec -i --env MYSQL_PWD=root "$target_container" mariadb --no-defaults --host=127.0.0.1 --user=root --database="$database" < "$workdir/database.sql"
docker exec --env MYSQL_PWD=root "$target_container" mariadb-dump --no-defaults --host=127.0.0.1 --user=root \
  --single-transaction --routines --events --triggers --hex-blob \
  --order-by-primary --skip-comments --skip-dump-date "$database" > "$workdir/restored.sql"
cmp -s "$workdir/database.sql" "$workdir/restored.sql"
echo "PASS: Datenbank und Anwendung/Dateien unverändert wiederhergestellt ($((SECONDS - started))s)."

url="mysql://root:root@db_test:3306/$database?charset=utf8mb4&serverVersion=11.8.9-MariaDB"
run_copy() {
  docker exec --workdir "$remote/app" --env APP_ENV=prod --env APP_DEBUG=0 \
    --env DATABASE_URL="$url" --env MAILER_DSN=null://null \
    --env DICOMA_PHASE2_TARGET_SNAPSHOT_CONFIRMED=1 "$web_container" "$@"
}
run_copy php scripts/phase2_target_snapshot_check.php
run_copy php bin/console lint:container --env=prod
run_copy php bin/console doctrine:schema:validate --env=prod
run_copy php bin/console doctrine:migrations:up-to-date --env=prod
echo 'PASS: Frische Prod-Anwendungskopie mit isolierter DB und Null-Mailer lauffähig.'
echo 'Keine Änderung an db, db_test, laufender Konfiguration oder bestehendem Prod-Cache.'
