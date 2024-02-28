<?php

namespace App\Controller;

use App\Entity\TankCheckDetail;
use App\Form\TankCheckDetailType;
use App\Repository\TankCheckDetailRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tank/check/detail')]
class TankCheckDetailController extends AbstractController
{
    #[Route('/', name: 'app_tank_check_detail_index', methods: ['GET'])]
    public function index(TankCheckDetailRepository $tankCheckDetailRepository): Response
    {
        return $this->render('tank_check_detail/index.html.twig', [
            'tank_check_details' => $tankCheckDetailRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_tank_check_detail_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $tankCheckDetail = new TankCheckDetail();
        $form = $this->createForm(TankCheckDetailType::class, $tankCheckDetail);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($tankCheckDetail);
            $entityManager->flush();

            return $this->redirectToRoute('app_tank_check_detail_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tank_check_detail/new.html.twig', [
            'tank_check_detail' => $tankCheckDetail,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_tank_check_detail_show', methods: ['GET'])]
    public function show(TankCheckDetail $tankCheckDetail): Response
    {
        return $this->render('tank_check_detail/show.html.twig', [
            'tank_check_detail' => $tankCheckDetail,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_tank_check_detail_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TankCheckDetail $tankCheckDetail, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TankCheckDetailType::class, $tankCheckDetail);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_tank_check_detail_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tank_check_detail/edit.html.twig', [
            'tank_check_detail' => $tankCheckDetail,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_tank_check_detail_delete', methods: ['POST'])]
    public function delete(Request $request, TankCheckDetail $tankCheckDetail, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$tankCheckDetail->getId(), $request->request->get('_token'))) {
            $entityManager->remove($tankCheckDetail);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_tank_check_detail_index', [], Response::HTTP_SEE_OTHER);
    }
}
