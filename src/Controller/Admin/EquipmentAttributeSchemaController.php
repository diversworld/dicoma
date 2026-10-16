<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EquipmentAttribute;
use App\Entity\EquipmentCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class EquipmentAttributeSchemaController extends AbstractController
{
    #[Route('/admin/equipment-attribute-schema', name: 'admin_equipment_attribute_schema', methods: ['GET'])]
    public function __invoke(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $raw = $request->query->get('categoryId');
        if (!is_string($raw) || !ctype_digit($raw) || (int) $raw < 1) {
            return $this->json(['error' => 'Ungültige Kategorie.'], 400);
        }
        $category = $entityManager->getRepository(EquipmentCategory::class)->find((int) $raw);
        if (!$category instanceof EquipmentCategory) {
            return $this->json(['error' => 'Kategorie nicht gefunden.'], 404);
        }
        $definitions = $entityManager->getRepository(EquipmentAttribute::class)
            ->findBy(['category' => $category], ['id' => 'ASC']);
        $attributes = [];
        foreach ($definitions as $definition) {
            $attributes[] = [
                'code' => $definition->getCode(),
                'label' => $definition->getLabel(),
                'type' => $definition->getDataType(),
                'required' => $definition->isRequired(),
            ];
        }
        return $this->json([
            'categoryId' => $category->getId(),
            'clubId' => $category->getClub()?->getId(),
            'attributes' => $attributes,
        ]);
    }
}
