<?php
/** Statischer Smoke-Test: kein Ersatz fuer HTTP-/Symfony-Tests. */
$root = dirname(__DIR__, 2);
$names = ['CoursePlanActionController', 'CourseStatusActionController', 'CourseCompletionWizardController', 'CourseTrainingMatrixController'];
foreach ($names as $name) {
    $code = file_get_contents($root.'/src/Controller/Admin/'.$name.'.php');
    if ($code === false || !str_contains($code, 'CourseManagementVoter::MANAGE') || !str_contains($code, 'denyAccessUnlessGranted')) {
        fwrite(STDERR, $name.": Berechtigungspruefung fehlt\n");
        exit(1);
    }
}
echo "4 direkte Kurscontroller: Voter-Aufrufe vorhanden\n";
