<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\EquipmentAssetRecord;
use App\Entity\EquipmentDocument;
use App\Entity\EquipmentItem;
use App\Entity\Regulator;
use App\Entity\Tank;
use App\Entity\TankMaintenanceOrder;
use App\Entity\User;
use App\Service\EquipmentInventory;
use App\Service\EquipmentMaintenanceService;
use App\Service\PrivateMemberDocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentInventoryController extends AbstractController
{
    private function clubId(): int
    {
        $user = $this->getUser();
        $id = $user instanceof User ? $user->getMember()?->getClub()?->getId() : null;
        if (!$id) { throw $this->createAccessDeniedException('Kein Verein zugeordnet.'); }
        return $id;
    }

    #[Route('/equipment-manager/inventory', name: 'equipment_manager_inventory', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $clubId = $this->clubId();
        $type = $request->query->getString('type', 'equipment');
        if (!isset(EquipmentInventory::TYPES[$type])) { throw $this->createNotFoundException(); }
        $page = max(1, $request->query->getInt('page', 1));
        $repo = $em->getRepository(EquipmentInventory::TYPES[$type]);
        return $this->render('equipment_manager/inventory.html.twig', [
            'assets' => $repo->findBy(['club' => $clubId], ['id' => 'ASC'], 50, ($page - 1) * 50),
            'total' => $repo->count(['club' => $clubId]), 'page' => $page, 'type' => $type,
        ]);
    }

    #[Route('/equipment-manager/inventory/{type}/{id}', name: 'equipment_manager_inventory_detail', requirements: ['type' => 'equipment|regulator|tank', 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function detail(string $type, int $id, Request $request, EntityManagerInterface $em, EquipmentInventory $inventory): Response
    {
        try { $asset = $inventory->asset($type, $id, $this->clubId()); }
        catch (\DomainException) { throw $this->createNotFoundException(); }
        if ($asset instanceof EquipmentItem && $asset->getTank() !== null) {
            return $this->redirectToRoute('equipment_manager_inventory_detail', ['type' => 'tank', 'id' => $asset->getTank()->getId()]);
        }
        $record = $inventory->findRecord($asset) ?? new EquipmentAssetRecord($asset);
        $form = $this->createFormBuilder($record, ['data_class' => EquipmentAssetRecord::class])
            ->add('ownership', ChoiceType::class, ['label' => 'Eigentumsverhältnis', 'choices' => ['Vereinseigentum' => 'club', 'Privateigentum' => 'private', 'Leihgabe' => 'borrowed']])
            ->add('ownerName', TextType::class, ['label' => 'Eigentümer / Leihgeber', 'required' => false])
            ->add('location', TextType::class, ['label' => 'Lagerort', 'required' => false])
            ->add('purchasedAt', DateType::class, ['label' => 'Anschaffungsdatum', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false])
            ->add('purchasePriceCents', MoneyType::class, ['label' => 'Anschaffungspreis', 'divisor' => 100, 'required' => false])
            ->add('inspectionIntervalMonths', IntegerType::class, ['label' => 'Prüfintervall in Monaten', 'required' => false])
            ->add('nextInspectionAt', DateType::class, ['label' => 'Nächste Prüfung (allgemeine Ausrüstung)', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false, 'disabled' => !($asset instanceof EquipmentItem)])
            ->add('approvalRequired', CheckboxType::class, ['label' => 'Verleih benötigt Gerätewart-Genehmigung', 'required' => false])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->wrapInTransaction(function () use ($inventory, $asset, $type, $id, $record, $em): void {
                $inventory->asset($type, $id, $this->clubId(), true);
                $record->record('inventory_updated', $this->getUser()->getUserIdentifier(), 'Stammdaten, Lagerort oder Prüfplanung aktualisiert.');
                if ($asset instanceof EquipmentItem) { $asset->setPurchasedAt($record->getPurchasedAt()); }
                elseif ($asset instanceof Tank) { $asset->setBuyDate($record->getPurchasedAt()); }
                $em->persist($record);
            });
            $this->addFlash('success', 'Inventarakte gespeichert.');
            return $this->redirectToRoute('equipment_manager_inventory_detail', compact('type', 'id'), Response::HTTP_SEE_OTHER);
        }
        $orders = $em->getRepository(TankMaintenanceOrder::class)->findBy([EquipmentInventory::field($asset) => $asset], ['id' => 'DESC']);
        $documents = $record->getId() ? $em->getRepository(EquipmentDocument::class)->findBy(['record' => $record], ['id' => 'DESC']) : [];
        return $this->render('equipment_manager/inventory_detail.html.twig', [
            'asset' => $asset, 'record' => $record, 'form' => $form, 'orders' => $orders,
            'documents' => $documents, 'type' => $type, 'due' => $inventory->dueAt($asset),
        ]);
    }

    #[Route('/equipment-manager/inventory/{type}/{id}/maintenance', name: 'equipment_manager_inventory_maintenance', requirements: ['type' => 'equipment|regulator|tank', 'id' => '\d+'], methods: ['POST'])]
    public function maintenance(string $type, int $id, Request $request, EquipmentMaintenanceService $service): Response
    {
        $clubId = $this->clubId();
        if (!$this->isCsrfTokenValid('inventory_maintenance_'.$type.'_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
        try {
            $actor = $this->getUser()->getUserIdentifier();
            match ($request->request->getString('operation')) {
                'open' => $service->open($type, $id, $clubId, $request->request->getString('kind'), $request->request->getString('description'), $request->request->getString('responsible'), $actor),
                'close' => $service->close($type, $id, $clubId, $request->request->getInt('order_id'), $request->request->getString('resolution'), $actor),
                'release' => $service->release($type, $id, $clubId, $request->request->getString('reason'), $actor),
                'inspect' => $service->inspect($type, $id, $clubId, $request->request->getString('performed'), $request->request->getString('next'), $request->request->getString('reference'), $actor),
                default => throw new \DomainException('Unbekannte Wartungsaktion.'),
            };
            $this->addFlash('success', 'Wartungsvorgang gespeichert. Abschluss und technische Freigabe erfolgen getrennt.');
        } catch (\DomainException $e) { $this->addFlash('danger', $e->getMessage()); }
        return $this->redirectToRoute('equipment_manager_inventory_detail', compact('type', 'id'), Response::HTTP_SEE_OTHER);
    }

    #[Route('/equipment-manager/inventory/{type}/{id}/documents', name: 'equipment_manager_inventory_document_upload', requirements: ['type' => 'equipment|regulator|tank', 'id' => '\d+'], methods: ['POST'])]
    public function upload(string $type, int $id, Request $request, EquipmentInventory $inventory, EntityManagerInterface $em, PrivateMemberDocumentStorage $storage): Response
    {
        $clubId = $this->clubId();
        if (!$this->isCsrfTokenValid('inventory_document_'.$type.'_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
        $file = $request->files->get('document');
        if (!$file instanceof UploadedFile) {
            $this->addFlash('danger', 'Dokument fehlt.');
        } else {
            $stored = null;
            try {
                $em->wrapInTransaction(function () use ($type, $id, $clubId, $inventory, $em, $storage, $file, &$stored): void {
                    $asset = $inventory->asset($type, $id, $clubId, true);
                    $originalName = $file->getClientOriginalName();
                    [$stored, $mime, $size] = $storage->store($file);
                    $record = $inventory->record($asset);
                    $em->persist(new EquipmentDocument($record, $originalName, $stored, $mime, $size, $this->getUser()->getUserIdentifier()));
                    $record->record('document_uploaded', $this->getUser()->getUserIdentifier(), $originalName);
                });
                $this->addFlash('success', 'Dokument privat abgelegt.');
            } catch (\DomainException|\InvalidArgumentException $e) {
                if ($stored !== null) { unlink($storage->path($stored)); }
                $this->addFlash('danger', $e->getMessage());
            } catch (\Throwable $e) {
                if ($stored !== null) { unlink($storage->path($stored)); }
                throw $e;
            }
        }
        return $this->redirectToRoute('equipment_manager_inventory_detail', compact('type', 'id'), Response::HTTP_SEE_OTHER);
    }

    #[Route('/equipment-manager/inventory/documents/{id}', name: 'equipment_manager_inventory_document', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(int $id, EntityManagerInterface $em, PrivateMemberDocumentStorage $storage): Response
    {
        $document = $em->find(EquipmentDocument::class, $id);
        if (!$document || $document->getRecord()->getAsset()->getClub()?->getId() !== $this->clubId()) { throw $this->createNotFoundException(); }
        $response = $this->file($storage->path($document->getStorageName()), $document->getOriginalName(), ResponseHeaderBag::DISPOSITION_ATTACHMENT);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
