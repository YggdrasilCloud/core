<?php

declare(strict_types=1);

namespace App\Photo\Domain\Repository;

use App\Photo\Application\Criteria\PhotoCriteria;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;

interface PhotoRepositoryInterface
{
    public function save(Photo $photo): void;

    public function findById(PhotoId $id): ?Photo;

    /**
     * Find photos by folder with optional sorting and filtering.
     *
     * @return list<Photo>
     */
    public function findByFolderId(
        FolderId $folderId,
        PhotoCriteria $criteria,
        int $limit,
        int $offset
    ): array;

    /**
     * Count photos by folder with optional filtering.
     */
    public function countByFolderId(FolderId $folderId, PhotoCriteria $criteria): int;

    public function remove(Photo $photo): void;
}
