<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\FileManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[IsGranted('ROLE_ADMIN')]
final class FileExplorerController extends AbstractController
{
    public function __construct(
        private readonly FileManager $fileManager,
        #[Autowire('%env(bool:CLUB_FUNCTION_MEMBERSHIP_ENABLED)%')] private readonly bool $functionModelEnabled,
    ) {
    }

    #[Route('/admin/files', name: 'admin_files_index', methods: ['GET'])]
    public function index(Request $request): RedirectResponse
    {
        $this->requireLegacyExplorer();
        $path = $this->fileManager->normalizePath($request->query->getString('path'));
        return $this->redirectToRoute('admin_media_index', ['legacy_path' => $path, '_fragment' => 'legacy-course-files']);
    }

    #[Route('/admin/files/download', name: 'admin_files_download', methods: ['GET'])]
    public function download(Request $request): BinaryFileResponse
    {
        $this->requireLegacyExplorer();
        $file = $this->fileManager->getFile($request->query->getString('path'));
        $response = new BinaryFileResponse($file);
        $filename = preg_replace('/[\x00-\x1f\x7f]/u', '_', str_replace('\\', '_', basename($file)));
        if ($filename === null || $filename === '') {
            throw new \RuntimeException('Der Dateiname kann nicht für den Download dargestellt werden.');
        }
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPrivate()->headers->addCacheControlDirective('no-store');
        return $response;
    }

    #[Route('/admin/files/preview/{path}', name: 'admin_files_preview', requirements: ['path' => '.+'], methods: ['GET'])]
    public function preview(string $path): BinaryFileResponse
    {
        $this->requireLegacyExplorer();
        return $this->fileManager->imageResponse($path);
    }

    private function requireLegacyExplorer(): void
    {
        if ($this->functionModelEnabled) {
            throw $this->createAccessDeniedException('Der Kursbild-Explorer ist noch nicht auf Ordnerrechte umgestellt.');
        }
    }
}
