<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetFolderPath;

final readonly class PathSegmentDto
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
