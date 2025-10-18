<?php

declare(strict_types=1);

namespace App\Photo\Infrastructure\Persistence\Doctrine\Mapper;

use App\Photo\Domain\Model\FileName;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Model\StoredFile;
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
            $photo->storedFile()->storagePath(),
            $photo->storedFile()->mimeType(),
            $photo->storedFile()->sizeInBytes(),
            $photo->uploadedAt(),
            $photo->storedFile()->thumbnailPath(),
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

        $storedFileProperty = $reflection->getProperty('storedFile');
        $storedFileProperty->setValue($photo, StoredFile::create(
            $entity->getStoragePath(),
            $entity->getMimeType(),
            $entity->getSizeInBytes(),
            $entity->getThumbnailPath(),
        ));

        $uploadedAtProperty = $reflection->getProperty('uploadedAt');
        $uploadedAtProperty->setValue($photo, $entity->getUploadedAt());

        $eventsProperty = $reflection->getProperty('domainEvents');
        $eventsProperty->setValue($photo, []);

        return $photo;
    }
}
