<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tank;
use App\Entity\EquipmentItem;
use App\Entity\Regulator;
use App\Entity\TankMaintenanceOrder;
use App\Entity\User;
use App\Service\EquipmentInventory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class EquipmentDashboardController extends AbstractController
{
    use EquipmentManagerClubTrait;
    #[Route('/equipment-manager', name: 'equipment_manager_dashboard', methods: ['GET'])]
    public function index(EntityManagerInterface $em, EquipmentInventory $inventory): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Anmeldung erforderlich.');
        }
        $club = $this->equipmentClub();
        $clubId = $this->equipmentClubId();
        $today = new \DateTimeImmutable('today');
        $until = $today->modify('+30 days');

        $assets = [];
        foreach ([EquipmentItem::class, Regulator::class, Tank::class] as $class) {
            array_push($assets, ...$em->getRepository($class)->findBy(['club' => $club], ['id' => 'ASC']));
        }
        $total = count($assets);
        $ready = 0; $blocked = 0; $damaged = 0; $expired = 0; $dueSoon = 0; $missingDate = 0;
        $upcoming = [];
        foreach ($assets as $asset) {
            if ($asset->getConditionStatus() === 'ready' && $asset->isLendable()) { ++$ready; }
            if (!$asset->isLendable()) { ++$blocked; }
            if ($asset->getConditionStatus() === 'damaged') { ++$damaged; }
            $due = $inventory->dueAt($asset);
            if ($due === null) {
                ++$missingDate;
                continue;
            }
            if ($due->format('Y-m-d') < $today->format('Y-m-d')) {
                ++$expired;
            } elseif ($due->format('Y-m-d') <= $until->format('Y-m-d')) {
                ++$dueSoon;
            }
            if ($due->format('Y-m-d') <= $until->format('Y-m-d')) {
                $upcoming[] = ['asset' => $asset, 'due' => $due, 'type' => $asset instanceof EquipmentItem ? 'equipment' : ($asset instanceof Regulator ? 'regulator' : 'tank')];
            }
        }
        usort($upcoming, static fn (array $a, array $b): int => ($a['due'] <=> $b['due']) ?: ($a['asset']->getId() <=> $b['asset']->getId()));
        $upcoming = array_slice($upcoming, 0, 10);

        $openOrders = (int) $em->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(TankMaintenanceOrder::class, 'o')
            ->leftJoin('o.tank', 't')->leftJoin('o.regulator', 'r')->leftJoin('o.equipmentItem', 'e')
            ->where('(t.club = :club OR r.club = :club OR e.club = :club)')
            ->andWhere('o.status = :status')
            ->setParameter('club', $club)
            ->setParameter('status', 'open')
            ->getQuery()->getSingleScalarResult();

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
