<?php

declare(strict_types=1);

namespace App\Photo\Infrastructure\Persistence\Doctrine\Repository;

use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Infrastructure\Persistence\Doctrine\Entity\FolderEntity;
use App\Photo\Infrastructure\Persistence\Doctrine\Mapper\FolderMapper;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineFolderRepository implements FolderRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

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
    public function findAll(): array
    {
        $entities = $this->entityManager
            ->getRepository(FolderEntity::class)
            ->findAll();

        return array_map(
            fn (FolderEntity $entity) => FolderMapper::toDomain($entity),
            $entities
        );
    }
}
