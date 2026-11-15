<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\{Club, ClubEvent, FileAction, MediaFile, MediaFolder, User, WebsitePublication};
use App\Security\FolderPermissions;
use App\Service\{AdminClubContext, FileManager, MediaFileStorage, MembershipRoleContinuity, PublicWebsite};
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{BinaryFileResponse, Request, Response, ResponseHeaderBag};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Util\ServerParams;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/media')]
#[IsGranted('ROLE_USER')]
final class MediaFileController extends AbstractController
{
    public function __construct(
        private readonly AdminClubContext $clubContext,
        private readonly TranslatorInterface $translator,
        private readonly MembershipRoleContinuity $continuity,
        private readonly FolderPermissions $folderPermissions,
        private readonly RoleHierarchyInterface $hierarchy,
        #[Autowire('%env(bool:CLUB_FUNCTION_MEMBERSHIP_ENABLED)%')] private readonly bool $functionModelEnabled,
        #[AutowireLocator([FileManager::class])] private readonly ContainerInterface $fileManagers,
    ) {}

    #[Route('', name: 'admin_media_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em, PublicWebsite $website): Response
    {
        $club = $this->club($website);
        $folder = $this->folder($request->query->getString('folder'), $club, $em);
        $canRead = $this->can($club, $folder, FileAction::READ);
        if ($folder !== null && !$this->canAccessFolder($club, $folder)) { throw $this->createAccessDeniedException('Keine Berechtigung für diesen Ordner.'); }
        $folders = $em->getRepository(MediaFolder::class)->findBy(['club' => $club], ['name' => 'ASC', 'id' => 'ASC']);
        $visible = [];
        foreach ($folders as $item) {
            if ($this->canAccessFolder($club, $item)) {
                for ($ancestor = $item; $ancestor !== null; $ancestor = $ancestor->getParent()) {
                    if (isset($visible[$ancestor->getId()])) { break; }
                    $visible[$ancestor->getId()] = true;
                }
            }
        }
        $treeFolders = [];
        foreach ($folders as $item) {
            if (isset($visible[$item->getId()])) { $treeFolders[$item->getParent()?->getId() ?? 0][] = $item; }
        }
        $treeFiles = [];
        foreach ($em->getRepository(MediaFile::class)->findBy(['club' => $club], ['originalName' => 'ASC', 'id' => 'ASC']) as $item) {
            if ($this->can($club, $item->getFolder(), FileAction::READ)) {
                $treeFiles[$item->getFolder()?->getId() ?? 0][] = $item;
            }
        }
        $children = array_filter($folders, static fn (MediaFolder $item): bool => $item->getParent()?->getId() === $folder?->getId());
        $files = $canRead ? $em->getRepository(MediaFile::class)->findBy(['club' => $club, 'folder' => $folder], ['id' => 'DESC']) : [];
        $rows = [];
        foreach ($files as $file) {
            $rows[] = ['file' => $file, 'used' => $em->getRepository(WebsitePublication::class)->count(['image' => $file])
                + $em->getRepository(ClubEvent::class)->count(['image' => $file])];
        }
        $breadcrumbs = [];
        for ($ancestor = $folder; $ancestor !== null; $ancestor = $ancestor->getParent()) {
            array_unshift($breadcrumbs, $ancestor);
        }
        $legacy = null;
        if ($request->query->has('legacy_path') && $this->functionModelEnabled) {
            throw $this->createAccessDeniedException('Der bisherige Kursbild-Explorer ist noch nicht auf Ordnerrechte umgestellt.');
        }
        if ($request->query->has('legacy_path')) {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
            $manager = $this->fileManagers->get(FileManager::class);
            if (!$manager instanceof FileManager) {
                throw new \LogicException('Der Kursbild-Explorer ist nicht verfügbar.');
            }
            $path = $manager->normalizePath($request->query->getString('legacy_path'));
            $legacyBreadcrumbs = [['name' => 'Kursbilder', 'path' => '']];
            $legacyTree = ['' => $manager->listDirectory('')];
            $current = '';
            foreach ($path === '' ? [] : explode('/', $path) as $part) {
                $current = ltrim($current.'/'.$part, '/');
                $legacyBreadcrumbs[] = ['name' => $part, 'path' => $current];
                $legacyTree[$current] = $manager->listDirectory($current);
            }
            $legacy = ['files' => $legacyTree[$path], 'breadcrumbs' => $legacyBreadcrumbs, 'tree' => $legacyTree, 'path' => $path];
        }
        return $this->render('admin/media/index.html.twig', [
            'rows' => $rows, 'folder' => $folder, 'folders' => array_filter($folders, fn (MediaFolder $item): bool => $this->can($club, $item, FileAction::UPLOAD)), 'children' => $children, 'club' => $club,
            'breadcrumbs' => $breadcrumbs, 'canManageEditorial' => $website->canManageEditorial(),
            'maxFileUploads' => (int) ini_get('max_file_uploads'), 'legacy' => $legacy,
            'folderFingerprint' => $folder === null ? null : $this->folderFingerprint($folder),
            'parentChoices' => $folder === null ? [] : array_filter($folders, fn (MediaFolder $candidate): bool => !$candidate->isWithin($folder) && $this->can($club, $candidate, FileAction::EDIT)),
            'treeFolders' => $treeFolders, 'treeFiles' => $treeFiles,
            'activeFolderIds' => array_map(static fn (MediaFolder $ancestor): ?int => $ancestor->getId(), $breadcrumbs),
            'canRead' => $canRead, 'canUpload' => $this->can($club, $folder, FileAction::UPLOAD),
            'canEdit' => $this->can($club, $folder, FileAction::EDIT), 'canDelete' => $this->can($club, $folder, FileAction::DELETE),
            'canUploadRoot' => $this->can($club, null, FileAction::UPLOAD),
            'canEditRoot' => $this->can($club, null, FileAction::EDIT),
            'functionModelEnabled' => $this->functionModelEnabled,
        ]);
    }

