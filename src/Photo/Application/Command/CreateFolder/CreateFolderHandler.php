<?php

declare(strict_types=1);

namespace App\Photo\Application\Command\CreateFolder;

use App\File\Domain\Service\PhysicalFolderStorage;
use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Service\FileSystemPathBuilder;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateFolderHandler
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
        private PhysicalFolderStorage $folderStorage,
        private FileSystemPathBuilder $pathBuilder,
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

        // Save folder entity to database first
        $this->folderRepository->save($folder);

        // Create physical directory on filesystem
        $folderPath = $this->pathBuilder->buildFolderPath($folder);
        $this->folderStorage->createDirectory($folderPath);
    }
}
