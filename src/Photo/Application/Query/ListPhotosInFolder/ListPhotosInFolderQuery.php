<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\ListPhotosInFolder;

use App\Photo\Domain\Criteria\PhotoCriteria;
use InvalidArgumentException;

final readonly class ListPhotosInFolderQuery
{
    public function __construct(
        public string $folderId,
        public int $page = 1,
        public int $perPage = 20,
        public ?PhotoCriteria $criteria = null,
    ) {
        if ($page < 1) {
            throw new InvalidArgumentException('Page must be greater than or equal to 1');
        }

        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('PerPage must be between 1 and 100');
        }
    }
}