    #[Route('/folders', name: 'admin_media_folder_create', methods: ['POST'])]
    public function createFolder(Request $request, EntityManagerInterface $em, PublicWebsite $website): Response
    {
        $club = $this->club($website);
        if (!$this->isCsrfTokenValid('media_folder_create_'.$club->getId(), $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $parent = $em->getConnection()->transactional(function () use ($request, $em, $club): ?MediaFolder {
            $this->continuity->lockClub($club);
            if ($this->functionModelEnabled) {
                $clubId = $club->getId();
                $em->clear();
                $club = $em->find(Club::class, $clubId) ?? throw $this->createAccessDeniedException();
            }
            $parent = $this->folder($request->request->getString('parent'), $club, $em);
            $this->requirePermission($club, $parent, FileAction::EDIT);
            try {
                $folder = new MediaFolder($club, $request->request->getString('name'), $parent);
                $this->assertUniqueFolder($em, $folder, $parent);
                $em->persist($folder);
                $em->flush();
                $this->addFlash('success', 'Ordner angelegt.');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
            return $parent;
        });
        return $this->redirectToRoute('admin_media_index', $this->folderParameters($parent), Response::HTTP_SEE_OTHER);
    }

    #[Route('/folders/{id}/edit', name: 'admin_media_folder_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editFolder(int $id, Request $request, EntityManagerInterface $em, PublicWebsite $website): Response
    {
        $club = $this->club($website);
        if (!$this->isCsrfTokenValid('media_folder_edit_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        $em->getConnection()->transactional(function () use ($id, $request, $em, $club): void {
            $this->continuity->lockClub($club);
            if ($this->functionModelEnabled) {
                $clubId = $club->getId();
                $em->clear();
                $club = $em->find(Club::class, $clubId) ?? throw $this->createAccessDeniedException();
            }
            $folder = $this->folder((string) $id, $club, $em) ?? throw $this->createNotFoundException();
            $this->requirePermission($club, $folder, FileAction::EDIT);
            $this->assertFolderFingerprint($request, $folder);
            $parent = $this->folder($request->request->getString('parent'), $club, $em);
            if ($folder->getParent()?->getId() !== $parent?->getId()) {
                $this->requirePermission($club, $parent, FileAction::EDIT);
                if ($this->functionModelEnabled) {
                    $actor = $em->find(User::class, $this->getUser()?->getId());
                    if ($actor === null || !in_array(User::ROLE_ADMIN, $this->hierarchy->getReachableRoleNames($actor->getRoles()), true)) {
                        throw $this->createAccessDeniedException('Nur Administratoren dürfen Ordner verschieben.');
                    }
                }
            }
            try {
                $name = $request->request->getString('name');
                $candidate = new MediaFolder($club, $name, $parent);
                $this->assertUniqueFolder($em, $candidate, $parent, $folder);
                $folder->moveTo($parent);
                $folder->rename($name);
                $em->flush();
                $this->addFlash('success', 'Ordner gespeichert.');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        });
        return $this->redirectToRoute('admin_media_index', ['folder' => $id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/folders/{id}/delete', name: 'admin_media_folder_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteFolder(int $id, Request $request, EntityManagerInterface $em, PublicWebsite $website): Response
    {
        $club = $this->club($website);
        if (!$this->isCsrfTokenValid('media_folder_delete_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        $parent = null;
        try {
            $em->getConnection()->transactional(function () use ($id, $request, $em, $club, &$parent): void {
                $this->continuity->lockClub($club);
                if ($this->functionModelEnabled) {
                    $clubId = $club->getId();
                    $em->clear();
                    $club = $em->find(Club::class, $clubId) ?? throw $this->createAccessDeniedException();
                }
                $folder = $this->folder((string) $id, $club, $em) ?? throw $this->createNotFoundException();
                $this->requirePermission($club, $folder, FileAction::DELETE);
                $this->assertFolderFingerprint($request, $folder);
                $parent = $folder->getParent();
                if ($em->getRepository(MediaFolder::class)->count(['parent' => $folder]) > 0
                    || $em->getRepository(MediaFile::class)->count(['folder' => $folder]) > 0) {
                    $this->addFlash('warning', 'Nur leere Ordner können gelöscht werden.');
                    $parent = $folder;
                    return;
                }
                $em->remove($folder);
                $em->flush();
                $this->addFlash('success', 'Ordner gelöscht.');
            });
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('warning', 'Der Ordner ist nicht mehr leer und kann nicht gelöscht werden.');
            return $this->redirectToRoute('admin_media_index', ['folder' => $id], Response::HTTP_SEE_OTHER);
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
        if (!$this->isCsrfTokenValid('media_upload_'.$club->getId(), $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $folder = $this->folder($request->request->getString('folder'), $club, $em);
        $this->requirePermission($club, $folder, FileAction::UPLOAD);
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
                $folderId = $folder?->getId();
                $this->withClubLock($club, $em, function (Club $lockedClub) use ($folderId, $em, $storage, $upload, $request): void {
                    $lockedFolder = $this->folder($folderId === null ? '' : (string) $folderId, $lockedClub, $em);
                    $this->requirePermission($lockedClub, $lockedFolder, FileAction::UPLOAD);
                    $file = $storage->store($lockedClub, $upload, $request->request->getString('alt_text'));
                    $file->setFolder($lockedFolder);
                    $saved = false;
                    try {
                        $em->persist($file); $em->flush(); $saved = true;
                    } finally {
                        if (!$saved) { $storage->delete($file); }
                    }
                });
                ++$savedCount;
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $upload->getClientOriginalName().': '.$this->translator->trans($e->getMessage()));
            }
        }
        if ($savedCount > 0) { $this->addFlash('success', new TranslatableMessage('%count% Datei(en) gespeichert.', ['%count%' => $savedCount])); }
        return $this->redirectToRoute('admin_media_index', $this->folderParameters($folder), Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/move', name: 'admin_media_move', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function move(int $id, Request $request, EntityManagerInterface $em, PublicWebsite $website): Response
    {
        if (!$this->isCsrfTokenValid('media_move_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $club = $this->club($website);
        return $this->withClubLock($club, $em, function (Club $lockedClub) use ($id, $request, $em, $website): Response {
            $file = $this->owned($id, $em, $website);
            $source = $file->getFolder();
            $target = $this->folder($request->request->getString('folder'), $lockedClub, $em);
            $this->requirePermission($lockedClub, $source, FileAction::EDIT);
            $this->requirePermission($lockedClub, $target, FileAction::UPLOAD);
            $file->setFolder($target);
            $em->flush();
            $this->addFlash('success', 'Datei verschoben.');
            return $this->redirectToRoute('admin_media_index', $this->folderParameters($source), Response::HTTP_SEE_OTHER);
        });
    }

    #[Route('/{id}', name: 'admin_media_file', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(int $id, EntityManagerInterface $em, MediaFileStorage $storage, PublicWebsite $website): BinaryFileResponse
    {
        $file = $this->owned($id, $em, $website);
        $this->requirePermission($file->getClub(), $file->getFolder(), FileAction::READ);
        return $storage->response($file, $file->isImage() ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    #[Route('/{id}/edit', name: 'admin_media_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, PublicWebsite $website): Response
    {
        $club = $this->club($website);
        $operation = function (Club $lockedClub) use ($id, $request, $em, $website): Response {
            $file = $this->owned($id, $em, $website);
            $this->requirePermission($lockedClub, $file->getFolder(), FileAction::EDIT);
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
        };
        return $request->isMethod('POST') ? $this->withClubLock($club, $em, $operation) : $operation($club);
    }

    #[Route('/{id}/delete', name: 'admin_media_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, MediaFileStorage $storage, PublicWebsite $website): Response
    {
        if (!$this->isCsrfTokenValid('media_delete_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException('Ungültiger CSRF-Token.'); }
        $club = $this->club($website);
        $removed = null;
        try {
            $response = $this->withClubLock($club, $em, function (Club $lockedClub) use ($id, $em, $website, &$removed): Response {
                $file = $this->owned($id, $em, $website);
                $this->requirePermission($lockedClub, $file->getFolder(), FileAction::DELETE);
                if ($em->getRepository(WebsitePublication::class)->count(['image' => $file]) > 0 || $em->getRepository(ClubEvent::class)->count(['image' => $file]) > 0) {
                    $this->addFlash('warning', 'Verwendete Dateien können nicht gelöscht werden.');
                } else {
                    $em->remove($file);
                    $em->flush();
                    $removed = $file;
                }
                return $this->redirectToRoute('admin_media_index', $this->folderParameters($file->getFolder()), Response::HTTP_SEE_OTHER);
            });
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('warning', 'Die Datei wurde inzwischen einem Inhalt zugeordnet und kann nicht gelöscht werden.');
            return $this->redirectToRoute('admin_media_index', [], Response::HTTP_SEE_OTHER);
        }
        if ($removed !== null) {
            $storage->delete($removed);
            $this->addFlash('success', 'Datei gelöscht.');
        }
        return $response;
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

    private function assertUniqueFolder(EntityManagerInterface $em, MediaFolder $candidate, ?MediaFolder $parent, ?MediaFolder $existing = null): void
    {
        $duplicate = $em->getRepository(MediaFolder::class)->findOneBy([
            'club' => $candidate->getClub(), 'parent' => $parent, 'name' => $candidate->getName(),
        ]);
        if ($duplicate !== null && $duplicate->getId() !== $existing?->getId()) {
            throw new \InvalidArgumentException('Ein Ordner mit diesem Namen existiert hier bereits.');
        }
    }

    private function folderFingerprint(MediaFolder $folder): string
    {
        return hash('sha256', json_encode([$folder->getId(), $folder->getName(), $folder->getParent()?->getId()], JSON_THROW_ON_ERROR));
    }

    private function assertFolderFingerprint(Request $request, MediaFolder $folder): void
    {
        if (!hash_equals($this->folderFingerprint($folder), $request->request->getString('snapshot'))) {
            throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException('Der Ordner wurde zwischenzeitlich geändert. Bitte neu laden.');
        }
    }

    private function club(PublicWebsite $website): Club
    {
        if ($this->functionModelEnabled) {
            return $this->clubContext->getClub() ?? throw $this->createAccessDeniedException('Vereinszuordnung erforderlich.');
        }
        $this->denyAccessUnlessGranted('ROLE_PRESS_OFFICER');
        if (!$this->isGranted('ROLE_ADMIN')) { return $website->editorialClub(); }
        return $this->clubContext->getClub() ?? throw $this->createAccessDeniedException('Vereinszuordnung erforderlich.');
    }

    private function can(Club $club, ?MediaFolder $folder, FileAction $action): bool
    {
        if (!$this->functionModelEnabled) { return true; }
        $user = $this->getUser();
        return $user instanceof User && $this->folderPermissions->can($user, $club, $folder, $action);
    }

    private function requirePermission(Club $club, ?MediaFolder $folder, FileAction $action): void
    {
        if (!$this->can($club, $folder, $action)) {
            throw $this->createAccessDeniedException('Keine Berechtigung für diese Dateiaktion.');
        }
    }

    private function canAccessFolder(Club $club, MediaFolder $folder): bool
    {
        foreach (FileAction::cases() as $action) {
            if ($this->can($club, $folder, $action)) {
                return true;
            }
        }
        return false;
    }

    private function withClubLock(Club $club, EntityManagerInterface $em, callable $operation): mixed
    {
        if (!$this->functionModelEnabled) {
            return $operation($club);
        }
        $id = $club->getId();
        return $em->getConnection()->transactional(function () use ($club, $id, $em, $operation): mixed {
            $this->continuity->lockClub($club);
            $em->clear();
            $current = $em->find(Club::class, $id) ?? throw $this->createAccessDeniedException();
            return $operation($current);
        });
    }
}
