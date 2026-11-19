<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MembershipCharge;
use App\Entity\MembershipPayment;
use App\Entity\MembershipDunningNotice;
use App\Entity\User;
use App\Enum\PermissionAction;
use App\Enum\PermissionModule;
use App\Security\ModuleAccess;
use App\Service\AdminClubContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
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
    public function index(Request $request): Response
    {
        $club = $this->clubs->getClub();
        $user = $this->getUser();
        if (!$club instanceof \App\Entity\Club || !$user instanceof User
            || !$this->access->can($user, $club, PermissionModule::FINANCE, PermissionAction::READ)) {
            throw $this->createAccessDeniedException();
        }
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;
        $query = $this->em->getRepository(MembershipCharge::class)->createQueryBuilder('charge')
            ->join('charge.member', 'm')->addSelect('m')
            ->where('m.club = :club')
            ->setParameter('club', $club)
            ->orderBy('charge.dueAt', 'ASC')->addOrderBy('charge.id', 'ASC');
        $summary = (clone $query)->select(
            'SUM(charge.amountCents - charge.paidCents) AS openCents',
            'SUM(charge.paidCents) AS paidCents',
        )->getQuery()->getSingleResult();
        $overdue = (clone $query)->select('SUM(charge.amountCents - charge.paidCents) AS cents')
            ->andWhere('charge.dueAt < :today AND charge.amountCents > charge.paidCents')
            ->setParameter('today', new \DateTimeImmutable('today'))->getQuery()->getSingleResult();
        $total = (int) (clone $query)->select('COUNT(charge.id)')->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($total / $limit));
        $page = min($page, $pages);
        $charges = $query->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->getQuery()->getResult();
        $openCents = 0; $overdueCents = 0; $paidCents = 0;
        $payments = [];
        $notices = [];
        if ($charges !== []) {
            foreach ($this->em->getRepository(MembershipPayment::class)->createQueryBuilder('payment')
                ->where('payment.charge IN (:charges)')->setParameter('charges', $charges)
                ->orderBy('payment.receivedAt', 'ASC')->getQuery()->getResult() as $payment) {
                $payments[$payment->getCharge()->getId()][] = $payment;
            }
            foreach ($this->em->getRepository(MembershipDunningNotice::class)->createQueryBuilder('notice')
                ->where('notice.charge IN (:charges)')->setParameter('charges', $charges)
                ->orderBy('notice.createdAt', 'DESC')->getQuery()->getResult() as $notice) {
                $notices[$notice->getCharge()->getId()][] = $notice;
            }
        }
        $rows = [];
        foreach ($charges as $charge) {
            $rows[] = [
                'charge' => $charge,
                'payments' => $payments[$charge->getId()] ?? [],
                'notices' => $notices[$charge->getId()] ?? [],
            ];
        }
        return $this->render('admin/membership/finance.html.twig', [
            'rows' => $rows,
            'openCents' => (int) ($summary['openCents'] ?? 0),
            'overdueCents' => (int) ($overdue['cents'] ?? 0),
            'paidCents' => (int) ($summary['paidCents'] ?? 0),
            'page' => $page,
            'pages' => $pages,
        ]);
    }
}
