<?php

declare(strict_types=1);

namespace App\Field;

use App\Service\ImageUploadValidator;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use Symfony\Component\HttpFoundation\File\File;

final class CourseImageField
{
    public static function create(): ImageField
    {
        return ImageField::new('image', 'Bild')
            ->setBasePath('/admin/files/preview/')
            ->setUploadDir('files/kurse/')
            ->setUploadedFileNamePattern('[randomhash].[extension]')
            ->setFileConstraints(ImageUploadValidator::constraint())
            ->setFormTypeOption('download_path', '/admin/files/preview/')
            ->formatValue(static fn (?string $value): ?string => $value === null ? null
                : '/admin/files/preview/'.implode('/', array_map(rawurlencode(...), explode('/', $value))))
            // Removing an association must not delete an image shared by other courses.
            ->setFormTypeOption('upload_delete', static function (File $file): void {})
            ->setRequired(false)
            ->setColumns(5)
            ->hideOnIndex();
    }
}
