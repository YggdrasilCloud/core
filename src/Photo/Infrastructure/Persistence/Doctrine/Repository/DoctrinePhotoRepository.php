<?php

declare(strict_types=1);

namespace App\Photo\Infrastructure\Persistence\Doctrine\Repository;

use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use App\Photo\Infrastructure\Persistence\Doctrine\Entity\PhotoEntity;
use App\Photo\Infrastructure\Persistence\Doctrine\Mapper\PhotoMapper;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePhotoRepository implements PhotoRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function save(Photo $photo): void
    {
        $entity = $this->entityManager->find(PhotoEntity::class, $photo->id()->toString());

        if ($entity === null) {
            // Nouvelle photo
            $entity = PhotoMapper::toEntity($photo);
            $this->entityManager->persist($entity);
        }
        // NOTE: No update path for existing photos. Photos are immutable after upload.
        // Future: add rename/move commands if needed.

        $this->entityManager->flush();
    }

    public function findById(PhotoId $id): ?Photo
    {
        $entity = $this->entityManager->find(PhotoEntity::class, $id->toString());

        if ($entity === null) {
            return null;
        }

        return PhotoMapper::toDomain($entity);
    }

    /**
     * @return list<Photo>
     */
    public function findByFolderId(FolderId $folderId, int $limit, int $offset): array
    {
        // Ensure limit and offset are always valid to prevent DQL errors
        $safeLimit = max(1, $limit);
        $safeOffset = max(0, $offset);

        $entities = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(PhotoEntity::class, 'p')
            ->where('p.folderId = :folderId')
            ->setParameter('folderId', $folderId->toString())
            ->orderBy('p.uploadedAt', 'DESC')
            ->setMaxResults($safeLimit)
            ->setFirstResult($safeOffset)
            ->getQuery()
            ->getResult()
        ;

        return array_map(
            static fn (PhotoEntity $entity) => PhotoMapper::toDomain($entity),
            $entities,
        );
    }

    public function countByFolderId(FolderId $folderId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(PhotoEntity::class, 'p')
            ->where('p.folderId = :folderId')
            ->setParameter('folderId', $folderId->toString())
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function remove(Photo $photo): void
    {
        $entity = $this->entityManager->find(PhotoEntity::class, $photo->id()->toString());

        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
