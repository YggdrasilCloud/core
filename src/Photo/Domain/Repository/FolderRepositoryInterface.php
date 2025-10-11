<?php

declare(strict_types=1);

namespace App\Photo\Domain\Repository;

use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;

interface FolderRepositoryInterface
{
    public function save(Folder $folder): void;

    public function findById(FolderId $id): ?Folder;

    public function remove(Folder $folder): void;
}
