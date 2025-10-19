<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\CreateFolder;

use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateFolderHandler
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
    ) {}

    public function __invoke(CreateFolderCommand $command): void
    {
        $folder = Folder::create(
            FolderId::fromString($command->folderId),
            FolderName::fromString($command->folderName),
            UserId::fromString($command->ownerId),
            $command->parentId !== null ? FolderId::fromString($command->parentId) : null,
        );

        $this->folderRepository->save($folder);
    }
}
