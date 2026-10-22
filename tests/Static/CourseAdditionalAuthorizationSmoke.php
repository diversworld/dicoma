<?php
declare(strict_types=1);
$dir = dirname(__DIR__, 2) . '/_stage/src/Controller/Admin/';
$expected = [
 'CourseAuditController.php' => 1,
 'CourseTimetablePublicationController.php' => 3,
 'ParticipantPassActionController.php' => 1,
 'TrainingProgressController.php' => 1,
 'CourseCalendarController.php' => 1,
];
foreach ($expected as $file => $minimum) {
    $source = file_get_contents($dir . $file);
    if (substr_count($source, "denyAccessUnlessGranted('COURSE_MANAGE'") < $minimum) {
        throw new RuntimeException('Missing authorization: ' . $file);
    }
}
if (!str_contains(file_get_contents($dir . 'InstructorTaskController.php'), "#[IsGranted('ROLE_SUPER_ADMIN')]")) {
    throw new RuntimeException('Unscoped overview is not restricted');
}
echo "Static authorization checks passed\n";
