<?php

namespace App\Repository;

use App\Entity\Student;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Student>
 *
 * @method Student|null find($id, $lockMode = null, $lockVersion = null)
 * @method Student|null findOneBy(array $criteria, array $orderBy = null)
 * @method Student[]    findAll()
 * @method Student[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StudentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @return User[] Returns an array of Student objects
     */
    public function findByStatus($value, $max): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :val' )
            ->andWhere('s.category = :category')
            ->setParameter('val', $value)
            ->setParameter('category', 'student')
            ->orderBy('s.id', 'ASC')
            ->setMaxResults($max)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return User[] Returns an array of Student objects
     */
    public function findByCategory($value, $max): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.category = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults($max)
            ->getQuery()
            ->getResult()
            ;
    }

    public function findOneByLastname($value): ?Student
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.lastname = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
