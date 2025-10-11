<?php

declare(strict_types=1);

namespace App\Tests\Unit\Photo\Domain\Model;

use App\Photo\Domain\Event\PhotoUploaded;
use App\Photo\Domain\Model\FileName;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Model\StoredFile;
use App\Photo\Domain\Model\UserId;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class PhotoTest extends TestCase
{
    public function testUploadCreatesPhotoWithCorrectData(): void
    {
        $photoId = PhotoId::generate();
        $folderId = FolderId::fromString('0199d0b2-31cf-72ef-b43c-7d5563a01cdf');
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $fileName = FileName::fromString('photo.jpg');
        $storedFile = StoredFile::create('2025/10/11/photo.jpg', 'image/jpeg', 1024);

        $photo = Photo::upload($photoId, $folderId, $userId, $fileName, $storedFile);

        self::assertTrue($photo->id()->equals($photoId));
        self::assertTrue($photo->folderId()->equals($folderId));
        self::assertTrue($photo->ownerId()->equals($userId));
        self::assertTrue($photo->fileName()->equals($fileName));
        self::assertSame($storedFile, $photo->storedFile());
        self::assertInstanceOf(\DateTimeImmutable::class, $photo->uploadedAt());
    }

    public function testUploadRecordsPhotoUploadedEvent(): void
    {
        $photoId = PhotoId::generate();
        $folderId = FolderId::fromString('0199d0b2-31cf-72ef-b43c-7d5563a01cdf');
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $fileName = FileName::fromString('photo.jpg');
        $storedFile = StoredFile::create('2025/10/11/photo.jpg', 'image/jpeg', 1024);

        $photo = Photo::upload($photoId, $folderId, $userId, $fileName, $storedFile);
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
        $storedFile = StoredFile::create('2025/10/11/photo.jpg', 'image/jpeg', 1024);

        $photo = Photo::upload($photoId, $folderId, $userId, $fileName, $storedFile);

        $firstPull = $photo->pullDomainEvents();
        $secondPull = $photo->pullDomainEvents();

        self::assertCount(1, $firstPull);
        self::assertCount(0, $secondPull);
    }

    public function testUploadedAtIsSetToCurrentTime(): void
    {
        $before = new \DateTimeImmutable();

        $photo = Photo::upload(
            PhotoId::generate(),
            FolderId::fromString('0199d0b2-31cf-72ef-b43c-7d5563a01cdf'),
            UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            FileName::fromString('photo.jpg'),
            StoredFile::create('2025/10/11/photo.jpg', 'image/jpeg', 1024)
        );

        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before->getTimestamp(), $photo->uploadedAt()->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $photo->uploadedAt()->getTimestamp());
    }
}
