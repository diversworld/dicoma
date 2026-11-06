<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CourseImageStorage
{
    public function __construct(#[Autowire('%bilder_ordner%')] private readonly string $directory) {}

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
        $file->move($this->directory, $name);

        return $name;
    }
}
