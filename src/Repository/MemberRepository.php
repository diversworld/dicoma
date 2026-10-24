<?php

namespace App\Repository;

use App\Entity\Member;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * @extends ServiceEntityRepository<Member>
 *
 * @method Member|null find($id, $lockMode = null, $lockVersion = null)
 * @method Member|null findOneBy(array $criteria, array $orderBy = null)
 * @method Member[]    findAll()
 * @method Member[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly RoleHierarchyInterface $roleHierarchy)
    {
        parent::__construct($registry, Member::class);
    }

    /** @return list<Member> */
    public function findInstructors(?\App\Entity\Club $club = null): array
    {
        return array_values(array_filter($this->findOrderedByName($club), fn (Member $member): bool =>
            $member->getUser() !== null && in_array(\App\Entity\User::ROLE_INSTRUCTOR,
                $this->roleHierarchy->getReachableRoleNames($member->getUser()->getRoles()), true)
        ));
    }

    /**
     * @return Member[] Returns an array of User objects
     */
    public function findByuser($value): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.user = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
            ;
    }
    
        public function findByUserId($value): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.user = :val')
            ->setParameter('val', $value)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
            ;
    }
    
    public function findById($value): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.id = :val')
            ->setParameter('val', $value)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult()
            ;
    }
    /**
     * @return Member[] Returns an array of Instructor objects
     */
    public function findByCategory($value): array
    {
        return $this->createQueryBuilder('i')
            ->join('i.person', 'person')
            ->andWhere('i.category = :val')
            ->setParameter('val', $value)
            ->orderBy('person.lastname', 'ASC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult()
        ;
    }

    /** @return list<Member> */
    public function findOrderedByName(?\App\Entity\Club $club = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->join('m.person', 'person')->addSelect('person')
            ->orderBy('person.lastname', 'ASC')
            ->addOrderBy('person.firstname', 'ASC');
        if ($club !== null) {
            $qb->andWhere('m.club = :club')->setParameter('club', $club);
        }
        return $qb->getQuery()->getResult();
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
