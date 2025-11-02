<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetFolderChildren;

use App\Photo\Application\Query\ListFolders\FolderDto;
use App\Photo\Domain\Criteria\FolderCriteria;

final readonly class GetFolderChildrenResult
{
    /**
     * @param list<FolderDto> $children
     */
    public function __construct(
        public array $children,
        public int $page,
        public int $perPage,
        public int $total,
        public FolderCriteria $criteria,
    ) {}
}
