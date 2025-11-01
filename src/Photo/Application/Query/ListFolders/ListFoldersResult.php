<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\ListFolders;

use App\Photo\UserInterface\Http\Request\FolderQueryParams;

final readonly class ListFoldersResult
{
    /**
     * @param list<FolderDto> $items
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $perPage,
        public int $total,
        public FolderQueryParams $queryParams,
    ) {}
}
