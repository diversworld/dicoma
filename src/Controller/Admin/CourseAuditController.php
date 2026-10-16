<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Courses;
use App\Entity\CourseAuditEvent;
use App\Entity\CourseParticipant;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class CourseAuditController extends AbstractController
{
    #[Route('/admin/course/{id}/history', name: 'admin_course_history', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function index(int $id, EntityManagerInterface $em): Response
    {
        $course = $em->getRepository(Courses::class)->find($id);
        if (!$course instanceof Courses) {
            throw $this->createNotFoundException('Kurs nicht gefunden.');
        }

        $events = $em->getRepository(CourseAuditEvent::class)->findBy(
            ['courseId' => $id], ['occurredAt' => 'DESC', 'id' => 'DESC'], 100
        );

        $participantIds = [];
        $actorIds = [];
        foreach ($events as $event) {
            if ($event->getParticipantId() !== null) {
                $participantIds[] = $event->getParticipantId();
            }
            if ($event->getActorUserId() !== null) {
                $actorIds[] = $event->getActorUserId();
            }
        }

        $participants = [];
        if ($participantIds !== []) {
            foreach ($em->getRepository(CourseParticipant::class)->findBy(['id' => array_unique($participantIds)]) as $participant) {
                // Never display participants belonging to another course.
                if ($participant->getCourse()?->getId() === $id) {
                    $participants[$participant->getId()] = $participant->getMember()?->getFullName() ?: 'Teilnehmer ohne Mitglied';
                }
            }
        }

        $actors = [];
        if ($actorIds !== []) {
            foreach ($em->getRepository(User::class)->findBy(['id' => array_unique($actorIds)]) as $actor) {
                $actors[$actor->getId()] = $actor->getUserIdentifier();
            }
        }

        return $this->render('admin/course/history.html.twig', [
            'course' => $course,
            'events' => $events,
            'participantNames' => $participants,
            'actorNames' => $actors,
        ]);
    }
}
