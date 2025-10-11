<?php

declare(strict_types=1);

namespace App\Photo\Infrastructure\Persistence\Doctrine\Mapper;

use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\UserId;
use App\Photo\Infrastructure\Persistence\Doctrine\Entity\FolderEntity;

final class FolderMapper
{
    public static function toEntity(Folder $folder): FolderEntity
    {
        return new FolderEntity(
            $folder->id()->toString(),
            $folder->name()->toString(),
            $folder->ownerId()->toString(),
            $folder->createdAt(),
        );
    }

    public static function toDomain(FolderEntity $entity): Folder
    {
        $reflection = new \ReflectionClass(Folder::class);
        $folder = $reflection->newInstanceWithoutConstructor();

        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($folder, FolderId::fromString($entity->getId()));

        $nameProperty = $reflection->getProperty('name');
        $nameProperty->setValue($folder, FolderName::fromString($entity->getName()));

        $ownerIdProperty = $reflection->getProperty('ownerId');
        $ownerIdProperty->setValue($folder, UserId::fromString($entity->getOwnerId()));

        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setValue($folder, $entity->getCreatedAt());

        $eventsProperty = $reflection->getProperty('domainEvents');
        $eventsProperty->setValue($folder, []);

        return $folder;
    }

    public static function updateEntity(Folder $folder, FolderEntity $entity): void
    {
        $entity->setName($folder->name()->toString());
    }
}
