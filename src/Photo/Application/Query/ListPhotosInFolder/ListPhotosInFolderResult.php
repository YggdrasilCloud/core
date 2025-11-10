<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\ListPhotosInFolder;

use App\Photo\Domain\Criteria\PhotoCriteria;

final readonly class ListPhotosInFolderResult
{
    public function __construct(
        public PhotoDtoCollection $photos,
        public int $page,
        public int $perPage,
        public int $total,
        public PhotoCriteria $criteria,
    ) {}
}
