<?php

declare(strict_types=1);
namespace App\Controller\Frontend;

use App\Entity\{ClubEvent, ClubEventPayment, ClubEventRegistration, Person, User};
use App\Service\{ClubEventBooking, PublicWebsite};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ClubEventController extends AbstractController
{
    #[Route('/events/{id}', name: 'club_event_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id, EntityManagerInterface $em, ClubEventBooking $booking, PublicWebsite $website): Response
    {
        $event = $em->find(ClubEvent::class, $id);
        $user = $this->getUser();
        if (!$event || $event->getClub()->getId() !== $website->club()?->getId()
            || !$booking->canSee($event, $user instanceof User ? $user : null)) { throw $this->createNotFoundException(); }
        $registration = $user instanceof User && $user->getPerson() !== null
            ? $em->getRepository(ClubEventRegistration::class)->findOneBy(['event' => $event, 'person' => $user->getPerson()]) : null;
        $response = $this->render('club_event/detail.html.twig', ['event' => $event, 'registration' => $registration]);
        if ($user instanceof User) { $response->setPrivate()->headers->addCacheControlDirective('no-store'); }
        return $response;
    }

    #[Route('/events/{id}/register', name: 'club_event_register', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function register(int $id, Request $request, ClubEventBooking $booking, EntityManagerInterface $em, PublicWebsite $website): Response
    {
        if (!$this->isCsrfTokenValid('event_register_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $event = $em->find(ClubEvent::class, $id);
        if (!$event || $event->getClub()->getId() !== $website->club()?->getId()) { throw $this->createNotFoundException(); }
        $user = $this->getUser();
        if (!$user instanceof User) { throw $this->createAccessDeniedException(); }
        try {
            $registration = $booking->register($id, $user);
            return $this->redirectToRoute('club_event_registration', ['id' => $registration->getId()], Response::HTTP_SEE_OTHER);
        } catch (\DomainException $e) {
            $this->addFlash('warning', $e->getMessage());
            return $this->redirectToRoute('club_event_detail', ['id' => $id], Response::HTTP_SEE_OTHER);
        }
    }

    #[Route('/event-registrations', name: 'club_event_registrations', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function registrations(EntityManagerInterface $em): Response
    {
        $response = $this->render('club_event/registrations.html.twig', ['registrations' => $em->getRepository(ClubEventRegistration::class)
            ->findBy(['person' => $this->person()], ['registeredAt' => 'DESC', 'id' => 'DESC'])]);
        $response->setPrivate()->headers->addCacheControlDirective('no-store');
        return $response;
    }

    #[Route('/event-registrations/{id}', name: 'club_event_registration', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function registration(int $id, EntityManagerInterface $em, ClubEventBooking $booking): Response
    {
        $registration = $this->ownRegistration($id, $em);
        $response = $this->render('club_event/registration.html.twig', ['registration' => $registration,
            'paid' => $booking->paid($registration), 'payments' => $em->getRepository(ClubEventPayment::class)->findBy(['registration' => $registration], ['id' => 'ASC'])]);
        $response->setPrivate()->headers->addCacheControlDirective('no-store');
        return $response;
    }

    #[Route('/event-registrations/{id}/cancel', name: 'club_event_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(int $id, Request $request, EntityManagerInterface $em, ClubEventBooking $booking): Response
    {
        if (!$this->isCsrfTokenValid('event_cancel_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        try {
            $booking->cancel($this->ownRegistration($id, $em), $this->person());
            $this->addFlash('success', 'Anmeldung storniert. Eventuell gezahlte Beträge werden durch die Vereinsverwaltung erstattet.');
        } catch (\DomainException $e) { $this->addFlash('warning', $e->getMessage()); }
        return $this->redirectToRoute('club_event_registration', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    private function person(): Person
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isVerified() || $user->getPerson() === null) {
            throw $this->createAccessDeniedException('Ein bestätigtes Personenkonto ist erforderlich.');
        }
        return $user->getPerson();
    }

    private function ownRegistration(int $id, EntityManagerInterface $em): ClubEventRegistration
    {
        $registration = $em->getRepository(ClubEventRegistration::class)->findOneBy(['id' => $id, 'person' => $this->person()]);
        if (!$registration instanceof ClubEventRegistration) { throw $this->createNotFoundException(); }
        return $registration;
    }
}
