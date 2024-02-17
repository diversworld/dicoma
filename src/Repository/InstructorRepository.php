<?php

namespace App\Repository;

use App\Entity\Instructor;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * @extends ServiceEntityRepository<Instructor>
 *
 * @method Instructor|null find($id, $lockMode = null, $lockVersion = null)
 * @method Instructor|null findOneBy(array $criteria, array $orderBy = null)
 * @method Instructor[]    findAll()
 * @method Instructor[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InstructorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @return User[] Returns an array of Instructor objects
     */
    public function findByStatus($value, $max): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status = :val')
            ->andWhere('i.category = :category')
            ->setParameter('val', $value)
            ->setParameter('category', 'instructor')
            ->orderBy('i.id', 'ASC')
            ->setMaxResults($max)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $instructor, string $newHashedPassword): void
    {
        if (!$instructor instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $instructor::class));
        }

        $instructor->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($instructor);
        $this->getEntityManager()->flush();
    }

//    public function findOneBySomeField($value): ?Instructor
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
