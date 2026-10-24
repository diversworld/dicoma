#!/usr/bin/env bash
# Run only inside the isolated DDEV db_test service, never against the application DB service.
set -euo pipefail

if [[ "${DICOMA_PHASE2_RESTORE_TEST_CONFIRMED:-0}" != '1' || "${MARIADB_DATABASE:-}" != 'db_test' || -z "${MARIADB_ROOT_PASSWORD:-}" ]]; then
  echo 'BLOCKIERT: Bestätigung und isolierter DDEV-db_test-Dienst erforderlich.' >&2
  exit 2
fi

export MYSQL_PWD="$MARIADB_ROOT_PASSWORD"
if [[ "$(mariadb --user=root --database=db_test --batch --skip-column-names --execute='SELECT DATABASE()')" != 'db_test' ]]; then
  echo 'BLOCKIERT: Unerwartete Quelldatenbank.' >&2
  exit 2
fi
if [[ "$(mariadb --user=root --batch --skip-column-names --execute='SELECT @@GLOBAL.event_scheduler')" == 'ON' ]]; then
  echo 'BLOCKIERT: Der Event-Scheduler muss auf dem isolierten Testdienst ausgeschaltet sein.' >&2
  exit 2
fi

workdir="$(mktemp -d /tmp/dicoma-restore.XXXXXXXX)"
chmod 700 "$workdir"
restore_db="dicoma_restore_test_$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"
created=0
cleanup() {
  local status=$?
  trap - EXIT
  if [[ "$created" == '1' && "$restore_db" =~ ^dicoma_restore_test_[0-9a-f]{16}$ ]]; then
    if ! mariadb --user=root --execute="DROP DATABASE \`$restore_db\`"; then
      echo "FEHLER: Temporäre Restore-Datenbank $restore_db konnte nicht entfernt werden." >&2
      status=1
    fi
  fi
  rm -f -- "$workdir/source.sql" "$workdir/restored.sql"
  rmdir -- "$workdir"
  exit "$status"
}
trap cleanup EXIT

started=$SECONDS
umask 077
mariadb-dump --user=root --single-transaction --routines --events --triggers \
  --hex-blob --order-by-primary --skip-comments --skip-dump-date db_test > "$workdir/source.sql"
backup_seconds=$((SECONDS - started))
checksum="$(sha256sum "$workdir/source.sql" | cut -d ' ' -f 1)"
echo "Backup erstellt und SHA-256 berechnet: $checksum (${backup_seconds}s)"

mariadb --user=root --execute="CREATE DATABASE \`$restore_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
created=1
started=$SECONDS
mariadb --user=root --database="$restore_db" < "$workdir/source.sql"
restore_seconds=$((SECONDS - started))
mariadb-dump --user=root --single-transaction --routines --events --triggers \
  --hex-blob --order-by-primary --skip-comments --skip-dump-date "$restore_db" > "$workdir/restored.sql"

if ! cmp -s "$workdir/source.sql" "$workdir/restored.sql"; then
  echo 'FEHLER: Wiederhergestelltes Schema/Daten entsprechen nicht exakt dem Backup.' >&2
  exit 1
fi

tables="$(mariadb --user=root --batch --skip-column-names --execute="SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$restore_db'")"
echo "PASS: Backup/Restore von db_test: $tables Tabellen, identischer Schema-/Daten-Dump, Restore ${restore_seconds}s."
echo 'Geltungsbereich: nur lokale Testdatenbank; keine Produktivdaten, Dokumentdateien oder produktionsnahe Betriebsabnahme.'
