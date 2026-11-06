<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AcceptanceGateTest extends TestCase
{
    public static function suites(): iterable
    {
        yield 'base suite' => ['unit,isolated', 'FAIL: PHPUnit (Unit und isoliertes SQLite)'];
        yield 'staging suite' => ['staging', 'FAIL: HTTP-Abnahme (Staging, inklusive Schreibtests)'];
    }

    #[DataProvider('suites')]
    public function testInterruptedPhpunitOutputFailsEitherGate(string $interruptedSuite, string $failure): void
    {
        $directory = sys_get_temp_dir().'/dicoma-gate-test-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        $php = $directory.'/php-stub';
        $composer = $directory.'/composer';
        try {
            file_put_contents($php, <<<'SH'
#!/bin/sh
if [ "$1" = "vendor/bin/phpunit" ] && [ "$3" = "$INTERRUPTED_SUITE" ]; then
    echo "Test execution was interrupted by a signal."
fi
exit 0
SH);
            file_put_contents($composer, "#!/bin/sh\nexit 0\n");
            self::assertTrue(chmod($php, 0700));
            self::assertTrue(chmod($composer, 0700));
            $environment = getenv();
            $environment['PATH'] = $directory.':'.$environment['PATH'];
            $environment['PHP_BIN'] = $php;
            $environment['APP_ENV'] = 'test';
            $environment['DICOMA_RUN_STAGING_DB_CHECKS'] = '1';
            $environment['DICOMA_RUN_STAGING_HTTP_TESTS'] = '1';
            $environment['DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED'] = '1';
            $environment['DICOMA_PHASE2_DESTRUCTIVE_TEST_CONFIRMED'] = '1';
            $environment['DICOMA_TEST_EXPECTED_DATABASE'] = 'db_test';
            $environment['INTERRUPTED_SUITE'] = $interruptedSuite;
            $process = proc_open(['bash', 'scripts/phase2_acceptance_gate.sh'], [
                0 => ['file', '/dev/null', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes, dirname(__DIR__, 2), $environment);
            self::assertIsResource($process);
            $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(1, proc_close($process), $output);
            self::assertStringContainsString($failure, $output);
            self::assertStringNotContainsString('AUSGEWÄHLTE PRÜFUNGEN: OK', $output);
        } finally {
            if (is_file($php)) { unlink($php); }
            if (is_file($composer)) { unlink($composer); }
            rmdir($directory);
        }
    }
}
