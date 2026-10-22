<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EquipmentCategory;
use App\Entity\EquipmentManufacturer;
use App\Entity\EquipmentModel;
use App\Entity\EquipmentSize;
use App\Entity\EquipmentSubType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class EquipmentInventoryOptionsController extends AbstractController
{
    #[Route('/admin/equipment/inventory-options', name: 'admin_equipment_inventory_options', methods: ['GET'])]
    public function __invoke(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $empty = ['subTypes' => [], 'models' => [], 'sizes' => []];
        $parse = static fn (mixed $raw): ?int => is_scalar($raw) && ctype_digit((string) $raw) && (int) $raw > 0 ? (int) $raw : null;
        $club = $parse($request->query->get('club'));
        $category = $parse($request->query->get('category'));
        $manufacturer = $parse($request->query->get('manufacturer'));
        if (!$club || !$category) return $this->json($empty);
        $categoryEntity = $em->getRepository(EquipmentCategory::class)->find($category);
        if (!$categoryEntity || $categoryEntity->getClub()?->getId() !== $club) return $this->json($empty);
        if ($manufacturer) {
            $maker = $em->getRepository(EquipmentManufacturer::class)->find($manufacturer);
            if (!$maker || $maker->getClub()?->getId() !== $club || !$maker->isActive()) return $this->json($empty);
        }
        $map = static fn (array $rows): array => array_map(static fn ($row): array => ['id' => $row->getId(), 'label' => (string) $row], $rows);
        return $this->json([
            'subTypes' => $map($em->getRepository(EquipmentSubType::class)->findBy(['club' => $club, 'category' => $category, 'active' => true], ['name' => 'ASC'])),
            'models' => $manufacturer ? $map($em->getRepository(EquipmentModel::class)->findBy(['club' => $club, 'category' => $category, 'manufacturer' => $manufacturer, 'active' => true], ['name' => 'ASC'])) : [],
            'sizes' => $manufacturer ? $map($em->getRepository(EquipmentSize::class)->findBy(['club' => $club, 'category' => $category, 'manufacturer' => $manufacturer, 'active' => true], ['name' => 'ASC'])) : [],
        ]);
    }
}
