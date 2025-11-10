<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetFolderChildren;

use App\Photo\Application\Query\ListFolders\FolderDtoCollection;
use App\Photo\Domain\Criteria\FolderCriteria;

final readonly class GetFolderChildrenResult
{
    public function __construct(
        public FolderDtoCollection $children,
        public int $page,
        public int $perPage,
        public int $total,
        public FolderCriteria $criteria,
    ) {}
}
