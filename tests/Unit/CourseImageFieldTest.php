<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Field\CourseImageField;
use App\Service\ImageUploadValidator;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Validation;

final class CourseImageFieldTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l7sAAAAASUVORK5CYII=';
    private const JPEG = '/9j/4AAQSkZJRgABAQEAYABgAAD//gA+Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2NjIpLCBkZWZhdWx0IHF1YWxpdHkK/9sAQwAIBgYHBgUIBwcHCQkICgwUDQwLCwwZEhMPFB0aHx4dGhwcICQuJyAiLCMcHCg3KSwwMTQ0NB8nOT04MjwuMzQy/9sAQwEJCQkMCwwYDQ0YMiEcITIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIy/8AAEQgAAQABAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A+f6KKKAP/9k=';
    private const WEBP = 'UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoBAAEAAUAmJaQAA3AA/v02aAA=';

    public function testFieldUsesRestrictedImagePolicyAndRandomNames(): void
    {
        $field = CourseImageField::create()->getAsDto();
        $constraint = $field->getCustomOption(ImageField::OPTION_FILE_CONSTRAINTS);
        self::assertInstanceOf(Image::class, $constraint);
        self::assertSame(ImageUploadValidator::MAX_BYTES, $constraint->maxSize);
        self::assertSame(ImageUploadValidator::MAX_PIXELS, $constraint->maxPixels);
        self::assertSame(array_keys(ImageUploadValidator::EXTENSIONS), $constraint->mimeTypes);
        self::assertSame('[randomhash].[extension]', $field->getCustomOption(ImageField::OPTION_UPLOADED_FILE_NAME_PATTERN));
    }

    public function testImageContentsAndExactLimits(): void
    {
        $constraint = CourseImageField::create()->getAsDto()->getCustomOption(ImageField::OPTION_FILE_CONSTRAINTS);
        $validator = Validation::createValidator();
        $png = base64_decode(self::PNG, true);
        self::assertIsString($png);
        $cases = [
            'png' => [$png, null, true],
            'jpeg' => [base64_decode(self::JPEG, true), null, true],
            'webp' => [base64_decode(self::WEBP, true), null, true],
            'svg' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', null, false],
            'html' => ['<!doctype html><script>alert(1)</script>', null, false],
            'broken-png' => ["\x89PNG\r\n\x1a\n", null, false],
            'pixel-limit' => [substr_replace($png, pack('N2', 40000, 1000), 16, 8), null, true],
            'above-pixel-limit' => [substr_replace($png, pack('N2', 40001, 1000), 16, 8), null, false],
            'size-limit' => [$png, ImageUploadValidator::MAX_BYTES, true],
            'above-size-limit' => [$png, ImageUploadValidator::MAX_BYTES + 1, false],
        ];
        foreach ($cases as $name => [$content, $size, $allowed]) {
            self::assertIsString($content);
            $path = tempnam(sys_get_temp_dir(), 'dicoma-image-policy-');
            self::assertIsString($path);
            try {
                self::assertSame(strlen($content), file_put_contents($path, $content));
                if ($size !== null) {
                    $file = fopen($path, 'r+');
                    self::assertIsResource($file);
                    self::assertTrue(ftruncate($file, $size));
                    fclose($file);
                }
                $upload = new UploadedFile($path, 'untrusted-name.png', null, null, true);
                self::assertSame($allowed, count($validator->validate($upload, $constraint)) === 0, $name);
            } finally {
                unlink($path);
            }

        }
    }

    public function testReplacingAnImageDoesNotDeleteSharedFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dicoma-shared-image-');
        self::assertIsString($path);
        file_put_contents($path, 'shared image');
        try {
            $delete = CourseImageField::create()->getAsDto()->getFormTypeOption('upload_delete');
            $delete(new File($path));
            self::assertSame('shared image', file_get_contents($path));
        } finally {
            unlink($path);
        }

    }

    public function testPreviewEncodesNestedFileNames(): void
    {
        $format = CourseImageField::create()->getAsDto()->getFormatValueCallable();
        self::assertSame('/admin/files/preview/nested/a%23%20b.jpg', $format('nested/a# b.jpg'));
    }
}
