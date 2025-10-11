<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\ListFolders;

final readonly class ListFoldersResult
{
    /**
     * @param list<FolderDto> $items
     */
    public function __construct(
        public array $items,
    ) {}
}
