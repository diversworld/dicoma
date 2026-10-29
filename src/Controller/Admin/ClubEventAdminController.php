<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\{Club, ClubEvent, ClubEventDocument, ClubEventPayment, ClubEventRegistration, User};
use App\Form\ClubEventType;
use App\Form\MediaImageType;
use App\Service\{ClubEventBooking, FrontendCourseRegistrationService, MediaFileStorage};
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/club-events')]
#[IsGranted('ROLE_ADMIN')]
final class ClubEventAdminController extends AbstractController
{
    #[Route('', name: 'admin_club_events', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('club_event/admin/index.html.twig', ['events' => $em->getRepository(ClubEvent::class)->findBy(['club' => $this->club()], ['startsAt' => 'DESC'])]);
    }

    #[Route('/new', name: 'admin_club_event_new', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $event = new ClubEvent($this->club());
        $form = $this->createForm(ClubEventType::class, $event);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($event); $em->flush();
            return $this->redirectToRoute('admin_club_event', ['id' => $event->getId()], Response::HTTP_SEE_OTHER);
        }
        return $this->render('club_event/admin/form.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'admin_club_event_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $event = $this->event($id, $em);
        $draft = clone $event;
        $form = $this->createForm(ClubEventType::class, $draft);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->getConnection()->transactional(function () use ($em, $event, $draft): void {
                    $em->refresh($event, LockMode::PESSIMISTIC_WRITE);
                    if ($event->isCancelled() || $em->getRepository(ClubEventRegistration::class)->count(['event' => $event]) > 0) {
                        throw new \DomainException('Eine abgesagte Veranstaltung oder Veranstaltung mit Anmeldungen kann nicht geändert werden.');
                    }
                    $event->copyDetailsFrom($draft); $em->flush();
                });
                return $this->redirectToRoute('admin_club_event', ['id' => $id], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $e) { $this->addFlash('warning', $e->getMessage()); }
        }
        return $this->render('club_event/admin/form.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'admin_club_event', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id, EntityManagerInterface $em, ClubEventBooking $booking): Response
    {
        $event = $this->event($id, $em);
        $rows = [];
        foreach ($em->getRepository(ClubEventRegistration::class)->findBy(['event' => $event], ['registeredAt' => 'ASC', 'id' => 'ASC']) as $registration) {
            $rows[] = ['registration' => $registration, 'paid' => $booking->paid($registration),
                'payments' => $em->getRepository(ClubEventPayment::class)->findBy(['registration' => $registration], ['id' => 'ASC'])];
        }
        return $this->render('club_event/admin/detail.html.twig', ['event' => $event, 'rows' => $rows,
            'documents' => $em->getRepository(ClubEventDocument::class)->findBy(['event' => $event], ['id' => 'DESC'])]);
    }

    #[Route('/{id}/documents', name: 'admin_club_event_document_upload', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function uploadDocument(int $id, Request $request, EntityManagerInterface $em, MediaFileStorage $storage): Response
    {
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
                $saved = false;
                try {
                    $em->persist(new ClubEventDocument($event, $registration, $file));
                    $em->flush(); $saved = true;
                } finally {
                    if (!$saved) { $storage->delete($file); }
                }
                $this->addFlash('success', 'Veranstaltungsdokument gespeichert.');
            } catch (\InvalidArgumentException $e) { $this->addFlash('danger', $e->getMessage()); }
        }
        return $this->redirectToRoute('admin_club_event', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/documents/{documentId}', name: 'admin_club_event_document', requirements: ['id' => '\d+', 'documentId' => '\d+'], methods: ['GET'])]
    public function document(int $id, int $documentId, EntityManagerInterface $em, MediaFileStorage $storage): Response
    {
        return $storage->response($this->documentForEvent($id, $documentId, $em)->file(), ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    #[Route('/{id}/documents/{documentId}/delete', name: 'admin_club_event_document_delete', requirements: ['id' => '\d+', 'documentId' => '\d+'], methods: ['POST'])]
    public function deleteDocument(int $id, int $documentId, Request $request, EntityManagerInterface $em, MediaFileStorage $storage): Response
    {
        if (!$this->isCsrfTokenValid('event_document_delete_'.$documentId, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $document = $this->documentForEvent($id, $documentId, $em);
        $file = $document->file();
        $em->remove($document); $em->flush();
        $storage->delete($file);
        $this->addFlash('success', 'Veranstaltungsdokument gelöscht.');
        return $this->redirectToRoute('admin_club_event', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/image', name: 'admin_club_event_image', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function image(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $event = $this->event($id, $em);
        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'event_image_'.$id])
            ->add('image', MediaImageType::class, ['club' => $event->getClub(), 'data' => $event->getImage()])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $image = $form->get('image')->getData();
            $em->getConnection()->transactional(function () use ($em, $event, $image): void {
                $em->refresh($event, LockMode::PESSIMISTIC_WRITE);
                $event->setImage($image);
                $em->flush();
            });
            $this->addFlash('success', 'Veranstaltungsbild gespeichert.');
            return $this->redirectToRoute('admin_club_event', ['id' => $id], Response::HTTP_SEE_OTHER);
        }
        return $this->render('club_event/admin/image.html.twig', ['form' => $form, 'event' => $event], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/{id}/cancel', name: 'admin_club_event_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(int $id, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('event_admin_cancel_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $event = $this->event($id, $em);
        try {
            $em->getConnection()->transactional(function () use ($em, $event): void {
                $em->refresh($event, LockMode::PESSIMISTIC_WRITE);
                if ($event->isCancelled()) { throw new \DomainException('Die Veranstaltung ist bereits abgesagt.'); }
                $event->cancel(); $em->flush();
            });
            $this->addFlash('warning', 'Veranstaltung abgesagt. Offene Erstattungen müssen manuell gebucht werden.');
        } catch (\DomainException $e) { $this->addFlash('warning', $e->getMessage()); }
        return $this->redirectToRoute('admin_club_event', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/registrations/{id}/payment', name: 'admin_club_event_payment', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function payment(int $id, Request $request, EntityManagerInterface $em, ClubEventBooking $booking): Response
    {
        if (!$this->isCsrfTokenValid('event_payment_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $registration = $em->find(ClubEventRegistration::class, $id);
        if (!$registration || $registration->getEvent()->getClub()->getId() !== $this->club()->getId()) { throw $this->createNotFoundException(); }
        try {
            $booking->recordPayment($registration, FrontendCourseRegistrationService::moneyToCents($request->request->getString('amount')),
                $request->request->getString('method'), $request->request->getString('reference'), $this->getUser()->getUserIdentifier());
            $this->addFlash('success', 'Zahlung oder Erstattung gebucht.');
        } catch (\DomainException $e) { $this->addFlash('warning', $e->getMessage()); }
        return $this->redirectToRoute('admin_club_event', ['id' => $registration->getEvent()->getId()], Response::HTTP_SEE_OTHER);
    }

    private function club(): Club
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getMember()?->getClub()?->getId() === null) { throw $this->createAccessDeniedException('Vereinszuordnung erforderlich.'); }
        return $user->getMember()->getClub();
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
