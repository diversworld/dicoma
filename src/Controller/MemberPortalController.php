<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MemberTank;
use App\Entity\MemberTankCheckBooking;
use App\Entity\TankCheck;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MEMBER')]
final class MemberPortalController extends AbstractController
{
    #[Route('/my', name: 'member_portal_dashboard', methods: ['GET'])]
    public function dashboard(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || ($member = $user->getMember()) === null) {
            throw $this->createAccessDeniedException('Kein Mitgliedsprofil zugeordnet.');
        }
        $tanks = $em->getRepository(MemberTank::class)->findBy(['member' => $member], ['id' => 'DESC']);
        $bookings = $em->getRepository(MemberTankCheckBooking::class)->findBy(['member' => $member], ['id' => 'DESC'], 6);
        $upcoming = $em->createQueryBuilder()->select('c')->from(TankCheck::class, 'c')
            ->where('c.isPublished = true')->andWhere('c.checkDate >= :today')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('c.checkDate', 'ASC')->setMaxResults(3)->getQuery()->getResult();
        $dueCount = 0;
        foreach ($tanks as $tank) {
            if (in_array($tank->getInspectionDueStatus(), ['Überfällig', 'Bald fällig'], true)) {
                ++$dueCount;
            }
        }
        return $this->render('member_portal/dashboard.html.twig', [
            'tankCount' => count($tanks), 'dueCount' => $dueCount,
            'bookings' => $bookings, 'upcoming' => $upcoming,
        ]);
    }
}
