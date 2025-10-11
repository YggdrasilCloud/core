<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetPhotoThumbnail;

final class ThumbnailFileNotFoundException extends \RuntimeException
{
    public function __construct(string $filePath)
    {
        parent::__construct(sprintf('Thumbnail file not found on disk: "%s"', $filePath));
    }
}
