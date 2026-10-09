<?php

namespace App\Controller;

use App\Entity\Brevets;
use App\Form\BrevetsType;
use App\Repository\BrevetsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/brevets')]
class BrevetsController extends AbstractController
{
    #[Route('/', name: 'app_brevets_index', methods: ['GET'])]
    public function index(BrevetsRepository $brevetsRepository): Response
    {
        return $this->render('brevets/index.html.twig', [
            'brevets' => $brevetsRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_brevets_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Überprüfen, ob der Benutzer als Admin authentifiziert ist
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('You are not authorized to perform this action.');
        }

        $brevet = new Brevets();
        $form = $this->createForm(BrevetsType::class, $brevet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($brevet);
            $entityManager->flush();

            return $this->redirectToRoute('app_brevets_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('brevets/new.html.twig', [
            'brevet' => $brevet,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_brevets_show', methods: ['GET'])]
    public function show(Brevets $brevet): Response
    {
        return $this->render('brevets/show.html.twig', [
            'brevet' => $brevet,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_brevets_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Brevets $brevet, EntityManagerInterface $entityManager): Response
    {
        // Überprüfen, ob der Benutzer als Admin authentifiziert ist
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('You are not authorized to perform this action.');
        }

        $form = $this->createForm(BrevetsType::class, $brevet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_brevets_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('brevets/edit.html.twig', [
            'brevet' => $brevet,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_brevets_delete', methods: ['POST'])]
    public function delete(Request $request, Brevets $brevet, EntityManagerInterface $entityManager): Response
    {
        // Überprüfen, ob der Benutzer als Admin authentifiziert ist
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('You are not authorized to perform this action.');
        }
                
        if ($this->isCsrfTokenValid('delete'.$brevet->getId(), $request->request->get('_token'))) {
            $entityManager->remove($brevet);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_brevets_index', [], Response::HTTP_SEE_OTHER);
    }
}
