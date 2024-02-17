<?php

namespace App\Controller;

use App\Entity\Instructor;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\InstructorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/instructor')]
class InstructorController extends AbstractController
{
    #[Route('/', name: 'app_instructor_index', methods: ['GET'])]
    public function index(InstructorRepository $instructorRepository): Response
    {
        return $this->render('instructor/index.html.twig', [
            'instructors' => $instructorRepository->findByStatus('true', 50),
        ]);
    }

    #[Route('/new', name: 'app_instructor_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $instructor = new Instructor();

        $instructor->setRoles(['ROLE_USER','ROLE_INSTRUCTOR']);

        $form = $this->createForm(UserType::class, $instructor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $instructor->setPassword(
                $userPasswordHasher->hashPassword(
                    $instructor,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager->persist($instructor);
            $entityManager->flush();

            return $this->redirectToRoute('app_instructor_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('instructor/new.html.twig', [
            'instructor' => $instructor,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_instructor_show', methods: ['GET'])]
    public function show(User $instructor): Response
    {
        return $this->render('instructor/show.html.twig', [
            'instructor' => $instructor,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_instructor_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $instructor, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordEncoder): Response
    {
        $oldPassword = $instructor->getPassword();

        $form = $this->createForm(UserType::class, $instructor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $encodedPassword = $passwordEncoder->hashPassword($instructor, $plainPassword);
                $instructor->setPassword($encodedPassword);
            } else {
                // keep the old password when the password field was not filled
                $instructor->setPassword($oldPassword);
            }

            $entityManager->persist($instructor);
            $entityManager->flush();

            return $this->redirectToRoute('app_instructor_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('instructor/edit.html.twig', [
            'instructor' => $instructor,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_instructor_delete', methods: ['POST'])]
    public function delete(Request $request, Instructor $instructor, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$instructor->getId(), $request->request->get('_token'))) {
            $entityManager->remove($instructor);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_instructor_index', [], Response::HTTP_SEE_OTHER);
    }
}
