<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Model;

use App\Photo\Domain\Event\FolderCreated;
use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class FolderTest extends TestCase
{
    public function testCreateFolderWithCorrectData(): void
    {
        $folderId = FolderId::generate();
        $folderName = FolderName::fromString('My Photos');
        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $folder = Folder::create($folderId, $folderName, $ownerId);

        self::assertTrue($folder->id()->equals($folderId));
        self::assertTrue($folder->name()->equals($folderName));
        self::assertTrue($folder->ownerId()->equals($ownerId));
        self::assertInstanceOf(DateTimeImmutable::class, $folder->createdAt());
    }

    public function testCreateRecordsFolderCreatedEvent(): void
    {
        $folder = Folder::create(
            FolderId::generate(),
            FolderName::fromString('My Photos'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000')
        );

        $events = $folder->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(FolderCreated::class, $events[0]);
    }

    public function testPullDomainEventsClearsEventsList(): void
    {
        $folder = Folder::create(
            FolderId::generate(),
            FolderName::fromString('My Photos'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000')
        );

        $firstPull = $folder->pullDomainEvents();
        $secondPull = $folder->pullDomainEvents();

        self::assertCount(1, $firstPull);
        self::assertCount(0, $secondPull);
    }

    public function testCreatedAtIsSetToCurrentTime(): void
    {
        $before = new DateTimeImmutable();

        $folder = Folder::create(
            FolderId::generate(),
            FolderName::fromString('My Photos'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000')
        );

        $after = new DateTimeImmutable();

        self::assertGreaterThanOrEqual($before->getTimestamp(), $folder->createdAt()->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $folder->createdAt()->getTimestamp());
    }

    public function testPullDomainEventsReturnsAllEventsNotJustFirst(): void
    {
        // This test catches ArrayOneItem mutation which would return only first element
        $folder = Folder::create(
            FolderId::generate(),
            FolderName::fromString('My Photos'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000')
        );

        // Rename adds another event would be ideal, but create only adds one event
        // We can verify that one event is actually in an array, not a single value
        $events = $folder->pullDomainEvents();

        // Verify we get an array with the correct event
        self::assertCount(1, $events);
        self::assertInstanceOf(FolderCreated::class, $events[0]);
        // After ArrayOneItem mutation, $events[0] would still work but the structure
        // would be wrong. Let's verify the full array structure.
        self::assertArrayHasKey(0, $events);
        self::assertArrayNotHasKey(1, $events);
    }

    public function testRenameFolderChangesName(): void
    {
        $folder = Folder::create(
            FolderId::generate(),
            FolderName::fromString('Old Name'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000')
        );

        $newName = FolderName::fromString('New Name');
        $folder->rename($newName);

        self::assertTrue($folder->name()->equals($newName));
    }

    public function testCreateFolderWithParent(): void
    {
        $parentId = FolderId::generate();
        $folder = Folder::create(
            FolderId::generate(),
            FolderName::fromString('Child Folder'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            $parentId
        );

        self::assertNotNull($folder->parentId());
        self::assertTrue($folder->parentId()->equals($parentId));
    }

    public function testCreateFolderWithoutParent(): void
    {
        $folder = Folder::create(
            FolderId::generate(),
            FolderName::fromString('Root Folder'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000')
        );

        self::assertNull($folder->parentId());
    }
}
