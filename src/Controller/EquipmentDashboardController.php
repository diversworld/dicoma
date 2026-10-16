<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tank;
use App\Entity\TankMaintenanceOrder;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentDashboardController extends AbstractController
{
    #[Route('/equipment-manager', name: 'equipment_manager_dashboard', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Anmeldung erforderlich.');
        }
        $club = $user->getMember()?->getClub();
        if ($club === null || $club->getId() === null) {
            throw $this->createAccessDeniedException('Kein Verein zugeordnet.');
        }
        $clubId = $club->getId();
        $today = new \DateTimeImmutable('today');
        $until = $today->modify('+30 days');

        $base = $em->createQueryBuilder()
            ->from(Tank::class, 't')
            ->where('IDENTITY(t.club) = :clubId')
            ->setParameter('clubId', $clubId);

        $count = static function (\Doctrine\ORM\QueryBuilder $query): int {
            return (int) $query->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();
        };

        $total = $count(clone $base);
        $ready = $count((clone $base)
            ->andWhere('t.conditionStatus = :ready')
            ->andWhere('t.lendable = :yes')
            ->setParameter('ready', 'ready')
            ->setParameter('yes', true));
        $blocked = $count((clone $base)
            ->andWhere('t.lendable = :no')
            ->setParameter('no', false));
        $damaged = $count((clone $base)
            ->andWhere('t.conditionStatus = :damaged')
            ->setParameter('damaged', 'damaged'));
        $expired = $count((clone $base)
            ->andWhere('t.nextCheckDate < :today')
            ->setParameter('today', $today));
        $dueSoon = $count((clone $base)
            ->andWhere('t.nextCheckDate >= :today')
            ->andWhere('t.nextCheckDate <= :until')
            ->setParameter('today', $today)
            ->setParameter('until', $until));
        $missingDate = $count((clone $base)->andWhere('t.nextCheckDate IS NULL'));

        $openOrders = (int) $em->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(TankMaintenanceOrder::class, 'o')
            ->join('o.tank', 't')
            ->where('IDENTITY(t.club) = :clubId')
            ->andWhere('o.status = :status')
            ->setParameter('clubId', $clubId)
            ->setParameter('status', 'open')
            ->getQuery()->getSingleScalarResult();

        $upcoming = (clone $base)
            ->select('t')
            ->andWhere('t.nextCheckDate IS NOT NULL')
            ->andWhere('t.nextCheckDate <= :until')
            ->setParameter('until', $until)
            ->orderBy('t.nextCheckDate', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()->getResult();

        return $this->render('equipment_manager/dashboard.html.twig', [
            'clubName' => $club->getName(),
            'total' => $total,
            'ready' => $ready,
            'blocked' => $blocked,
            'damaged' => $damaged,
            'openOrders' => $openOrders,
            'expired' => $expired,
            'dueSoon' => $dueSoon,
            'missingDate' => $missingDate,
            'upcoming' => $upcoming,
            'today' => $today,
        ]);
    }
}
