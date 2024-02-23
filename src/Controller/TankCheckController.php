<?php

namespace App\Controller;

use App\Entity\TankCheck;
use App\Form\TankCheckType;
use App\Repository\TankCheckRepository;
use App\Repository\TankRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tankcheck')]
class TankCheckController extends AbstractController
{
    #[Route('/', name: 'app_tank_check_index', methods: ['GET'])]
    public function index(TankCheckRepository $tankCheckRepository): Response
    {
        return $this->render('tank_check/index.html.twig', [
            'tank_checks' => $tankCheckRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_tank_check_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, TankRepository $tankRepository): Response
    {
        $tankCheck = new TankCheck();
        $form = $this->createForm(TankCheckType::class, $tankCheck);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($tankCheck);
            $entityManager->flush();

            return $this->redirectToRoute('app_tank_check_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tank_check/new.html.twig', [
            'tank_check' => $tankCheck,
            'form' => $form,
            'available_tanks' => $tankRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_tank_check_show', methods: ['GET'])]
    public function show(TankCheck $tankCheck): Response
    {
        return $this->render('tank_check/show.html.twig', [
            'tank_check' => $tankCheck,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_tank_check_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TankCheck $tankCheck, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TankCheckType::class, $tankCheck);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_tank_check_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tank_check/edit.html.twig', [
            'tank_check' => $tankCheck,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_tank_check_delete', methods: ['POST'])]
    public function delete(Request $request, TankCheck $tankCheck, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$tankCheck->getId(), $request->request->get('_token'))) {
            $entityManager->remove($tankCheck);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_tank_check_index', [], Response::HTTP_SEE_OTHER);
    }
}
