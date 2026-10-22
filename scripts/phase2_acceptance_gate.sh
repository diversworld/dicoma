#!/usr/bin/env bash
# Read-only pre-release gate. Does not deploy, migrate or clear caches.
set -uo pipefail
cd "$(dirname "$0")/.."
PHP_BIN="${PHP_BIN:-php}"
fail=0
run() {
  local label="$1"; shift
  printf '\n=== %s ===\n' "$label"
  if "$@"; then echo "PASS: $label"; else echo "FAIL: $label"; fail=1; fi
}
if [[ ! -f vendor/autoload.php ]]; then
  echo 'BLOCKIERT: vendor/autoload.php fehlt; composer install muss auf Staging erfolgt sein.' >&2
  exit 2
fi
run 'PHP-Routenprüfung' "$PHP_BIN" tests/security/phase2_route_gate.php
run 'Symfony-Container (test)' "$PHP_BIN" bin/console lint:container --env=test
run 'Symfony-Routing (test)' "$PHP_BIN" bin/console debug:router --env=test
if [[ -x vendor/bin/phpunit ]]; then
  run 'PHPUnit (test)' vendor/bin/phpunit --no-progress
else
  echo 'BLOCKIERT: vendor/bin/phpunit fehlt.' >&2
  fail=1
fi
# Database checks only when explicitly requested against a STAGING connection.
if [[ "${DICOMA_RUN_STAGING_DB_CHECKS:-0}" == '1' ]]; then
  if [[ "${APP_ENV:-}" != 'test' ]]; then
    echo 'BLOCKIERT: Datenbankprüfungen nur bei APP_ENV=test.' >&2
    fail=1
  else
    run 'Doctrine-Mapping und Schema (test)' "$PHP_BIN" bin/console doctrine:schema:validate --env=test
    run 'Doctrine-Migrationsstatus (test)' "$PHP_BIN" bin/console doctrine:migrations:status --env=test
  fi
else
  echo 'INFO: Datenbankprüfungen übersprungen; für Staging APP_ENV=test und DICOMA_RUN_STAGING_DB_CHECKS=1 setzen.'
fi
if [[ "$fail" -eq 0 ]]; then
  echo 'TECHNISCHES GATE: OK. Fachliche Abnahme und HTTP-Rollentests weiterhin erforderlich.'
else
  echo 'TECHNISCHES GATE: NICHT BESTANDEN.' >&2
fi
exit "$fail"
