<?php

declare(strict_types=1);

namespace App\Photo\Infrastructure\Persistence\Doctrine\Entity;

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

    #[ORM\Column(type: 'string', length: 500)]
    private string $storagePath;

    #[ORM\Column(type: 'string', length: 100)]
    private string $mimeType;

    #[ORM\Column(type: 'integer')]
    private int $sizeInBytes;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $uploadedAt;

    public function __construct(
        string $id,
        string $folderId,
        string $ownerId,
        string $fileName,
        string $storagePath,
        string $mimeType,
        int $sizeInBytes,
        \DateTimeImmutable $uploadedAt,
    ) {
        $this->id = $id;
        $this->folderId = $folderId;
        $this->ownerId = $ownerId;
        $this->fileName = $fileName;
        $this->storagePath = $storagePath;
        $this->mimeType = $mimeType;
        $this->sizeInBytes = $sizeInBytes;
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

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSizeInBytes(): int
    {
        return $this->sizeInBytes;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }
}
