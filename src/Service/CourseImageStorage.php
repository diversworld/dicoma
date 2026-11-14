<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CourseImageStorage
{
    public function __construct(
        #[Autowire('%bilder_ordner%')] private readonly string $directory,
        #[Autowire('%kernel.project_dir%')] private readonly ?string $root = null,
        #[Autowire(service: Connection::class)] private readonly ?Connection $connection = null,
    ) {}

    public function directory(): string
    {
        if ($this->root === null) {
            return $this->directory;
        }
        if (!is_file($this->root.'/files/.course-images-active') && $this->connection !== null) {
            $this->verifyReferences();
        }
        $path = PrivateStorageMigration::activate($this->root, 'kurse', 'public/images/kurse', '.course-images-active', 'Kursbilder');
        if ($path !== $this->directory) {
            throw new \LogicException('Der Kursbildpfad stimmt nicht mit dem privaten Speicher überein.');
        }
        return $path;
    }

    private function verifyReferences(): void
    {
        foreach (['courses', 'schedule'] as $table) {
            $rows = $this->connection->executeQuery("SELECT image FROM $table WHERE image IS NOT NULL AND image <> ''");
            while (($name = $rows->fetchOne()) !== false) {
                if (!is_string($name) || str_starts_with($name, '/') || str_contains($name, '\\') || str_contains($name, "\0")) {
                    throw new \RuntimeException('Ungültiger gespeicherter Kursbildpfad.');
                }
                $path = $this->directory;
                foreach (explode('/', $name) as $part) {
                    if ($part === '' || $part === '.' || $part === '..') {
                        throw new \RuntimeException('Ungültiger gespeicherter Kursbildpfad.');
                    }
                    $path .= '/'.$part;
                    if (is_link($path)) {
                        throw new \RuntimeException('Symbolischer Link im Kursbildpfad.');
                    }
                }
                if (!is_file($path) || !is_readable($path)) {
                    throw new \RuntimeException('Referenzierte Kursbilder müssen vor der Umstellung vollständig übernommen werden.');
                }
            }
        }
    }

    public function store(UploadedFile $file): string
    {
        $size = $file->getSize();
        if (!$file->isValid() || $size === false || $size < 1 || $size > ImageUploadValidator::MAX_BYTES) {
            throw new \InvalidArgumentException('Bild ungültig oder größer als 10 MB.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        if (!isset(ImageUploadValidator::EXTENSIONS[$mime])) {
            throw new \InvalidArgumentException('Nur JPG, PNG und WebP sind erlaubt.');
        }
        ImageUploadValidator::validate($file->getPathname(), $mime);

        $name = bin2hex(random_bytes(20)).'.'.ImageUploadValidator::EXTENSIONS[$mime];
        $file->move($this->directory(), $name);

        return $name;
    }
}
