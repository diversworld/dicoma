<?php

namespace App\Controller;

use App\Entity\Student;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/student')]
class StudentController extends AbstractController
{
    #[Route('/', name: 'app_student_index', methods: ['GET'])]
    public function index(StudentRepository|\EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext $studentRepository): Response
    {
        return $this->render('student/index.html.twig', [
            'students' => $studentRepository->findByStatus('true', 25),
        ]);
    }

    #[Route('/new', name: 'app_student_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UserPasswordHasherInterface $studentPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $student = new User();

        $student->setRoles(['ROLE_USER','ROLE_STUDENT']);
        $student->setCategory('student');

        $form = $this->createForm(UserType::class, $student);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $student->setPassword(
                $studentPasswordHasher->hashPassword(
                    $student,
                    $form->get('plainPassword')->getdata()
                )
            );

            $entityManager->persist($student);
            $entityManager->flush();

            return $this->redirectToRoute('app_student_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('student/new.html.twig', [
            'student' => $student,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_student_show', methods: ['GET'])]
    public function show(User $student): Response
    {
        return $this->render('student/show.html.twig', [
            'student' => $student,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_student_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $student, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordEncoder): Response
    {
        $oldPassword = $student->getPassword();

        $form = $this->createForm(UserType::class, $student);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // encode only when password was entered/changed
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $encodedPassword = $passwordEncoder->hashPassword($student, $plainPassword);
                $student->setPassword($encodedPassword);
            } else {
                // keep the old password when the password field was not filled
                $student->setPassword($oldPassword);
            }

            $entityManager->persist($student);
            $entityManager->flush();

            return $this->redirectToRoute('app_student_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('student/edit.html.twig', [
            'student' => $student,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_student_delete', methods: ['POST'])]
    public function delete(Request $request, Student $student, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$student->getId(), $request->request->get('_token'))) {
            $entityManager->remove($student);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_student_index', [], Response::HTTP_SEE_OTHER);
    }
}
