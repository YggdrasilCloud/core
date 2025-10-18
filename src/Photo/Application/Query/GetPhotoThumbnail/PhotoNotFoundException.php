<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetPhotoThumbnail;

use RuntimeException;

use function sprintf;

final class PhotoNotFoundException extends RuntimeException
{
    public function __construct(string $photoId)
    {
        parent::__construct(sprintf('Photo with ID "%s" not found', $photoId));
    }
}
