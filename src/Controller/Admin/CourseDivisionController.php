<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Club;
use App\Entity\Courses;
use App\Entity\Sport;
use App\Form\CourseDivisionSelectionType;
use App\Form\Model\CourseDivisionSelection;
use App\Security\Voter\CourseManagementVoter;
use App\Service\AdminClubContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class CourseDivisionController extends AbstractController
{
    public function __construct(private readonly AdminClubContext $clubContext) {}

    #[Route('/admin/training/divisions/review', name: 'admin_course_division_review', methods: ['GET'])]
    public function review(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_TRAINING_MANAGER') && !$this->isGranted('ROLE_COURSE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        $ownClub = $this->clubContext->getClub();
        $requested = $request->query->getString('club');
        if ($requested === 'unassigned' && $this->isGranted('ROLE_SUPER_ADMIN')) {
            $club = null;
        } elseif ($requested !== '') {
            if (!ctype_digit($requested) || (int) $requested < 1) { throw $this->createNotFoundException(); }
            $club = $em->find(Club::class, (int) $requested) ?? throw $this->createNotFoundException();
            if ($ownClub?->getId() !== $club->getId()) {
                throw $this->createAccessDeniedException();
            }
        } else {
            $club = $ownClub ?? throw $this->createAccessDeniedException('Vereinszuordnung erforderlich.');
        }
        $courses = $em->getRepository(Courses::class)->findBy(['club' => $club, 'divisionReviewed' => false], ['title' => 'ASC']);
        return $this->render('admin/course/division_review.html.twig', ['club' => $club, 'courses' => $courses]);
    }

    #[Route('/admin/training/course/{id}/divisions', name: 'admin_course_division_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Courses $course, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(CourseManagementVoter::MANAGE, $course);
        $club = $course->getClub();
        if ($club === null) {
            throw $this->createNotFoundException('Dem Kurs muss zuerst ein Verein zugeordnet werden.');
        }
        if ($this->clubContext->getClub()?->getId() !== $club->getId()) {
            throw $this->createAccessDeniedException('Kurs gehört nicht zum ausgewählten Verein.');
        }
        $selection = new CourseDivisionSelection($course);
        $form = $this->createForm(CourseDivisionSelectionType::class, $selection, [
            'divisions' => $em->getRepository(Sport::class)->findBy(['club' => $club], ['sortOrder' => 'ASC', 'name' => 'ASC']),
        ])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($selection->divisions as $division) {
                if (!$division->isActive() && !$course->getDivisions()->contains($division)) {
                    $form->get('divisions')->addError(new FormError('Inaktive Sparten können nicht neu zugeordnet werden.'));
                }
            }
            if ($form->isValid()) {
                $course->updateDivisionAssignment($selection->scope !== 'unreviewed', $selection->divisions->toArray());
                $em->flush();
                $this->addFlash('success', 'Spartenzuordnung gespeichert.');
                return $this->redirectToRoute('admin_portal_training_course', ['id' => $course->getId()], Response::HTTP_SEE_OTHER);
            }
        }
        return $this->render('admin/course/divisions.html.twig', [
            'course' => $course, 'form' => $form->createView(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
