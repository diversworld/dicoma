<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\WebsitePublication;
use App\Form\WebsitePublicationType;
use App\Service\PublicWebsite;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PRESS_OFFICER')]
final class WebsiteEditorialController extends AbstractController
{
    #[Route('/editorial', name: 'website_editorial', methods: ['GET'])]
    public function index(PublicWebsite $website, EntityManagerInterface $em): Response
    {
        return $this->render('editorial/index.html.twig', ['publications' => $em->getRepository(WebsitePublication::class)->findBy(['club' => $website->editorialClub()], ['id' => 'DESC'])]);
    }

    #[Route('/editorial/new', name: 'website_editorial_new', methods: ['GET', 'POST'])]
    #[Route('/editorial/{id}/edit', name: 'website_editorial_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, PublicWebsite $website, EntityManagerInterface $em, ?int $id = null): Response
    {
        $club = $website->editorialClub();
        $publication = $id === null ? new WebsitePublication($club) : $em->getRepository(WebsitePublication::class)->findOneBy(['id' => $id, 'club' => $club]);
        if ($publication === null) { throw $this->createNotFoundException(); }
        $form = $this->createForm(WebsitePublicationType::class, $publication, ['club' => $club]);
        $form->handleRequest($request);
        $conflict = false;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if ($id !== null && (string) $form->get('version')->getData() !== (string) $publication->getVersion()) {
                    throw new \DomainException('Der Beitrag wurde inzwischen geändert. Bitte neu laden.');
                }
                $publication->record($this->getUser()->getUserIdentifier());
                $em->persist($publication); $em->flush();
                $this->addFlash('success', $publication->isPublished() ? 'Beitrag freigegeben. Sichtbar ab dem Veröffentlichungszeitpunkt, sofern die verknüpfte Veranstaltung freigegeben ist.' : 'Entwurf gespeichert; nicht öffentlich sichtbar.');
                return $this->redirectToRoute('website_editorial', [], Response::HTTP_SEE_OTHER);
            } catch (OptimisticLockException|\DomainException $e) {
                $form->addError(new FormError('Gleichzeitige Änderung: Bitte den Beitrag neu laden. Nicht gespeichert.'));
                $conflict = true;
            }
        }
        return $this->render('editorial/edit.html.twig', ['form' => $form, 'publication' => $publication], new Response(status: $conflict ? 409 : ($form->isSubmitted() ? 422 : 200)));
    }
}
