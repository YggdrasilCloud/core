<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetFolderPath;

final readonly class GetFolderPathResult
{
    /**
     * @param list<PathSegmentDto> $path Path segments from root to target folder
     */
    public function __construct(
        public array $path,
    ) {}
}
