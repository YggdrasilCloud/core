<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\CreateFolder;

use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateFolderHandler
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
    ) {}

    public function __invoke(CreateFolderCommand $command): void
    {
        $parentId = $command->parentId !== null ? FolderId::fromString($command->parentId) : null;

        // Validate parent exists (if provided)
        if ($parentId !== null) {
            $parent = $this->folderRepository->findById($parentId);

            if ($parent === null) {
                throw new InvalidArgumentException("Parent folder not found: {$command->parentId}");
            }
        }

        $folder = Folder::create(
            FolderId::fromString($command->folderId),
            FolderName::fromString($command->folderName),
            UserId::fromString($command->ownerId),
            $parentId,
        );

        $this->folderRepository->save($folder);
    }
}
