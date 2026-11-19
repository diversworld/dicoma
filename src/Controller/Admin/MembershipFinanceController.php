<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MembershipCharge;
use App\Entity\MembershipPayment;
use App\Entity\User;
use App\Enum\PermissionAction;
use App\Enum\PermissionModule;
use App\Security\ModuleAccess;
use App\Service\AdminClubContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/membership-finance')]
#[IsGranted('ROLE_USER')]
final class MembershipFinanceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminClubContext $clubs,
        private readonly ModuleAccess $access,
    ) {}

    #[Route('', name: 'admin_membership_finance', methods: ['GET'])]
    public function index(): Response
    {
        $club = $this->clubs->getClub();
        $user = $this->getUser();
        if (!$club instanceof \App\Entity\Club || !$user instanceof User
            || !$this->access->can($user, $club, PermissionModule::FINANCE, PermissionAction::READ)) {
            throw $this->createAccessDeniedException();
        }
        $charges = $this->em->getRepository(MembershipCharge::class)->createQueryBuilder('charge')
            ->join('charge.member', 'm')->addSelect('m')
            ->where('m.club = :club')
            ->setParameter('club', $club)
            ->orderBy('charge.dueAt', 'ASC')->addOrderBy('charge.id', 'ASC')
            ->getQuery()->getResult();
        $openCents = 0; $overdueCents = 0; $paidCents = 0;
        $rows = [];
        foreach ($charges as $charge) {
            $openCents += $charge->getOutstandingCents();
            $paidCents += $charge->getPaidCents();
            if ($charge->getStatus() === 'overdue') { $overdueCents += $charge->getOutstandingCents(); }
            $rows[] = [
                'charge' => $charge,
                'payments' => $this->em->getRepository(MembershipPayment::class)->findBy(['charge' => $charge], ['id' => 'ASC']),
            ];
        }
        return $this->render('admin/membership/finance.html.twig', [
            'rows' => $rows,
            'openCents' => $openCents,
            'overdueCents' => $overdueCents,
            'paidCents' => $paidCents,
        ]);
    }
}
