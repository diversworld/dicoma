<?php
// Standalone smoke test; no Composer or Symfony runtime required.
namespace Symfony\Component\DependencyInjection\Attribute { #[\Attribute] class Autowire { public function __construct(string $value) {} } }
namespace {
    require __DIR__ . '/../src/Security/BootstrapRegistrationLock.php';
    $dir = sys_get_temp_dir() . '/dicoma-bootstrap-test-' . bin2hex(random_bytes(6));
    mkdir($dir);
    $lock = new \App\Security\BootstrapRegistrationLock($dir);
    $called = false;
    $value = $lock->synchronized(function () use (&$called) { $called = true; return 42; });
    if (!$called || $value !== 42) { throw new \RuntimeException('Lock callback failed'); }
    try { $lock->synchronized(function () { throw new \RuntimeException('expected'); }); }
    catch (\RuntimeException $e) { if ($e->getMessage() !== 'expected') { throw $e; } }
    if ($lock->synchronized(fn () => 'released') !== 'released') { throw new \RuntimeException('Lock not released'); }
    unlink($dir . '/var/lock/dicoma-bootstrap.lock'); rmdir($dir . '/var/lock'); rmdir($dir . '/var'); rmdir($dir);
    echo "OK: callback, return value, exception release\n";
}
