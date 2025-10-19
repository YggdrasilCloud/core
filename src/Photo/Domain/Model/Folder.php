<?php

declare(strict_types=1);

namespace App\Photo\Domain\Model;

use App\Photo\Domain\Event\FolderCreated;
use DateTimeImmutable;

final class Folder
{
    /** @var list<object> */
    private array $domainEvents = [];

    private function __construct(
        private FolderId $id,
        private FolderName $name,
        private UserId $ownerId,
        private DateTimeImmutable $createdAt,
        private ?FolderId $parentId = null,
    ) {}

    public static function create(
        FolderId $id,
        FolderName $name,
        UserId $ownerId,
        ?FolderId $parentId = null,
    ): self {
        $folder = new self(
            $id,
            $name,
            $ownerId,
            new DateTimeImmutable(),
            $parentId,
        );

        $folder->recordEvent(new FolderCreated(
            $id->toString(),
            $name->toString(),
            $ownerId->toString(),
        ));

        return $folder;
    }

    public function rename(FolderName $newName): void
    {
        $this->name = $newName;
    }

    public function id(): FolderId
    {
        return $this->id;
    }

    public function name(): FolderName
    {
        return $this->name;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function parentId(): ?FolderId
    {
        return $this->parentId;
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
