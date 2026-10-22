<?php

namespace App\Controller;

use App\Entity\Courses;
use App\Entity\User;
use App\Security\Voter\CourseManagementVoter;
use App\Enum\CourseStatus;
use App\Form\CoursesType;
use App\Repository\CoursesRepository;
use App\Repository\ScheduleRepository;
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
    #[IsGranted("ROLE_MEMBER")]
    public function index(CoursesRepository $coursesRepository): Response
    {
        return $this->render('courses/index.html.twig', [
            'courses' => $coursesRepository->findBy(['club' => $this->currentClub(), 'status' => CourseStatus::OPEN], ['id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_courses_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
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
                $dateiName = $imageFile->getFilename(). '.' .$imageFile->guessClientExtension();
                $imageFile->move($this->getParameter('bilder_ordner'), $dateiName);
                $course->setImage($dateiName);
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
    public function upload(Request $request): JsonResponse
    {
        $this->logger->info('Files in the request:', $request->files->all());

        $uploadedFile = $request->files->get('file');

        if ($uploadedFile) {
            $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$uploadedFile->guessExtension();

            try {
                $uploadedFile->move(
                    $this->getParameter('bilder_ordner'),
                    $newFilename
                );
            } catch (FileException $e) {
                $this->logger->error('Failed to upload the file: ' . $e->getMessage());
                return new JsonResponse([
                    "status" => 500,
                    "message" => "Unexpected error during file upload.",
                ]);
            }

            return new JsonResponse([
                "status" => 200,
                "message" => "File uploaded successfully.",
                "file" => $newFilename,
                "allFiles" => $this->getFiles() // Return all files
            ]);

        } else {
            $this->logger->error('No file found in the request');

            return new JsonResponse([
                "status" => 400,
                "message" => "No file was submitted.",
            ]);
        }
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

    #[Route('/{id}/edit', name: 'app_courses_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Courses $course, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(CourseManagementVoter::MANAGE, $course);
        $originalClub = $course->getClub();
        $files = $this->getFiles();

        $form = $this->createForm(CoursesType::class, $course, [
            'files' => $files
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Eine Vereinsverschiebung ist nicht Teil dieses Bearbeitungsablaufs.
            $course->setClub($originalClub);
            $selectedFile = $request->request->get('selectedFile');

                if ($selectedFile) {
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
    private function getFiles(): array
    {
        $dirPath = $this->getParameter('bilder_ordner');
        $files = scandir($dirPath);
        $files = array_diff($files, ['.', '..']);
        // logic to prefix the file paths with $dirPath to form full URLs
        // depends on your directory structure and may not be needed
        return array_map(function($filename) use ($dirPath) { return  '/' . $filename; }, $files);
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
