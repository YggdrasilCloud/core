<?php

declare(strict_types=1);

namespace App\Tests\Functional\Photo\UserInterface\Http\Controller;

use App\File\Domain\Service\PhysicalFolderStorage;
use App\Photo\Domain\Model\Folder;
use App\Photo\Domain\Model\FolderId;
use App\Photo\Domain\Model\FolderName;
use App\Photo\Domain\Model\UserId;
use App\Photo\Domain\Repository\FolderRepositoryInterface;
use App\Photo\Domain\Service\FileSystemPathBuilder;
use App\Tests\Functional\JsonResponseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

use const JSON_THROW_ON_ERROR;

final class MoveFolderControllerTest extends WebTestCase
{
    use JsonResponseTestTrait;

    public function testMoveFolderToNewParentReturns200(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        // Create parent folder
        $parentId = FolderId::generate();
        $parentFolder = Folder::create(
            $parentId,
            FolderName::fromString('Parent_'.$parentId->toString()),
            $ownerId
        );

        // Create folder to move
        $folderId = FolderId::generate();
        $folder = Folder::create(
            $folderId,
            FolderName::fromString('Child_'.$folderId->toString()),
            $ownerId
        );

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($parentFolder);
        $folderRepo->save($folder);

        /** @var FileSystemPathBuilder $pathBuilder */
        $pathBuilder = $container->get(FileSystemPathBuilder::class);

        /** @var PhysicalFolderStorage $folderStorage */
        $folderStorage = $container->get(PhysicalFolderStorage::class);

        // Create physical directories
        $parentPath = $pathBuilder->buildFolderPath($parentFolder);
        $folderStorage->createDirectory($parentPath);

        $originalPath = $pathBuilder->buildFolderPath($folder);
        $folderStorage->createDirectory($originalPath);

        try {
            $client->request(
                'PATCH',
                '/api/folders/'.$folderId->toString().'/move',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['targetParentId' => $parentId->toString()], JSON_THROW_ON_ERROR)
            );

            self::assertResponseStatusCodeSame(Response::HTTP_OK);

            $data = $this->decodeJsonResponse($client->getResponse());

            self::assertArrayHasKey('id', $data);
            self::assertArrayHasKey('parentId', $data);
            self::assertSame($folderId->toString(), $data['id']);
            self::assertSame($parentId->toString(), $data['parentId']);

            // Verify folder was actually moved in the database
            $movedFolder = $folderRepo->findById($folderId);
            self::assertNotNull($movedFolder);
            self::assertSame($parentId->toString(), $movedFolder->parentId()?->toString());
        } finally {
            // Re-fetch the folder to get updated path after move
            $updatedFolder = $folderRepo->findById($folderId);

            // Clean up physical directories - order matters!
            // First remove the moved folder (now inside parent)
            if ($updatedFolder !== null) {
                $movedPath = $pathBuilder->buildFolderPath($updatedFolder);
                if ($folderStorage->directoryExists($movedPath)) {
                    $folderStorage->removeEmptyDirectory($movedPath);
                }
            }
            // Original path should no longer exist after move
            if ($folderStorage->directoryExists($originalPath)) {
                $folderStorage->removeEmptyDirectory($originalPath);
            }
            // Then remove parent (now empty)
            if ($folderStorage->directoryExists($parentPath)) {
                $folderStorage->removeEmptyDirectory($parentPath);
            }
        }
    }

    public function testMoveFolderToRootReturns200(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        // Create parent folder
        $parentId = FolderId::generate();
        $parentFolder = Folder::create(
            $parentId,
            FolderName::fromString('Parent_'.$parentId->toString()),
            $ownerId
        );

        // Create child folder (already has parent)
        $folderId = FolderId::generate();
        $folder = Folder::create(
            $folderId,
            FolderName::fromString('Child_'.$folderId->toString()),
            $ownerId,
            $parentId
        );

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($parentFolder);
        $folderRepo->save($folder);

        /** @var FileSystemPathBuilder $pathBuilder */
        $pathBuilder = $container->get(FileSystemPathBuilder::class);

        /** @var PhysicalFolderStorage $folderStorage */
        $folderStorage = $container->get(PhysicalFolderStorage::class);

        // Create physical directories
        $parentPath = $pathBuilder->buildFolderPath($parentFolder);
        $folderStorage->createDirectory($parentPath);

        $originalPath = $pathBuilder->buildFolderPath($folder);
        $folderStorage->createDirectory($originalPath);

        try {
            $client->request(
                'PATCH',
                '/api/folders/'.$folderId->toString().'/move',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['targetParentId' => null], JSON_THROW_ON_ERROR)
            );

            self::assertResponseStatusCodeSame(Response::HTTP_OK);

            $data = $this->decodeJsonResponse($client->getResponse());

            self::assertArrayHasKey('id', $data);
            self::assertArrayHasKey('parentId', $data);
            self::assertSame($folderId->toString(), $data['id']);
            self::assertNull($data['parentId']);

            // Verify folder was actually moved in the database
            $movedFolder = $folderRepo->findById($folderId);
            self::assertNotNull($movedFolder);
            self::assertNull($movedFolder->parentId());
        } finally {
            // Re-fetch the folder to get updated path after move
            $updatedFolder = $folderRepo->findById($folderId);

            // Clean up physical directories - order matters!
            // First remove the moved folder (now at root)
            if ($updatedFolder !== null) {
                $movedPath = $pathBuilder->buildFolderPath($updatedFolder);
                if ($folderStorage->directoryExists($movedPath)) {
                    $folderStorage->removeEmptyDirectory($movedPath);
                }
            }
            // Original path should no longer exist after move
            if ($folderStorage->directoryExists($originalPath)) {
                $folderStorage->removeEmptyDirectory($originalPath);
            }
            // Then remove parent (now empty)
            if ($folderStorage->directoryExists($parentPath)) {
                $folderStorage->removeEmptyDirectory($parentPath);
            }
        }
    }

    public function testMoveFolderReturns404WhenFolderNotFound(): void
    {
        $client = self::createClient();

        $nonExistentId = '550e8400-e29b-41d4-a716-446655440099';

        $client->request(
            'PATCH',
            '/api/folders/'.$nonExistentId.'/move',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['targetParentId' => null], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testMoveFolderReturns400WhenCycleDetected(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');

        // Create parent folder
        $parentId = FolderId::generate();
        $parentFolder = Folder::create(
            $parentId,
            FolderName::fromString('Parent_'.$parentId->toString()),
            $ownerId
        );

        // Create child folder
        $childId = FolderId::generate();
        $childFolder = Folder::create(
            $childId,
            FolderName::fromString('Child_'.$childId->toString()),
            $ownerId,
            $parentId
        );

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($parentFolder);
        $folderRepo->save($childFolder);

        /** @var FileSystemPathBuilder $pathBuilder */
        $pathBuilder = $container->get(FileSystemPathBuilder::class);

        /** @var PhysicalFolderStorage $folderStorage */
        $folderStorage = $container->get(PhysicalFolderStorage::class);

        // Create physical directories
        $parentPath = $pathBuilder->buildFolderPath($parentFolder);
        $folderStorage->createDirectory($parentPath);

        $childPath = $pathBuilder->buildFolderPath($childFolder);
        $folderStorage->createDirectory($childPath);

        try {
            // Try to move parent into its child (would create cycle)
            $client->request(
                'PATCH',
                '/api/folders/'.$parentId->toString().'/move',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['targetParentId' => $childId->toString()], JSON_THROW_ON_ERROR)
            );

            self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        } finally {
            // Clean up physical directories
            if ($folderStorage->directoryExists($childPath)) {
                $folderStorage->removeEmptyDirectory($childPath);
            }
            if ($folderStorage->directoryExists($parentPath)) {
                $folderStorage->removeEmptyDirectory($parentPath);
            }
        }
    }

    public function testMoveFolderReturns400WhenBodyIsEmpty(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $folderId = FolderId::generate();
        $folder = Folder::create($folderId, FolderName::fromString('Test Folder'), $ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        $client->request(
            'PATCH',
            '/api/folders/'.$folderId->toString().'/move',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            ''
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testMoveFolderReturns400WhenJsonIsInvalid(): void
    {
        $client = self::createClient();
        $container = $client->getContainer();

        $ownerId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $folderId = FolderId::generate();
        $folder = Folder::create($folderId, FolderName::fromString('Test Folder'), $ownerId);

        /** @var FolderRepositoryInterface $folderRepo */
        $folderRepo = $container->get(FolderRepositoryInterface::class);
        $folderRepo->save($folder);

        $client->request(
            'PATCH',
            '/api/folders/'.$folderId->toString().'/move',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'invalid json'
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}
