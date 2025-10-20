<?php

declare(strict_types=1);

namespace App\Photo\Domain\Exception;

use App\Photo\Domain\Model\FolderId;
use DomainException;

final class FolderNotFoundException extends DomainException
{
    public static function withId(FolderId $folderId): self
    {
        return new self(sprintf('Folder not found: %s', $folderId->toString()));
    }
}
