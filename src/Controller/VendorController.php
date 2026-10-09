<?php

namespace App\Controller;

use App\Entity\Vendor;
use App\Form\VendorType;
use App\Repository\VendorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/vendor')]
class VendorController extends AbstractController
{
    #[Route('/', name: 'app_vendor_index', methods: ['GET'])]
    public function index(VendorRepository $vendorRepository): Response
    {
        return $this->render('vendor/index.html.twig', [
            'vendors' => $vendorRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_vendor_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $vendor = new Vendor();
        $form = $this->createForm(VendorType::class, $vendor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($vendor);
            $entityManager->flush();

            return $this->redirectToRoute('app_vendor_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('vendor/new.html.twig', [
            'vendor' => $vendor,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_vendor_show', methods: ['GET'])]
    public function show(Vendor $vendor): Response
    {
        return $this->render('vendor/show.html.twig', [
            'vendor' => $vendor,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_vendor_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Vendor $vendor, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(VendorType::class, $vendor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_vendor_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('vendor/edit.html.twig', [
            'vendor' => $vendor,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_vendor_delete', methods: ['POST'])]
    public function delete(Request $request, Vendor $vendor, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$vendor->getId(), $request->request->get('_token'))) {
            $entityManager->remove($vendor);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_vendor_index', [], Response::HTTP_SEE_OTHER);
    }
}
