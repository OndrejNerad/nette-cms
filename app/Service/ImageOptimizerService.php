<?php declare(strict_types=1);

namespace App\Service;

use Nette\NotSupportedException;
use Nette\Utils\Image;
use Nette\Utils\ImageException;

class ImageOptimizerService
{
    /**
     * Resizes an image to fit within $maxDimension x $maxDimension (never upscaling)
     * and re-encodes it as JPEG at the given quality. Returns null if the bytes can't
     * be decoded as an image (unsupported/corrupt format) or the GD extension isn't
     * available, so callers can decide how to handle the original, un-optimized bytes.
     */
    public function optimize(string $binaryData, int $maxDimension, int $quality): ?string
    {
        try {
            $image = Image::fromString($binaryData);
        } catch (ImageException | NotSupportedException) {
            return null;
        }

        $image->resize($maxDimension, $maxDimension, Image::ShrinkOnly);

        return $image->toString(Image::JPEG, $quality);
    }
}
