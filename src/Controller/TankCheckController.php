<?php

namespace App\Controller;

use App\Entity\Tank;
use App\Entity\TankCheck;
use App\Form\TankCheckType;
use App\Form\TankType;
use App\Repository\TankCheckArticleRepository;
use App\Repository\TankCheckRepository;
use App\Repository\TankRepository;
use App\Repository\VendorRepository;
use App\Service\TankCheckService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tankcheck')]
class TankCheckController extends AbstractController
{
    private $tankCheckService;

    public function __construct(TankCheckService $tankCheckService)
    {
        $this->tankCheckService = $tankCheckService;
    }

    #[Route('/', name: 'app_tank_check_index', methods: ['GET'])]
    public function index(TankCheckRepository $tankCheckRepository): Response
    {
        return $this->render('tank_check/index.html.twig', [
            'tank_checks' => $tankCheckRepository->findAll(),
        ]);
    }

    #[Route('/book-inspection/{tankId}', name: 'app_book_inspection', methods: ['GET'])]
    public function bookInspection(int $tankId, TankRepository $tankRepository): Response
    {
        $tank = $tankRepository->find($tankId);

        if (!$tank) {
            throw new NotFoundHttpException("Tank not found.");
        }

        $totalPrice = $this->tankCheckService->bookTankInspection($tank);

        return new Response(
            "The total price for the tank inspection is €" . number_format($totalPrice, 2)
        );
    }

    #[Route('/new', name: 'app_tank_check_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, TankCheckRepository $tankCheckRepository): Response
    {
        $tank = new Tank();
        $availableChecks = $tankCheckRepository->findAll(); // Fetch available checks
        $form = $this->createForm(TankType::class, $tank, [
            'available_checks' => $availableChecks,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($tank);
            $entityManager->flush();

            return $this->redirectToRoute('app_tank_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tank/new.html.twig', [
            'tank' => $tank,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_tank_check_show', methods: ['GET'])]
    public function show(TankCheck $tankCheck, TankCheckArticleRepository $checkArticleRepository): Response
    {
        $checkArticles = $checkArticleRepository->findByCheck($tankCheck->getId());

        return $this->render('tank_check/show.html.twig', [
            'tank_check' => $tankCheck,
            'tank_check_articles' => $checkArticles,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_tank_check_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Tank $tank, EntityManagerInterface $entityManager, TankCheckRepository $tankCheckRepository): Response
    {
        $availableChecks = $tankCheckRepository->findAll(); // Fetch available checks

        $form = $this->createForm(TankType::class, $tank, [
            'available_checks' => $availableChecks,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_tank_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tank/edit.html.twig', [
            'tank' => $tank,
            'form' => $form->createView(),
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
