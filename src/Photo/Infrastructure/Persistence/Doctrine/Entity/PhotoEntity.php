<?php

declare(strict_types=1);

namespace App\Photo\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'photos')]
#[ORM\Index(name: 'idx_folder_id', columns: ['folder_id'])]
class PhotoEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36)]
    private string $folderId;

    #[ORM\Column(type: 'string', length: 36)]
    private string $ownerId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $fileName;

    #[ORM\Column(type: 'string', length: 1024)]
    private string $storageKey;

    #[ORM\Column(type: 'string', length: 50)]
    private string $storageAdapter;

    #[ORM\Column(type: 'string', length: 100)]
    private string $mimeType;

    #[ORM\Column(type: 'integer')]
    private int $sizeInBytes;

    #[ORM\Column(type: 'string', length: 1024, nullable: true)]
    private ?string $thumbnailKey = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $uploadedAt;

    public function __construct(
        string $id,
        string $folderId,
        string $ownerId,
        string $fileName,
        string $storageKey,
        string $storageAdapter,
        string $mimeType,
        int $sizeInBytes,
        DateTimeImmutable $uploadedAt,
        ?string $thumbnailKey = null,
    ) {
        $this->id = $id;
        $this->folderId = $folderId;
        $this->ownerId = $ownerId;
        $this->fileName = $fileName;
        $this->storageKey = $storageKey;
        $this->storageAdapter = $storageAdapter;
        $this->mimeType = $mimeType;
        $this->sizeInBytes = $sizeInBytes;
        $this->thumbnailKey = $thumbnailKey;
        $this->uploadedAt = $uploadedAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFolderId(): string
    {
        return $this->folderId;
    }

    public function getOwnerId(): string
    {
        return $this->ownerId;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getStorageKey(): string
    {
        return $this->storageKey;
    }

    public function getStorageAdapter(): string
    {
        return $this->storageAdapter;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSizeInBytes(): int
    {
        return $this->sizeInBytes;
    }

    public function getUploadedAt(): DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function getThumbnailKey(): ?string
    {
        return $this->thumbnailKey;
    }

    public function setThumbnailKey(?string $thumbnailKey): void
    {
        $this->thumbnailKey = $thumbnailKey;
    }
}
