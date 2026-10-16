<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tank;
use App\Entity\TankMaintenanceOrder;
use App\Entity\TankConditionHistory;
use App\Entity\User;
use App\Service\TankMaintenanceService;
use App\Service\TankStatusWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Vereinsgebundene Gerätewart-Funktionen im Frontend; kein EasyAdmin-Zugriff. */
#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentManagerController extends AbstractController
{
    private const PAGE_SIZE = 25;

    private function clubId(): int
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Anmeldung erforderlich.');
        }
        $id = $user->getMember()?->getClub()?->getId();
        if ($id === null) {
            throw $this->createAccessDeniedException('Kein Verein zugeordnet.');
        }
        return $id;
    }

    private function scopedTank(EntityManagerInterface $em, int $id): Tank
    {
        $tank = $em->getRepository(Tank::class)->find($id);
        if (!$tank || $tank->getClub()?->getId() !== $this->clubId()) {
            throw $this->createNotFoundException('Flasche nicht gefunden.');
        }
        return $tank;
    }

    #[Route('/equipment-manager/tanks/{id}', name: 'equipment_manager_tank_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function tankDetail(int $id, EntityManagerInterface $em): Response
    {
        $tank = $this->scopedTank($em, $id);
        $history = $em->getRepository(TankConditionHistory::class)->findBy(
            ['tank' => $tank], ['occurredAt' => 'DESC', 'id' => 'DESC'], 100
        );
        $orders = $em->getRepository(TankMaintenanceOrder::class)->findBy(
            ['tank' => $tank], ['id' => 'DESC'], 20
        );
        return $this->render('equipment_manager/tank_detail.html.twig', [
            'tank' => $tank,
            'history' => $history,
            'orders' => $orders,
            'clubName' => $tank->getClub()->getName(),
        ]);
    }

    #[Route('/equipment-manager/tanks/{id}/maintenance', name: 'equipment_manager_maintenance', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function maintenance(int $id, EntityManagerInterface $em): Response
    {
        $tank = $this->scopedTank($em, $id);
        $orders = $em->getRepository(TankMaintenanceOrder::class)->findBy(['tank' => $tank], ['id' => 'DESC'], 100);
        return $this->render('equipment_manager/maintenance.html.twig', [
            'tank' => $tank,
            'orders' => $orders,
            'clubName' => $tank->getClub()->getName(),
        ]);
    }

    #[Route('/equipment-manager/tanks/{id}/maintenance', name: 'equipment_manager_maintenance_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function maintenanceSubmit(int $id, Request $request, EntityManagerInterface $em, TankMaintenanceService $service): Response
    {
        $tank = $this->scopedTank($em, $id);
        if (!$this->isCsrfTokenValid('equipment_maintenance_'.$id, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        $actor = $this->getUser()->getUserIdentifier();
        $operation = $request->request->get('operation');
        try {
            if ($operation === 'open') {
                $service->open($id, (string) $request->request->get('description', ''), $actor, $this->clubId());
                $this->addFlash('success', 'Wartungsauftrag eröffnet; Flasche gesperrt.');
            } elseif ($operation === 'close') {
                $rawId = $request->request->get('order_id');
                if (!is_string($rawId) || !ctype_digit($rawId) || (int) $rawId < 1) {
                    throw new \DomainException('Ungültige Auftragsnummer.');
                }
                $service->closeForTank($tank->getId(), (int) $rawId, (string) $request->request->get('resolution', ''), $actor, $this->clubId());
                $this->addFlash('success', 'Wartungsauftrag abgeschlossen. Die Flasche bleibt gesperrt.');
            } else {
                throw new \DomainException('Unbekannte Aktion.');
            }
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
        return $this->redirectToRoute('equipment_manager_maintenance', ['id' => $id], Response::HTTP_SEE_OTHER);
    }


    #[Route('/equipment-manager/tanks/{id}/block', name: 'equipment_manager_tank_block', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function block(int $id, Request $request, EntityManagerInterface $em, TankStatusWorkflow $workflow): Response
    {
        $this->scopedTank($em, $id);
        if (!$this->isCsrfTokenValid('equipment_block_'.$id, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        $operation = $request->request->get('operation');
        if (!is_string($operation) || !in_array($operation, ['maintenance', 'damaged'], true)) {
            $this->addFlash('danger', 'Ungültiger Sperrgrund.');
            return $this->redirectToRoute('equipment_manager_tank_detail', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        try {
            $workflow->apply(
                $id,
                $operation,
                (string) $request->request->get('reason', ''),
                $this->getUser()->getUserIdentifier(),
                $this->clubId()
            );
            $this->addFlash('success', 'Technischer Zustand aktualisiert; Flasche für den Verleih gesperrt.');
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
        return $this->redirectToRoute('equipment_manager_tank_detail', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/equipment-manager/tanks/{id}/release', name: 'equipment_manager_tank_release', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function release(int $id, Request $request, EntityManagerInterface $em, TankStatusWorkflow $workflow): Response
    {
        $this->scopedTank($em, $id);
        if (!$this->isCsrfTokenValid('equipment_release_'.$id, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        try {
            $workflow->apply($id, 'release', (string) $request->request->get('reason', ''),
                $this->getUser()->getUserIdentifier(), $this->clubId());
            $this->addFlash('success', 'Flasche wurde für den Verleih freigegeben.');
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
        return $this->redirectToRoute('equipment_manager_tank_detail', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/equipment-manager/tanks', name: 'equipment_manager_tanks', methods: ['GET'])]
    public function tanks(Request $request, EntityManagerInterface $em): Response
    {
        $club = $this->getUser()->getMember()->getClub();
        $this->clubId();

        $filter = $request->query->get('filter', 'all');
        if (!is_string($filter) || !in_array($filter, ['all', 'ready', 'blocked', 'damaged', 'open'], true)) {
            throw $this->createNotFoundException('Unbekannter Flaschenfilter.');
        }

        $rawPage = $request->query->get('page', '1');
        if (!is_string($rawPage) || !ctype_digit($rawPage) || strlen($rawPage) > 8) {
            throw $this->createNotFoundException('Ungültige Seitenzahl.');
        }
        $page = max(1, (int) $rawPage);
        $clubId = $club->getId();

        // Ein gemeinsamer, vereinsgebundener Filter für Zählung und Seitenabfrage.
        $query = $em->createQueryBuilder()
            ->from(Tank::class, 't')
            ->where('IDENTITY(t.club) = :clubId')
            ->setParameter('clubId', $clubId);

        switch ($filter) {
            case 'ready':
                $query->andWhere('t.conditionStatus = :readyStatus')
                    ->andWhere('t.lendable = :lendable')
                    ->setParameter('readyStatus', 'ready')
                    ->setParameter('lendable', true);
                break;
            case 'blocked':
                $query->andWhere('t.lendable = :lendable')
                    ->setParameter('lendable', false);
                break;
            case 'damaged':
                $query->andWhere('t.conditionStatus = :damagedStatus')
                    ->setParameter('damagedStatus', 'damaged');
                break;
            case 'open':
                $subquery = $em->createQueryBuilder()
                    ->select('o.id')
                    ->from(TankMaintenanceOrder::class, 'o')
                    ->where('o.tank = t')
                    ->andWhere('o.status = :openStatus')
                    ->getDQL();
                $query->andWhere($query->expr()->exists($subquery))
                    ->setParameter('openStatus', 'open');
                break;
        }

        $count = (int) (clone $query)
            ->select('COUNT(t.id)')
            ->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($count / self::PAGE_SIZE));
        if ($page > $pages) {
            throw $this->createNotFoundException('Seite nicht vorhanden.');
        }

        $tanks = (clone $query)
            ->select('t')
            ->orderBy('t.inventory', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE)
            ->getQuery()->getResult();

        $ids = array_map(static fn (Tank $tank): int => $tank->getId(), $tanks);
        $openCounts = [];
        if ($ids !== []) {
            $rows = $em->createQueryBuilder()
                ->select('IDENTITY(o.tank) AS tankId, COUNT(o.id) AS orderCount')
                ->from(TankMaintenanceOrder::class, 'o')
                ->join('o.tank', 't')
                ->where('o.status = :status')
                ->andWhere('t.id IN (:tankIds)')
                ->andWhere('IDENTITY(t.club) = :clubId')
                ->setParameter('status', 'open')
                ->setParameter('tankIds', $ids)
                ->setParameter('clubId', $clubId)
                ->groupBy('o.tank')
                ->getQuery()->getArrayResult();
            foreach ($rows as $row) {
                $openCounts[(int) $row['tankId']] = (int) $row['orderCount'];
            }
        }

        return $this->render('equipment_manager/tanks.html.twig', [
            'tanks' => $tanks,
            'openCounts' => $openCounts,
            'clubName' => $club->getName(),
            'page' => $page,
            'pages' => $pages,
            'total' => $count,
            'filter' => $filter,
        ]);
    }
}
