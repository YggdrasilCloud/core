<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Model;

use App\Photo\Domain\Event\PhotoMoved;
use App\Photo\Domain\Event\PhotoUploaded;
use App\Photo\Domain\Model\FileName;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Model\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PhotoTest extends TestCase
{
    public function testUploadCreatesPhotoWithCorrectData(): void
    {
        $photoId = PhotoId::generate();
        $folderId = FolderId::fromString('0199d0b2-31cf-72ef-b43c-7d5563a01cdf');
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $fileName = FileName::fromString('photo.jpg');
        $storageKey = 'photos/folder-id/photo-id';
        $storageAdapter = 'local';
        $mimeType = 'image/jpeg';
        $sizeInBytes = 1024;

        $photo = Photo::upload($photoId, $folderId, $userId, $fileName, $storageKey, $storageAdapter, $mimeType, $sizeInBytes);

        self::assertTrue($photo->id()->equals($photoId));
        self::assertTrue($photo->folderId()->equals($folderId));
        self::assertTrue($photo->ownerId()->equals($userId));
        self::assertTrue($photo->fileName()->equals($fileName));
        self::assertSame($storageKey, $photo->storageKey());
        self::assertSame($storageAdapter, $photo->storageAdapter());
        self::assertSame($mimeType, $photo->mimeType());
        self::assertSame($sizeInBytes, $photo->sizeInBytes());
        self::assertNull($photo->thumbnailKey());
        self::assertInstanceOf(DateTimeImmutable::class, $photo->uploadedAt());
    }

    public function testUploadRecordsPhotoUploadedEvent(): void
    {
        $photoId = PhotoId::generate();
        $folderId = FolderId::fromString('0199d0b2-31cf-72ef-b43c-7d5563a01cdf');
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $fileName = FileName::fromString('photo.jpg');
        $storageKey = 'photos/folder-id/photo-id';
        $storageAdapter = 'local';
        $mimeType = 'image/jpeg';
        $sizeInBytes = 1024;

        $photo = Photo::upload($photoId, $folderId, $userId, $fileName, $storageKey, $storageAdapter, $mimeType, $sizeInBytes);
        $events = $photo->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(PhotoUploaded::class, $events[0]);
    }

    public function testPullDomainEventsClearsEventsList(): void
    {
        $photoId = PhotoId::generate();
        $folderId = FolderId::fromString('0199d0b2-31cf-72ef-b43c-7d5563a01cdf');
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $fileName = FileName::fromString('photo.jpg');
        $storageKey = 'photos/folder-id/photo-id';
        $storageAdapter = 'local';
        $mimeType = 'image/jpeg';
        $sizeInBytes = 1024;

        $photo = Photo::upload($photoId, $folderId, $userId, $fileName, $storageKey, $storageAdapter, $mimeType, $sizeInBytes);

        $firstPull = $photo->pullDomainEvents();
        $secondPull = $photo->pullDomainEvents();

        self::assertCount(1, $firstPull);
        self::assertCount(0, $secondPull);
    }

    public function testUploadedAtIsSetToCurrentTime(): void
    {
        $before = new DateTimeImmutable();

        $photo = Photo::upload(
            PhotoId::generate(),
            FolderId::fromString('0199d0b2-31cf-72ef-b43c-7d5563a01cdf'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            FileName::fromString('photo.jpg'),
            'photos/folder-id/photo-id',
            'local',
            'image/jpeg',
            1024
        );

        $after = new DateTimeImmutable();

        self::assertGreaterThanOrEqual($before->getTimestamp(), $photo->uploadedAt()->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $photo->uploadedAt()->getTimestamp());
    }

    public function testMoveToFolderChangesFolder(): void
    {
        $originalFolderId = FolderId::fromString('0199d0b2-31cf-72ef-b43c-7d5563a01cdf');
        $newFolderId = FolderId::fromString('0199d0b2-31cf-72ef-b43c-7d5563a01ce0');

        $photo = $this->createPhoto($originalFolderId);
        $photo->pullDomainEvents(); // Clear upload event

        $photo->moveToFolder($newFolderId);

        self::assertTrue($photo->folderId()->equals($newFolderId));
    }

    public function testMoveToFolderRecordsPhotoMovedEvent(): void
    {
        $originalFolderId = FolderId::fromString('0199d0b2-31cf-72ef-b43c-7d5563a01cdf');
        $newFolderId = FolderId::fromString('0199d0b2-31cf-72ef-b43c-7d5563a01ce0');

        $photo = $this->createPhoto($originalFolderId);
        $photo->pullDomainEvents(); // Clear upload event

        $photo->moveToFolder($newFolderId);
        $events = $photo->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(PhotoMoved::class, $events[0]);
        self::assertSame($originalFolderId->toString(), $events[0]->fromFolderId);
        self::assertSame($newFolderId->toString(), $events[0]->toFolderId);
    }

    public function testMoveToSameFolderDoesNothing(): void
    {
        $folderId = FolderId::fromString('0199d0b2-31cf-72ef-b43c-7d5563a01cdf');

        $photo = $this->createPhoto($folderId);
        $photo->pullDomainEvents(); // Clear upload event

        $photo->moveToFolder($folderId);
        $events = $photo->pullDomainEvents();

        self::assertTrue($photo->folderId()->equals($folderId));
        self::assertCount(0, $events);
    }

    private function createPhoto(FolderId $folderId): Photo
    {
        return Photo::upload(
            PhotoId::generate(),
            $folderId,
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            FileName::fromString('photo.jpg'),
            'photos/folder-id/photo-id',
            'local',
            'image/jpeg',
            1024
        );
    }
}
