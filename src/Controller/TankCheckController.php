<?php

namespace App\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;

use App\Entity\TankCheck;
use App\Repository\TankCheckArticleRepository;
use App\Repository\TankCheckRepository;
use App\Repository\TankRepository;
use App\Service\AdminEquipmentScope;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tankcheck')]
class TankCheckController extends AbstractController
{
    public function __construct(private readonly AdminEquipmentScope $equipmentScope) {}

    #[Route('/', name: 'app_tank_check_index', methods: ['GET'])]
    public function index(TankCheckRepository $tankCheckRepository): Response
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->redirectToRoute('equipment_manager_tuv', [], Response::HTTP_SEE_OTHER);
        }
        return $this->render('tank_check/index.html.twig', [
            'tank_checks' => $tankCheckRepository->findAll(),
        ]);
    }

    #[Route('/book-inspection/{tankId}', name: 'app_legacy_tank_check_booking', methods: ['GET'])]
    public function bookInspection(int $tankId, TankRepository $tankRepository): Response
    {
        $tank = $tankRepository->find($tankId);

        if (!$tank) {
            throw new NotFoundHttpException("Tank not found.");
        }

        $this->equipmentScope->assertAsset($tank);
        return $this->redirectToRoute('equipment_manager_tuv', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/new', name: 'app_tank_check_new', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(): Response
    {
        return $this->redirectToRoute('equipment_manager_tuv', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_tank_check_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(TankCheck $tankCheck, TankCheckArticleRepository $checkArticleRepository): Response
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->redirectToRoute('equipment_manager_tuv', [], Response::HTTP_SEE_OTHER);
        }
        $checkArticles = $checkArticleRepository->findByCheck($tankCheck->getId());

        return $this->render('tank_check/show.html.twig', [
            'tank_check' => $tankCheck,
            'tank_check_articles' => $checkArticles,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_tank_check_edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(): Response
    {
        return $this->redirectToRoute('equipment_manager_tuv', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_tank_check_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function delete(Request $request, TankCheck $tankCheck, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$tankCheck->getId(), $request->request->get('_token'))) {
            $entityManager->remove($tankCheck);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_tank_check_index', [], Response::HTTP_SEE_OTHER);
    }
}
