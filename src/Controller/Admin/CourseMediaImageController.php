<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\{Courses, Schedule};
use App\Form\MediaImageType;
use App\Security\Voter\CourseMediaImageEditVoter;
use App\Service\{AdminClubContext, MembershipRoleContinuity};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/course-images')]
#[IsGranted('ROLE_USER')]
final class CourseMediaImageController extends AbstractController
{
    public function __construct(
        private readonly AdminClubContext $clubs,
        private readonly MembershipRoleContinuity $continuity,
        #[Autowire('%env(bool:CLUB_FUNCTION_MEMBERSHIP_ENABLED)%')] private readonly bool $enabled,
    ) {}

    #[Route('/course/{id}', name: 'admin_course_media_image', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function course(int $id, Request $request, EntityManagerInterface $em): Response
    {
        return $this->handle($id, false, $request, $em);
    }

    #[Route('/schedule/{id}', name: 'admin_schedule_media_image', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function schedule(int $id, Request $request, EntityManagerInterface $em): Response
    {
        return $this->handle($id, true, $request, $em);
    }

    private function handle(int $id, bool $isSchedule, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->enabled) {
            throw $this->createNotFoundException();
        }
        $club = $this->clubs->getClub() ?? throw $this->createAccessDeniedException('Vereinszuordnung erforderlich.');
        $operation = fn (): Response => $this->editImage($id, $isSchedule, $request, $em);
        if (!$request->isMethod('POST')) {
            return $operation();
        }
        return $em->getConnection()->transactional(function () use ($club, $em, $operation): Response {
            $this->continuity->lockClub($club);
            $em->clear();
            return $operation();
        });
    }

    private function editImage(int $id, bool $isSchedule, Request $request, EntityManagerInterface $em): Response
    {
        $club = $this->clubs->getClub() ?? throw $this->createAccessDeniedException('Vereinszuordnung erforderlich.');
        $entity = $em->find($isSchedule ? Schedule::class : Courses::class, $id);
        if (!$entity instanceof Courses && !$entity instanceof Schedule) {
            throw $this->createNotFoundException();
        }
        $course = $entity instanceof Schedule ? $entity->getCourses() : $entity;
        if ($course?->getClub()?->getId() !== $club->getId()
            || ($entity instanceof Schedule && $entity->getInstructor() !== null
                && $entity->getInstructor()->getClub()?->getId() !== $club->getId())) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(CourseMediaImageEditVoter::EDIT, $entity);
        $current = $entity->getMediaImage();
        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'course_media_image_'.($isSchedule ? 'schedule_' : 'course_').$id])
            ->add('image', MediaImageType::class, ['club' => $club, 'data' => $current])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entity->setMediaImage($form->get('image')->getData());
            $em->flush();
            $this->addFlash('success', 'Bild gespeichert.');
            return $this->redirectToRoute($isSchedule ? 'admin_schedule_media_image' : 'admin_course_media_image', ['id' => $id], Response::HTTP_SEE_OTHER);
        }
        return $this->render('admin/course/media_image.html.twig', [
            'form' => $form,
            'entity' => $entity,
            'isSchedule' => $isSchedule,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
