<?php
// Isolated guard test without Symfony runtime; never touches the real project marker.
namespace Symfony\Component\HttpKernel\Exception { class NotFoundHttpException extends \RuntimeException {} }
namespace {
    require __DIR__ . '/../src/Security/InstallationGuard.php';
    $dir = sys_get_temp_dir() . '/dicoma-guard-' . bin2hex(random_bytes(8));
    mkdir($dir);
    $_SERVER['DICOMA_INSTALL_ENABLED'] = '1';
    $_SERVER['DICOMA_INSTALL_COMPLETED'] = '0';
    $guard = new \App\Security\InstallationGuard($dir);
    $guard->denyUnlessExplicitlyEnabled();
    $guard->markCompleted();
    $blocked = false;
    try { $guard->denyUnlessExplicitlyEnabled(); } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) { $blocked = true; }
    if (!$blocked) { throw new \RuntimeException('Setup was not blocked after completion.'); }
    $blockedDuplicate = false;
    try { $guard->markCompleted(); } catch (\RuntimeException $e) { $blockedDuplicate = true; }
    if (!$blockedDuplicate) { throw new \RuntimeException('Marker was overwritten.'); }
    unlink($dir . '/var/install-completed.marker'); rmdir($dir . '/var'); rmdir($dir);
    echo "OK: persistent marker blocks setup and cannot be overwritten\n";
}
