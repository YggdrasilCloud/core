<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetFolderChildren;

use App\Photo\Application\Criteria\FolderCriteria;
use InvalidArgumentException;

final readonly class GetFolderChildrenQuery
{
    public function __construct(
        public string $parentId,
        public int $page = 1,
        public int $perPage = 50,
        public ?FolderCriteria $criteria = null,
    ) {
        if ($page < 1) {
            throw new InvalidArgumentException('Page must be greater than or equal to 1');
        }

        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('PerPage must be between 1 and 100');
        }
    }
}
