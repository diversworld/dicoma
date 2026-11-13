<?php
namespace App\Repository;

use App\Entity\ScheduleTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ScheduleTemplate> */
final class ScheduleTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { 
        parent::__construct($registry, ScheduleTemplate::class); 
    }
    
    /**
     * @return Courses[] Returns an array of Courses objects
     */
    public function findByClub($value): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.club = :val')
            ->setParameter('val', $value)
            ->orderBy('c.club', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
}
