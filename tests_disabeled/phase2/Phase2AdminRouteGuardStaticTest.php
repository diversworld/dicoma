<?php
declare(strict_types=1);
// Statischer Regressionstest ohne Symfony-Bootstrap.
// Start: php tests/Security/Phase2AdminRouteGuardStaticTest.php
$base = dirname(__DIR__, 2) . '/src/Controller/Admin/';
$expected = [
    'ClubTankCheckBookingSaveController.php' => 1,
    'ClubTankInspectionController.php' => 1,
    'EquipmentAttributeSchemaController.php' => 1,
    'EquipmentBookingPositionController.php' => 1,
    'EquipmentCenterController.php' => 1,
    'EquipmentInventoryOptionsController.php' => 1,
    'EquipmentLoanActionController.php' => 1,
    'EquipmentManufacturerOptionsController.php' => 1,
    'TankStatusController.php' => 1,
    'TuvBookingOverviewController.php' => 2,
    'TuvBookingServicesController.php' => 1,
    'TuvInspectionResultSaveController.php' => 1,
    'TuvInvoiceController.php' => 1,
    'TuvInvoiceSettingsController.php' => 1,
    'TuvInvoiceSettingsOverviewController.php' => 1,
    'TuvMemberOrderExportController.php' => 1,
    'TuvMessageTemplateController.php' => 1,
    'TuvNotificationSendNowController.php' => 1,
    'TuvOperationsController.php' => 7,
    'TuvPaymentIntakeController.php' => 2,
    'TuvSupplierReconciliationController.php' => 2,
];
$total = 0;
foreach ($expected as $file => $count) {
    $source = file_get_contents($base . $file);
    if ($source === false || !str_contains($source, "#[IsGranted('ROLE_SUPER_ADMIN')]")) {
        fwrite(STDERR, "Fehlende Super-Admin-Sperre: $file\n"); exit(1);
    }
    $routes = preg_match_all("/\#\[Route\(/", $source);
    if ($routes !== $count) {
        fwrite(STDERR, "Routenanzahl geändert: $file ($routes statt $count)\n"); exit(1);
    }
    $total += $routes;
}
if ($total !== 30) { fwrite(STDERR, "Unerwartete Anzahl: $total\n"); exit(1); }
echo "OK: 30 Admin-Routen in 21 Controllern rollenbasiert geschützt.\n";
