<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\{Club, ClubEvent, ClubEventDocument, ClubEventPayment, ClubEventRegistration, FileAction, MediaFile, User};
use App\Enum\{PermissionAction, PermissionModule};
use App\Form\ClubEventType;
use App\Form\MediaImageType;
use App\Security\{EditorialAccess, FolderPermissions, ModuleAccess};
use App\Service\{AdminClubContext, ClubEventBooking, FrontendCourseRegistrationService, MediaFileStorage, MembershipRoleContinuity};
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/admin/club-events')]
#[IsGranted('ROLE_USER')]
final class ClubEventAdminController extends AbstractController
{
    public function __construct(
        private readonly AdminClubContext $clubContext,
        private readonly EditorialAccess $editorial,
        private readonly ModuleAccess $modules,
        private readonly FolderPermissions $folders,
        private readonly MembershipRoleContinuity $continuity,
        #[Autowire('%env(bool:CLUB_FUNCTION_MEMBERSHIP_ENABLED)%')] private readonly bool $enabled,
    ) {}

    #[Route('', name: 'admin_club_events', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $this->requireWebsite(PermissionAction::READ);
        return $this->render('club_event/admin/index.html.twig', [
            'events' => $em->getRepository(ClubEvent::class)->findBy(['club' => $this->club()], ['startsAt' => 'DESC']),
            'canCreate' => $this->canWebsite(PermissionAction::CREATE),
        ]);
    }

    #[Route('/new', name: 'admin_club_event_new', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        return $this->withWriteLock($request, $em, fn (): Response => $this->createEvent($request, $em));
    }

    private function createEvent(Request $request, EntityManagerInterface $em): Response
    {
        $this->requireWebsite(PermissionAction::CREATE);
        $event = new ClubEvent($this->club());
        $form = $this->createForm(ClubEventType::class, $event, ['csrf_token_id' => 'club_event_'.$event->getClub()->getId()]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($event); $em->flush();
            return $this->eventRedirect($event);
        }
        return $this->render('club_event/admin/form.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'admin_club_event_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        try {
            return $this->withWriteLock($request, $em, fn (): Response => $this->editEvent($id, $request, $em));
        } catch (\DomainException $e) {
            $this->addFlash('warning', $e->getMessage());
            return $this->redirectToRoute('admin_club_event_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }
    }

    private function editEvent(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->requireWebsite(PermissionAction::EDIT);
        $event = $this->event($id, $em);
        $draft = clone $event;
        $form = $this->createForm(ClubEventType::class, $draft, [
            'csrf_token_id' => 'club_event_'.$event->getClub()->getId(),
            'image_locked' => !$this->canReadImage($event),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->getConnection()->transactional(function () use ($em, $event, $draft, $form): void {
                    $em->refresh($event, LockMode::PESSIMISTIC_WRITE);
                    $registrations = $em->getRepository(ClubEventRegistration::class)->createQueryBuilder('r')
                        ->where('r.event = :event')->setParameter('event', $event)
                        ->setMaxResults(1)->getQuery()->setLockMode(LockMode::PESSIMISTIC_READ)->getResult();
                    if ($event->isCancelled() || $registrations !== []) {
                        throw new \DomainException('Eine abgesagte Veranstaltung oder Veranstaltung mit Anmeldungen kann nicht geändert werden.');
                    }
                    if (!$form->has('image')) {
                        $draft->setImage($event->getImage());
                    } elseif (!$this->canReadImage($event)) {
                        throw $this->createAccessDeniedException('Keine Leseberechtigung für das bisherige Bild.');
                    }
                    $event->copyDetailsFrom($draft); $em->flush();
                });
                return $this->eventRedirect($event);
            } catch (\DomainException $e) {
                if ($this->enabled) { throw $e; }
                $this->addFlash('warning', $e->getMessage());
            }
        }
        return $this->render('club_event/admin/form.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'admin_club_event', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id, EntityManagerInterface $em, ClubEventBooking $booking): Response
    {
        $this->requireWebsite(PermissionAction::READ);
        $event = $this->event($id, $em);
        $canMembers = $this->canModule(PermissionModule::MEMBERSHIP, PermissionAction::READ);
        $canFinance = $canMembers && $this->canModule(PermissionModule::FINANCE, PermissionAction::READ);
        $rows = [];
        if ($canMembers) {
            foreach ($em->getRepository(ClubEventRegistration::class)->findBy(['event' => $event], ['registeredAt' => 'ASC', 'id' => 'ASC']) as $registration) {
                $rows[] = ['registration' => $registration, 'paid' => $canFinance ? $booking->paid($registration) : null,
                    'payments' => $canFinance ? $em->getRepository(ClubEventPayment::class)->findBy(['registration' => $registration], ['id' => 'ASC']) : []];
            }
        }
        return $this->render('club_event/admin/detail.html.twig', ['event' => $event, 'rows' => $rows,
            'documents' => $canFinance ? $em->getRepository(ClubEventDocument::class)->findBy(['event' => $event], ['id' => 'DESC']) : [],
            'canMembers' => $canMembers, 'canFinance' => $canFinance,
            'canCreate' => $this->canWebsite(PermissionAction::CREATE),
            'canEdit' => $this->canWebsite(PermissionAction::EDIT),
            'canDelete' => $this->canWebsite(PermissionAction::DELETE),
            'canPay' => $canFinance && $this->canModule(PermissionModule::FINANCE, PermissionAction::CREATE),
            'canEditEvent' => $this->canWebsite(PermissionAction::EDIT) && $em->getRepository(ClubEventRegistration::class)->count(['event' => $event]) === 0,
            'canReadImage' => $this->canReadImage($event),
        ]);
    }

    #[Route('/{id}/documents', name: 'admin_club_event_document_upload', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function uploadDocument(int $id, Request $request, EntityManagerInterface $em, MediaFileStorage $storage): Response
    {
        $file = null;
        try {
            return $this->withWriteLock($request, $em, function () use ($id, $request, $em, $storage, &$file): Response {
                return $this->uploadEventDocument($id, $request, $em, $storage, $file);
            });
        } catch (\Throwable $exception) {
            if ($file !== null) { $storage->delete($file); }
            throw $exception;
        }
    }

    private function uploadEventDocument(int $id, Request $request, EntityManagerInterface $em, MediaFileStorage $storage, ?MediaFile &$file): Response
    {
        $this->requireDocument(PermissionAction::CREATE);
        $event = $this->event($id, $em);
        if (!$this->isCsrfTokenValid('event_document_upload_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $audience = $request->request->getString('audience');
        $registration = null;
        if ($audience === 'individual') {
            $registration = $em->getRepository(ClubEventRegistration::class)->findOneBy(['id' => $request->request->getString('registration'), 'event' => $event]);
            if ($registration === null) { throw $this->createNotFoundException('Anmeldung nicht gefunden.'); }
        } elseif ($audience !== 'all') {
            throw $this->createAccessDeniedException('Empfängerkreis auswählen.');
        }
        $upload = $request->files->get('file');
        if (!$upload instanceof UploadedFile) {
            $this->addFlash('danger', 'Bitte eine Datei auswählen; maximal 10 MB.');
        } else {
            try {
                $file = $storage->store($event->getClub(), $upload, '');
                $document = new ClubEventDocument($event, $registration, $file);
            } catch (\InvalidArgumentException $e) {
                if ($file !== null) { $storage->delete($file); $file = null; }
                $this->addFlash('danger', $e->getMessage());
                return $this->eventRedirect($event);
            }
            $em->persist($document);
            $em->flush();
            $this->addFlash('success', 'Veranstaltungsdokument gespeichert.');
        }
        return $this->redirectToRoute('admin_club_event', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/documents/{documentId}', name: 'admin_club_event_document', requirements: ['id' => '\d+', 'documentId' => '\d+'], methods: ['GET'])]
    public function document(int $id, int $documentId, EntityManagerInterface $em, MediaFileStorage $storage): Response
    {
        $this->requireDocument(PermissionAction::READ);
        return $storage->response($this->documentForEvent($id, $documentId, $em)->file(), ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    #[Route('/{id}/documents/{documentId}/delete', name: 'admin_club_event_document_delete', requirements: ['id' => '\d+', 'documentId' => '\d+'], methods: ['POST'])]
    public function deleteDocument(int $id, int $documentId, Request $request, EntityManagerInterface $em, MediaFileStorage $storage): Response
    {
        $file = null;
        $response = $this->withWriteLock($request, $em, function () use ($id, $documentId, $request, $em, &$file): Response {
            $this->requireDocument(PermissionAction::DELETE);
            if (!$this->isCsrfTokenValid('event_document_delete_'.$documentId, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
            $document = $this->documentForEvent($id, $documentId, $em);
            $file = $document->file();
            $em->remove($document); $em->flush();
            $this->addFlash('success', 'Veranstaltungsdokument gelöscht.');
            return $this->redirectToRoute('admin_club_event', ['id' => $id], Response::HTTP_SEE_OTHER);
        });
        if ($file !== null) { $storage->delete($file); }
        return $response;
    }

    #[Route('/{id}/image', name: 'admin_club_event_image', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function image(int $id, Request $request, EntityManagerInterface $em): Response
    {
        return $this->withWriteLock($request, $em, fn (): Response => $this->eventImage($id, $request, $em));
    }

    private function eventImage(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->requireWebsite(PermissionAction::EDIT);
        $event = $this->event($id, $em);
        if (!$this->canReadImage($event)) { throw $this->createAccessDeniedException('Keine Leseberechtigung für das bisherige Bild.'); }
        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'event_image_'.$id])
            ->add('image', MediaImageType::class, ['club' => $event->getClub(), 'data' => $event->getImage()])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $image = $form->get('image')->getData();
            $em->getConnection()->transactional(function () use ($em, $event, $image): void {
                $em->refresh($event, LockMode::PESSIMISTIC_WRITE);
                if (!$this->canReadImage($event)) { throw $this->createAccessDeniedException('Keine Leseberechtigung für das bisherige Bild.'); }
                $event->setImage($image);
                $em->flush();
            });
            $this->addFlash('success', 'Veranstaltungsbild gespeichert.');
            return $this->eventRedirect($event);
        }
        return $this->render('club_event/admin/image.html.twig', ['form' => $form, 'event' => $event], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/{id}/cancel', name: 'admin_club_event_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(int $id, Request $request, EntityManagerInterface $em): Response
    {
        try {
            return $this->withWriteLock($request, $em, fn (): Response => $this->cancelEvent($id, $request, $em));
        } catch (\DomainException $e) {
            $this->addFlash('warning', $e->getMessage());
            return $this->eventRedirect($this->event($id, $em));
        }
    }

    private function cancelEvent(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->requireWebsite(PermissionAction::DELETE);
        if (!$this->isCsrfTokenValid('event_admin_cancel_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $event = $this->event($id, $em);
        $em->getConnection()->transactional(function () use ($em, $event): void {
            $em->refresh($event, LockMode::PESSIMISTIC_WRITE);
            if ($event->isCancelled()) { throw new \DomainException('Die Veranstaltung ist bereits abgesagt.'); }
            $event->cancel(); $em->flush();
        });
        $this->addFlash('warning', 'Veranstaltung abgesagt. Offene Erstattungen müssen manuell gebucht werden.');
        return $this->eventRedirect($event);
    }

    #[Route('/registrations/{id}/payment', name: 'admin_club_event_payment', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function payment(int $id, Request $request, EntityManagerInterface $em, ClubEventBooking $booking): Response
    {
        try {
            return $this->withWriteLock($request, $em, fn (): Response => $this->eventPayment($id, $request, $em, $booking));
        } catch (\DomainException $e) {
            $this->addFlash('warning', $e->getMessage());
            $registration = $em->find(ClubEventRegistration::class, $id) ?? throw $this->createNotFoundException();
            return $this->eventRedirect($registration->getEvent());
        }
    }

    private function eventPayment(int $id, Request $request, EntityManagerInterface $em, ClubEventBooking $booking): Response
    {
        $this->requireDocument(PermissionAction::READ);
        if (!$this->canModule(PermissionModule::FINANCE, PermissionAction::CREATE)) { throw $this->createAccessDeniedException(); }
        if (!$this->isCsrfTokenValid('event_payment_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $registration = $em->find(ClubEventRegistration::class, $id);
        if (!$registration || $registration->getEvent()->getClub()->getId() !== $this->club()->getId()) { throw $this->createNotFoundException(); }
        $booking->recordPayment($registration, FrontendCourseRegistrationService::moneyToCents($request->request->getString('amount')),
            $request->request->getString('method'), $request->request->getString('reference'), $this->getUser()->getUserIdentifier());
        $this->addFlash('success', 'Zahlung oder Erstattung gebucht.');
        return $this->redirectToRoute('admin_club_event', ['id' => $registration->getEvent()->getId()], Response::HTTP_SEE_OTHER);
    }

    private function canWebsite(PermissionAction $action): bool
    {
        return $this->enabled ? $this->editorial->can($action) : $this->isGranted(User::ROLE_ADMIN);
    }

    private function requireWebsite(PermissionAction $action): void
    {
        if (!$this->canWebsite($action)) { throw $this->createAccessDeniedException('Keine Berechtigung für diese Veranstaltungsaktion.'); }
    }

    private function canModule(PermissionModule $module, PermissionAction $action): bool
    {
        if (!$this->enabled) { return $this->isGranted(User::ROLE_ADMIN); }
        $user = $this->getUser();
        $club = $this->clubContext->getClub();
        return $user instanceof User && $club !== null && $this->modules->can($user, $club, $module, $action);
    }

    private function requireDocument(PermissionAction $action): void
    {
        $this->requireWebsite($action);
        if (!$this->canWebsite(PermissionAction::READ)
            || !$this->canModule(PermissionModule::MEMBERSHIP, PermissionAction::READ)
            || !$this->canModule(PermissionModule::FINANCE, PermissionAction::READ)) {
            throw $this->createAccessDeniedException('Teilnehmer- und Finanzrechte sind erforderlich.');
        }
    }

    private function canReadImage(ClubEvent $event): bool
    {
        $image = $event->getImage();
        $user = $this->getUser();
        return !$this->enabled || $image === null || ($user instanceof User
            && $image->getClub()->getId() === $event->getClub()->getId()
            && $this->folders->can($user, $event->getClub(), $image->getFolder(), FileAction::READ));
    }

    private function withWriteLock(Request $request, EntityManagerInterface $em, callable $operation): Response
    {
        if (!$this->enabled || !$request->isMethod('POST')) { return $operation(); }
        $club = $this->club();
        return $em->getConnection()->transactional(function () use ($club, $em, $operation): Response {
            $this->continuity->lockClub($club);
            $em->clear();
            return $operation();
        });
    }

    private function eventRedirect(ClubEvent $event): Response
    {
        return $this->canWebsite(PermissionAction::READ)
            ? $this->redirectToRoute('admin_club_event', ['id' => $event->getId()], Response::HTTP_SEE_OTHER)
            : $this->redirectToRoute('app_portal', [], Response::HTTP_SEE_OTHER);
    }

    private function club(): Club
    {
        return $this->clubContext->getClub() ?? throw $this->createAccessDeniedException('Vereinszuordnung erforderlich.');
    }

    private function event(int $id, EntityManagerInterface $em): ClubEvent
    {
        $event = $em->find(ClubEvent::class, $id);
        if (!$event || $event->getClub()->getId() !== $this->club()->getId()) { throw $this->createNotFoundException(); }
        return $event;
    }

    private function documentForEvent(int $id, int $documentId, EntityManagerInterface $em): ClubEventDocument
    {
        $event = $this->event($id, $em);
        $document = $em->getRepository(ClubEventDocument::class)->findOneBy(['id' => $documentId, 'event' => $event]);
        if (!$document instanceof ClubEventDocument) { throw $this->createNotFoundException(); }
        return $document;
    }
}
