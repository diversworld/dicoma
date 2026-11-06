<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\Admin\CoursesCrudController;
use App\Controller\Admin\ScheduleCrudController;
use App\Service\ImageUploadValidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Constraints\Image;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CourseImageControllerFieldsTest extends KernelTestCase
{
    public function testCourseAndScheduleFormsUseRestrictedImageFields(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for isolated controller tests.');
        }

        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null';
        putenv('DATABASE_URL=sqlite:///:memory:');
        putenv('MAILER_DSN=null://null');
        self::bootKernel(['environment' => 'test']);
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $em->getConnection()->getParams()['driver']);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        foreach ([CoursesCrudController::class, ScheduleCrudController::class] as $class) {
            $controller = $container->get($class);
            foreach ([Crud::PAGE_NEW, Crud::PAGE_EDIT] as $page) {
                $imageFields = [];
                foreach ($controller->configureFields($page) as $field) {
                    if ($field->getAsDto()->getProperty() === 'image') {
                        $imageFields[] = $field;
                    }
                }

                self::assertCount(1, $imageFields, "$class: $page");
                self::assertInstanceOf(ImageField::class, $imageFields[0]);
                $dto = $imageFields[0]->getAsDto();
                $constraint = $dto->getCustomOption(ImageField::OPTION_FILE_CONSTRAINTS);
                self::assertInstanceOf(Image::class, $constraint);
                self::assertSame(array_keys(ImageUploadValidator::EXTENSIONS), $constraint->mimeTypes);
                self::assertSame(ImageUploadValidator::MAX_BYTES, $constraint->maxSize);
                self::assertSame(ImageUploadValidator::MAX_PIXELS, $constraint->maxPixels);
                self::assertSame('[randomhash].[extension]', $dto->getCustomOption(ImageField::OPTION_UPLOADED_FILE_NAME_PATTERN));
            }
        }
    }
}
