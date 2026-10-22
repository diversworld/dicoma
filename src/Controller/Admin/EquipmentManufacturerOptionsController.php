<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EquipmentManufacturer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class EquipmentManufacturerOptionsController extends AbstractController
{
    #[Route('/admin/api/equipment-manufacturers', name: 'admin_equipment_manufacturer_options', methods: ['GET'])]
    public function __invoke(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $raw = $request->query->get('club');
        if (!is_scalar($raw) || !ctype_digit((string) $raw) || (int) $raw < 1) {
            return $this->json([], 200, ['Cache-Control' => 'no-store']);
        }

        $manufacturers = $em->getRepository(EquipmentManufacturer::class)->findBy(
            ['club' => (int) $raw, 'active' => true],
            ['name' => 'ASC']
        );
        $items = array_map(static fn (EquipmentManufacturer $manufacturer): array => [
            'id' => $manufacturer->getId(),
            'label' => $manufacturer->getName(),
        ], $manufacturers);

        return $this->json($items, 200, ['Cache-Control' => 'no-store']);
    }
}
