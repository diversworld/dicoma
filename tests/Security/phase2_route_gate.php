<?php
declare(strict_types=1);
/** Standalone source-level safety gate; does NOT replace Symfony HTTP tests. */
$root = dirname(__DIR__, 2);
$spec = [
    'ClubTankCheckBookingSaveController' => ['/admin/tuv/club-bookings/save'],
    'ClubTankInspectionController' => ['/admin/tuv/club-tank/{id}'],
    'EquipmentAttributeSchemaController' => ['/admin/equipment-attribute-schema'],
    'EquipmentBookingPositionController' => ['/admin/equipment-booking/add-position'],
    'EquipmentCenterController' => ['/admin/equipment-center'],
    'EquipmentInventoryOptionsController' => ['/admin/equipment/inventory-options'],
    'EquipmentLoanActionController' => ['/admin/equipment-loan/{id}/{action}'],
    'EquipmentManufacturerOptionsController' => ['/admin/api/equipment-manufacturers'],
    'TankStatusController' => ['/admin/{_locale}/tank-status/{id}'],
    'TuvBookingOverviewController' => ['/admin/tuv/bookings/{id}/status', '/admin/tuv/bookings'],
    'TuvBookingServicesController' => ['/admin/tuv/booking/{id}/services'],
    'TuvInspectionResultSaveController' => ['/admin/tuv/inspection-result/{id}/save'],
    'TuvInvoiceController' => ['/admin/tuv/invoice/issue/{id}'],
    'TuvInvoiceSettingsController' => ['/admin/tuv/invoice/settings/{id}'],
    'TuvInvoiceSettingsOverviewController' => ['/admin/tuv/invoice/settings'],
    'TuvMemberOrderExportController' => ['/admin/tuv/check/{id}/member-orders.csv'],
    'TuvMessageTemplateController' => ['/admin/tuv/message-templates'],
    'TuvNotificationSendNowController' => ['/admin/tuv-notification/{id}/send-now'],
    'TuvOperationsController' => [
        '/admin/tuv/operations/{id}', '/admin/tuv/operations/{id}/handover/print',
        '/admin/tuv/operations/{id}/handover', '/admin/tuv/operations/{id}/document',
        '/admin/tuv/document/{id}', '/admin/tuv/operations/{id}/group',
        '/admin/tuv/operations/{id}/notify',
    ],
    'TuvPaymentIntakeController' => ['/admin/tuv/check/{id}/payment-intake', '/admin/tuv/booking/{id}/collect-payment'],
    'TuvSupplierReconciliationController' => ['/admin/tuv/check/{id}/supplier-reconciliation.csv', '/admin/tuv/check/{id}/supplier-reconciliation'],
];
$failures = [];
$count = 0;
foreach ($spec as $class => $routes) {
    $path = $root . '/src/Controller/Admin/' . $class . '.php';
    if (!is_file($path)) { $failures[] = "Datei fehlt: $path"; continue; }
    $source = file_get_contents($path);
    // Require the class-level attribute, not a method-level or commented occurrence.
    $classPattern = '/#\\[IsGranted\\([\'\"]ROLE_SUPER_ADMIN[\'\"]\\)\\]\\s*(?:final\\s+)?class\\s+' . preg_quote($class, '/') . '\\b/s';
    if (!preg_match($classPattern, $source)) $failures[] = "Klassenattribut fehlt: $class";
    foreach ($routes as $route) {
        ++$count;
        if (!str_contains($source, "'" . $route . "'") && !str_contains($source, '"' . $route . '"')) {
            $failures[] = "Route fehlt oder geändert: $class $route";
        }
    }
}
if ($count !== 30) $failures[] = "Erwartet 30 Routen, gefunden: $count";
if ($failures) {
    fwrite(STDERR, "PHASE-2-ROUTENPRÜFUNG: FEHLER\\n" . implode("\\n", $failures) . "\\n");
    exit(1);
}
echo "PHASE-2-ROUTENPRÜFUNG: OK ($count Routen, " . count($spec) . " Controller; nur Quellcodeprüfung)\\n";
