<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class FileManager
{
    private string $rootPath;

    public function __construct(
        #[Autowire('%bilder_ordner%')]
        string $rootPath,
        ?CourseImageStorage $storage = null,
    ) {
        if ($storage !== null && $storage->directory() !== $rootPath) {
            throw new \LogicException('Der Explorer verwendet nicht den Kursbildspeicher.');
        }
        $root = realpath($rootPath);

        if ($root === false || !is_dir($root)) {
            throw new \RuntimeException('Bilderordner nicht gefunden.');
        }

        $this->rootPath = $root;
    }

    public function getDirectory(string $relativePath = ''): string
    {
        $fullPath = $this->resolve($relativePath);
        if (!is_dir($fullPath)) {
            throw new NotFoundHttpException('Ordner nicht gefunden.');
        }

        return $fullPath;
    }

    public function getFile(string $relativePath): string
    {
        $fullPath = $this->resolve($relativePath);
        if (!is_file($fullPath) || !is_readable($fullPath)) {
            throw new NotFoundHttpException('Datei nicht gefunden oder nicht lesbar.');
        }

        return $fullPath;
    }

    public function imageResponse(string $relativePath): BinaryFileResponse
    {
        $path = $this->getFile($relativePath);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!isset(ImageUploadValidator::EXTENSIONS[$mime])) {
            throw new NotFoundHttpException('Bild nicht gefunden.');
        }
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
        $response->setPrivate()->headers->addCacheControlDirective('no-store');
        return $response;
    }

    public function normalizePath(string $relativePath): string
    {
        if (str_contains($relativePath, "\0")) {
            throw new BadRequestHttpException('Ungültiger Pfad.');
        }
        if (DIRECTORY_SEPARATOR === '\\') {
            $relativePath = str_replace('\\', '/', $relativePath);
        }
        $parts = array_values(array_filter(explode('/', $relativePath), static fn (string $part): bool => $part !== ''));
        foreach ($parts as $part) {
            if ($part === '.' || $part === '..') {
                throw new BadRequestHttpException('Ungültiger Pfad.');
            }
        }
        return implode('/', $parts);
    }

    private function resolve(string $relativePath): string
    {
        $relativePath = $this->normalizePath($relativePath);
        $path = $this->rootPath;
        foreach (array_filter(explode('/', $relativePath), static fn (string $part): bool => $part !== '') as $part) {
            $path .= DIRECTORY_SEPARATOR.$part;
            if (is_link($path)) {
                throw new BadRequestHttpException('Symbolische Links sind nicht erlaubt.');
            }
        }
        $fullPath = realpath($path);
        if ($fullPath === false) {
            throw new NotFoundHttpException('Pfad nicht gefunden.');
        }

        if (
            $fullPath !== $this->rootPath &&
            !str_starts_with($fullPath, $this->rootPath . DIRECTORY_SEPARATOR)
        ) {
            throw new BadRequestHttpException('Zugriff verweigert.');
        }

        return $fullPath;
    }

    /** @return list<array{name: string, path: string, isDirectory: bool, size: int|null, modified: int}> */
    public function listDirectory(string $relativePath = ''): array
    {
        $relativePath = $this->normalizePath($relativePath);
        $directory = $this->getDirectory($relativePath);
        $entries = scandir($directory);

        if ($entries === false) {
            throw new \RuntimeException('Ordner nicht lesbar.');
        }

        $items = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $directory . '/' . $entry;

            // Symbolische Links nicht anzeigen
            if (is_link($fullPath)) {
                continue;
            }

            $isDirectory = is_dir($fullPath);

            if (!$isDirectory && !is_file($fullPath)) {
                continue;
            }
            $size = $isDirectory ? null : filesize($fullPath);
            $modified = filemtime($fullPath);
            if ($size === false || $modified === false) {
                throw new \RuntimeException('Dateiinformationen konnten nicht gelesen werden.');
            }

            $items[] = [
                'name' => $entry,
                'path' => ltrim(
                    $relativePath . '/' . $entry,
                    '/'
                ),
                'isDirectory' => $isDirectory,
                'size' => $size,
                'modified' => $modified,
            ];
        }

        usort($items, static function ($a, $b) {
            if ($a['isDirectory'] !== $b['isDirectory']) {
                return $a['isDirectory'] ? -1 : 1;
            }

            return strnatcasecmp($a['name'], $b['name']);
        });

        return $items;
    }
}
