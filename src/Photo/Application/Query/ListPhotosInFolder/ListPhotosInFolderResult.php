<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\ListPhotosInFolder;

use App\Photo\Domain\Criteria\PhotoCriteria;

final readonly class ListPhotosInFolderResult
{
    /**
     * @param list<PhotoDto> $photos
     */
    public function __construct(
        public array $photos,
        public int $page,
        public int $perPage,
        public int $total,
        public PhotoCriteria $criteria,
    ) {}
}
