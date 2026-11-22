<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AuditEvent;
use App\Entity\PrivacyPolicy;
use App\Entity\User;
use App\Enum\PermissionAction;
use App\Enum\PermissionModule;
use App\Security\ModuleAccess;
use App\Service\AdminClubContext;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/privacy')]
#[IsGranted('ROLE_USER')]
final class PrivacyController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminClubContext $clubs,
        private readonly ModuleAccess $access,
        private readonly AuditLogger $audit,
    ) {}

    #[Route('', name: 'admin_privacy', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $club = $this->clubs->getClub() ?? throw $this->createAccessDeniedException();
        $user = $this->getUser();
        if (!$user instanceof User || !$this->access->can($user, $club, PermissionModule::AUDIT, PermissionAction::READ)) { throw $this->createAccessDeniedException(); }
        $policy = $this->em->getRepository(PrivacyPolicy::class)->findOneBy(['club' => $club]) ?? new PrivacyPolicy($club);
        if ($request->isMethod('POST')) {
            if (!$this->access->can($user, $club, PermissionModule::AUDIT, PermissionAction::EDIT)) { throw $this->createAccessDeniedException(); }
            if (!$this->isCsrfTokenValid('privacy_policy', $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
            $policy->setMemberRetentionYears($request->request->getInt('memberRetentionYears'));
            $policy->setMessageRetentionDays($request->request->getInt('messageRetentionDays'));
            $policy->setFinancialRetentionYears($request->request->getInt('financialRetentionYears'));
            $policy->setAuditRetentionYears($request->request->getInt('auditRetentionYears'));
            $policy->setPurgeEnabled($request->request->getBoolean('purgeEnabled'));
            $this->em->persist($policy);
            $this->audit->record($club, $user->getUserIdentifier(), 'privacy.policy.updated', $policy, [
                'memberYears' => $policy->getMemberRetentionYears(),
                'messageDays' => $policy->getMessageRetentionDays(),
                'financialYears' => $policy->getFinancialRetentionYears(),
                'auditYears' => $policy->getAuditRetentionYears(),
            ]);
            $this->em->flush();
            $this->addFlash('success', 'Aufbewahrungsrichtlinie gespeichert.');
            return $this->redirectToRoute('admin_privacy');
        }
        return $this->render('admin/privacy/index.html.twig', [
            'policy' => $policy,
            'audit' => $this->em->getRepository(AuditEvent::class)->findBy(['club' => $club], ['occurredAt' => 'DESC'], 100),
        ]);
    }
}
