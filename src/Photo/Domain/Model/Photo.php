<?php

declare(strict_types=1);

namespace App\Photo\Domain\Model;

use App\Photo\Domain\Event\PhotoUploaded;
use DateTimeImmutable;

final class Photo
{
    /** @var list<object> */
    private array $domainEvents = [];

    private function __construct(
        private PhotoId $id,
        private FolderId $folderId,
        private UserId $ownerId,
        private FileName $fileName,
        private string $storageKey,
        private string $storageAdapter,
        private string $mimeType,
        private int $sizeInBytes,
        private ?string $thumbnailKey,
        private DateTimeImmutable $uploadedAt,
        private ?DateTimeImmutable $takenAt,
    ) {}

    public static function upload(
        PhotoId $id,
        FolderId $folderId,
        UserId $ownerId,
        FileName $fileName,
        string $storageKey,
        string $storageAdapter,
        string $mimeType,
        int $sizeInBytes,
        ?string $thumbnailKey = null,
        ?DateTimeImmutable $takenAt = null,
    ): self {
        $photo = new self(
            $id,
            $folderId,
            $ownerId,
            $fileName,
            $storageKey,
            $storageAdapter,
            $mimeType,
            $sizeInBytes,
            $thumbnailKey,
            new DateTimeImmutable(),
            $takenAt,
        );

        $photo->recordEvent(new PhotoUploaded(
            $id->toString(),
            $folderId->toString(),
            $ownerId->toString(),
            $fileName->toString(),
            $storageKey,
            $mimeType,
            $sizeInBytes,
        ));

        return $photo;
    }

    public function id(): PhotoId
    {
        return $this->id;
    }

    public function folderId(): FolderId
    {
        return $this->folderId;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function fileName(): FileName
    {
        return $this->fileName;
    }

    public function storageKey(): string
    {
        return $this->storageKey;
    }

    public function storageAdapter(): string
    {
        return $this->storageAdapter;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function sizeInBytes(): int
    {
        return $this->sizeInBytes;
    }

    public function thumbnailKey(): ?string
    {
        return $this->thumbnailKey;
    }

    public function uploadedAt(): DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function takenAt(): ?DateTimeImmutable
    {
        return $this->takenAt;
    }

    /**
     * @return list<object>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    /**
     * Records a domain event to be published later.
     *
     * Future enhancement: implement Transactional Outbox pattern
     * - Store events in dedicated outbox table within same transaction
     * - Use separate process to reliably publish events to message bus
     * - Ensures event delivery even if message broker is temporarily unavailable
     * - Provides exactly-once delivery guarantee with idempotency keys
     */
    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
