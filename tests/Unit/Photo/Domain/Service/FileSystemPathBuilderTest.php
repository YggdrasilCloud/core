<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Service;

use App\File\Domain\Service\FileNameSanitizer;
use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Service\FileSystemPathBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FileSystemPathBuilderTest extends TestCase
{
    private FolderRepositoryInterface $folderRepository;
    private FileNameSanitizer $sanitizer;
    private FileSystemPathBuilder $pathBuilder;

    protected function setUp(): void
    {
        $this->folderRepository = $this->createMock(FolderRepositoryInterface::class);
        $this->sanitizer = new FileNameSanitizer();
        $this->pathBuilder = new FileSystemPathBuilder($this->folderRepository, $this->sanitizer);
    }

    public function testBuildFolderPathForRootFolder(): void
    {
        $folder = Folder::create(
            FolderId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'),
            FolderName::fromString('Vacances'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            null, // No parent
        );

        $path = $this->pathBuilder->buildFolderPath($folder);

        self::assertSame('photos/Vacances', $path);
    }

    public function testBuildFolderPathWithOneParent(): void
    {
        $parentId = FolderId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $parent = Folder::create(
            $parentId,
            FolderName::fromString('Vacances'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            null,
        );

        $child = Folder::create(
            FolderId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'),
            FolderName::fromString('Été 2024'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            $parentId,
        );

        $this->folderRepository
            ->expects(self::once())
            ->method('findById')
            ->with($parentId)
            ->willReturn($parent)
        ;

        $path = $this->pathBuilder->buildFolderPath($child);

        self::assertSame('photos/Vacances/Été 2024', $path);
    }

    public function testBuildFolderPathWithMultipleLevels(): void
    {
        $grandParentId = FolderId::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
        $parentId = FolderId::fromString('6ba7b811-9dad-11d1-80b4-00c04fd430c8');

        $grandParent = Folder::create(
            $grandParentId,
            FolderName::fromString('Archives'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            null,
        );

        $parent = Folder::create(
            $parentId,
            FolderName::fromString('2024'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            $grandParentId,
        );

        $child = Folder::create(
            FolderId::fromString('6ba7b812-9dad-11d1-80b4-00c04fd430c8'),
            FolderName::fromString('Photos'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            $parentId,
        );

        $this->folderRepository
            ->expects(self::exactly(2))
            ->method('findById')
            ->willReturnCallback(static function ($id) use ($parentId, $parent, $grandParentId, $grandParent) {
                if ($id->equals($parentId)) {
                    return $parent;
                }
                if ($id->equals($grandParentId)) {
                    return $grandParent;
                }

                return null;
            })
        ;

        $path = $this->pathBuilder->buildFolderPath($child);

        self::assertSame('photos/Archives/2024/Photos', $path);
    }

    public function testBuildFolderPathSanitizesFolderNames(): void
    {
        $folder = Folder::create(
            FolderId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'),
            FolderName::fromString('Folder<>Name'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            null,
        );

        $path = $this->pathBuilder->buildFolderPath($folder);

        // FolderName already sanitizes and replaces multiple underscores with one
        self::assertSame('photos/Folder_Name', $path);
    }

    public function testBuildFolderPathThrowsExceptionWhenParentNotFound(): void
    {
        $parentId = FolderId::fromString('6ba7b813-9dad-11d1-80b4-00c04fd430c8');
        $child = Folder::create(
            FolderId::fromString('6ba7b814-9dad-11d1-80b4-00c04fd430c8'),
            FolderName::fromString('Orphan'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            $parentId,
        );

        $this->folderRepository
            ->expects(self::once())
            ->method('findById')
            ->with($parentId)
            ->willReturn(null) // Parent not found
        ;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Parent folder not found: 6ba7b813-9dad-11d1-80b4-00c04fd430c8');

        $this->pathBuilder->buildFolderPath($child);
    }

    public function testBuildFolderPathThrowsExceptionOnMaxDepthExceeded(): void
    {
        // Create a mock that simulates deep nesting
        $folderId = FolderId::fromString('6ba7b815-9dad-11d1-80b4-00c04fd430c8');
        $parentId = FolderId::fromString('6ba7b816-9dad-11d1-80b4-00c04fd430c8');

        $folder = Folder::create(
            $folderId,
            FolderName::fromString('Deep'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            $parentId,
        );

        // Mock repository to always return a folder with a parent
        // This simulates infinite recursion
        $this->folderRepository
            ->method('findById')
            ->willReturn($folder)
        ;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Maximum folder depth exceeded - possible circular reference');

        $this->pathBuilder->buildFolderPath($folder);
    }

    public function testBuildFilePathForRootFolder(): void
    {
        $folder = Folder::create(
            FolderId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'),
            FolderName::fromString('Vacances'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            null,
        );

        $path = $this->pathBuilder->buildFilePath($folder, 'plage.jpg');

        self::assertSame('photos/Vacances/plage.jpg', $path);
    }

    public function testBuildFilePathWithParentFolder(): void
    {
        $parentId = FolderId::fromString('6ba7b817-9dad-11d1-80b4-00c04fd430c8');
        $parent = Folder::create(
            $parentId,
            FolderName::fromString('Vacances'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            null,
        );

        $child = Folder::create(
            FolderId::fromString('6ba7b818-9dad-11d1-80b4-00c04fd430c8'),
            FolderName::fromString('Été 2024'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            $parentId,
        );

        $this->folderRepository
            ->expects(self::once())
            ->method('findById')
            ->with($parentId)
            ->willReturn($parent)
        ;

        $path = $this->pathBuilder->buildFilePath($child, 'beach.jpg');

        self::assertSame('photos/Vacances/Été 2024/beach.jpg', $path);
    }

    public function testBuildFilePathSanitizesFileName(): void
    {
        $folder = Folder::create(
            FolderId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'),
            FolderName::fromString('Photos'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            null,
        );

        $path = $this->pathBuilder->buildFilePath($folder, 'photo<>file.jpg');

        self::assertSame('photos/Photos/photo__file.jpg', $path);
    }

    public function testBuildFilePathPreservesUnicodeCharacters(): void
    {
        $folder = Folder::create(
            FolderId::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'),
            FolderName::fromString('Été'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            null,
        );

        $path = $this->pathBuilder->buildFilePath($folder, 'plage-🏖️.jpg');

        self::assertSame('photos/Été/plage-🏖️.jpg', $path);
    }
}
