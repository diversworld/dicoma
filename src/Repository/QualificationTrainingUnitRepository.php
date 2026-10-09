<?php

namespace App\Repository;

use App\Entity\QualificationTrainingUnit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class QualificationTrainingUnitRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct(
            $registry,
            QualificationTrainingUnit::class
        );
    }
}