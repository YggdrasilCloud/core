<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetFolderPath;

final readonly class GetFolderPathQuery
{
    public function __construct(
        public string $folderId,
    ) {}
}
