<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\{Club, ClubEvent, MediaFile, MediaFolder, User, WebsitePublication};
use App\Service\{MediaFileStorage, PublicWebsite};
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{BinaryFileResponse, Request, Response, ResponseHeaderBag};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Util\ServerParams;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/media')]
#[IsGranted('ROLE_PRESS_OFFICER')]
final class MediaFileController extends AbstractController
{
    #[Route('', name: 'admin_media_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em, PublicWebsite $website): Response
    {
        $club = $this->club($website);
        $folder = $this->folder($request->query->getString('folder'), $club, $em);
        $folders = $em->getRepository(MediaFolder::class)->findBy(['club' => $club], ['name' => 'ASC', 'id' => 'ASC']);
        $children = array_filter($folders, static fn (MediaFolder $item): bool => $item->getParent()?->getId() === $folder?->getId());
        $files = $em->getRepository(MediaFile::class)->findBy(['club' => $club, 'folder' => $folder], ['id' => 'DESC']);
        $rows = [];
        foreach ($files as $file) {
            $rows[] = ['file' => $file, 'used' => $em->getRepository(WebsitePublication::class)->count(['image' => $file])
                + $em->getRepository(ClubEvent::class)->count(['image' => $file])];
        }
        $breadcrumbs = [];
        for ($ancestor = $folder; $ancestor !== null; $ancestor = $ancestor->getParent()) {
            array_unshift($breadcrumbs, $ancestor);
        }
        return $this->render('admin/media/index.html.twig', [
            'rows' => $rows, 'folder' => $folder, 'folders' => $folders, 'children' => $children,
            'breadcrumbs' => $breadcrumbs, 'canManageEditorial' => $website->canManageEditorial(),
            'maxFileUploads' => (int) ini_get('max_file_uploads'),
        ]);
    }

    #[Route('/folders', name: 'admin_media_folder_create', methods: ['POST'])]
    public function createFolder(Request $request, EntityManagerInterface $em, PublicWebsite $website): Response
    {
        $club = $this->club($website);
        if (!$this->isCsrfTokenValid('media_folder_create', $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $parent = $this->folder($request->request->getString('parent'), $club, $em);
        try {
            $folder = new MediaFolder($club, $request->request->getString('name'), $parent);
            if ($em->getRepository(MediaFolder::class)->findOneBy(['club' => $club, 'parent' => $parent, 'name' => $folder->getName()])) {
                throw new \InvalidArgumentException('Ein Ordner mit diesem Namen existiert hier bereits.');
            }
            $em->persist($folder);
            $em->flush();
            $this->addFlash('success', 'Ordner angelegt.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
        return $this->redirectToRoute('admin_media_index', $this->folderParameters($parent), Response::HTTP_SEE_OTHER);
    }

    #[Route('/upload', name: 'admin_media_upload', methods: ['POST'])]
    public function upload(Request $request, RequestStack $requestStack, EntityManagerInterface $em, MediaFileStorage $storage, PublicWebsite $website): Response
    {
        $club = $this->club($website);
        if ((new ServerParams($requestStack))->hasPostMaxSizeBeenExceeded()) {
            throw new HttpException(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'Die Gesamtgröße der Dateien überschreitet das Serverlimit. Bitte weniger Dateien gleichzeitig hochladen.');
        }
        if (!$this->isCsrfTokenValid('media_upload', $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $folder = $this->folder($request->request->getString('folder'), $club, $em);
        $uploads = $request->files->get('files') ?? $request->files->get('file');
        $uploads = is_array($uploads) ? $uploads : [$uploads];
        $selectedCount = $request->request->getString('selected_count');
        if ($selectedCount !== '' && ctype_digit($selectedCount) && (int) $selectedCount > count(array_filter($uploads))) {
            $this->addFlash('danger', 'Nicht alle ausgewählten Dateien wurden vom Server angenommen. Bitte das Serverlimit prüfen und die fehlenden Dateien erneut hochladen.');
        }
        if ($uploads === [] || $uploads === [null]) {
            $this->addFlash('danger', 'Bitte mindestens eine Datei auswählen; die maximale Dateigröße beträgt 10 MB je Datei.');
            $uploads = [];
        }
        $savedCount = 0;
        foreach ($uploads as $upload) {
            if (!$upload instanceof UploadedFile) {
                $this->addFlash('danger', 'Eine Datei konnte nicht hochgeladen werden.');
                continue;
            }
            try {
                $file = $storage->store($club, $upload, $request->request->getString('alt_text'));
                $file->setFolder($folder);
                $saved = false;
                try {
                    $em->persist($file); $em->flush(); $saved = true;
                } finally {
                    if (!$saved) { $storage->delete($file); }
                }
                ++$savedCount;
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $upload->getClientOriginalName().': '.$e->getMessage());
            }
        }
        if ($savedCount > 0) { $this->addFlash('success', $savedCount.' Datei(en) gespeichert.'); }
        return $this->redirectToRoute('admin_media_index', $this->folderParameters($folder), Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/move', name: 'admin_media_move', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function move(int $id, Request $request, EntityManagerInterface $em, PublicWebsite $website): Response
    {
        if (!$this->isCsrfTokenValid('media_move_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $file = $this->owned($id, $em, $website);
        $source = $file->getFolder();
        $target = $this->folder($request->request->getString('folder'), $file->getClub(), $em);
        $file->setFolder($target);
        $em->flush();
        $this->addFlash('success', 'Datei verschoben.');
        return $this->redirectToRoute('admin_media_index', $this->folderParameters($source), Response::HTTP_SEE_OTHER);
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
            return $this->redirectToRoute('admin_media_index', $this->folderParameters($file->getFolder()), Response::HTTP_SEE_OTHER);
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
        return $this->redirectToRoute('admin_media_index', $this->folderParameters($file->getFolder()), Response::HTTP_SEE_OTHER);
    }

    private function owned(int $id, EntityManagerInterface $em, PublicWebsite $website): MediaFile
    {
        $file = $em->find(MediaFile::class, $id);
        if (!$file || $file->getClub()->getId() !== $this->club($website)->getId()) { throw $this->createNotFoundException(); }
        return $file;
    }

    private function folder(string $id, Club $club, EntityManagerInterface $em): ?MediaFolder
    {
        if ($id === '') { return null; }
        if (!ctype_digit($id) || (int) $id < 1) { throw $this->createNotFoundException('Ordner nicht gefunden.'); }
        $folder = $em->find(MediaFolder::class, (int) $id);
        if (!$folder || $folder->getClub()->getId() !== $club->getId()) { throw $this->createNotFoundException('Ordner nicht gefunden.'); }
        return $folder;
    }

    private function folderParameters(?MediaFolder $folder): array
    {
        return $folder ? ['folder' => $folder->getId()] : [];
    }

    private function club(PublicWebsite $website): Club
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->getMember()?->getClub()) { throw $this->createAccessDeniedException('Vereinszuordnung erforderlich.'); }
        if (!$this->isGranted('ROLE_ADMIN')) { return $website->editorialClub(); }
        return $user->getMember()->getClub();
    }

}
