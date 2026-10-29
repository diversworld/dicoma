<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\{Club, ClubEvent, MediaFile, User, WebsitePublication};
use App\Service\{MediaFileStorage, PublicWebsite};
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{BinaryFileResponse, Request, Response, ResponseHeaderBag};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/media')]
#[IsGranted('ROLE_PRESS_OFFICER')]
final class MediaFileController extends AbstractController
{
    #[Route('', name: 'admin_media_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em, PublicWebsite $website): Response
    {
        $club = $this->club($website);
        $files = $em->getRepository(MediaFile::class)->findBy(['club' => $club], ['id' => 'DESC']);
        $rows = [];
        foreach ($files as $file) {
            $rows[] = ['file' => $file, 'used' => $em->getRepository(WebsitePublication::class)->count(['image' => $file])
                + $em->getRepository(ClubEvent::class)->count(['image' => $file])];
        }
        return $this->render('admin/media/index.html.twig', ['rows' => $rows, 'canManageEditorial' => $website->canManageEditorial()]);
    }

    #[Route('/upload', name: 'admin_media_upload', methods: ['POST'])]
    public function upload(Request $request, EntityManagerInterface $em, MediaFileStorage $storage, PublicWebsite $website): Response
    {
        $club = $this->club($website);
        if (!$this->isCsrfTokenValid('media_upload', $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $upload = $request->files->get('file');
        if (!$upload instanceof UploadedFile) {
            $this->addFlash('danger', 'Bitte eine Datei auswählen; die maximale Dateigröße beträgt 10 MB.');
        } else {
            try {
                $file = $storage->store($club, $upload, $request->request->getString('alt_text'));
                $saved = false;
                try {
                    $em->persist($file); $em->flush(); $saved = true;
                } finally {
                    if (!$saved) { $storage->delete($file); }
                }
                $this->addFlash('success', 'Datei gespeichert.');
            } catch (\InvalidArgumentException $e) { $this->addFlash('danger', $e->getMessage()); }
        }
        return $this->redirectToRoute('admin_media_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'admin_media_file', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(int $id, EntityManagerInterface $em, MediaFileStorage $storage, PublicWebsite $website): BinaryFileResponse
    {
        $file = $this->owned($id, $em, $website);
        return $storage->response($file, $file->isImage() ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    #[Route('/{id}/edit', name: 'admin_media_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, PublicWebsite $website): Response
    {
        $file = $this->owned($id, $em, $website);
        $form = $this->createFormBuilder($file, ['csrf_token_id' => 'media_edit_'.$id])
            ->add('originalName', TextType::class, ['label' => 'Dateiname'])
            ->add('altText', TextType::class, ['label' => 'Alternativtext für Bilder', 'required' => false, 'empty_data' => ''])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Dateiangaben gespeichert.');
            return $this->redirectToRoute('admin_media_index', [], Response::HTTP_SEE_OTHER);
        }
        return $this->render('admin/media/edit.html.twig', ['form' => $form, 'file' => $file], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/{id}/delete', name: 'admin_media_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, MediaFileStorage $storage, PublicWebsite $website): Response
    {
        if (!$this->isCsrfTokenValid('media_delete_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $file = $this->owned($id, $em, $website);
        if ($em->getRepository(WebsitePublication::class)->count(['image' => $file]) > 0 || $em->getRepository(ClubEvent::class)->count(['image' => $file]) > 0) {
            $this->addFlash('warning', 'Verwendete Dateien können nicht gelöscht werden.');
        } else {
            try {
                $em->remove($file); $em->flush();
                $storage->delete($file);
                $this->addFlash('success', 'Datei gelöscht.');
            } catch (ForeignKeyConstraintViolationException) {
                $this->addFlash('warning', 'Die Datei wurde inzwischen einem Inhalt zugeordnet und kann nicht gelöscht werden.');
            }
        }
        return $this->redirectToRoute('admin_media_index', [], Response::HTTP_SEE_OTHER);
    }

    private function owned(int $id, EntityManagerInterface $em, PublicWebsite $website): MediaFile
    {
        $file = $em->find(MediaFile::class, $id);
        if (!$file || $file->getClub()->getId() !== $this->club($website)->getId()) { throw $this->createNotFoundException(); }
        return $file;
    }

    private function club(PublicWebsite $website): Club
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->getMember()?->getClub()) { throw $this->createAccessDeniedException('Vereinszuordnung erforderlich.'); }
        if (!$this->isGranted('ROLE_ADMIN')) { return $website->editorialClub(); }
        return $user->getMember()->getClub();
    }

}
