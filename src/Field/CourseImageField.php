<?php

declare(strict_types=1);

namespace App\Field;

use App\Service\ImageUploadValidator;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;

final class CourseImageField
{
    public static function create(): ImageField
    {
        return ImageField::new('image', 'Bild')
            ->setBasePath('/images/kurse/')
            ->setUploadDir('public/images/kurse/')
            ->setUploadedFileNamePattern('[randomhash].[extension]')
            ->setFileConstraints(ImageUploadValidator::constraint())
            ->setRequired(false)
            ->setColumns(5)
            ->hideOnIndex();
    }
}
