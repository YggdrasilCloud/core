<?php

declare(strict_types=1);

namespace App\Photo\Infrastructure\Persistence\Doctrine\Mapper;

use App\Photo\Domain\Model\FileName;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Model\UserId;
use App\Photo\Infrastructure\Persistence\Doctrine\Entity\PhotoEntity;
use ReflectionClass;

final class PhotoMapper
{
    public static function toEntity(Photo $photo): PhotoEntity
    {
        return new PhotoEntity(
            $photo->id()->toString(),
            $photo->folderId()->toString(),
            $photo->ownerId()->toString(),
            $photo->fileName()->toString(),
            $photo->storageKey(),
            $photo->storageAdapter(),
            $photo->mimeType(),
            $photo->sizeInBytes(),
            $photo->uploadedAt(),
            $photo->thumbnailKey(),
        );
    }

    public static function toDomain(PhotoEntity $entity): Photo
    {
        $reflection = new ReflectionClass(Photo::class);
        $photo = $reflection->newInstanceWithoutConstructor();

        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($photo, PhotoId::fromString($entity->getId()));

        $folderIdProperty = $reflection->getProperty('folderId');
        $folderIdProperty->setValue($photo, FolderId::fromString($entity->getFolderId()));

        $ownerIdProperty = $reflection->getProperty('ownerId');
        $ownerIdProperty->setValue($photo, UserId::fromString($entity->getOwnerId()));

        $fileNameProperty = $reflection->getProperty('fileName');
        $fileNameProperty->setValue($photo, FileName::fromString($entity->getFileName()));

        $storageKeyProperty = $reflection->getProperty('storageKey');
        $storageKeyProperty->setValue($photo, $entity->getStorageKey());

        $storageAdapterProperty = $reflection->getProperty('storageAdapter');
        $storageAdapterProperty->setValue($photo, $entity->getStorageAdapter());

        $mimeTypeProperty = $reflection->getProperty('mimeType');
        $mimeTypeProperty->setValue($photo, $entity->getMimeType());

        $sizeInBytesProperty = $reflection->getProperty('sizeInBytes');
        $sizeInBytesProperty->setValue($photo, $entity->getSizeInBytes());

        $thumbnailKeyProperty = $reflection->getProperty('thumbnailKey');
        $thumbnailKeyProperty->setValue($photo, $entity->getThumbnailKey());

        $uploadedAtProperty = $reflection->getProperty('uploadedAt');
        $uploadedAtProperty->setValue($photo, $entity->getUploadedAt());

        $eventsProperty = $reflection->getProperty('domainEvents');
        $eventsProperty->setValue($photo, []);

        return $photo;
    }
}
