<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{Club, MediaFile};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class MediaFileStorage
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $root) {}

    public function store(Club $club, UploadedFile $file, string $altText): MediaFile
    {
        if (!$file->isValid() || $file->getSize() === false || $file->getSize() < 1 || $file->getSize() > 10 * 1024 * 1024) {
            throw new \InvalidArgumentException('Datei ungültig oder größer als 10 MB.');
        }
        $altText = trim($altText);
        if (mb_strlen($altText) > 255) { throw new \InvalidArgumentException('Der Alternativtext darf höchstens 255 Zeichen lang sein.'); }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
        if (isset($types[$mime])) {
            $extension = $types[$mime];
            if (str_starts_with($mime, 'image/')) {
                $info = getimagesize($file->getPathname());
                if ($info === false || ($info['mime'] ?? null) !== $mime || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 40000000) {
                    throw new \InvalidArgumentException('Das Bild ist ungültig oder überschreitet 40 Megapixel.');
                }
            }
        } else {
            $extension = strtolower($file->getClientOriginalExtension());
            $office = [
                'docx' => ['word/document.xml', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                'xlsx' => ['xl/workbook.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                'pptx' => ['ppt/presentation.xml', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            ];
            if (!isset($office[$extension]) || !in_array($mime, ['application/zip', $office[$extension][1]], true)) {
                throw new \InvalidArgumentException('Erlaubt sind JPG, PNG, WebP, PDF, DOCX, XLSX und PPTX.');
            }
            if (!class_exists(\ZipArchive::class)) { throw new \RuntimeException('Die PHP-Erweiterung zip wird für Office-Dateien benötigt.'); }
            $zip = new \ZipArchive();
            if ($zip->open($file->getPathname()) !== true) { throw new \InvalidArgumentException('Ungültiges Office-Dokument.'); }
            try {
                if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName($office[$extension][0]) === false) {
                    throw new \InvalidArgumentException('Ungültiges Office-Dokument.');
                }
            } finally { $zip->close(); }
            $mime = $office[$extension][1];
        }
        $name = bin2hex(random_bytes(20)).'.'.$extension;
        $original = mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255);
        $original = trim(preg_replace('/[\x00-\x1f\x7f]/u', '', $original) ?? '');
        if ($original === '' || $original === '.' || $original === '..') { throw new \InvalidArgumentException('Ungültiger Dateiname.'); }
        $size = (int) $file->getSize();
        $file->move($this->directory(), $name);
        return new MediaFile($club, $name, $original, $mime, $size, $altText);
    }

    public function path(MediaFile $file): string
    {
        $name = $file->getStorageName();
        if (!preg_match('/^[a-f0-9]{40}\.(jpg|png|webp|pdf|docx|xlsx|pptx)$/D', $name)) {
            throw new \LogicException('Ungültiger Dateiname in der Mediathek.');
        }
        return $this->directory().'/'.$name;
    }

    public function delete(MediaFile $file): void
    {
        (new Filesystem())->remove($this->path($file));
    }

    public function response(MediaFile $file, string $disposition): BinaryFileResponse
    {
        $response = new BinaryFileResponse($this->path($file));
        $response->headers->set('Content-Type', $file->getMimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition($disposition, $file->getOriginalName(), $file->getStorageName());
        $response->setPrivate()->headers->addCacheControlDirective('no-store');
        return $response;
    }

    private function directory(): string
    {
        $path = $this->root.'/var/private/website_media';
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new \RuntimeException('Private Dateiablage nicht verfügbar.');
        }
        return $path;
    }
}
