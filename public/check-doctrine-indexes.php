
<?php

require __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;

$kernel = new Kernel('prod', false);
$kernel->boot();

$em = $kernel->getContainer()
    ->get(EntityManagerInterface::class);

$entities = [
    App\Entity\CourseTrainingUnit::class,
    App\Entity\QualificationTrainingUnit::class,
    App\Entity\Schedule::class,
    App\Entity\ScheduleTemplateEntry::class,
    App\Entity\TrainingUnitResult::class,
    App\Entity\TrainingUnitType::class,
];

foreach ($entities as $entityClass) {
    $metadata = $em->getClassMetadata($entityClass);

    echo PHP_EOL . $entityClass . PHP_EOL;

    print_r(
        $metadata->table['uniqueConstraints'] ?? []
    );
}
