<?php

declare(strict_types=1);

namespace App\Photo\Infrastructure\Persistence\Doctrine\Repository;

use App\Photo\Domain\Criteria\PhotoCriteria;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\Photo;
use App\Photo\Domain\Model\PhotoId;
use App\Photo\Domain\Repository\PhotoRepositoryInterface;
use App\Photo\Infrastructure\Persistence\Doctrine\Entity\PhotoEntity;
use App\Photo\Infrastructure\Persistence\Doctrine\Mapper\PhotoMapper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

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
    public function findByFolderId(
        FolderId $folderId,
        PhotoCriteria $criteria,
        int $limit,
        int $offset
    ): array {
        // Ensure limit and offset are always valid to prevent DQL errors
        $safeLimit = max(1, $limit);
        $safeOffset = max(0, $offset);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(PhotoEntity::class, 'p')
            ->where('p.folderId = :folderId')
            ->setParameter('folderId', $folderId->toString())
        ;

        // Apply filters
        $this->applyFilters($qb, $criteria);

        // Apply sorting
        $this->applySorting($qb, $criteria);

        $entities = $qb
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

    public function countByFolderId(FolderId $folderId, PhotoCriteria $criteria): int
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(PhotoEntity::class, 'p')
            ->where('p.folderId = :folderId')
            ->setParameter('folderId', $folderId->toString())
        ;

        // Apply same filters for accurate count
        $this->applyFilters($qb, $criteria);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function remove(Photo $photo): void
    {
        $entity = $this->entityManager->find(PhotoEntity::class, $photo->id()->toString());

        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }

    /**
     * Apply filtering criteria to query builder.
     */
    private function applyFilters(QueryBuilder $qb, PhotoCriteria $criteria): void
    {
        // Search by filename (case-insensitive substring)
        if ($criteria->search !== null) {
            $qb->andWhere('LOWER(p.fileName) LIKE LOWER(:search)')
                ->setParameter('search', '%'.$criteria->search.'%')
            ;
        }

        // Filter by MIME types
        if ($criteria->mimeTypes !== []) {
            $qb->andWhere('p.mimeType IN (:mimeTypes)')
                ->setParameter('mimeTypes', $criteria->mimeTypes)
            ;
        }

        // Filter by file extensions
        if ($criteria->extensions !== []) {
            $extensionConditions = [];
            foreach ($criteria->extensions as $index => $extension) {
                $paramName = 'ext_'.$index;
                $extensionConditions[] = "p.fileName LIKE :{$paramName}";
                $qb->setParameter($paramName, '%.'.$extension);
            }
            $qb->andWhere($qb->expr()->orX(...$extensionConditions));
        }

        // Filter by size range
        if ($criteria->sizeMin !== null) {
            $qb->andWhere('p.sizeInBytes >= :sizeMin')
                ->setParameter('sizeMin', $criteria->sizeMin)
            ;
        }
        if ($criteria->sizeMax !== null) {
            $qb->andWhere('p.sizeInBytes <= :sizeMax')
                ->setParameter('sizeMax', $criteria->sizeMax)
            ;
        }

        // Filter by date range using COALESCE(takenAt, uploadedAt)
        // This provides consistent date filtering: use EXIF capture date if available,
        // otherwise fall back to upload date. Ensures all photos can be filtered by date
        // regardless of whether EXIF metadata is present.
        if ($criteria->dateFrom !== null) {
            $qb->andWhere('COALESCE(p.takenAt, p.uploadedAt) >= :dateFrom')
                ->setParameter('dateFrom', $criteria->dateFrom)
            ;
        }
        if ($criteria->dateTo !== null) {
            $qb->andWhere('COALESCE(p.takenAt, p.uploadedAt) <= :dateTo')
                ->setParameter('dateTo', $criteria->dateTo)
            ;
        }
    }

    /**
     * Apply sorting criteria to query builder.
     *
     * Important: When sorting by 'takenAt', we use COALESCE(p.takenAt, p.uploadedAt).
     * This ensures photos without EXIF capture date (takenAt = null) fall back to
     * upload date for sorting, preventing null values from appearing at the beginning
     * or end depending on database NULL collation.
     *
     * All sorts include a secondary sort by ID to ensure stable pagination.
     * Without secondary sorting, photos with identical primary sort values (e.g., same uploadedAt)
     * would be returned in non-deterministic order, causing duplicates across page boundaries.
     */
    private function applySorting(QueryBuilder $qb, PhotoCriteria $criteria): void
    {
        $sortOrder = strtoupper($criteria->sortOrder);

        match ($criteria->sortBy) {
            'uploadedAt' => $qb->orderBy('p.uploadedAt', $sortOrder)->addOrderBy('p.id', $sortOrder),
            // COALESCE ensures photos without EXIF date use uploadedAt for consistent sorting
            'takenAt' => $qb->orderBy('COALESCE(p.takenAt, p.uploadedAt)', $sortOrder)->addOrderBy('p.id', $sortOrder),
            'fileName' => $qb->orderBy('p.fileName', $sortOrder)->addOrderBy('p.id', $sortOrder),
            'sizeInBytes' => $qb->orderBy('p.sizeInBytes', $sortOrder)->addOrderBy('p.id', $sortOrder),
            'mimeType' => $qb->orderBy('p.mimeType', $sortOrder)->addOrderBy('p.id', $sortOrder),
            default => $qb->orderBy('p.uploadedAt', 'DESC')->addOrderBy('p.id', 'DESC'), // Fallback
        };
    }
}
