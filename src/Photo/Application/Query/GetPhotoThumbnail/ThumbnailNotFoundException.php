<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetPhotoThumbnail;

use RuntimeException;

use function sprintf;

final class ThumbnailNotFoundException extends RuntimeException
{
    public function __construct(string $photoId)
    {
        parent::__construct(sprintf('Thumbnail not available for photo "%s"', $photoId));
    }
}
