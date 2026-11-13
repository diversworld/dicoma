<?php

namespace App\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;

use App\Entity\Tank;
use App\Form\TankType;
use App\Repository\TankCheckRepository;
use App\Repository\TankRepository;
use App\Service\TankCheckService;
use App\Service\AdminEquipmentScope;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tank')]
class TankController extends AbstractController
{
    private $tankCheckService;
    private $entityManager;

    public function __construct(TankCheckService $tankCheckService, EntityManagerInterface $entityManager, private readonly AdminEquipmentScope $equipmentScope)
    {
        $this->tankCheckService = $tankCheckService;
        $this->entityManager = $entityManager;
    }

    private function checkForCurrentInspection(Tank $tank): bool
    {
        $today = new \DateTime();
        foreach ($tank->getTankChecks() as $tankCheck) {
            if ($tankCheck->getCheckDate() > $today) {
                return true;
            }
        }
        return false;
    }

    #[Route('/', name: 'app_tank_index', methods: ['GET'])]
    public function index(TankRepository $tankRepository, TankCheckRepository $tankCheckRepository): Response
    {
        $tanks = $tankRepository->findBy(['club' => $this->equipmentScope->club()]);
        $nextCheck = $tankCheckRepository->findNextCheck();

        return $this->render('tank/index.html.twig', [
            'tanks' => $tanks,
            'nextCheck' => $nextCheck,
        ]);
    }

    #[Route('/new', name: 'app_tank_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager, TankCheckRepository $tankCheckRepository): Response
    {
        $tank = (new Tank())->setClub($this->equipmentScope->club());
        $form = $this->createForm(TankType::class, $tank);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($tank);
            $entityManager->flush();

            return $this->redirectToRoute('app_tank_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tank/new.html.twig', [
            'tank' => $tank,
            'form' => $form,
            'available_checks' => $tankCheckRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_tank_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Tank $tank): Response
    {
        $this->equipmentScope->assertAsset($tank);
        return $this->render('tank/show.html.twig', [
            'tank' => $tank,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_tank_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Tank $tank, EntityManagerInterface $entityManager): Response
    {
        $this->equipmentScope->assertAsset($tank);
        $form = $this->createForm(TankType::class, $tank);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_tank_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tank/edit.html.twig', [
            'tank' => $tank,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_tank_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Tank $tank, EntityManagerInterface $entityManager): Response
    {
        $this->equipmentScope->assertAsset($tank);
        if ($this->isCsrfTokenValid('delete'.$tank->getId(), $request->request->get('_token'))) {
            $entityManager->remove($tank);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_tank_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/book-inspection/{tankId}/{checkId}', name: 'app_book_inspection', methods: ['GET'])]
    public function bookInspection(int $tankId, int $checkId, TankRepository $tankRepository, TankCheckRepository $tankCheckRepository): Response
    {
        $tank = $tankRepository->find($tankId);
        $tankCheck = $tankCheckRepository->find($checkId);

        if (!$tank || !$tankCheck) {
            throw $this->createNotFoundException("Tank or Check not found.");
        }

        $this->equipmentScope->assertAsset($tank);
        return $this->redirectToRoute('equipment_manager_tuv_club_register', ['id' => $tankCheck->getId()], Response::HTTP_SEE_OTHER);
    }
}
