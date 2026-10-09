<?php
namespace App\Repository;

use App\Entity\ScheduleTemplateEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ScheduleTemplateEntry> */
final class ScheduleTemplateEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, ScheduleTemplateEntry::class); }
}
