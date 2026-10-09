<?php

namespace App\Repository;

use App\Entity\CourseTrainingUnit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CourseTrainingUnitRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct(
            $registry,
            CourseTrainingUnit::class
        );
    }
}