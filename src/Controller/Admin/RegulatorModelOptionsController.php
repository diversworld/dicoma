<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\RegulatorModel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class RegulatorModelOptionsController extends AbstractController
{
    #[Route('/admin/api/regulator-model-options', name: 'admin_regulator_model_options', methods: ['GET'])]
    public function __invoke(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $clubId = filter_var($request->query->get('club'), FILTER_VALIDATE_INT);
        $manufacturerId = filter_var($request->query->get('manufacturer'), FILTER_VALIDATE_INT);
        if (!$clubId || !$manufacturerId) {
            return $this->json(['first' => [], 'second' => []]);
        }
        $models = $em->getRepository(RegulatorModel::class)->findBy([
            'club' => $clubId, 'manufacturer' => $manufacturerId, 'active' => true,
        ], ['name' => 'ASC']);
        $result = ['first' => [], 'second' => []];
        foreach ($models as $model) {
            $result[$model->getStage()][] = ['id' => $model->getId(), 'label' => $model->getName()];
        }
        return $this->json($result, 200, ['Cache-Control' => 'no-store']);
    }
}
