<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\ListFolders;

use App\Photo\Domain\Criteria\FolderCriteria;

final readonly class ListFoldersResult
{
    public function __construct(
        public FolderDtoCollection $items,
        public int $page,
        public int $perPage,
        public int $total,
        public FolderCriteria $criteria,
    ) {}
}
