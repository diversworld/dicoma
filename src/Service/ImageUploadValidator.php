<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Validator\Constraints\Image;

final class ImageUploadValidator
{
    public const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_PIXELS = 40000000;

    public static function constraint(): Image
    {
        return new Image(
            maxSize: self::MAX_BYTES,
            mimeTypes: array_keys(self::EXTENSIONS),
            maxPixels: self::MAX_PIXELS,
        );
    }

    public static function validate(string $path, string $mime): void
    {
        $image = getimagesize($path);
        if ($image === false || ($image['mime'] ?? null) !== $mime
            || $image[0] < 1 || $image[1] < 1 || $image[0] * $image[1] > self::MAX_PIXELS) {
            throw new \InvalidArgumentException('Das Bild ist ungültig oder überschreitet 40 Megapixel.');
        }
    }
}
