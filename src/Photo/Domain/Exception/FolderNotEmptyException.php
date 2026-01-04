<?php

declare(strict_types=1);

namespace App\Photo\Domain\Exception;

use App\Photo\Domain\Model\FolderId;
use DomainException;

use function sprintf;

final class FolderNotEmptyException extends DomainException
{
    public static function withContent(FolderId $folderId, int $photoCount, int $subfolderCount): self
    {
        $parts = [];

        if ($photoCount > 0) {
            $parts[] = sprintf('%d photo(s)', $photoCount);
        }

        if ($subfolderCount > 0) {
            $parts[] = sprintf('%d subfolder(s)', $subfolderCount);
        }

        return new self(sprintf(
            'Cannot delete folder %s: contains %s. Use recursive=true to delete all content.',
            $folderId->toString(),
            implode(' and ', $parts),
        ));
    }
}
