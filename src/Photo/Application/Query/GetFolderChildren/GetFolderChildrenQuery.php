<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetFolderChildren;

final readonly class GetFolderChildrenQuery
{
    public function __construct(
        public string $parentId,
    ) {}
}
