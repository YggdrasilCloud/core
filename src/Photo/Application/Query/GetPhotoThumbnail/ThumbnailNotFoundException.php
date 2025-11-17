<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetPhotoThumbnail;

use DomainException;

final class ThumbnailNotFoundException extends DomainException
{
    public static function forPhoto(string $photoId): self
    {
        return new self("Thumbnail not found for photo: {$photoId}");
    }
}
