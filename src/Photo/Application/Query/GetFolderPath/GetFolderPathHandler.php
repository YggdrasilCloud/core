<?php

declare(strict_types=1);

namespace App\Photo\Application\Query\GetFolderPath;

use App\Photo\Domain\Exception\FolderNotFoundException;
use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetFolderPathHandler
{
    public function __construct(
        private FolderRepositoryInterface $folderRepository,
    ) {}

    public function __invoke(GetFolderPathQuery $query): GetFolderPathResult
    {
        $folderId = FolderId::fromString($query->folderId);
        $folder = $this->folderRepository->findById($folderId);

        if ($folder === null) {
            throw FolderNotFoundException::withId($folderId);
        }

        $path = $this->buildPath($folder);

        return new GetFolderPathResult($path);
    }

    /**
     * Build path from root to target folder.
     *
     * @return list<PathSegmentDto>
     */
    private function buildPath(Folder $folder): array
    {
        $segments = [];
        $current = $folder;
        $visited = [];

        // Traverse from target to root, collecting segments
        while (true) {
            $currentId = $current->id()->toString();

            // Detect cycles
            if (isset($visited[$currentId])) {
                throw new InvalidArgumentException("Cycle detected in folder hierarchy at: {$currentId}");
            }

            $visited[$currentId] = true;

            // Add current folder to path
            $segments[] = new PathSegmentDto(
                id: $current->id()->toString(),
                name: $current->name()->toString(),
            );

            // Stop if we reached the root
            if ($current->parentId() === null) {
                break;
            }

            // Move to parent
            $parent = $this->folderRepository->findById($current->parentId());

            if ($parent === null) {
                throw FolderNotFoundException::withId($current->parentId());
            }

            $current = $parent;
        }

        // Reverse to get root-to-target order
        return array_reverse($segments);
    }
}
