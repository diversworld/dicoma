<?php

namespace App\Controller;

use App\Entity\Courses;
use App\Entity\User;
use App\Security\Voter\CourseManagementVoter;
use App\Enum\CourseStatus;
use App\Form\CoursesType;
use App\Repository\CoursesRepository;
use App\Repository\ScheduleRepository;
use App\Service\AdminClubContext;
use App\Service\CourseImageStorage;
use App\Service\FileManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/courses')]
class CoursesController extends AbstractController
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/', name: 'app_courses_index', methods: ['GET'])]
    public function index(CoursesRepository $coursesRepository): Response
    {
        if (!$this->isGranted('ROLE_MEMBER')) {
            return $this->redirectToRoute('frontend_course_registration_index');
        }
        return $this->render('courses/index.html.twig', [
            'courses' => $coursesRepository->findBy(['club' => $this->currentClub(), 'status' => CourseStatus::OPEN], ['id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_courses_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager, CourseImageStorage $storage): Response
    {
        $course = new Courses();
        // Die Vereinszuordnung kommt ausschließlich aus dem angemeldeten Konto.
        // Eine abweichende Formularauswahl darf sie nicht überschreiben.
        $club = $this->currentClub();
        $course->setClub($club);

        $form = $this->createForm(CoursesType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $course->setClub($club);
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $course->setImage($storage->store($imageFile));
            }

            $entityManager->persist($course);
            $entityManager->flush();

            return $this->redirectToRoute('app_courses_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('courses/new.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function upload(Request $request, EntityManagerInterface $entityManager, AdminClubContext $clubContext, CourseImageStorage $storage): JsonResponse
    {
        $courseId = $request->request->getString('course_id');
        if (!ctype_digit($courseId) || (int) $courseId < 1
            || !$this->isCsrfTokenValid('course_image_upload_'.$courseId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger Kurs oder CSRF-Token.');
        }

        $course = $entityManager->find(Courses::class, (int) $courseId);
        if (!$course instanceof Courses) {
            throw $this->createNotFoundException('Kurs nicht gefunden.');
        }
        $this->denyAccessUnlessGranted(CourseManagementVoter::MANAGE, $course);
        if ($course->getClub()?->getId() === null || $course->getClub()->getId() !== $clubContext->getClub()?->getId()) {
            throw $this->createAccessDeniedException('Kurs gehört nicht zum ausgewählten Verein.');
        }

        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return new JsonResponse(['message' => 'Bitte ein Bild auswählen.'], Response::HTTP_BAD_REQUEST);
        }
        try {
            $name = $storage->store($uploadedFile);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (FileException $e) {
            $this->logger->error('Course image upload failed.', ['exception' => $e]);
            return new JsonResponse(['message' => 'Das Bild konnte nicht gespeichert werden.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['status' => 200, 'message' => 'Bild gespeichert.', 'file' => $name]);
    }

    #[Route('/{id}', name: 'app_courses_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted("ROLE_MEMBER")]
    public function show(Courses $course, $id, ScheduleRepository $scheduleRepository): Response
    {
        if ($course->getClub()?->getId() !== $this->currentClub()->getId() || $course->getStatus() !== CourseStatus::OPEN) {
            throw $this->createNotFoundException('Kurs nicht verfuegbar.');
        }
        return $this->render('courses/show.html.twig', [
            'course' => $course,
            'schedules' => $scheduleRepository->findScheduleWithBookingCount($id),
        ]);
    }

    #[Route('/{id}/image', name: 'app_courses_image', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_MEMBER')]
    public function image(Courses $course, FileManager $files): Response
    {
        if ($course->getClub()?->getId() !== $this->currentClub()->getId()
            || $course->getStatus() !== CourseStatus::OPEN || $course->getImage() === null) {
            throw $this->createNotFoundException('Kursbild nicht verfügbar.');
        }
        return $files->imageResponse($course->getImage());
    }

    #[Route('/{id}/edit', name: 'app_courses_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Courses $course, EntityManagerInterface $entityManager, FileManager $manager, CourseImageStorage $storage): Response
    {
        $this->denyAccessUnlessGranted(CourseManagementVoter::MANAGE, $course);
        $originalClub = $course->getClub();
        $files = array_map(
            static fn (array $file): string => $file['path'],
            array_values(array_filter($manager->listDirectory(), static fn (array $file): bool => !$file['isDirectory']))
        );

        $form = $this->createForm(CoursesType::class, $course, [
            'files' => $files
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Eine Vereinsverschiebung ist nicht Teil dieses Bearbeitungsablaufs.
            $course->setClub($originalClub);
            $selectedFile = $request->request->get('selectedFile');

                $imageFile = $form->get('image')->getData();
                if ($imageFile) {
                    $course->setImage($storage->store($imageFile));
                } elseif (is_string($selectedFile) && $selectedFile !== '') {
                    if (!in_array($selectedFile, $files, true)) {
                        throw $this->createAccessDeniedException('Ungültige Bildauswahl.');
                    }
                    $course->setImage($selectedFile);
                }

                $entityManager->persist($course);
                $entityManager->flush();

                return $this->redirectToRoute('app_courses_index');
        }

        return $this->render('courses/edit.html.twig', [
            'course' => $course,
            'form' => $form->createView(),
            'files' => $files
        ]);
    }

    private function currentClub(): \App\Entity\Club {
        $user=$this->getUser();
        $club=$user instanceof User ? $user->getMember()?->getClub() : null;
        if (!$club) throw $this->createAccessDeniedException('Keine Vereinszuordnung.');
        return $club;
    }
    #[Route('/{id}', name: 'app_courses_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Courses $course, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(CourseManagementVoter::MANAGE, $course);
        if ($this->isCsrfTokenValid('delete'.$course->getId(), $request->request->get('_token'))) {
            $entityManager->remove($course);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_courses_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Der alte Endpunkt nahm beliebige Dateinamen ohne Objektbindung/CSRF an.
     * Bis zur Einführung einer kursbezogenen Bildverwaltung bewusst gesperrt.
     */
    #[Route('/image/delete', name: 'delete_image', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete_image(Request $request): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Bildlöschung nur über eine autorisierte Kursverwaltung möglich.',
        ], Response::HTTP_GONE);
    }
}
