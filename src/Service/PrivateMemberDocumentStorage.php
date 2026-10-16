<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PrivateMemberDocumentStorage
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir) {}

    public function path(string $storageName): string
    {
        if (!preg_match('/^[a-f0-9]{40}\.(pdf|jpg|png)$/D', $storageName)) {
            throw new \InvalidArgumentException('Ungueltiger Speichername.');
        }

        return $this->directory() . '/' . $storageName;
    }

    /** @return array{0:string,1:string,2:int} */
    public function store(UploadedFile $file): array
    {
        if (!$file->isValid() || $file->getSize() === false || $file->getSize() > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('Datei ungueltig oder groesser als 5 MB.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        $extensions = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
        if (!isset($extensions[$mime])) {
            throw new \InvalidArgumentException('Nur PDF, JPG und PNG sind erlaubt.');
        }

        $name = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
        $file->move($this->directory(), $name);
        return [$name, $mime, (int) filesize($this->path($name))];
    }

    private function directory(): string
    {
        $dir = $this->projectDir . '/var/private/member_documents';
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Private Dokumentenablage nicht verfuegbar.');
        }
        return $dir;
    }
}
