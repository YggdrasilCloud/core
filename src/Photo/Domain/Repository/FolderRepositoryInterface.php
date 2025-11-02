<?php

declare(strict_types=1);

namespace App\Photo\Domain\Repository;

use App\Photo\Domain\Criteria\FolderCriteria;
use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;

interface FolderRepositoryInterface
{
    public function save(Folder $folder): void;

    public function findById(FolderId $id): ?Folder;

    public function remove(Folder $folder): void;

    /**
     * Find all folders with optional sorting and filtering.
     *
     * @return list<Folder>
     */
    public function findAll(FolderCriteria $criteria, int $limit, int $offset): array;

    /**
     * Count all folders with optional filtering.
     */
    public function count(FolderCriteria $criteria): int;

    /**
     * Find folders by parent ID with optional sorting and filtering.
     *
     * @return list<Folder>
     */
    public function findByParentId(FolderId $parentId, FolderCriteria $criteria, int $limit, int $offset): array;

    /**
     * Count folders by parent ID with optional filtering.
     */
    public function countByParentId(FolderId $parentId, FolderCriteria $criteria): int;
}
