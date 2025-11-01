<?php

declare(strict_types=1);

namespace App\Photo\Infrastructure\Persistence\Doctrine\Repository;

use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Infrastructure\Persistence\Doctrine\Entity\FolderEntity;
use App\Photo\Infrastructure\Persistence\Doctrine\Mapper\FolderMapper;
use App\Photo\UserInterface\Http\Request\FolderQueryParams;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class DoctrineFolderRepository implements FolderRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function save(Folder $folder): void
    {
        $entity = $this->entityManager->find(FolderEntity::class, $folder->id()->toString());

        if ($entity === null) {
            // Nouveau dossier
            $entity = FolderMapper::toEntity($folder);
            $this->entityManager->persist($entity);
        } else {
            // Mise à jour
            FolderMapper::updateEntity($folder, $entity);
        }

        $this->entityManager->flush();
    }

    public function findById(FolderId $id): ?Folder
    {
        $entity = $this->entityManager->find(FolderEntity::class, $id->toString());

        if ($entity === null) {
            return null;
        }

        return FolderMapper::toDomain($entity);
    }

    public function remove(Folder $folder): void
    {
        $entity = $this->entityManager->find(FolderEntity::class, $folder->id()->toString());

        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }

    /**
     * @return list<Folder>
     */
    public function findAll(FolderQueryParams $queryParams, int $limit, int $offset): array
    {
        $safeLimit = max(1, $limit);
        $safeOffset = max(0, $offset);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(FolderEntity::class, 'f');

        // Apply filters
        $this->applyFilters($qb, $queryParams);

        // Apply sorting
        $this->applySorting($qb, $queryParams);

        $entities = $qb
            ->setMaxResults($safeLimit)
            ->setFirstResult($safeOffset)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (FolderEntity $entity) => FolderMapper::toDomain($entity),
            $entities
        );
    }

    public function count(FolderQueryParams $queryParams): int
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(f.id)')
            ->from(FolderEntity::class, 'f');

        // Apply same filters for accurate count
        $this->applyFilters($qb, $queryParams);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<Folder>
     */
    public function findByParentId(
        FolderId $parentId,
        FolderQueryParams $queryParams,
        int $limit,
        int $offset
    ): array {
        $safeLimit = max(1, $limit);
        $safeOffset = max(0, $offset);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(FolderEntity::class, 'f')
            ->where('f.parentId = :parentId')
            ->setParameter('parentId', $parentId->toString());

        // Apply filters
        $this->applyFilters($qb, $queryParams);

        // Apply sorting
        $this->applySorting($qb, $queryParams);

        $entities = $qb
            ->setMaxResults($safeLimit)
            ->setFirstResult($safeOffset)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (FolderEntity $entity) => FolderMapper::toDomain($entity),
            $entities
        );
    }

    public function countByParentId(FolderId $parentId, FolderQueryParams $queryParams): int
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(f.id)')
            ->from(FolderEntity::class, 'f')
            ->where('f.parentId = :parentId')
            ->setParameter('parentId', $parentId->toString());

        // Apply same filters for accurate count
        $this->applyFilters($qb, $queryParams);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Apply filtering criteria to query builder.
     */
    private function applyFilters(QueryBuilder $qb, FolderQueryParams $queryParams): void
    {
        // Search by folder name (case-insensitive substring)
        if ($queryParams->search !== null) {
            $qb->andWhere('LOWER(f.name) LIKE LOWER(:search)')
                ->setParameter('search', '%' . $queryParams->search . '%');
        }

        // Filter by creation date range
        if ($queryParams->dateFrom !== null) {
            $qb->andWhere('f.createdAt >= :dateFrom')
                ->setParameter('dateFrom', $queryParams->dateFrom);
        }
        if ($queryParams->dateTo !== null) {
            $qb->andWhere('f.createdAt <= :dateTo')
                ->setParameter('dateTo', $queryParams->dateTo);
        }
    }

    /**
     * Apply sorting criteria to query builder.
     */
    private function applySorting(QueryBuilder $qb, FolderQueryParams $queryParams): void
    {
        $sortOrder = strtoupper($queryParams->sortOrder);

        match ($queryParams->sortBy) {
            'name' => $qb->orderBy('f.name', $sortOrder),
            'createdAt' => $qb->orderBy('f.createdAt', $sortOrder),
            default => $qb->orderBy('f.name', 'ASC'), // Fallback
        };
    }
}
