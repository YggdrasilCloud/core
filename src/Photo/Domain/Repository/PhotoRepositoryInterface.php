<?php

declare(strict_types=1);

namespace App\Photo\Domain\Repository;

use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\UserInterface\Http\Request\PhotoQueryParams;

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
        PhotoQueryParams $queryParams,
        int $limit,
        int $offset
    ): array;

    /**
     * Count photos by folder with optional filtering.
     */
    public function countByFolderId(FolderId $folderId, PhotoQueryParams $queryParams): int;

    public function remove(Photo $photo): void;
}
