<?php
// Statischer Regressionstest ohne Symfony-Bootstrap; kein Ersatz fuer HTTP-Tests.
$source = file_get_contents(__DIR__ . '/../../src/Controller/Admin/CoursesCrudController.php');
$checks = [
    'P11 Listenfilter' => "entity.club = :managedClub",
    'P12 Voter' => 'CourseManagementVoter::MANAGE',
    'P13 Club-Bindung' => 'enforceCourseClub',
    'P14 Aktionsschutz' => 'assertCourseManage($course)',
    'P15 CRUD-Schutz' => 'assertCourseManage($entityInstance)',
];
foreach ($checks as $label => $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "FEHLER: $label\n"); exit(1);
    }
    echo "OK: $label\n";
}
