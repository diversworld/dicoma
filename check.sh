#!/usr/bin/env bash
set -euo pipefail

if [[ ! -f /.dockerenv ]]; then
    cd "$(dirname "$0")"
    exec ddev exec bash ./check.sh
fi

export APP_ENV=test
export KERNEL_CLASS='App\Kernel'
export DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED=1
export MIGRATION_TEST_DATABASE_URL='mysql://db:db@db_test:3306/db_test?charset=utf8mb4'

export DICOMA_TEST_EXPECTED_DATABASE='db_test'

export DICOMA_TEST_CLUB_A_ADMIN_EMAIL='phase2-isolation-admin-a@example.invalid'
export DICOMA_TEST_CLUB_B_ADMIN_EMAIL='phase2-isolation-admin-b@example.invalid'
export DICOMA_TEST_SUPER_ADMIN_EMAIL='phase2-isolation-super-admin@example.invalid'
export DICOMA_TEST_ROLE_USER_EMAIL='phase2-isolation-normal-user@example.invalid'
export DICOMA_TEST_DISPOSABLE_MEMBER_A_ID=5
export DICOMA_TEST_DISPOSABLE_MEMBER_B_ID=6
export DICOMA_TEST_CLUB_A_PARTICIPANT_ID=1
export DICOMA_TEST_CLUB_B_PARTICIPANT_ID=2
export DICOMA_PHASE2_DESTRUCTIVE_TEST_CONFIRMED=1

export DICOMA_TEST_CLUB_A_COURSE_ID=1
export DICOMA_TEST_CLUB_B_COURSE_ID=2

export DICOMA_TEST_CLUB_A_COURSE_ID=1
export DICOMA_TEST_CLUB_B_COURSE_ID=2

echo "DB-Freigabe: ${DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED:-NICHT_GESETZT}"
echo "Testdatenbank: ${DICOMA_TEST_EXPECTED_DATABASE:-NICHT_GESETZT}"
echo "Verein Admin A: ${DICOMA_TEST_CLUB_A_ADMIN_EMAIL:-NICHT_GESETZT}"
echo "Verein Admin B: ${DICOMA_TEST_CLUB_B_ADMIN_EMAIL:-NICHT_GESETZT}"
echo "Super Admin: ${DICOMA_TEST_SUPER_ADMIN_EMAIL:-NICHT_GESETZT}"
echo "Normaler Benutzer: ${DICOMA_TEST_ROLE_USER_EMAIL:-NICHT_GESETZT}"
echo "Mitglied A: ${DICOMA_TEST_DISPOSABLE_MEMBER_A_ID:-NICHT_GESETZT}"
echo "Mitglied B: ${DICOMA_TEST_DISPOSABLE_MEMBER_B_ID:-NICHT_GESETZT}"
echo "Verein A Teilnehmer: ${DICOMA_TEST_CLUB_A_PARTICIPANT_ID:-NICHT_GESETZT}"
echo "Verein B Teilnehmer: ${DICOMA_TEST_CLUB_B_PARTICIPANT_ID:-NICHT_GESETZT}"
echo "Verein A Kurs: ${DICOMA_TEST_CLUB_A_COURSE_ID:-NICHT_GESETZT}"
echo "Verein B Kurs: ${DICOMA_TEST_CLUB_B_COURSE_ID:-NICHT_GESETZT}"

#/opt/plesk/php/8.4/bin/php vendor/bin/phpunit --no-progress --testdox --display-errors --display-skipped --display-phpunit-deprecations tests/Http/Phase2ClubIsolationHttpTest.php
#/opt/plesk/php/8.4/bin/php vendor/bin/phpunit --no-progress --testdox --display-skipped tests/Http/Phase2MemberIsolationHttpTest.php
#/opt/plesk/php/8.4/bin/php vendor/bin/phpunit --no-progress --testdox --display-skipped tests/Http/Phase2MemberWriteGuardHttpTest.php
#/opt/plesk/php/8.4/bin/php vendor/bin/phpunit --no-progress --testdox --display-skipped tests/Http/Phase2MemberWriteAuthorizationTest.php
#/opt/plesk/php/8.4/bin/php vendor/bin/phpunit --no-progress --testdox --display-skipped tests/Http/Phase2MemberValidEditHttpTest.php
#/opt/plesk/php/8.4/bin/php vendor/bin/phpunit --no-progress --testdox --display-skipped tests/Http/Phase2MemberValidDeleteHttpTest.php
#/opt/plesk/php/8.4/bin/php vendor/bin/phpunit --no-progress --testdox tests/Http/Phase2MemberValidDeleteHttpTest.php
#ddev php vendor/bin/phpunit --no-progress --testdox --display-skipped tests/Http/Phase2CourseParticipantIsolationHttpTest.php
#php vendor/bin/phpunit --no-progress --testdox --display-skipped --display-errors tests/Http/Phase2CourseParticipantIsolationHttpTest.php
#php vendor/bin/phpunit --no-progress --filter 'MigrationChain' --display-errors --display-skipped --display-deprecations
#php vendor/bin/phpunit --no-progress --filter testClubBookingRollbackAbortsBeforeRemovingReferences --display-errors
php vendor/bin/phpunit   --no-progress  --testdox --display-skipped  --display-errors --filter 'Phase2'   tests/Http/