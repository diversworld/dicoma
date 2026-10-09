<?php
namespace App\Repository;

use App\Entity\ScheduleTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ScheduleTemplate> */
final class ScheduleTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, ScheduleTemplate::class); }
}
