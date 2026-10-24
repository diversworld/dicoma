#!/usr/bin/env bash
# Does not deploy, migrate, or run staging HTTP writes without explicit consent.
set -uo pipefail
cd "$(dirname "$0")/.."
PHP_BIN="${PHP_BIN:-php}"
fail=0
if [[ ! "${DICOMA_RUN_STAGING_DB_CHECKS:-0}" =~ ^[01]$ || ! "${DICOMA_RUN_STAGING_HTTP_TESTS:-0}" =~ ^[01]$ ]]; then
  echo 'BLOCKIERT: Staging-Schalter müssen 0 oder 1 sein.' >&2
  exit 2
fi
run() {
  local label="$1"; shift
  printf '\n=== %s ===\n' "$label"
  if "$@"; then echo "PASS: $label"; else echo "FAIL: $label"; fail=1; fi
}
if [[ ! -f vendor/autoload.php ]]; then
  echo 'BLOCKIERT: vendor/autoload.php fehlt; composer install muss auf Staging erfolgt sein.' >&2
  exit 2
fi
run 'PHP-Routenprüfung (statisch)' "$PHP_BIN" tests/Security/phase2_route_gate.php
run 'Composer-Konfiguration' composer validate --no-check-publish
run 'PHP-Plattform' composer check-platform-reqs
run 'Symfony-YAML (test)' "$PHP_BIN" bin/console lint:yaml config --env=test
run 'Symfony-Container (test)' "$PHP_BIN" bin/console lint:container --env=test
check_routes() {
  "$PHP_BIN" bin/console debug:router --env=test --format=json >/dev/null
}
run 'Symfony-Routing (test)' check_routes
run 'Doctrine-Mapping (ohne Datenbank)' "$PHP_BIN" bin/console doctrine:schema:validate --skip-sync --env=test
run 'Installationssperre' "$PHP_BIN" tests/installation_guard_standalone.php
run 'Bootstrap-Sperre' "$PHP_BIN" tests/bootstrap_lock_standalone.php
if [[ -x vendor/bin/phpunit ]]; then
  run 'PHPUnit (Unit und isoliertes SQLite)' "$PHP_BIN" vendor/bin/phpunit --testsuite unit,isolated --no-progress --fail-on-skipped --fail-on-incomplete
else
  echo 'BLOCKIERT: vendor/bin/phpunit fehlt.' >&2
  fail=1
fi
if [[ "$fail" -ne 0 ]]; then
  echo 'BASIS-GATE: NICHT BESTANDEN; keine Staging-Prüfungen ausgeführt.' >&2
  exit "$fail"
fi
# Check the real connection before any optional staging checks.
if [[ "${DICOMA_RUN_STAGING_DB_CHECKS:-0}" == '1' || "${DICOMA_RUN_STAGING_HTTP_TESTS:-0}" == '1' ]]; then
  if [[ "${APP_ENV:-}" != 'test' || "${DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED:-0}" != '1' || -z "${DICOMA_TEST_EXPECTED_DATABASE:-}" ]]; then
    echo 'BLOCKIERT: APP_ENV=test, Testdatenbankbestätigung und erwarteter Datenbankname erforderlich.' >&2
    fail=1
  elif ! "$PHP_BIN" bin/console app:test-database:check --env=test --expected="$DICOMA_TEST_EXPECTED_DATABASE"; then
    echo 'BLOCKIERT: Testdatenbankprüfung fehlgeschlagen; keine weiteren Staging-Prüfungen.' >&2
    fail=1
  else
    if [[ "${DICOMA_RUN_STAGING_DB_CHECKS:-0}" == '1' || "${DICOMA_RUN_STAGING_HTTP_TESTS:-0}" == '1' ]]; then
      run 'Doctrine-Mapping und Schema (test)' "$PHP_BIN" bin/console doctrine:schema:validate --env=test
      run 'Keine offenen Migrationen (test)' "$PHP_BIN" bin/console doctrine:migrations:up-to-date --env=test
    fi
    if [[ "${DICOMA_RUN_STAGING_HTTP_TESTS:-0}" == '1' ]]; then
      if [[ "$fail" -ne 0 ]]; then
        echo 'BLOCKIERT: Schema oder Migrationsstand fehlerhaft; keine HTTP-Schreibtests ausgeführt.' >&2
      elif [[ "${DICOMA_PHASE2_DESTRUCTIVE_TEST_CONFIRMED:-0}" != '1' ]]; then
        echo 'BLOCKIERT: HTTP-Suite enthält Schreibtests; ausdrückliche Bestätigung erforderlich.' >&2
        fail=1
      else
        run 'HTTP-Abnahme (Staging, inklusive Schreibtests)' "$PHP_BIN" vendor/bin/phpunit --testsuite staging --no-progress --fail-on-skipped --fail-on-incomplete
      fi
    fi
  fi
else
  echo 'OFFEN: Staging-Schema, Migrationsstand und vollständige HTTP-Abnahme wurden nicht geprüft.'
fi
if [[ "$fail" -eq 0 ]]; then
  echo 'AUSGEWÄHLTE PRÜFUNGEN: OK. Keine automatische Phase-2- oder Produktivfreigabe; offene Gates separat abnehmen.'
else
  echo 'TECHNISCHES GATE: NICHT BESTANDEN.' >&2
fi
exit "$fail"
