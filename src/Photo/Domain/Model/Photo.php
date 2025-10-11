<?php

declare(strict_types=1);

namespace App\Photo\Domain\Model;

use App\Photo\Domain\Event\PhotoUploaded;

final class Photo
{
    /** @var list<object> */
    private array $domainEvents = [];

    private function __construct(
        private PhotoId $id,
        private FolderId $folderId,
        private UserId $ownerId,
        private FileName $fileName,
        private StoredFile $storedFile,
        private \DateTimeImmutable $uploadedAt,
    ) {
    }

    public static function upload(
        PhotoId $id,
        FolderId $folderId,
        UserId $ownerId,
        FileName $fileName,
        StoredFile $storedFile,
    ): self {
        $photo = new self(
            $id,
            $folderId,
            $ownerId,
            $fileName,
            $storedFile,
            new \DateTimeImmutable(),
        );

        $photo->recordEvent(new PhotoUploaded(
            $id->toString(),
            $folderId->toString(),
            $ownerId->toString(),
            $fileName->toString(),
            $storedFile->storagePath(),
            $storedFile->mimeType(),
            $storedFile->sizeInBytes(),
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

    public function storedFile(): StoredFile
    {
        return $this->storedFile;
    }

    public function uploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
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
