<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\RegulatorModel;
use App\Service\AdminEquipmentScope;
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
    public function __invoke(Request $request, EntityManagerInterface $em, AdminEquipmentScope $scope): JsonResponse
    {
        $clubId = filter_var($request->query->get('club'), FILTER_VALIDATE_INT);
        $manufacturerId = filter_var($request->query->get('manufacturer'), FILTER_VALIDATE_INT);
        if (!$clubId || !$manufacturerId) {
            return $this->json(['first' => [], 'second' => []]);
        }
        $club = $scope->club();
        if ($club->getId() !== $clubId) {
            throw $this->createAccessDeniedException('Verein gehört nicht zum ausgewählten Kontext.');
        }
        $models = $em->getRepository(RegulatorModel::class)->createQueryBuilder('model')
            ->join('model.manufacturer', 'manufacturer')
            ->where('model.club = :club')->andWhere('manufacturer.club = :club')
            ->andWhere('manufacturer.id = :manufacturer')->andWhere('model.active = true')
            ->setParameter('club', $club)->setParameter('manufacturer', $manufacturerId)
            ->orderBy('model.name', 'ASC')->getQuery()->getResult();
        $result = ['first' => [], 'second' => []];
        foreach ($models as $model) {
            $result[$model->getStage()][] = ['id' => $model->getId(), 'label' => $model->getName()];
        }
        return $this->json($result, 200, ['Cache-Control' => 'no-store']);
    }
}
