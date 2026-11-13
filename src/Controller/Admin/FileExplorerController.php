<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\FileManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class FileExplorerController extends AbstractController
{
    public function __construct(
        private readonly FileManager $fileManager
    ) {
    }

    #[Route('/admin/files', name: 'admin_files_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $path = $this->fileManager->normalizePath($request->query->getString('path'));
        $files = $this->fileManager->listDirectory($path);

        $parts = $path === ''
            ? []
            : explode('/', $path);

        $breadcrumbs = [
            ['name' => 'Kursbilder', 'path' => '']
        ];

        $currentPath = '';

        foreach ($parts as $part) {
            $currentPath = ltrim($currentPath . '/' . $part, '/');

            $breadcrumbs[] = [
                'name' => $part,
                'path' => $currentPath
            ];
        }

        return $this->render('admin/files/explorer.html.twig', [
            'files' => $files,
            'currentPath' => $path,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    #[Route('/admin/files/download', name: 'admin_files_download', methods: ['GET'])]
    public function download(Request $request): BinaryFileResponse
    {
        $file = $this->fileManager->getFile($request->query->getString('path'));
        $response = new BinaryFileResponse($file);
        $filename = preg_replace('/[\x00-\x1f\x7f]/u', '_', str_replace('\\', '_', basename($file))) ?: 'download';
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPrivate()->headers->addCacheControlDirective('no-store');
        return $response;
    }
}
