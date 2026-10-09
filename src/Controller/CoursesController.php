<?php

namespace App\Controller;

use App\Entity\Courses;
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
        return $this->render('courses/index.html.twig', [
            'courses' => $coursesRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_courses_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $course = new Courses();

        $form = $this->createForm(CoursesType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
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
    public function show(Courses $course, $id, ScheduleRepository $scheduleRepository): Response
    {
        return $this->render('courses/show.html.twig', [
            'course' => $course,
            'schedules' => $scheduleRepository->findScheduleWithBookingCount($id),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_courses_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Courses $course, EntityManagerInterface $entityManager): Response
    {
        $files = $this->getFiles();

        $form = $this->createForm(CoursesType::class, $course, [
            'files' => $files
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
    public function delete(Request $request, Courses $course, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$course->getId(), $request->request->get('_token'))) {
            $entityManager->remove($course);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_courses_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/image/delete', name: 'delete_image', methods: ['DELETE'])]
    public function delete_image(Request $request): JsonResponse
    {
        $filename = $request->request->get('filename');

        $filepath = $this->getParameter('bilder_ordner') . '/' . $filename;

        if (file_exists($filepath)) {
            unlink($filepath);
            return new JsonResponse(['status' => 200, 'message' => 'File deleted.']);
        } else {
            return new JsonResponse(['status' => 400, 'message' => 'File not found']);
        }
    }
}
