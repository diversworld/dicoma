<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ClubDocumentStorage
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'text/plain',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.oasis.opendocument.text',
    ];

    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir) {}

    public function store(UploadedFile $file): array
    {
        if (!$file->isValid() || $file->getSize() === false || $file->getSize() > 20_000_000) {
            throw new \DomainException('Dokument ist ungültig oder größer als 20 MB.');
        }
        $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new \DomainException('Dieser Dateityp ist nicht erlaubt.');
        }
        $storageName = bin2hex(random_bytes(16)).'.'.($file->guessExtension() ?? 'bin');
        $directory = $this->directory();
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Dokumentablage konnte nicht erstellt werden.');
        }
        $file->move($directory, $storageName);
        @chmod($directory.'/'.$storageName, 0600);
        return [
            'storageName' => $storageName,
            'originalName' => mb_substr($originalName, 0, 255),
            'mimeType' => $mime,
            'size' => (int) filesize($directory.'/'.$storageName),
        ];
    }

    public function path(string $storageName): string
    {
        if (!preg_match('/^[a-f0-9]{32}\.[a-z0-9]+$/D', $storageName)) {
            throw new \DomainException('Ungültiger Dokumentpfad.');
        }
        return $this->directory().'/'.$storageName;
    }

    private function directory(): string { return $this->projectDir.'/var/club-documents'; }
}
