<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MembershipCharge;
use App\Entity\MembershipPayment;
use App\Entity\MembershipDunningNotice;
use App\Entity\SepaDirectDebitBatch;
use App\Entity\User;
use App\Enum\PermissionAction;
use App\Enum\PermissionModule;
use App\Security\ModuleAccess;
use App\Service\AdminClubContext;
use App\Service\SepaDirectDebitService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

#[Route('/admin/membership-finance', defaults: DashboardController::ROUTE_DEFAULTS)]
#[IsGranted('ROLE_USER')]
final class MembershipFinanceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminClubContext $clubs,
        private readonly ModuleAccess $access,
        private readonly LoggerInterface $logger,
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
            'sepaBatches' => $this->em->getRepository(SepaDirectDebitBatch::class)->findBy(['club' => $club], ['id' => 'DESC'], 20),
            'canFinanceWrite' => $this->access->can($user, $club, PermissionModule::FINANCE, PermissionAction::EDIT),
            'sepaMinimumDate' => $this->secondBankWorkingDay()->format('Y-m-d'),
            'sepaSuggestedDate' => $this->secondBankWorkingDay()->format('Y-m-d'),
        ]);
    }

    #[Route('/sepa', name: 'admin_membership_sepa_create', methods: ['POST'])]
    public function createSepa(Request $request, SepaDirectDebitService $sepa): Response
    {
        $club = $this->clubs->getClub() ?? throw $this->createAccessDeniedException();
        $user = $this->getUser();
        if (!$user instanceof User || !$this->access->can($user, $club, PermissionModule::FINANCE, PermissionAction::CREATE)) { throw $this->createAccessDeniedException(); }
        if (!$this->isCsrfTokenValid('sepa_batch_create', $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
        try {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->request->getString('collectionDate'));
            if ($date === false) {
                throw new \DomainException('Ungültiges Einzugsdatum.');
            }
            $chargeIds = array_map('intval', $request->request->all('charges'));
            $sepa->create($club, $user, $request->request->getString('batchKey'), $date, $chargeIds);
            $this->em->flush();
            $this->addFlash('success', 'Lastschriftlauf als Entwurf angelegt.');
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }
        return $this->redirectToRoute('admin_membership_finance');
    }

    #[Route('/sepa/{id}/approve', name: 'admin_membership_sepa_approve', methods: ['POST'])]
    public function approveSepa(SepaDirectDebitBatch $batch, Request $request, SepaDirectDebitService $sepa): Response
    {
        $club = $this->clubs->getClub() ?? throw $this->createAccessDeniedException();
        $user = $this->getUser();
        if (!$user instanceof User || $batch->getClub()->getId() !== $club->getId()
            || !$this->access->can($user, $club, PermissionModule::FINANCE, PermissionAction::EDIT)) { throw $this->createAccessDeniedException(); }
        if (!$this->isCsrfTokenValid('sepa_batch_approve_'.$batch->getId(), $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
        try {
            $sepa->approve($batch, $user);
            $this->em->flush();
            $this->addFlash('success', 'Lastschriftlauf nach Vier-Augen-Prinzip freigegeben.');
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }
        return $this->redirectToRoute('admin_membership_finance');
    }

    #[Route('/sepa/{id}.xml', name: 'admin_membership_sepa_export', methods: ['POST'])]
    public function exportSepa(SepaDirectDebitBatch $batch, Request $request, SepaDirectDebitService $sepa): Response
    {
        $club = $this->clubs->getClub() ?? throw $this->createAccessDeniedException();
        $user = $this->getUser();
        if (!$user instanceof User || $batch->getClub()->getId() !== $club->getId()
            || !$this->access->can($user, $club, PermissionModule::FINANCE, PermissionAction::EDIT)) { throw $this->createAccessDeniedException(); }
        if (!$this->isCsrfTokenValid('sepa_batch_export_'.$batch->getId(), $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
        try {
            $result = $sepa->export($batch, $user);
            $this->em->flush();
            return new Response($result['xml'], 200, [
                'Content-Type' => 'application/xml',
                'Content-Disposition' => 'attachment; filename="pain.008-'.$batch->getId().'.xml"',
                'Cache-Control' => 'private, no-store',
                'X-Content-SHA256' => $result['sha256'],
            ]);
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        } catch (\RuntimeException $exception) {
            $this->logger->error('SEPA pain.008 export failed.', [
                'batchId' => $batch->getId(),
                'exception' => $exception,
            ]);
            $this->addFlash('danger', 'Die SEPA-Datei konnte nicht erzeugt werden. Mandats- und Gläubigerdaten sowie den Verschlüsselungsschlüssel prüfen.');
        }
        return $this->redirectToRoute('admin_membership_finance');
    }

    private function secondBankWorkingDay(): \DateTimeImmutable
    {
        $date = new \DateTimeImmutable('today');
        $workingDays = 0;
        while ($workingDays < 2) {
            $date = $date->modify('+1 day');
            if (!in_array((int) $date->format('N'), [6, 7], true)) {
                ++$workingDays;
            }
        }
        return $date;
    }
}
