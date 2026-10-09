<?php

namespace App\Form;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Reconnaît un logo sans l'extension PHP fileinfo, absente sur certains postes.
 */
final class LogoImage
{
    public const MESSAGE = 'Importez une image PNG, JPEG, WEBP ou GIF.';

    public static function extension(?UploadedFile $file): ?string
    {
        if (!$file instanceof UploadedFile) {
            return null;
        }

        $path = $file->getPathname();
        $info = @getimagesize($path);

        return match (\is_array($info) ? ($info[2] ?? null) : null) {
            \IMAGETYPE_PNG => 'png',
            \IMAGETYPE_JPEG => 'jpg',
            \IMAGETYPE_GIF => 'gif',
            \IMAGETYPE_WEBP => 'webp',
            default => self::webpFromHeader($path),
        };
    }

    private static function webpFromHeader(string $path): ?string
    {
        $header = file_get_contents($path, false, null, 0, 12);

        return \is_string($header) && str_starts_with($header, 'RIFF') && 'WEBP' === substr($header, 8, 4)
            ? 'webp'
            : null;
    }
}
