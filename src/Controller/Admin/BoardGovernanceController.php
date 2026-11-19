<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\BoardMeeting;
use App\Entity\BoardResolution;
use App\Entity\BoardTask;
use App\Entity\ClubDocument;
use App\Entity\ClubDocumentVersion;
use App\Entity\MembershipDunningNotice;
use App\Entity\User;
use App\Service\AdminClubContext;
use App\Service\BoardDashboard;
use App\Service\BoardGovernanceService;
use App\Service\ClubDocumentStorage;
use App\Service\SimplePdfRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_USER')]
final class BoardGovernanceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminClubContext $clubs,
    ) {}

    #[Route('/governance', name: 'admin_governance', methods: ['GET'])]
    public function index(BoardDashboard $dashboard): Response
    {
        $this->assertGovernanceReader();
        $club = $this->clubs->getClub() ?? throw $this->createAccessDeniedException();
        $meetings = $this->em->getRepository(BoardMeeting::class)->createQueryBuilder('meeting')
            ->addSelect('resolutions', 'participants')
            ->leftJoin('meeting.resolutions', 'resolutions')
            ->leftJoin('meeting.participants', 'participants')
            ->where('meeting.club = :club')->setParameter('club', $club)
            ->orderBy('meeting.startsAt', 'DESC')->getQuery()->getResult();
        return $this->render('admin/governance/index.html.twig', [
            'metrics' => $dashboard->forClub($club),
            'meetings' => $meetings,
            'taskStatuses' => BoardTask::statusChoices(),
            'users' => $this->em->getRepository(User::class)->createQueryBuilder('u')
                ->leftJoin('u.member', 'm')
                ->where('m.club = :club')->setParameter('club', $club)->orderBy('u.user', 'ASC')
                ->getQuery()->getResult(),
        ]);
    }

    #[Route('/governance/meetings/{id}/resolutions', name: 'admin_governance_resolution_create', methods: ['POST'])]
    public function createResolution(BoardMeeting $meeting, Request $request, BoardGovernanceService $governance): Response
    {
        $this->assertGovernanceWriter();
        $this->assertCsrf('resolution_'.$meeting->getId(), $request);
        $this->assertMeetingClub($meeting);
        $responsibleId = $request->request->getInt('responsible');
        $responsible = $responsibleId > 0 ? $this->em->find(User::class, $responsibleId) : null;
        $due = $this->dateFromInput($request->request->getString('dueAt'));
        $resolution = new BoardResolution(
            $meeting,
            $request->request->getString('title'),
            $request->request->getString('decision'),
        );
        $resolution->setResponsible($responsible)->setDueAt($due);
        $meeting->addResolution($resolution);
        $this->em->persist($resolution);
        if ($request->request->getBoolean('createTask')) {
            $actor = $this->getUser();
            if (!$actor instanceof User) {
                throw $this->createAccessDeniedException();
            }
            $governance->createTaskFromResolution($resolution, $actor);
        }
        $this->em->flush();
        $this->addFlash('success', 'Beschluss gespeichert.');
        return $this->redirectToRoute('admin_governance');
    }

    #[Route('/governance/resolutions/{id}/task', name: 'admin_governance_resolution_task', methods: ['POST'])]
    public function createTask(BoardResolution $resolution, Request $request, BoardGovernanceService $governance): Response
    {
        $this->assertGovernanceWriter();
        $this->assertCsrf('resolution_task_'.$resolution->getId(), $request);
        $this->assertMeetingClub($resolution->getMeeting());
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $governance->createTaskFromResolution($resolution, $actor);
        $this->em->flush();
        $this->addFlash('success', 'Aufgabe aus Beschluss angelegt.');
        return $this->redirectToRoute('admin_governance');
    }

    #[Route('/documents', name: 'admin_club_documents', methods: ['GET', 'POST'])]
    public function documents(Request $request, ClubDocumentStorage $storage): Response
    {
        $this->assertGovernanceReader();
        $club = $this->clubs->getClub() ?? throw $this->createAccessDeniedException();
        if ($request->isMethod('POST')) {
            $this->assertGovernanceWriter();
            $this->assertCsrf('club_document_upload', $request);
            $file = $request->files->get('document');
            if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                throw $this->createAccessDeniedException('Dokument fehlt.');
            }
            $actor = $this->getUser();
            if (!$actor instanceof User) {
                throw $this->createAccessDeniedException();
            }
            $stored = $storage->store($file);
            try {
                $documentId = $request->request->getInt('documentId');
                $document = $documentId > 0 ? $this->em->find(ClubDocument::class, $documentId) : null;
                if ($document !== null) {
                    if ($document->getClub()->getId() !== $club->getId()) {
                        throw $this->createAccessDeniedException();
                    }
                } else {
                    $document = new ClubDocument($club, $actor, $request->request->getString('title'));
                    $document->setCategory($request->request->getString('category'))->setMemberVisible($request->request->getBoolean('memberVisible'));
                    $this->em->persist($document);
                }
                $latest = $this->em->getRepository(ClubDocumentVersion::class)->createQueryBuilder('version')
                    ->select('MAX(version.version)')->join('version.document', 'document')
                    ->where('document = :document')->setParameter('document', $document)->getQuery()->getSingleScalarResult();
                $version = new ClubDocumentVersion($document, (int) $latest + 1, $stored['storageName'], $stored['originalName'], $stored['mimeType'], $stored['size'], $actor);
                $document->addVersion($version)->setStatus(ClubDocument::STATUS_PENDING);
                $this->em->flush();
            } catch (\Throwable $exception) {
                @unlink($storage->path($stored['storageName']));
                throw $exception;
            }
            $this->addFlash('success', 'Dokumentversion hochgeladen und zur Freigabe vorgelegt.');
            return $this->redirectToRoute('admin_club_documents');
        }
        return $this->render('admin/governance/documents.html.twig', [
            'documents' => $this->em->getRepository(ClubDocument::class)->findBy(['club' => $club], ['id' => 'DESC']),
            'categories' => ClubDocument::categories(),
        ]);
    }

    #[Route('/documents/versions/{id}/approve', name: 'admin_club_document_approve', methods: ['POST'])]
    public function approveDocument(ClubDocumentVersion $version, Request $request): Response
    {
        $this->assertBoardApprover();
        $this->assertCsrf('document_approve_'.$version->getId(), $request);
        $club = $this->clubs->getClub() ?? throw $this->createAccessDeniedException();
        if ($version->getDocument()->getClub()->getId() !== $club->getId()) {
            throw $this->createAccessDeniedException();
        }
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $version->approve($actor);
        $version->getDocument()->setStatus(ClubDocument::STATUS_APPROVED);
        $this->em->flush();
        $this->addFlash('success', 'Dokument freigegeben.');
        return $this->redirectToRoute('admin_club_documents');
    }

    #[Route('/documents/versions/{id}/download', name: 'admin_club_document_download', methods: ['GET'])]
    public function downloadDocument(ClubDocumentVersion $version, ClubDocumentStorage $storage): Response
    {
        $club = $this->clubs->getClub() ?? throw $this->createAccessDeniedException();
        $document = $version->getDocument();
        if ($document->getClub()->getId() !== $club->getId()
        || ($version->getApprovedAt() === null && !$this->isGovernanceReader())
        || (!$document->isMemberVisible() && !$this->isGovernanceReader())) {
            throw $this->createAccessDeniedException();
        }
        $response = new BinaryFileResponse($storage->path($version->getStorageName()));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $version->getOriginalName());
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }

    #[Route('/membership-finance/dunning/{id}.pdf', name: 'admin_membership_dunning_pdf', methods: ['GET'])]
    public function dunningPdf(MembershipDunningNotice $notice, SimplePdfRenderer $pdf): Response
    {
        $this->assertFinanceReader();
        if ($notice->getCharge()->getMember()->getClub()?->getId() !== $this->clubs->getClub()?->getId()) {
            throw $this->createAccessDeniedException();
        }
        return new Response($pdf->render($notice->getSubject(), explode("\n", $notice->getBody())), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="mahnung-'.$notice->getId().'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function assertMeetingClub(BoardMeeting $meeting): void
    {
        if ($meeting->getClub()->getId() !== $this->clubs->getClub()?->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
    private function assertCsrf(string $id, Request $request): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
    }
    private function dateFromInput(string $value): ?\DateTimeImmutable
    {
        if ($value === '') { return null; }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false) { throw $this->createNotFoundException('Ungültige Frist.'); }
        return $date->setTime(23, 59);
    }
    private function isGovernanceReader(): bool { return $this->isGranted(User::ROLE_BOARD) || $this->isGranted(User::ROLE_SECRETARY) || $this->isGranted(User::ROLE_ADMIN); }
    private function assertGovernanceReader(): void { if (!$this->isGovernanceReader()) { throw $this->createAccessDeniedException(); } }
    private function assertGovernanceWriter(): void { $this->assertGovernanceReader(); }
    private function assertBoardApprover(): void { if (!$this->isGranted(User::ROLE_BOARD) && !$this->isGranted(User::ROLE_ADMIN)) { throw $this->createAccessDeniedException(); } }
    private function assertFinanceReader(): void { if (!$this->isGranted(User::ROLE_TREASURER) && !$this->isGranted(User::ROLE_ADMIN)) { throw $this->createAccessDeniedException(); } }
}
