<?php

declare(strict_types=1);

namespace App\Photo\Domain\Repository;

use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;

interface PhotoRepositoryInterface
{
    public function save(Photo $photo): void;

    public function findById(PhotoId $id): ?Photo;

    /**
     * @return list<Photo>
     */
    public function findByFolderId(FolderId $folderId, int $limit, int $offset): array;

    public function countByFolderId(FolderId $folderId): int;

    public function remove(Photo $photo): void;
}
