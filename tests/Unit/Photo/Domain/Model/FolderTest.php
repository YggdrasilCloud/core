<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Model;

use App\Photo\Domain\Event\FolderCreated;
use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\UserId;
use PHPUnit\Framework\TestCase;

final class FolderTest extends TestCase
{
    public function testCreateFolderWithCorrectData(): void
    {
        $folderId = FolderId::generate();
        $folderName = FolderName::fromString('My Photos');
        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        $folder = Folder::create($folderId, $folderName, $ownerId);

        $this->assertTrue($folder->id()->equals($folderId));
        $this->assertTrue($folder->name()->equals($folderName));
        $this->assertTrue($folder->ownerId()->equals($ownerId));
        $this->assertInstanceOf(\DateTimeImmutable::class, $folder->createdAt());
    }

    public function testCreateRecordsFolderCreatedEvent(): void
    {
        $folder = Folder::create(
            FolderId::generate(),
            FolderName::fromString('My Photos'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000')
        );

        $events = $folder->pullDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(FolderCreated::class, $events[0]);
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

        $this->assertCount(1, $firstPull);
        $this->assertCount(0, $secondPull);
    }

    public function testCreatedAtIsSetToCurrentTime(): void
    {
        $before = new \DateTimeImmutable();

        $folder = Folder::create(
            FolderId::generate(),
            FolderName::fromString('My Photos'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000')
        );

        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $folder->createdAt()->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $folder->createdAt()->getTimestamp());
    }
}
